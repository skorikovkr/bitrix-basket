<?php

namespace Legacy\Sale;

use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Sale\Internals\BasketTable;

class BasketElementTable extends \Bitrix\Iblock\ElementTable
{
    public static function withSelect(Query $query)
    {
        $query->setSelect([
            'ID',
            'NAME',
            'PREVIEW_PICTURE',
        ]);
    }

    public static function withProperties(Query $query)
    {
        $query->registerRuntimeField(
            'ELEMENT_PROPERTY', 
            new ReferenceField(
                'ELEMENT_PROPERTY',
                \Legacy\Iblock\ElementPropertyTable::class,
                [
                    'ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                ]
            )
        );
        $query->registerRuntimeField(
            'PROPERTY_FEATURE', 
            new ReferenceField(
                'PROPERTY_FEATURE',
                \Bitrix\Iblock\PropertyFeatureTable::class,
                [
                    'ref.PROPERTY_ID' => 'this.ELEMENT_PROPERTY.IBLOCK_PROPERTY_ID',
                ]
            )
        );

        $query->addFilter('=PROPERTY_FEATURE.FEATURE_ID', "IN_BASKET");
        $query->addFilter('=PROPERTY_FEATURE.IS_ENABLED', "Y");

        $query->addSelect('ELEMENT_PROPERTY.IBLOCK_PROPERTY.ID', 'PROPERTY_ID');
        $query->addSelect('ELEMENT_PROPERTY.IBLOCK_PROPERTY.NAME', 'PROPERTY_NAME');
        $query->addSelect('ELEMENT_PROPERTY.IBLOCK_PROPERTY.CODE', 'PROPERTY_CODE');
        $query->addSelect('ELEMENT_PROPERTY.VALUE', 'PROPERTY_VALUE');
    }

    public static function withBasket(Query $query, $UID, $OID = null)
    {
        if ($UID > 0) {
            $query->registerRuntimeField(
                'BASKET', 
                new ReferenceField(
                    'BASKET',
                    BasketTable::class,
                    [
                        'ref.PRODUCT_ID' => 'this.ID',
                        'ref.FUSER_ID' => new SqlExpression('?', $UID),
                    ],
                )
            );

            $query->addFilter('!=BASKET_ID', null);
            $query->addFilter('=BASKET.ORDER_ID', $OID);

            $query->addSelect('BASKET.ID', 'BASKET_ID');
            $query->addSelect('BASKET.PRICE', 'PRICE');
            $query->addSelect('BASKET.BASE_PRICE', 'BASE_PRICE');
            $query->addSelect('BASKET.QUANTITY', 'QUANTITY');
        }
    }

    public static function withID(Query $query, $ID)
    {
        $query->addFilter('=ID', $ID);
    }
}