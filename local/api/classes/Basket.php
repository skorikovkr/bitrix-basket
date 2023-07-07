<?php

namespace Legacy\API;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale;
use Legacy\General\Constants;

class Basket
{
    private static function getBasketItemProps($arRequest)
    {
        $id = (int) $arRequest['id'];
        if ($id <= 0) {
            throw new \Exception('Некорректный ID товара');
        }

        $q = \Legacy\Sale\BasketElementTable::query()->withID($id)->withSelect();
        $db = $q->exec();
        $properties = [];
        while ($res = $db->fetch()) {
            $properties []= [
                'NAME' => $res['PROPERTY_NAME'],
                'CODE' => $res['PROPERTY_CODE'],
                'VALUE' => $res['PROPERTY_VALUE'],
            ];
        }
        return $properties;
    }

    public static function getLength($arRequest)
    {
        $basket = \Legacy\Sale\Basket::loadItems();

        return ['length' => $basket->getLength()];
    }

    public static function getPrice($arRequest)
    {
        $basket = \Legacy\Sale\Basket::loadItems();

        return ['price' => $basket->getPrice()];
    }

    public static function remove($arRequest)
    {
        $basketId = intval($arRequest['id']);

        $basket = \Legacy\Sale\Basket::loadItems();
        $basket->delete($basketId);

        return array_merge(self::getLength($arRequest), self::getPrice($arRequest));
    }

    public static function add($arRequest)
    {
        $fields = [
            'PRODUCT_ID' => $arRequest['id'],
            'QUANTITY' => $arRequest['qunatity'] ?? 1,
            'PROPS' => self::getBasketItemProps($arRequest),
        ];
        $r = \Bitrix\Catalog\Product\Basket::addProduct($fields);
        if ($r->isSuccess()) {
            return array_merge($r->getData(), self::getLength($arRequest), self::getPrice($arRequest)); 
        } else {
            throw new \Exception(implode('. ', $r->getErrorMessages()));
        }
    }

    public static function setQuantity($arRequest)
    {
        if (!Loader::includeModule('sale')) {
            throw new \Exception('Не удалось подключить необходимые модули.');
        }

        /** ID товара в корзине */
        $id = intval($arRequest['id']);
        if ($id <= 0) {
            throw new ArgumentException('Неверный ID товара.');
        }

        $quantity = intval($arRequest['quantity']);
        if ($quantity <= 0) {
            throw new ArgumentException('Неверное количество товара.');
        }

        $basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Context::getCurrent()->getSite());
        $basketItem = $basket->getItemById($id);
        $basketItem->setField('QUANTITY', $quantity);
        $obRes = $basket->save();

        if ($obRes->isSuccess()) {
            return array_merge(self::getLength($arRequest), self::getPrice($arRequest));
        }
    }

    public static function get()
    {
        $result = ['items' => [], 'count' => 0];
        if ($user = User::get()) {
            $items = [];
            $properties = Properties::get(['iblock_id' => Constants::IB_OFFERS, 'is_smart_filter' => true]);
            $q = \Legacy\Sale\BasketElementTable::query()->withSelect()->withProperties()->withBasket($user['id']);
            $db = $q->exec();
            while ($arr = $db->fetch()) {
                $items[$arr['ID']]['id'] = $arr['ID'];
                $items[$arr['ID']]['bid'] = $arr['BASKET_ID'];
                $items[$arr['ID']]['name'] = mb_strlen($arr['NAME']) > 35 ? mb_substr($arr['NAME'], 0, 34).'…' : trim($arr['NAME']);
                $items[$arr['ID']]['fullname'] = $arr['NAME'];
                $items[$arr['ID']]['picture'] = getFilePath($arr['PREVIEW_PICTURE']);
                $items[$arr['ID']]['price'] = $arr['PRICE'];
                $items[$arr['ID']]['price_base'] = $arr['BASE_PRICE'];
                $items[$arr['ID']]['quantity'] = $arr['QUANTITY'];
                $items[$arr['ID']]['properties'][mb_strtolower($arr['PROPERTY_CODE'])] = [
                    'id' => $arr['PROPERTY_ID'],
                    'code' => $arr['PROPERTY_CODE'],
                    'name' => $arr['PROPERTY_NAME'],
                    'value' => $arr['PROPERTY_VALUE'],
                    'values' => $properties[$arr['PROPERTY_CODE']]['VALUES'],
                ];
            }
            $result['items'] = $items;
            $result['count'] = count($items);
        }
        return $result;
    }
}
