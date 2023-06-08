<?php
/**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0).
 * It is also available through the world-wide-web at this URL: https://opensource.org/licenses/AFL-3.0
 */

namespace PrestaShop\Module\mwrspecialcategory\Entity;

use PrestaShop\PrestaShop\Adapter\Entity\ObjectModel;

/**
 * This entity database state is managed by PrestaShop ObjectModel
 */
class SpecialCategory extends ObjectModel
{
    /**
     * @var int
     */
    public $id_category;

    /**
     * @var int
     */
    public $is_special_category;

    public static $definition = [
        'table' => 'mwrspecialcategory',
        'primary' => 'id_modifier',
        'fields' => [
            'id_category' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'is_special_category' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
        ],
    ];
}