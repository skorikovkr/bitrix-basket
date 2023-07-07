<?php

namespace Legacy\API;

use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Context;
use Bitrix\Sale;
use Legacy\General\Constants;

class Order
{
    public static function checkout($arRequest)
    {
        $user = User::get();

        $userId = $user['id'];
        if (is_null($userId)) {
            throw new \Exception('Ошибка при оформлении заказа.');
        }

        $basket = Basket::get();
        $order = Sale\Order::create(Context::getCurrent()->getSite(), $userId);
        $order->setPersonTypeId(Constants::PERSON_TYPE_INDIVIDUAL);
        $order->setField('USER_DESCRIPTION', $arRequest['comment']);

        $propertyCollection = $order->getPropertyCollection();

        $fio = $propertyCollection->getPayerName();
        $fio->setValue($arRequest['name']);

        $phone = $propertyCollection->getPhone();
        $phone->setValue($arRequest['phone']);

        $email = $propertyCollection->getUserEmail();
        $email->setValue($arRequest['email']);

        try {
            $city = $basket->getOrderProperty('CITY');
            $reqCity = Json::decode($arRequest['city']);
            if (is_array($reqCity) && mb_strlen($reqCity['value']) > 0) {
                $city->setValue($reqCity['value']);
            }
        } catch (\Throwable $e) {}

        $address = $basket->getOrderProperty('ADDRESS');
        if ($arRequest['delivery'] == 'pickup') {
            $store = Store::get(['id' => $arRequest['address']]);
            $addressValue = $store['ADDRESS'];
        } else {
            $addressValue = $arRequest['address'];
        }
        if ($address) {
            $address->setValue($addressValue);
        }

        $shipmentCollection = $order->getShipmentCollection();
        if($arRequest['delivery'] == 'pickup') {
            $shipment = $shipmentCollection->createItem(
                \Bitrix\Sale\Delivery\Services\Manager::getObjectById(Constants::DELIVERY_SAMOVYVOZ)
            );
        } else {
            $shipment = $shipmentCollection->createItem(
                \Bitrix\Sale\Delivery\Services\Manager::getObjectById(Constants::DELIVERY_DOSTAVKA_KUREROM)
            );
        }
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        foreach ($basket->getBasket() as $basketItem)
        {
            $item = $shipmentItemCollection->createItem($basketItem);
            $item->setQuantity($basketItem->getQuantity());
        }

        $paymentCollection = $order->getPaymentCollection();
        $paymentCode = 'PAY_SYSTEM_'.mb_strtoupper($arRequest['payment']);
        $reflector = new \ReflectionClass(Constants::class);
        $constants = $reflector->getConstants();
        if (!$constants[$paymentCode]) {
            throw new \Exception('Ошибка платежной системы.');
        }
        $payment = $paymentCollection->createItem(
            \Bitrix\Sale\PaySystem\Manager::getObjectById($constants[$paymentCode])
        );
        $payment->setField("SUM", $order->getPrice());
        $payment->setField("CURRENCY", $order->getCurrency());

        $orderId = $basket->order();
        if ($orderId && $arRequest['payment'] == 'card') {
            if (is_null($_SESSION['SALE_ORDER_ID'])) {
                $_SESSION['SALE_ORDER_ID'] = [];
            }
            $_SESSION['SALE_ORDER_ID'] []= $orderId;

            $context = Application::getInstance()->getContext();
            $service = Sale\PaySystem\Manager::getObjectById($payment->getPaymentSystemId());
            ob_start('paymentButtonHtml');
            $result = $service->initiatePay($payment, $context->getRequest());
            $paymentButtonHtml = ob_get_contents();
            ob_end_clean();

            $doc = new \DOMDocument();
            $doc->loadHTML($paymentButtonHtml);
            $tags = $doc->getElementsByTagName('a');
            if ($tag = $tags->item(0)) {
                $href = $tag->getAttribute('href');
            }

            if (!$result->isSuccess())
            {
                throw new \Exception(implode('. ', $result->getErrorMessages()));
            }
        }

        return ['payment_link' => $href ?? '', 'orderNum' => $orderId];
    }
}
