<?php

namespace Legacy\API;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale;
use Legacy\General\Constants;

class Basket
{
    private static function getBasketItemProps($id)
    {
        return \CIBlockElement::GetByID($id)->GetNextElement()->GetProperties();
    }

    private static function mapBrandRefsToNames($brandIds) {

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
        $basket = \Legacy\Sale\Basket::loadItems();
        $fields = [
            'PRODUCT_ID' => $arRequest['id'],
            'QUANTITY' => $arRequest['quantity'] ?? 1,
        ];
        $r = $basket->addProduct($fields);
        if ($r->isSuccess()) {
            return array_merge($r->getData(), self::getLength($arRequest), self::getPrice($arRequest));
        } else {
            throw new \Exception(implode('. ', $r->getErrorMessages()));
        }
    }

    public static function setQuantity($arRequest)
    {
        /** ID товара в корзине */
        $id = intval($arRequest['id']);
        $quantity = intval($arRequest['quantity']);
        $basket = \Legacy\Sale\Basket::loadItems();
        $r = $basket->changeQuantity($id, $quantity);

        if ($r->isSuccess()) {
            return array_merge(self::getLength($arRequest), self::getPrice($arRequest));
        } else {
            throw new \Exception(implode('. ', $r->getErrorMessages()));
        }
    }

    public static function get()
    {
        $result = [];
        $items = [];
        $basket = \Legacy\Sale\Basket::loadItems();
        $itemsRes = $basket->getItems();
        while ($arr = $itemsRes->fetch()) {
            $props = self::getBasketItemProps(intval($arr['PRODUCT_ID']));
            $items[$arr['ID']]['id'] = $arr['ID'];
            $items[$arr['ID']]['name'] = mb_strlen($arr['NAME']) > 35 ? mb_substr($arr['NAME'], 0, 34).'…' : trim($arr['NAME']);
            $items[$arr['ID']]['fullname'] = $arr['NAME'];
            $items[$arr['ID']]['price'] = $arr['PRICE'];
            $items[$arr['ID']]['price_base'] = $arr['BASE_PRICE'];
            $items[$arr['ID']]['quantity'] = $arr['QUANTITY'];
            $items[$arr['ID']]['article'] = $props["ARTNUMBER"]["VALUE"];
            $items[$arr['ID']]['measure'] = $arr["MEASURE_NAME"];

            $XML_IDs = $props["BRAND_REF"]["VALUE"];
            $a = $props["BRAND_REF"]['USER_TYPE_SETTINGS'];
            $tableName = $a['TABLE_NAME'];
            $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(
                array("filter" => array(
                    'TABLE_NAME' => $tableName
                ))
            )->fetch();
            if (isset($hlblock['ID']))
            {
                foreach ($XML_IDs as $XML_ID) {
                    $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
                    $entity_data_class = $entity->getDataClass();
                    $res = $entity_data_class::getList( array('filter'=>array( 'UF_XML_ID' => $XML_ID,)) );
                    if ($item = $res->fetch())
                    {
                        $items[$arr['ID']]['brand'] []= $item["UF_NAME"];
                    }
                }
            }

            $items[$arr['ID']]['arr'] = $arr;
            $items[$arr['ID']]['props'] = $props;
        }
        $result['items'] = $items;
        $result['count'] = count($items);

        return $result;
    }
}