<?php

namespace Legacy\Catalog;

use Legacy\Iblock\ElementPropertyTable;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Catalog\ProductTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Iblock\ElementTable;
use \Bitrix\Main\DB\SqlExpression;

class ProductElementTable extends ElementTable
{
    public static function withID(Query $query, int $id)
    {
        $query->addFilter('=ID', $id);
        $query->setSelect([
            'ID',
            'NAME',
            'SECTION_ID' => 'IBLOCK_SECTION.ID',
            'SECTION_CODE' => 'IBLOCK_SECTION.CODE',
            'PREVIEW_PICTURE',
            'PREVIEW_TEXT',
            'DETAIL_PICTURE',
            'DETAIL_TEXT',
        ]);
    }

    public static function withCatalog(Query $query)
    {
        $query->registerRuntimeField(
            'PRODUCT',
            new ReferenceField(
                'PRODUCT',
                ProductTable::class,
                [
                    'this.ID' => 'ref.ID',
                ]
            )
        );
        $query->registerRuntimeField(
            'PRICE',
            new ReferenceField(
                'PRICE',
                PriceTable::class,
                [
                    'this.ID' => 'ref.PRODUCT_ID',
                ]
            )
        );
        
        $query->addSelect('PRODUCT.TYPE', 'PRODUCT_TYPE');
        $query->addSelect('PRICE.PRICE', 'PRICE_PRICE');
        $query->addSelect('PRICE.CATALOG_GROUP_ID', 'PRICE_CATALOG_GROUP_ID');
    }

    public static function withProperties(Query $query, $properties)
    {
        foreach($properties as $code => $property) {
            $key = 'PROPERTY_'.$code;
            $query->registerRuntimeField(
                $key,
                new ReferenceField(
                    $key,
                    ElementPropertyTable::class,
                    [
                        'this.ID' => 'ref.IBLOCK_ELEMENT_ID',
                        'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', $property['ID']),
                    ]
                )
            );
            if ($property['MULTIPLE']) {
                $query->addSelect(new \Bitrix\Main\Entity\ExpressionField(
                    $key.'_VALUE',
                    "GROUP_CONCAT(distinct %s)",
                    [$key.'.VALUE']
                ));
            } else {
                $query->addSelect($key.'.VALUE', $key.'_VALUE');
            }
        }
    }
}