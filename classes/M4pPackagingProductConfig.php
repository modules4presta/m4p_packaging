<?php

declare(strict_types=1);

/**
 * m4p_packaging
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Per-product settings: units of the product in the cart that make the arranged delivery available.
 */
class M4pPackagingProductConfig
{
    public static function getThreshold(int $idProduct): int
    {
        if ($idProduct <= 0) {
            return 0;
        }

        $query = new DbQuery();
        $query->select('arranged_delivery_threshold')
            ->from('m4p_product_config')
            ->where('id_product = ' . $idProduct);

        return (int) Db::getInstance()->getValue($query);
    }

    public static function setThreshold(int $idProduct, int $thresholdUnits): void
    {
        Db::getInstance()->insert(
            'm4p_product_config',
            ['id_product' => $idProduct, 'arranged_delivery_threshold' => max(0, $thresholdUnits)],
            false,
            true,
            Db::ON_DUPLICATE_KEY
        );
    }

    public static function deleteByProduct(int $idProduct): void
    {
        Db::getInstance()->delete('m4p_product_config', 'id_product = ' . $idProduct);
    }
}
