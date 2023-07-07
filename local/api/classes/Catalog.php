<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Legacy\Catalog\CatalogTable;
use Legacy\Catalog\FilterTable;
use Legacy\General\Constants;

class Catalog
{

    private static function processData($query)
    {
        $result = [];

        $properties = Properties::get(['iblock_id' => Constants::IB_CLOTHES, 'is_smart_filter' => true]);
        $db = $query->exec();
        while ($arr = $db->fetch()) {
            $arr['OFFER_PRICE'] = array_map(function ($a) {
                $tmp = explode(':', $a);
                return [
                    'ID' => $tmp[0],
                    'PRICE' => $tmp[1],
                ];
            }, explode(',', $arr['OFFER_PRICE']));
            foreach ($arr['OFFER_PRICE'] as $val) {
                if ($val['PRICE'] == $arr['MIN_PRICE']) {
                    $arr['OFFER_ID'] = $val['ID'];
                }
            }
            unset($arr['OFFER_PRICE']);
 
            $pkey = 'PROPERTY_'.$properties['SIZE']['ID'].'_VALUE';
            if ($arr[$pkey]) {
                $arr['OFFERS'] = array_map(function ($a) use ($properties) {
                    $tmp = explode(':', $a);
                    return [
                        'ID' => $tmp[0],
                        'SIZE' => [
                            'ALIAS' => $properties['SIZE']['VALUES'][$tmp[1]]['alias'],
                            'CODE' => $tmp[1],
                        ],
                    ];
                }, explode(',', $arr[$pkey]));
            }

            $result[$arr['OFFER_ID']]= [
                'ID' => $arr['OFFER_ID'],
                'MIN_PRICE' => $arr['MIN_PRICE'],
                'MAX_PRICE' => $arr['MAX_PRICE'],
                'OFFERS' => $arr['OFFERS'],
            ];
        }

        $db = ElementTable::getList([
            'select' => [
                'ID',
                'NAME',
                'PREVIEW_PICTURE',
                'DETAIL_PICTURE',
            ],
            'filter' => [
                'ID' => array_keys($result),
            ],
        ]);
        while ($arr = $db->fetch()) {
            foreach ($result as $key => $item) {
                if ($arr['ID'] == $item['ID']) {
                    $result[$key]['NAME'] = mb_strlen(trim($arr['NAME'])) > 35 ? mb_substr(trim($arr['NAME']), 0, 33).'…' : trim($arr['NAME']);
                    $result[$key]['PREVIEW_PICTURE'] = getFilePath($arr['PREVIEW_PICTURE']);
                    $result[$key]['DETAIL_PICTURE'] = getFilePath($arr['DETAIL_PICTURE']);
                    break;
                }
            }
        }

        return array_change_key_case_recursive(array_values($result));
    }

    public static function getCategories()
    {
        $result = [
            [
                'id' => 0,
                'name' => 'Все товары',
                'code' => 'all',
            ],
        ];

        if (Loader::includeModule('iblock')) {
            $db = SectionTable::getList([
                'select' => [
                    'ID',
                    'NAME',
                    'CODE',
                ],
                'filter' => [
                    'IBLOCK_ID' => Constants::IB_CLOTHES,
                    'ACTIVE' => true,
                ],
            ]);

            while ($res = $db->fetch()) {
                $result []= [
                    'id' => $res['ID'],
                    'name' => $res['NAME'],
                    'code' => $res['CODE'],
                ];
            }
        }

        return $result;
    }

    public static function getFilter($arRequest)
    {
        $result = [
            'categories' => [
                'name' => 'Категория',
                'items' => self::getCategories()
            ],
        ];

        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $properties = Properties::get(['iblock_id' => Constants::IB_CLOTHES, 'is_smart_filter' => true]);
            $category = $arRequest['category'] ?? '';
            $filter = $arRequest['filter'] ?? [];
            if (count($filter) == 1) {
                foreach ($properties as $pcode => $property) {
                    if (mb_strtolower($property['CODE']) == mb_strtolower((key($filter)))) {
                        foreach ($property['VALUES'] as $key => $value) {
                            $properties[$pcode]['VALUES'][$key]['is_available'] = true;
                        }
                        break;
                    }
                }
            }

            $q = FilterTable::query()->withDefault($properties)->withFromCategory($category)->withFilter($filter);
            $db = $q->exec();
            while ($arr = $db->fetch()) {
                foreach ($arr as $column => $value) {
                    if (mb_strpos($column, 'P_') === 0) {
                        $code = mb_substr($column, 2);
                        foreach ($properties as $pcode => $property) {
                            if ($pcode == $code) {
                                $properties[$pcode]['VALUES'][$value]['is_available'] = true;
                            }
                        }
                    }
                }
            }

            foreach ($properties as $pid => $property) {
                $isEmpty = true;
                foreach($property['VALUES'] as $code => $value) {
                    $isEmpty = !$value;
                    if (!$isEmpty) {
                        break;
                    }
                }
                if ($isEmpty) {
                    unset($properties[$pid]);
                }
            }

            foreach ($properties as $pid => $property) {
                $result[mb_strtolower($property['CODE'])] = [
                    'name' => $property['NAME'],
                    'items' => $property['VALUES'],
                ];
            }
        }

        return $result;
    }

    public static function get($arRequest)
    {
        $result = [];
        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $id = $arRequest['id'] ?? [];
            $page = (int) $arRequest['page'];
            $category = $arRequest['category'] ?? '';
            $filter = $arRequest['filter'] ?? [];
            $filter = self::getFilter($arRequest);

            $q = CatalogTable::query()
            ->withDefault()
            ->withID($id)
            ->withFromCategory($category)
            ->withFilter($arRequest['filter'] ?? [])
            ->withGroupByProperty(Constants::IB_PROP_CLOTHES_COLOR, Constants::IB_PROP_CLOTHES_OFFERS_SIZES_CLOTHES)
            ->withPage($page)
            ->withExclude($arRequest['exclude'] ?? [])
            ;

            switch ($arRequest['sortby']) {
                case 'new':
                    $q->withOrderBy('MAX_DATE_CREATE', 'desc');
                    break;
                case 'popular':
                    $q->withOrderBy('MAX_SHOW_COUNTER', 'desc');
                    break;
                case 'cheap':
                    $q->withOrderBy('MIN_PRICE', CatalogTable::ASCENDING);
                    break;
                case 'expensive':
                    $q->withOrderBy('MIN_PRICE', CatalogTable::DESCENDING);
                    break;
            }

            $result['items'] = self::processData($q);
            $result['count'] = $q->queryCountTotal();
            $result['categories'] = self::getCategories();
            $result['filter'] = $filter;
        }

        return $result;
    }
}