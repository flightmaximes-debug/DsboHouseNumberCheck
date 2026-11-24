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
        if ($deliveryAddress instanceof Address && !$this->hasHouseNumber($deliveryAddress)) {
            $orders->update($orderId, ['statusId' => self::REVIEW_STATUS]);
        }
    }

    private function hasHouseNumber(Address $address): bool
    {
        if (preg_match('/\p{Nd}/u', (string) $address->address2) === 1) {
            return true;
        }

        // Some channels import the complete street line into address1.
        $street = trim(preg_replace('/\s+/u', ' ', (string) $address->address1) ?? '');
        $number = '\p{Nd}+(?: ?\p{L})?(?: *[-–\/] *\p{Nd}+(?: ?\p{L})?)?';

        return preg_match('/(?:\p{L}.* ' . $number . '$|^' . $number . ' +.*\p{L})/u', $street) === 1;
    }
}
