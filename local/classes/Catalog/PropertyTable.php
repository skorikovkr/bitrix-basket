<?php

namespace Legacy\Catalog;

use Legacy\General\Constants;
use Legacy\Iblock\ElementPropertyTable;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionPropertyTable;
use Bitrix\Catalog\ProductTable;

class PropertyTable extends \Bitrix\Iblock\PropertyTable
{
    public static function setDefaultScope($query)
    {
        $query->setSelect([
            'ID',
            'IBLOCK_ID',
            'CODE',
            'NAME',
            'PROPERTY_TYPE',
            'MULTIPLE',
            'USER_TYPE',
            'USER_TYPE_SETTINGS',
        ]);
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

    public static function withRuntimeHighloadBlocks(Query $query)
    {
        $query->setSelect([new ExpressionField('SETTINGS', 'DISTINCT %s', ['USER_TYPE_SETTINGS'])]);
        $query->registerRuntimeField(
            'SECTION_PROPERTY',
            new ReferenceField(
                'SECTION_PROPERTY',
                SectionPropertyTable::class,
                [
                    'this.ID' => 'ref.PROPERTY_ID',
                ]
            )
        );
        $query->setFilter([
            'ACTIVE' => true,
            'SECTION_PROPERTY.SMART_FILTER' => true,
        ]);
    }

    public static function withProperties(Query $query)
    {
        $query->setSelect([
            'ID',
            'IBLOCK_ID',
            'CODE',
            'NAME',
            'PROPERTY_TYPE',
            'MULTIPLE',
            'USER_TYPE',
            'USER_TYPE_SETTINGS',
        ]);
        $query->setFilter(['ACTIVE' => true]);
    }

    public static function withValues(Query $query)
    {
        $query->registerRuntimeField(
            'ELEMENT_PROPERTY',
            new ReferenceField(
                'ELEMENT_PROPERTY',
                ElementPropertyTable::class,
                [
                    'this.ID' => 'ref.IBLOCK_PROPERTY_ID',
                ]
            )
        );
        $query->addSelect('ELEMENT_PROPERTY.VALUE', 'VALUE');
    }

    public static function withSmartFilterOnly(Query $query)
    {
        $query->registerRuntimeField(
            'SECTION_PROPERTY',
            new ReferenceField(
                'SECTION_PROPERTY',
                SectionPropertyTable::class,
                [
                    'this.ID' => 'ref.PROPERTY_ID',
                ]
            )
        );
        $query->setFilter(['SECTION_PROPERTY.SMART_FILTER' => true]);
    }

    public static function withAddSelect(Query $query, $value)
    {
        $select = $query->getSelect();
        $query->setSelect(array_merge($select, $value));
    }

    public static function withAddRuntime(Query $query, $values)
    {
        foreach ($values as $key => $value) {
            $query->registerRuntimeField($key, new ReferenceField(
                $key,
                $value['data_type'],
                $value['reference'],
            ));
        }
    }

    public static function withIblockFilter(Query $query, $iblockId)
    {
        $query->addFilter("IBLOCK_ID", $iblockId);
    }

    public static function withSort(Query $query)
    {
        $query->addOrder('SORT', 'ASC');
    }
}