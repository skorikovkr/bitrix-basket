<?php

namespace Legacy\API;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale;

class Basket
{
    private static function getBasketItemProps($id)
    {
        return \CIBlockElement::GetByID($id)->GetNextElement()->GetProperties();
    }

    private static function mapBrandRefsToNames($brands) {
        $XML_IDs = $brands["VALUE"];
        $a = $brands['USER_TYPE_SETTINGS'];
        $tableName = $a['TABLE_NAME'];
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(
            array("filter" => array(
                'TABLE_NAME' => $tableName
            ))
        )->fetch();
        $result = [];
        if (isset($hlblock['ID']))
        {
            foreach ($XML_IDs as $XML_ID) {
                $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
                $entity_data_class = $entity->getDataClass();
                $res = $entity_data_class::getList( array('filter'=>array( 'UF_XML_ID' => $XML_ID,)) );
                if ($item = $res->fetch())
                {
                    $result []= $item["UF_NAME"];
                }
            }
        }
        return $result;
    }

    private static function getSectionName($productId) {
        $rsElement = \CIBlockElement::GetList(array(), array('ID' => $productId), false, false, array('ID', 'IBLOCK_SECTION_ID'));
        if($arElement = $rsElement->Fetch())
        {
            $res = \CIBlockSection::GetByID($arElement['IBLOCK_SECTION_ID']);
            if($ar_res = $res->GetNext())
                return  $ar_res['NAME'];
        }
        return null;
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

    public static function removeBunch($arRequest)
    {
        $ids = [];
        foreach (json_decode($arRequest["ids"]) as $id)
            $ids []= intval($id);
        $basket = \Legacy\Sale\Basket::loadItems();
        $basket->deleteBunch($ids);
        return array_merge(self::getLength($arRequest), self::getPrice($arRequest));
    }

    public static function removeAll()
    {
        $basket = \Legacy\Sale\Basket::loadItems();
        $basket->deleteAll();
        return array_merge(['length' => $basket->getLength()], ['price' => $basket->getPrice()]);
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
            $items[$arr['ID']]['price_base'] = $arr['BASE_PRICE'];
            $items[$arr['ID']]['quantity'] = $arr['QUANTITY'];
            $items[$arr['ID']]['full_price'] = doubleval($arr['QUANTITY']) * doubleval($arr['BASE_PRICE']);
            $items[$arr['ID']]['article'] = $props["ARTNUMBER"]["VALUE"];
            $items[$arr['ID']]['measure'] = $arr["MEASURE_NAME"];
            $items[$arr['ID']]['brands'] = self::mapBrandRefsToNames($props["BRAND_REF"]);
            $items[$arr['ID']]['category'] = self::getSectionName($arr['PRODUCT_ID']);
        }
        $result['items'] = $items;
        $result['count'] = count($items);
        $result['total_price'] = $basket->getPrice();

        return $result;
    }
}