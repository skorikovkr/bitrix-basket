<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use Legacy\Catalog\PropertyTable;
use Legacy\HighLoadBlock\Entity;

class Properties
{

    private static function processData($query)
    {
        $result = [];

        $db = $query->exec();
        while ($res = $db->fetch()) {
            $res['USER_TYPE_SETTINGS'] = unserialize($res['USER_TYPE_SETTINGS']);
            $tableName = $res['USER_TYPE_SETTINGS']['TABLE_NAME'];
            $alias = '';
            if ($tableName) {
                $alias = $res[$tableName.'_NAME'];
            }

            if (isset($result[$res['CODE']])) {
                if (!array_key_exists($res['VALUE'], $result[$res['ID']]['VALUES']) && $alias) {
                    $result[$res['CODE']]['VALUES'][$res['VALUE']] = [
                        'alias' => $alias,
                    ];
                }
            } else {
                $result[$res['CODE']]['ID'] = $res['ID'];
                $result[$res['CODE']]['IBLOCK_ID'] = $res['IBLOCK_ID'];
                $result[$res['CODE']]['CODE'] = $res['CODE'];
                $result[$res['CODE']]['NAME'] = $res['NAME'];
                $result[$res['CODE']]['TYPE'] = $res['PROPERTY_TYPE'];
                $result[$res['CODE']]['USER_TYPE'] = $res['USER_TYPE'];
                $result[$res['CODE']]['MULTIPLE'] = ($res['MULTIPLE'] == 'Y');
                if ($alias) {
                    $result[$res['CODE']]['VALUES'] = [$res['VALUE'] => ['alias' => $alias]];
                }
            }
        }

        return $result;
    }

    public static function get($arRequest)
    {
        $result = [];
        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $iblockId = $arRequest['iblock_id'];
            $query = PropertyTable::query()->withRuntimeHighloadBlocks();
            if ($iblockId > 0) {
                $query->withIblockFilter($iblockId);
            }

            $select = [];
            $runtime = [];
            $db = $query->exec();
            while ($res = $db->fetch()) {
                $res['SETTINGS'] = unserialize($res['SETTINGS']);
                $tableName = $res['SETTINGS']['TABLE_NAME'];
                $select[$tableName.'_NAME'] = $tableName.'.UF_NAME';
                $id = Entity::getInstance()->getId($tableName);
                $runtime[$tableName] = [
                    'data_type' => Entity::getInstance()->getDataClass($id),
                    'reference' => [
                        'this.ELEMENT_PROPERTY.VALUE' => 'ref.UF_XML_ID',
                    ],
                ];
            }

            if ($arRequest['is_smart_filter']) {
                $query = self::getSmartFilter($arRequest);
                $query->withAddSelect($select)->withAddRuntime($runtime)->withSmartFilterOnly()->withValues();
            } else {
                $query = self::getProperties($arRequest);
            }

            if ($iblockId > 0) {
                $query->withIblockFilter($iblockId);
            }
            $result = self::processData($query);
        }

        return $result;
    }

    private static function getProperties($arRequest)
    {
        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $iblockId = $arRequest['iblock_id'];

            $query = PropertyTable::query()
            ->withProperties()
            ->withIblockFilter($iblockId)
            ->withSort()
            ;

            return $query;
        }

        throw new \Exception('Ошибка!');
    }

    private static function getSmartFilter($arRequest)
    {
        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $iblockId = $arRequest['iblock_id'];

            $query = PropertyTable::query()
            ->withProperties()
            ->withIblockFilter($iblockId)
            ->withSmartFilterOnly()
            ->withValues()
            ->withSort()
            ;
            return $query;
        }

        throw new \Exception('Ошибка!');
    }
}