<?php

namespace DsboHouseNumberCheck\Procedures;

use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Comment\Contracts\CommentRepositoryContract;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;

class CheckDeliveryHouseNumber
{
    private const SALES_ORDER_TYPE = 1;
    private const REVIEW_STATUS = 5.1;
    private const GERMANY = 1;
    private const AUSTRIA = 2;
    private const SWITZERLAND = 4;
    private const HOUSE_NUMBER = '\p{Nd}+(?: ?\p{L})?(?: *[-–\/] *\p{Nd}+(?: ?\p{L})?)?';

    public function execute(EventProceduresTriggered $event): void
    {
        $eventOrder = $event->getOrder();

        if (!$eventOrder instanceof Order || (int) $eventOrder->typeId !== self::SALES_ORDER_TYPE) {
            return;
        }

        $orderId = (int) $eventOrder->id;
        /** @var OrderRepositoryContract $orders */
        $orders = pluginApp(OrderRepositoryContract::class);
        $order = $orders->findById($orderId, ['addresses', 'comments']);
        $deliveryAddress = $order->deliveryAddress;

        if (!$deliveryAddress instanceof Address) {
            return;
        }

        $errors = $this->getDeliveryAddressErrors($deliveryAddress);
        if ($errors === []) {
            return;
        }

        if ((float) $order->statusId !== self::REVIEW_STATUS) {
            $orders->update($orderId, ['statusId' => self::REVIEW_STATUS]);
        }

        $this->addAddressNote($order, $errors);
    }

    private function addAddressNote(Order $order, array $errors): void
    {
        $text = "Automatische Lieferadressprüfung\n- " . implode("\n- ", $errors);

        foreach ($order->comments ?? [] as $comment) {
            if (!(bool) $comment->isVisibleForContact && (string) $comment->text === $text) {
                return;
            }
        }

        /** @var CommentRepositoryContract $comments */
        $comments = pluginApp(CommentRepositoryContract::class);
        $comments->createComment([
            'referenceType' => 'order',
            'referenceValue' => (int) $order->id,
            'userId' => (int) $order->ownerId,
            'text' => $text,
            'isVisibleForContact' => false,
        ]);
    }

    private function getDeliveryAddressErrors(Address $address): array
    {
        $errors = [];
        $company = (string) $address->name1;
        $recipient = $company . ' ' . $address->name2 . ' ' . $address->name3;

        if (preg_match('/\A.{0,40}\z/us', $company) !== 1) {
            $errors[] = 'Address Name 1 (Firma) ist zu lang. Das Feld darf höchstens 40 Zeichen enthalten.';
        }

        if (preg_match('/\p{Cc}/u', $company) === 1) {
            $errors[] = 'Address Name 1 (Firma) enthält einen Zeilenumbruch oder ein unsichtbares Steuerzeichen. Bitte einzeilig ausfüllen.';
        }

        if (preg_match('/[^\p{Z}\p{Cc}]/u', $recipient) !== 1) {
            $errors[] = 'Der Empfängername fehlt. Bitte einen Namen oder eine Firma eintragen.';
        }

        return array_merge($errors, $this->getStreetErrors($address), $this->getLocalityErrors($address));
    }

    private function getLocalityErrors(Address $address): array
    {
        $errors = [];
        $country = (int) $address->countryId;
        $town = $this->normalizeField((string) $address->town);
        $postcode = $this->normalizeField((string) $address->postalCode);

        if ($country <= 0) {
            $errors[] = 'Das Lieferland fehlt. Bitte ein Land auswählen.';
        }

        if ($town === '') {
            $errors[] = 'Der Ort fehlt. Bitte den Ortsnamen eintragen.';
        } elseif (preg_match('/\A[^\p{Cc}]*\p{L}[^\p{Cc}]*\z/u', $town) !== 1) {
            $errors[] = 'Im Feld Ort steht kein gültiger Ortsname. Bitte den Ortsnamen eintragen.';
        } elseif ($postcode !== '' && stripos($town, $postcode . ' ') === 0) {
            $errors[] = 'Im Feld Ort steht auch die Postleitzahl. Bitte beide Angaben in ihre eigenen Felder schreiben.';
        }

        // PlentyONE's fixed country IDs determine the required postal-code format.
        $postcodeLengths = [self::GERMANY => 5, self::AUSTRIA => 4, self::SWITZERLAND => 4];
        $requiredLength = $postcodeLengths[$country] ?? 0;

        if ($postcode === '' && $requiredLength > 0) {
            $errors[] = 'Die Postleitzahl fehlt. Bitte die PLZ im Feld Postleitzahl eintragen.';
        } elseif ($requiredLength > 0 && preg_match('/\A[0-9]{' . $requiredLength . '}\z/', $postcode) !== 1) {
            $errors[] = 'Die Postleitzahl muss für dieses Lieferland aus genau ' . $requiredLength . ' Ziffern bestehen.';
        } elseif ($postcode !== '' && preg_match('/\A[A-Z0-9]+(?:[ -][A-Z0-9]+)*\z/i', $postcode) !== 1) {
            $errors[] = 'Die Postleitzahl hat ein ungültiges Format. Bitte das Feld Postleitzahl prüfen.';
        }

        return $errors;
    }

