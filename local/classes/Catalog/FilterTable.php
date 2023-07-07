<?php

namespace Legacy\Catalog;

use Legacy\General\Constants;
use Legacy\Iblock\ElementPropertyTable;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionPropertyTable;
use Bitrix\Catalog\ProductTable;

class FilterTable extends ProductTable
{
    public static function withDefault(Query $query, $properties)
    {
        $query->setSelect([
            'ID',
        ]);
        $query->addFilter("@TYPE", [ProductTable::TYPE_PRODUCT, ProductTable::TYPE_OFFER]);

        $query->registerRuntimeField(
            'ELEMENT',
            new ReferenceField(
                'ELEMENT',
                ElementTable::class,
                [
                    'this.ID' => 'ref.ID',
                ]
            )
        );
        $query->addFilter("ELEMENT.ACTIVE", true);
        
        $propertyFilter = [
            'LOGIC'=>'OR',
        ];
        foreach ($properties as $pcode => $item) {
            $key = 'PROPERTY_'.$pcode;
            $query->registerRuntimeField(
                $key,
                new ReferenceField(
                    $key,
                    ElementPropertyTable::class,
                    [
                        'this.ID' => 'ref.IBLOCK_ELEMENT_ID',
                        'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', $item['ID']),
                    ]
                )
            );
            $query->addSelect($key.'.VALUE', 'P_'.$pcode);
            $propertyFilter['!==P_'.$pcode] = null;
        }
        $query->addFilter(null, $propertyFilter);
    } 

    public static function withFilter(Query $query, array $filter)
    {
        foreach ($filter as $code => $value) {
            $query->addFilter('@P_'.mb_strtoupper($code), $value);
        }
    }

    public static function withFromCategory(Query $query, string $category)
    {
        if (mb_strlen($category) > 0 && $category !== 'all') {
            $query->registerRuntimeField(
                'CML2_LINK',
                new ReferenceField(
                    'CML2_LINK',
                    ElementPropertyTable::class,
                    [
                        'this.ID' => 'ref.IBLOCK_ELEMENT_ID',
                        'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::IB_PROP_OFFERS_CML2_LINK),
                    ]
                )
            );
            $query->registerRuntimeField(
                'SKU',
                new ReferenceField(
                    'SKU',
                    ElementTable::class,
                    [
                        'this.CML2_LINK.VALUE' => 'ref.ID',
                    ]
                )
            );

            $query->addFilter(null, [
                'LOGIC' => 'OR',
                'ELEMENT.IBLOCK_SECTION.CODE' => $category,
                'SKU.IBLOCK_SECTION.CODE' => $category
            ]);
        }
    }
}