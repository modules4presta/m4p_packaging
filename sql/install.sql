CREATE TABLE IF NOT EXISTS `PREFIX_m4p_package` (
    `id_m4p_package` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(10) UNSIGNED NOT NULL,
    `id_product_attribute` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `id_attribute` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `qty_per_package` INT(10) UNSIGNED NOT NULL DEFAULT 1,
    `price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT '0.000000',
    `moq` INT(10) UNSIGNED NOT NULL DEFAULT 1,
    `position` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_m4p_package`),
    KEY `idx_id_product` (`id_product`),
    KEY `idx_id_product_attribute` (`id_product_attribute`)
) ENGINE=_MYSQL_ENGINE_ DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_m4p_product_config` (
    `id_product` INT(10) UNSIGNED NOT NULL,
    `arranged_delivery_threshold` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_product`)
) ENGINE=_MYSQL_ENGINE_ DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