    private function getStreetErrors(Address $address): array
    {
        $errors = [];
        $street = $this->normalizeField((string) $address->address1);
        $number = $this->normalizeField((string) $address->address2);

        if (preg_match('/\A(?:Packstation|Postfiliale|DHL Paketshop)\z/i', $street) === 1) {
            return $this->getDhlAddressErrors($address, $street, $number);
        }

        if ($street === '') {
            $errors[] = 'Die Straße fehlt. Bitte den Straßennamen in Adresse 1 eintragen.';
        } elseif (preg_match('/\A[^\p{Cc}]*\p{L}[^\p{Cc}]*\z/u', $street) !== 1) {
            $errors[] = 'In Adresse 1 steht kein gültiger Straßenname. Bitte nur die Straße eintragen.';
        } else {
            if ($this->containsHouseNumber($street)) {
                $errors[] = 'In Adresse 1 stehen Straße und Hausnummer zusammen. Bitte in Adresse 1 nur die Straße und in Adresse 2 die Hausnummer eintragen.';
            }

            if (preg_match('/(?:\A| )c\/o(?: |\z)|, *(?:Wohnung|Whg\.?|Etage|Hinterhaus)\b/iu', $street) === 1) {
                $errors[] = 'Bei der Straße steht ein Adresszusatz. Bitte den Zusatz in Adresse 3 eintragen.';
            }
        }

        if ($number === '') {
            $errors[] = 'Die Hausnummer fehlt im Feld Adresse 2.';
        } elseif (preg_match('/\A' . self::HOUSE_NUMBER . '\z/u', $number) !== 1) {
            $errors[] = 'Das Feld Hausnummer (Adresse 2) ist fehlerhaft. Bitte nur die Hausnummer eintragen und Zusätze in Adresse 3 verschieben.';
        }

        return $errors;
    }

    private function getDhlAddressErrors(Address $address, string $street, string $number): array
    {
        $errors = [];

        if ((int) $address->countryId !== self::GERMANY) {
            $errors[] = 'Für diese DHL-Abholadresse muss Deutschland als Lieferland ausgewählt sein.';
        }

        if (preg_match('/\A[0-9]{3}\z/', $number) !== 1) {
            $errors[] = 'Bitte die dreistellige Nummer der Packstation oder Filiale in Adresse 2 eintragen.';
        }

        if (strcasecmp($street, 'Packstation') === 0
            && preg_match('/\A[0-9]+\z/', $this->normalizeField((string) $address->postident)) !== 1
            && preg_match('/\A[0-9]+\z/', $this->normalizeField((string) $address->address3)) !== 1
        ) {
            $errors[] = 'Für die Packstation fehlt eine gültige DHL-Postnummer. Bitte die Postnummer oder Adresse 3 prüfen.';
        }

        return $errors;
    }

    private function containsHouseNumber(string $street): bool
    {
        $streetName = $this->normalizeField(explode(',', $street, 2)[0]);

        // Numbered street names such as "Straße 17" are valid with a separate house number.
        if (preg_match('/\A(?:Straße|Strasse|Weg|Street|Avenue|Rue|Route) +(?:Nr\.? +)?\p{Nd}+\z/iu', $streetName) === 1) {
            return false;
        }

        $numberInStreet = '/(?:\p{L}.*[ .]' . self::HOUSE_NUMBER . '\z|\A' . self::HOUSE_NUMBER . ' +.*\p{L})/u';

        return preg_match($numberInStreet, $streetName) === 1;
    }

    private function normalizeField(string $value): string
    {
        return trim(preg_replace('/\h+/u', ' ', $value) ?? '', ' ');
    }
}
