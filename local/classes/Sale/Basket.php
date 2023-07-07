<?php

namespace Legacy\Sale;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale;
use Legacy\Main\User;

class Basket
{
    /** @var Sale\Order $order */
    var $order;
    /** @var Sale\Basket $basket */
    var $basket;

    private function __construct()
    {
        if (!Loader::includeModule('sale')) {
            throw new \Exception('Не удалось подключить модуль "sale".');
        }

        if (!Loader::includeModule('catalog')) {
            throw new \Exception('Не удалось подключить модуль "catalog".');
        }

        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Не удалось подключить модуль "iblock".');
        }
    }

    public static function loadItems()
    {
        global $USER;
        $self = new self;
        $self->order = Sale\Order::create(Context::getCurrent()->getSite(), $USER->GetID());
        $self->basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Context::getCurrent()->getSite());
        $self->order->setBasket($self->basket);
        return $self;
    }

    public function setCoupon($coupon)
    {
        Sale\DiscountCouponsManager::init(
            Sale\DiscountCouponsManager::MODE_ORDER, [
                "userId" => $this->order->getUserId(),
                "orderId" => $this->order->getId()
            ]
        );

        $res = Sale\DiscountCouponsManager::add($coupon);
        if (!$res) {
            $coupons = Sale\DiscountCouponsManager::get(true, [], true);
            $statusList = Sale\DiscountCouponsManager::getStatusList(true);
            throw new \Exception('Промокод '.$statusList[$coupons[$coupon]['STATUS']]);
        }

        $discounts = $this->order->getDiscount();
        $discounts->calculate();
        $this->save();
    }

    public function clearCoupon()
    {
        Sale\DiscountCouponsManager::clear(true);
        $discounts = $this->order->getDiscount();
        $discounts->calculate();
        $this->save();
    }

    public function getPrice()
    {
        return $this->basket->getPrice();
    }

    public function getLength()
    {
        return $this->basket->count();
    }

    public function getCoupons()
    {
        return Sale\DiscountCouponsManager::get(true, [], false);
    }

    public function save()
    {
        return $this->basket->save();
    }

    public function delete($id)
    {
        $basketItem = $this->basket->getItemById($id);
        $basketItem->delete();
        return $this->save();
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getBasket()
    {
        return $this->basket;
    }

    public function getOrderProperty(string $code)
    {
        $propertyCollection = $this->getOrder()->getPropertyCollection();
        foreach ($propertyCollection as $property) {
            if ($property->getField('CODE') == $code) {
                return $property;
            }
        }
        return null;
    }

    public function order()
    {
        $obResult = $this->order->save();
        if (!$obResult->isSuccess()) {
            throw new \Exception(implode('. ', $obResult->getErrorMessages()));
        }
        return $obResult->getId();
    }

    public function getUser()
    {
        return new User($this->order->getUserId());
    }
}
