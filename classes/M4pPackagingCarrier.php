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
 * The "arranged delivery" carrier, the only option for carts that reach a packaged product's threshold.
 */
class M4pPackagingCarrier
{
    public const CONFIG_REFERENCE = 'M4P_PACKAGING_CARRIER_REFERENCE';
    public const CONFIG_GROUPS = 'M4P_PACKAGING_ARRANGED_GROUPS';

    public static function getCarrierId(): int
    {
        $reference = (int) Configuration::getGlobalValue(self::CONFIG_REFERENCE);
        if ($reference <= 0) {
            return 0;
        }

        $carrier = Carrier::getCarrierByReference($reference);

        return $carrier instanceof Carrier ? (int) $carrier->id : 0;
    }

    /**
     * Uses the stored carrier, then the newest carrier created by the module (restored when deleted), and creates
     * one only when none exists.
     */
    public static function ensureCarrier(string $moduleName): int
    {
        $idCarrier = self::getCarrierId() ?: self::findModuleCarrier($moduleName);
        $carrier = $idCarrier > 0 ? new Carrier($idCarrier) : self::createCarrier($moduleName);

        if ($carrier->deleted) {
            $carrier->deleted = false;
            $carrier->update();
        }

        Configuration::updateGlobalValue(self::CONFIG_REFERENCE, (int) $carrier->id_reference);

        return (int) $carrier->id;
    }

    public static function deleteCarrier(): void
    {
        $idCarrier = self::getCarrierId();
        if ($idCarrier <= 0) {
            return;
        }

        $carrier = new Carrier($idCarrier);
        $carrier->deleted = true;
        $carrier->update();
    }

    /**
     * Leaves only the delivery options with the carrier when a product in the cart reaches its threshold or the
     * customer belongs to one of the selected groups, and removes them otherwise.
     *
     * @param array<int, array<string, array<string, mixed>>> $deliveryOptions Cart::getDeliveryOptionList() result
     *
     * @return array<int, array<string, array<string, mixed>>>
     */
    public static function filterDeliveryOptions(Cart $cart, array $deliveryOptions): array
    {
        $idCarrier = self::getCarrierId();
        if ($idCarrier <= 0) {
            return $deliveryOptions;
        }

        $keepCarrier = self::isCustomerInArrangedGroup($cart) || M4pPackagingThreshold::isReached($cart);
        foreach ($deliveryOptions as $idAddress => $options) {
            $carrierOptions = array_filter(
                $options,
                static fn (array $option): bool => isset($option['carrier_list'][$idCarrier])
            );
            if ($keepCarrier && $carrierOptions === []) {
                continue;
            }

            $deliveryOptions[$idAddress] = $keepCarrier ? $carrierOptions : array_diff_key($options, $carrierOptions);
        }

        return $deliveryOptions;
    }

    /**
     * @return int[]
     */
    public static function getArrangedGroupIds(): array
    {
        $groupIds = json_decode((string) Configuration::getGlobalValue(self::CONFIG_GROUPS), true);

        return is_array($groupIds) ? array_values(array_filter(array_map('intval', $groupIds))) : [];
    }

    /**
     * @param int[] $groupIds
     */
    public static function saveArrangedGroupIds(array $groupIds): bool
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));

        return Configuration::updateGlobalValue(self::CONFIG_GROUPS, json_encode($groupIds));
    }

    private static function isCustomerInArrangedGroup(Cart $cart): bool
    {
        $arrangedGroupIds = self::getArrangedGroupIds();
        if ($arrangedGroupIds === [] || (int) $cart->id_customer <= 0) {
            return false;
        }

        $customerGroupIds = array_map('intval', Customer::getGroupsStatic((int) $cart->id_customer));

        return array_intersect($arrangedGroupIds, $customerGroupIds) !== [];
    }

    private static function findModuleCarrier(string $moduleName): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT id_carrier FROM `' . _DB_PREFIX_ . 'carrier`
             WHERE external_module_name = \'' . pSQL($moduleName) . '\'
             ORDER BY deleted ASC, id_carrier DESC'
        );
    }

    private static function createCarrier(string $moduleName): Carrier
    {
        $translator = Context::getContext()->getTranslator();
        $defaultLanguage = new Language((int) Configuration::get('PS_LANG_DEFAULT'));

        $carrier = new Carrier();
        $carrier->name = $translator->trans('Arranged delivery', [], 'Modules.M4ppackaging.Admin', (string) $defaultLanguage->locale);
        $carrier->active = true;
        $carrier->is_free = true;
        $carrier->shipping_handling = false;
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_FREE;
        $carrier->range_behavior = false;
        $carrier->grade = 9;
        $carrier->url = '';
        $carrier->external_module_name = $moduleName;
        foreach (Language::getLanguages(false) as $language) {
            $carrier->delay[(int) $language['id_lang']] = $translator->trans(
                'Delivery date agreed with you after the order',
                [],
                'Modules.M4ppackaging.Admin',
                (string) $language['locale']
            );
        }

        if (!$carrier->add()) {
            throw new PrestaShopException('Cannot create the arranged delivery carrier');
        }

        $carrier->setGroups(array_column(Group::getGroups((int) $defaultLanguage->id), 'id_group'));
        foreach (Zone::getZones(true) as $zone) {
            $carrier->addZone((int) $zone['id_zone']);
        }

        return $carrier;
    }
}
