<?php

namespace Legacy\Sale;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale;
use Legacy\Main\User;

class Basket
{
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

        if (!\CModule::IncludeModule('iblock')) {
            throw new \Exception('Не удалось подключить модуль "iblock".');
        }
    }

    public static function loadItems()
    {
        global $USER;
        $self = new self;
        $self->basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Context::getCurrent()->getSite());
        return $self;
    }

    public function getPrice()
    {
        return $this->basket->getPrice();
    }

    public function getLength()
    {
        return $this->basket->count();
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

    public function deleteBunch($ids)
    {
        foreach ($ids as $id) {
            $basketItem = $this->basket->getItemById($id);
            if ($basketItem)
                $basketItem->delete();
        }
        return $this->save();
    }

    public function deleteAll()
    {
        foreach ($this->getItems() as $item) {
            $basketItem = $this->basket->getItemById($item["ID"]);
            $basketItem->delete();
        }
        return $this->save();
    }

    public function getBasket()
    {
        return $this->basket;
    }

    public function getUser()
    {
        return new User($this->order->getUserId());
    }

    public function addProduct($product) {
        return \Bitrix\Catalog\Product\Basket::addProduct($product);
    }

    public function changeQuantity($basketItemId, $quantity) {
        if ($quantity <= 0) {
            throw new ArgumentException('Неверное количество товара.');
        }
        if ($basketItemId <= 0) {
            throw new ArgumentException('Неверный ID товара.');
        }

        $basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Context::getCurrent()->getSite());
        $basketItem = $basket->getItemById($basketItemId);
        $basketItem->setField('QUANTITY', $quantity);
        $obRes = $basket->save();
        return $obRes;
    }

    public function getItems() {
        $basketRes = Sale\Internals\BasketTable::getList(array(
            'filter' => array(
                'FUSER_ID' => Sale\Fuser::getId(),
            )
        ));
        return $basketRes;
    }
}
