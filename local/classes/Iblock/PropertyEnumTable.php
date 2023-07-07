<?php
namespace Legacy\Iblock;

use Bitrix\Main;

/**
 * Class PropertyEnumTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> PROPERTY_ID int mandatory
 * <li> VALUE string(255) mandatory
 * <li> DEF bool optional default 'N'
 * <li> SORT int optional default 500
 * <li> XML_ID string(200) mandatory
 * <li> TMP_ID string(40) optional
 * <li> PROPERTY reference to {@link \Bitrix\Iblock\IblockPropertyTable}
 * </ul>
 *
 * @package Bitrix\Iblock
 **/

class PropertyEnumTable extends Main\Entity\DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'b_iblock_property_enum';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return array(
            'ID' => array(
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
            ),
            'PROPERTY_ID' => array(
                'data_type' => 'integer',
                'required' => true,
            ),
            'VALUE' => array(
                'data_type' => 'string',
                'required' => true,
                'validation' => array(__CLASS__, 'validateValue'),
            ),
            'DEF' => array(
                'data_type' => 'boolean',
                'values' => array('N', 'Y'),
            ),
            'SORT' => array(
                'data_type' => 'integer',
            ),
            'XML_ID' => array(
                'data_type' => 'string',
                'required' => true,
                'validation' => array(__CLASS__, 'validateXmlId'),
            ),
            'TMP_ID' => array(
                'data_type' => 'string',
                'validation' => array(__CLASS__, 'validateTmpId'),
            ),
            'PROPERTY' => array(
                'data_type' => 'Bitrix\Iblock\IblockProperty',
                'reference' => array('=this.PROPERTY_ID' => 'ref.ID'),
            ),
        );
    }
    /**
     * Returns validators for VALUE field.
     *
     * @return array
     */
    public static function validateValue()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }
    /**
     * Returns validators for XML_ID field.
     *
     * @return array
     */
    public static function validateXmlId()
    {
        return array(
            new Main\Entity\Validator\Length(null, 200),
        );
    }
    /**
     * Returns validators for TMP_ID field.
     *
     * @return array
     */
    public static function validateTmpId()
    {
        return array(
            new Main\Entity\Validator\Length(null, 40),
        );
    }
}