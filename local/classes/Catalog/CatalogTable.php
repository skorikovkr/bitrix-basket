<?php

namespace Legacy\Catalog;

use Legacy\General\Constants;
use Legacy\Iblock\ElementPropertyTable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\GroupLangTable;

class CatalogTable extends PriceTable
{
    const DEFAULT_LIMIT = 24;
    const ASCENDING = 'ASC';
    const DESCENDING = 'DESC';

    public static function withDefault(Query $query)
    {
        $query->addFilter("ELEMENT.ACTIVE", true);
        $query->addFilter("@PRODUCT.TYPE", [ProductTable::TYPE_PRODUCT, ProductTable::TYPE_OFFER]);
        $query->addFilter("@ELEMENT.IBLOCK_ID", [Constants::IB_CATALOG, Constants::IB_OFFERS]);
        
        $query->registerRuntimeField(
            'CML2_LINK',
            new ReferenceField(
                'CML2_LINK',
                ElementPropertyTable::class,
                [
                    'this.PRODUCT_ID' => 'ref.IBLOCK_ELEMENT_ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::IB_PROP_OFFERS_CML2_LINK),
                ]
            )
        );
        $query->addSelect(new ExpressionField('SKU_ID', 'CASE WHEN %s IS NOT NULL THEN %s ELSE %s END', ['CML2_LINK.VALUE', 'CML2_LINK.VALUE', 'PRODUCT_ID']));

        $query->registerRuntimeField(
            'REAL_ELEMENT',
            new ReferenceField(
                'REAL_ELEMENT',
                ElementTable::class,
                [
                    'this.CML2_LINK.VALUE' => 'ref.ID',
                ]
            )
        );
        
        $query->addSelect(new ExpressionField('MAX_DATE_CREATE', 'MAX(UNIX_TIMESTAMP(%s))', ['ELEMENT.DATE_CREATE']));
        $query->addSelect(new ExpressionField('MAX_SHOW_COUNTER', 'MAX(%s)', ['ELEMENT.SHOW_COUNTER']));

        $query->addSelect(new ExpressionField('OFFER_PRICE', 'GROUP_CONCAT(CONCAT(%s,":",%s))', ['PRODUCT_ID', 'PRICE']));
        $query->addSelect(new ExpressionField('MIN_PRICE', 'MIN(%s)', ['PRICE']));
        $query->addSelect(new ExpressionField('MAX_PRICE', 'MAX(%s)', ['PRICE']));

        if ($query->getLimit() == 0) {
            $query->setLimit(self::DEFAULT_LIMIT);
        }
    } 

    public static function withID(Query $query, array $ids)
    {
        if (count($ids) > 0) {
            $query->addFilter('=PRODUCT_ID', $ids);
        }
    }

    public static function withExclude(Query $query, array $ids)
    {
        if (count($ids) > 0) {
            $query->addFilter('!=PRODUCT_ID', $ids);
        }
    }

    public static function withFilter(Query $query, array $filter)
    {
        foreach ($filter as $code => $value) {
            $key = 'FILTER_PROPERTY_'.mb_strtoupper($code);
            $query->registerRuntimeField(
                $key, 
                new ReferenceField(
                    $key,
                    ElementPropertyTable::class,
                    [
                        'this.PRODUCT_ID' => 'ref.IBLOCK_ELEMENT_ID',
                        'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', constant('Legacy\General\Constants::IB_PROP_OFFERS_'.mb_strtoupper($code))),
                    ]
                )
            );
            $query->addFilter('@'.$key.'.VALUE', $value);
        }
    }

    public static function withGroupByProperty(Query $query, int $pid, int $opid)
    {
        $key = 'PROPERTY_'.$pid;
        $query->registerRuntimeField(
            $key, 
            new ReferenceField(
                $key,
                ElementPropertyTable::class,
                [
                    'this.PRODUCT_ID' => 'ref.IBLOCK_ELEMENT_ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', $pid),
                ]
            )
        );
        $query->addSelect($key.'.VALUE', $key.'_VALUE');
        $query->addGroup($key.'_VALUE');

        $key = 'PROPERTY_'.$opid;
        $query->registerRuntimeField(
            $key, 
            new ReferenceField(
                $key,
                ElementPropertyTable::class,
                [
                    'this.PRODUCT_ID' => 'ref.IBLOCK_ELEMENT_ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', $opid),
                ]
            )
        );
        $query->addSelect(new ExpressionField($key.'_VALUE', 'GROUP_CONCAT(distinct CONCAT(%s, ":", %s))', ['PRODUCT_ID', $key.'.VALUE']));
    }

    public static function withFromCategory(Query $query, string $category)
    {
        if (mb_strlen($category) > 0 && $category !== 'all') {
            $query->addFilter(null, [
                'LOGIC' => 'OR',
                'ELEMENT.IBLOCK_SECTION.CODE' => $category,
                'REAL_ELEMENT.IBLOCK_SECTION.CODE' => $category
            ]);
        }
    }

    public static function withPage(Query $query, int $page)
    {
        if ($page > 0) {
            $query->setOffset(($page - 1) * $query->getLimit());
        }
    }

    public static function withOrderBy(Query $query, $def, $order)
    {
        $query->addOrder($def, $order);
    }
}