<?php

$sql = [];

$sql[] = '
CREATE TABLE IF NOT EXISTS `' . pSQL(_DB_PREFIX_) . 'mwrspecialcategory` (
    `id_modifier` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_category` INT(10) UNSIGNED NOT NULL,
    `is_special_category` TINYINT(1) NOT NULL,
    PRIMARY KEY (`id_modifier`)
) ENGINE=' . pSQL(_MYSQL_ENGINE_) . ' COLLATE=utf8_unicode_ci;
';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
