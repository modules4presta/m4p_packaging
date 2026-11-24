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
 * Counts units of packaged products in a cart (packages x pieces per package) against their thresholds.
 */
class M4pPackagingThreshold
{
    /**
     * True when at least one product with packages reaches its own threshold.
     */
    public static function isReached(Cart $cart): bool
    {
        foreach (self::getCartUnitsByProduct($cart) as $idProduct => $units) {
            $threshold = M4pPackagingProductConfig::getThreshold($idProduct);
            if ($threshold > 0 && $units >= $threshold) {
                return true;
            }
        }

        return false;
    }

    public static function getCartUnits(Cart $cart, int $idProduct): int
    {
        return self::getCartUnitsByProduct($cart)[$idProduct] ?? 0;
    }

    /**
     * @return array<int, int> units per id_product
     */
    private static function getCartUnitsByProduct(Cart $cart): array
    {
        if (!$cart->id) {
            return [];
        }

        $unitsByProduct = [];
        foreach ($cart->getProducts() as $line) {
            $package = M4pPackagingPackage::getByProductAttribute((int) $line['id_product_attribute']);
            if (!$package) {
                continue;
            }

            $idProduct = (int) $line['id_product'];
            $unitsByProduct[$idProduct] = ($unitsByProduct[$idProduct] ?? 0)
                + (int) $line['cart_quantity'] * (int) $package['qty_per_package'];
        }

        return $unitsByProduct;
    }
}
