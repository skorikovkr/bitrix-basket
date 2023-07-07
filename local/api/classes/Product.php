<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Web\Json;
use Legacy\Catalog\ProductElementTable;
use Legacy\API\Sizetable;
use Legacy\General\Constants;
use Bitrix\Catalog\ProductTable;

class Product
{
    private static function processCatalog($query)
    {
        $result = [];

        $db = $query->exec();
        if ($res = $db->fetch()) {
            $result['id'] = $res['ID'];
            $result['type'] = $res['PRODUCT_TYPE'];
            $result['name'] = trim($res['NAME']);
            if ($res['SECTION_ID']) {
                $result['section_id'] = $res['SECTION_ID'];
            }
            $result['preview_picture'] = $res['PREVIEW_PICTURE'];
            $result['preview_text'] = $res['PREVIEW_TEXT'];
            $result['detail_picture'] = $res['DETAIL_PICTURE'];
            $result['detail_text'] = $res['DETAIL_TEXT'];
            $result['prices'][$res['PRICE_CATALOG_GROUP_ID']] = $res['PRICE_PRICE'];
        }
        $result['preview_picture'] = getFilePath($result['preview_picture']);
        $result['detail_picture'] = getFilePath($result['detail_picture']);
        $result['price_min'] = min($result['prices']);
        $result['price_max'] = max($result['prices']);
        unset($result['prices']);
        
        return $result;
    }

    public static function processProperties($query, $properties)
    {
        $result = [];

        $db = $query->exec();
        if ($res = $db->fetch()) {
            foreach($res as $code => $value) {
                if (mb_strpos($code, 'PROPERTY_') !== false) {
                    $matches = [];
                    if (preg_match('/PROPERTY_(.*)_VALUE/', $code, $matches)) {
                        $pcode = $matches[1];
                        if ($properties[$pcode]) {
                            switch ($properties[$pcode]['USER_TYPE']) {
                                case 'sprint_editor':
                                    $result['content'] = Json::decode($value ?? '{}');
                                    foreach($result['content']['blocks'] as $i => $block) {
                                        foreach($block['images'] as $j => $image) {
                                            $result['content']['blocks'][$i]['images'][$j]['file']['ORIGIN_SRC'] = getServerName().$image['file']['ORIGIN_SRC'];
                                            $result['content']['blocks'][$i]['images'][$j]['file']['SRC'] = getServerName().$image['file']['SRC'];
                                        }

                                        if ($block['file']) {
                                            $result['content']['blocks'][$i]['file']['ORIGIN_SRC'] = getServerName().$result['content']['blocks'][$i]['file']['ORIGIN_SRC'];
                                            $result['content']['blocks'][$i]['file']['SRC'] = getServerName().$result['content']['blocks'][$i]['file']['SRC'];
                                        }
                                    }
                                    break;
                                case 'SKU':
                                    $qc = ProductElementTable::query()->withID($value)->withCatalog();
                                    $elementProperties = Properties::get(['iblock_id' => Constants::IB_CATALOG]);
                                    $qp = ProductElementTable::query()->withID($value)->withProperties($elementProperties);
                                    $el = array_merge(self::processCatalog($qc), self::processProperties($qp, $elementProperties));
                                    $result['section_id'] = $el['section_id'];
                                    $result['content'] = $el['content'];
                                    $result['offers'] = Offers::get(['id' => $el['id']]);
                                    break;
                                default:
                                    $result['properties'][mb_strtolower($pcode)] = [];
                                    $property = &$result['properties'][mb_strtolower($pcode)];
        
                                    $property['id'] = $properties[$pcode]['ID'];
                                    $property['code'] = $pcode;
                                    $property['name'] = $properties[$pcode]['NAME'];
                                    $property['alias'] = $properties[$pcode]['VALUES'][$value]['alias'];
        
                                    if ($properties[$pcode]['MULTIPLE']) {
                                        $property['value'] = explode(',', $value);
                                    } else {
                                        $property['value'] = $value;
                                    }
        
                                    if ($properties[$pcode]['TYPE'] == PropertyTable::TYPE_FILE) {
                                        if (is_array($property['value'])) {
                                            foreach ($property['value'] as $i => $k) {
                                                $property['value'][$i] = getFilePath($k);
                                            }
                                        } else {
                                            $property['value'] = getFilePath($property['value']);
                                        }
                                    }       
                                    break;
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    public static function getRelatedItems($arRequest)
    {
        $result = [];
        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $id = (int) $arRequest['id'];
            if ($id > 0) {
                $res = Catalog::get([
                    'category' => $id,
                    'exclude' => $arRequest['exclude'],
                ]);
                $result = $res['items'];
            }
        }

        return $result;
    }

    public static function get($arRequest)
    {
        $result = [
            'data' => [],
            'offers' => [],
            'size_table' => [],
            'related_items' => [],
        ];

        if (Loader::includeModule('iblock') && Loader::includeModule('catalog')) {
            $id = (int) $arRequest['id'];
            if ($id > 0) {
                $q = ProductElementTable::query()->withID($id)->withCatalog();
                $result['data'] = self::processCatalog($q);
                switch($result['data']['type']) {
                    case ProductTable::TYPE_PRODUCT:
                        $properties = Properties::get(['iblock_id' => Constants::IB_CATALOG]);
                        break;
                    case ProductTable::TYPE_OFFER:
                        $properties = array_merge(
                            Properties::get(['iblock_id' => Constants::IB_OFFERS]),
                            Properties::get(['iblock_id' => Constants::IB_OFFERS, 'is_smart_filter' => true]),
                        );
                        break;
                }
                unset($result['data']['type']);
                if ($properties) {
                    $q = ProductElementTable::query()->withID($id)->withProperties($properties);
                    $result['data'] = array_merge(self::processProperties($q, $properties), $result['data']);
                }
                $result['offers'] = $result['data']['offers'];
                unset($result['data']['offers']);

                $exclude = array_unique(array_merge([$result['data']['id']], array_keys($result['offers']) ?? []));
                $result['related_items'] = self::getRelatedItems(['id' => $result['data']['section_id'], 'exclude' => $exclude]);

                foreach($result['offers'] as $offer) {
                    foreach($offer['properties'] as $pkey => $pvalue) {
                        if ($pkey == 'gender') {
                            if (!isset($result['size_table'][$pvalue['value']])) {
                                if ($table = Sizetable::getByGender(['code' => $pvalue['value']])) {
                                    $result['size_table'][$pvalue['value']] = $table;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }
}