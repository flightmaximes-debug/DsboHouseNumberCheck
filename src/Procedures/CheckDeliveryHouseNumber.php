<?php

namespace DsboHouseNumberCheck\Procedures;

use Plenty\Modules\Account\Address\Models\Address;
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
        $order = $orders->findById($orderId, ['addresses']);

        if ((float) $order->statusId === self::REVIEW_STATUS) {
            return;
        }

        $deliveryAddress = $order->deliveryAddress;
        if ($deliveryAddress instanceof Address && !$this->isCompleteDeliveryAddress($deliveryAddress)) {
            $orders->update($orderId, ['statusId' => self::REVIEW_STATUS]);
        }
    }

    private function isCompleteDeliveryAddress(Address $address): bool
    {
        $company = (string) $address->name1;
        $recipient = $company . ' ' . $address->name2 . ' ' . $address->name3;

        if (preg_match('/\A[^\p{Cc}]{0,40}\z/u', $company) !== 1
            || preg_match('/[^\p{Z}\p{Cc}]/u', $recipient) !== 1
        ) {
            return false;
        }

        return $this->hasValidLocality($address) && $this->hasSeparateStreetAndNumber($address);
    }

    private function hasValidLocality(Address $address): bool
    {
        $country = (int) $address->countryId;
        $town = $this->normalizeField((string) $address->town);
        $postcode = $this->normalizeField((string) $address->postalCode);

        if ($country <= 0 || preg_match('/\A[^\p{Cc}]*\p{L}[^\p{Cc}]*\z/u', $town) !== 1) {
            return false;
        }

        if ($postcode !== '' && stripos($town, $postcode . ' ') === 0) {
            return false;
        }

        // PlentyONE's fixed country IDs determine the required postal-code format.
        switch ($country) {
            case self::GERMANY:
                return preg_match('/\A[0-9]{5}\z/', $postcode) === 1;
            case self::AUSTRIA:
            case self::SWITZERLAND:
                return preg_match('/\A[0-9]{4}\z/', $postcode) === 1;
            default:
                return $postcode === '' || preg_match('/\A[A-Z0-9]+(?:[ -][A-Z0-9]+)*\z/i', $postcode) === 1;
        }
    }

    private function hasSeparateStreetAndNumber(Address $address): bool
    {
        $street = $this->normalizeField((string) $address->address1);
        $number = $this->normalizeField((string) $address->address2);

        if (preg_match('/\A(?:Packstation|Postfiliale|DHL Paketshop)\z/i', $street) === 1) {
            if ((int) $address->countryId !== self::GERMANY || preg_match('/\A[0-9]{3}\z/', $number) !== 1) {
                return false;
            }

            return strcasecmp($street, 'Packstation') !== 0
                || preg_match('/\A[0-9]+\z/', $this->normalizeField((string) $address->postident)) === 1
                || preg_match('/\A[0-9]+\z/', $this->normalizeField((string) $address->address3)) === 1;
        }

        if (preg_match('/\A' . self::HOUSE_NUMBER . '\z/u', $number) !== 1
            || preg_match('/\A[^\p{Cc}]*\p{L}[^\p{Cc}]*\z/u', $street) !== 1
            || preg_match('/(?:\A| )c\/o(?: |\z)|, *(?:Wohnung|Whg\.?|Etage|Hinterhaus)\b/iu', $street) === 1
        ) {
            return false;
        }

        $streetName = $this->normalizeField(explode(',', $street, 2)[0]);

        // Numbered street names such as "Straße 17" are valid with a separate house number.
        if (preg_match('/\A(?:Straße|Strasse|Weg|Street|Avenue|Rue|Route) +(?:Nr\.? +)?\p{Nd}+\z/iu', $streetName) === 1) {
            return true;
        }

        $numberInStreet = '/(?:\p{L}.*[ .]' . self::HOUSE_NUMBER . '\z|\A' . self::HOUSE_NUMBER . ' +.*\p{L})/u';

        return preg_match($numberInStreet, $streetName) === 0;
    }

    private function normalizeField(string $value): string
    {
        return trim(preg_replace('/\h+/u', ' ', $value) ?? '', ' ');
    }
}
