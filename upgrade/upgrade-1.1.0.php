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
 * Renames settings and columns to the module prefix, registers the current hooks and recalculates the combination
 * prices from the gross package prices.
 */
function upgrade_module_1_1_0(M4p_packaging $module): bool
{
    m4p_packaging_1_1_0_rename_column('m4p_package', 'price', '`price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT \'0.000000\'');
    m4p_packaging_1_1_0_rename_column('m4p_product_config', 'free_shipping_threshold', '`arranged_delivery_threshold` INT(10) UNSIGNED NOT NULL DEFAULT 0');

    m4p_packaging_1_1_0_move_setting('M4P_ATTRIBUTE_GROUP_ID', M4pPackagingAttributes::CONFIG_GROUP);
    $oldCarrierId = (int) Configuration::getGlobalValue('M4P_CARRIER_ID');
    if ($oldCarrierId > 0 && !Configuration::getGlobalValue(M4pPackagingCarrier::CONFIG_REFERENCE)) {
        $carrier = new Carrier($oldCarrierId);
        if (Validate::isLoadedObject($carrier)) {
            Configuration::updateGlobalValue(M4pPackagingCarrier::CONFIG_REFERENCE, (int) $carrier->id_reference);
        }
    }
    Configuration::deleteByName('M4P_CARRIER_ID');
    Configuration::deleteByName('M4P_CARRIER_REFERENCE');

    foreach (['actionProductSave', 'actionCarrierUpdate', 'displayHeader'] as $hook) {
        $module->unregisterHook($hook);
    }
    $module->registerHook([
        'actionObjectProductUpdateAfter',
        'actionFrontControllerSetMedia',
        'actionFrontControllerSetVariables',
    ]);

    M4pPackagingCarrier::ensureCarrier($module->name);
    M4pPackagingAttributes::ensureGroup();
    M4pPackagingAttributes::translateValueNames();

    foreach (M4pPackagingPackage::getProductIds() as $idProduct) {
        $product = new Product($idProduct);
        if (Validate::isLoadedObject($product)) {
            M4pPackagingPackage::syncPricesForProduct($product);
        }
    }

    return true;
}

function m4p_packaging_1_1_0_rename_column(string $table, string $oldColumn, string $definition): void
{
    $exists = Db::getInstance()->executeS(
        'SHOW COLUMNS FROM `' . _DB_PREFIX_ . $table . '` LIKE \'' . pSQL($oldColumn) . '\''
    );
    if (!empty($exists)) {
        Db::getInstance()->execute(
            'ALTER TABLE `' . _DB_PREFIX_ . $table . '` CHANGE `' . bqSQL($oldColumn) . '` ' . $definition
        );
    }
}

function m4p_packaging_1_1_0_move_setting(string $oldKey, string $newKey): void
{
    $value = Configuration::getGlobalValue($oldKey);
    if ($value !== false && !Configuration::getGlobalValue($newKey)) {
        Configuration::updateGlobalValue($newKey, $value);
    }
    Configuration::deleteByName($oldKey);
}
