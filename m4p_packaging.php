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

require_once __DIR__ . '/classes/M4pPackagingPackage.php';
require_once __DIR__ . '/classes/M4pPackagingProductConfig.php';
require_once __DIR__ . '/classes/M4pPackagingThreshold.php';
require_once __DIR__ . '/classes/M4pPackagingAttributes.php';
require_once __DIR__ . '/classes/M4pPackagingCarrier.php';
require_once __DIR__ . '/classes/M4pPackagingPlural.php';

/**
 * Sells a product in packages (1 pc, 9 pcs…) mapped to combinations, with a minimum order per package and an
 * "arranged delivery" carrier offered once the cart holds enough units of the product.
 */
class M4p_packaging extends Module
{
    public const ADMIN_CONTROLLER = 'AdminAjaxM4pPackaging';

    private const HOOKS = [
        'displayAdminProductsExtra',
        'actionAdminControllerSetMedia',
        'actionObjectProductUpdateAfter',
        'actionProductDelete',
        'displayProductVariants',
        'actionFrontControllerSetMedia',
        'actionFrontControllerSetVariables',
    ];

    public function __construct()
    {
        $this->name = 'm4p_packaging';
        $this->tab = 'pricing_promotion';
        $this->version = '1.2.0';
        $this->author = 'Modules4Presta';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Package variants', [], 'Modules.M4ppackaging.Admin');
        $this->description = $this->trans('Sells products in packages with a minimum order per package and an arranged delivery option from a number of units.', [], 'Modules.M4ppackaging.Admin');
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function install(): bool
    {
        return parent::install()
            && $this->runSqlFile('install.sql')
            && $this->registerHook(self::HOOKS)
            && $this->installTab()
            && M4pPackagingCarrier::ensureCarrier($this->name) > 0
            && M4pPackagingAttributes::ensureGroup() > 0
            && M4pPackagingCarrier::saveArrangedGroupIds([]);
    }

    /**
     * The attribute group, its values and the product combinations stay: orders refer to them.
     */
    public function uninstall(): bool
    {
        M4pPackagingCarrier::deleteCarrier();

        return parent::uninstall()
            && $this->runSqlFile('uninstall.sql')
            && $this->uninstallTab()
            && Configuration::deleteByName(M4pPackagingCarrier::CONFIG_REFERENCE)
            && Configuration::deleteByName(M4pPackagingCarrier::CONFIG_GROUPS)
            && Configuration::deleteByName(M4pPackagingAttributes::CONFIG_GROUP);
    }

    public function getContent(): string
    {
        $output = '';
        if (Tools::isSubmit('submitM4pPackaging')) {
            $groupIds = array_filter(
                array_column(Group::getGroups((int) $this->context->language->id), 'id_group'),
                static fn ($idGroup): bool => (bool) Tools::getValue('arranged_groups_' . (int) $idGroup)
            );
            $saved = M4pPackagingCarrier::saveArrangedGroupIds($groupIds);
            $output = $saved
                ? $this->displayConfirmation($this->trans('Settings saved.', [], 'Modules.M4ppackaging.Admin'))
                : $this->displayError($this->trans('Settings not saved. Try again.', [], 'Modules.M4ppackaging.Admin'));
        }

        return $output . $this->renderSettingsForm();
    }

    private function renderSettingsForm(): string
    {
        $groups = Group::getGroups((int) $this->context->language->id);

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitM4pPackaging';
        $helper->default_form_language = (int) $this->context->language->id;
        $selectedGroupIds = M4pPackagingCarrier::getArrangedGroupIds();
        foreach ($groups as $group) {
            $idGroup = (int) $group['id_group'];
            $helper->fields_value['arranged_groups_' . $idGroup] = in_array($idGroup, $selectedGroupIds, true);
        }

        return $helper->generateForm([[
            'form' => [
                'legend' => ['title' => $this->trans('Arranged delivery', [], 'Modules.M4ppackaging.Admin')],
                'input' => [[
                    'type' => 'checkbox',
                    'name' => 'arranged_groups',
                    'label' => $this->trans('Customer groups with arranged delivery only', [], 'Modules.M4ppackaging.Admin'),
                    'desc' => $this->trans('A customer in a selected group (default or additional) sees only arranged delivery at checkout, whatever the cart holds.', [], 'Modules.M4ppackaging.Admin'),
                    'values' => ['query' => $groups, 'id' => 'id_group', 'name' => 'name'],
                ]],
                'submit' => ['title' => $this->trans('Save', [], 'Modules.M4ppackaging.Admin')],
            ],
        ]]);
    }

    public function installTab(): bool
    {
        if (Tab::getIdFromClassName(self::ADMIN_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->active = true;
        $tab->class_name = self::ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->id_parent = -1;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $this->displayName;
        }

        return (bool) $tab->add();
    }

    private function uninstallTab(): bool
    {
        $idTab = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);

        return $idTab === 0 || (bool) (new Tab($idTab))->delete();
    }

    private function runSqlFile(string $fileName): bool
    {
        $sql = str_replace(['PREFIX_', '_MYSQL_ENGINE_'], [_DB_PREFIX_, _MYSQL_ENGINE_], (string) file_get_contents(__DIR__ . '/sql/' . $fileName));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Called from override/classes/Cart.php.
     *
     * @param array<int, array<string, array<string, mixed>>> $deliveryOptions
     *
     * @return array<int, array<string, array<string, mixed>>>
     */
    public function filterDeliveryOptions(Cart $cart, array $deliveryOptions): array
    {
        return M4pPackagingCarrier::filterDeliveryOptions($cart, $deliveryOptions);
    }

    /**
     * PS 9 purifies this hook's HTML (no data-* or inputs), so admin.js builds the panel inside this anchor.
     */
    public function hookDisplayAdminProductsExtra(array $params): string
    {
        return (int) ($params['id_product'] ?? 0) > 0 ? '<div id="m4p-packaging-root"></div>' : '';
    }

    public function hookActionAdminControllerSetMedia(): void
    {
        if (($this->context->controller->controller_name ?? '') !== 'AdminProducts') {
            return;
        }

        $this->context->controller->addJS($this->getPathUri() . 'views/js/admin.js?v=' . filemtime(__DIR__ . '/views/js/admin.js'));

        $currency = Currency::getDefaultCurrency();
        Media::addJsDef([
            'm4pPackagingConfig' => [
                'ajaxUrl' => $this->context->link->getAdminLink(self::ADMIN_CONTROLLER),
                'i18n' => [
                    'configure' => $this->trans('Configure packages', [], 'Modules.M4ppackaging.Admin'),
                    'title' => $this->trans('Package variants', [], 'Modules.M4ppackaging.Admin'),
                    'intro' => $this->trans('Each package becomes a combination of the product. The customer picks a package and orders a number of packages.', [], 'Modules.M4ppackaging.Admin'),
                    'threshold' => $this->trans('Arranged delivery from (pieces)', [], 'Modules.M4ppackaging.Admin'),
                    'thresholdHelp' => $this->trans('Pieces of this product in the cart (packages x pieces per package) from which arranged delivery is the only delivery option at checkout. 0 – never.', [], 'Modules.M4ppackaging.Admin'),
                    'qtyPerPkg' => $this->trans('Pieces per package', [], 'Modules.M4ppackaging.Admin'),
                    'price' => $this->trans('Gross price per package (%currency%)', ['%currency%' => $currency ? $currency->iso_code : ''], 'Modules.M4ppackaging.Admin'),
                    'priceHelp' => $this->trans('Price the customer pays for one package, tax included. The combination price is recalculated from it when the product price or tax rule changes.', [], 'Modules.M4ppackaging.Admin'),
                    'moq' => $this->trans('Minimum packages per order', [], 'Modules.M4ppackaging.Admin'),
                    'remove' => $this->trans('Remove', [], 'Modules.M4ppackaging.Admin'),
                    'addVariant' => $this->trans('Add package', [], 'Modules.M4ppackaging.Admin'),
                    'save' => $this->trans('Save packages', [], 'Modules.M4ppackaging.Admin'),
                    'close' => $this->trans('Close', [], 'Modules.M4ppackaging.Admin'),
                    'saving' => $this->trans('Saving…', [], 'Modules.M4ppackaging.Admin'),
                    'saved' => $this->trans('Packages saved. Reload the page to see the combinations.', [], 'Modules.M4ppackaging.Admin'),
                    'error' => $this->trans('Packages not saved:', [], 'Modules.M4ppackaging.Admin'),
                    'connError' => $this->trans('No connection with the shop. Try again.', [], 'Modules.M4ppackaging.Admin'),
                    'loading' => $this->trans('Loading…', [], 'Modules.M4ppackaging.Admin'),
                    'loadError' => $this->trans('Packages could not be loaded. Reload the page.', [], 'Modules.M4ppackaging.Admin'),
                ],
            ],
        ]);
    }

    public function hookActionObjectProductUpdateAfter(array $params): void
    {
        $product = $params['object'] ?? null;
        if ($product instanceof Product && Validate::isLoadedObject($product)) {
            M4pPackagingPackage::syncPricesForProduct($product);
        }
    }

    public function hookActionProductDelete(array $params): void
    {
        $idProduct = (int) ($params['id_product'] ?? 0);
        if ($idProduct <= 0) {
            return;
        }

        M4pPackagingPackage::deleteByProduct($idProduct);
        M4pPackagingProductConfig::deleteByProduct($idProduct);
    }

    /**
     * Lets the theme hide the core attribute selector of the packaging group on products with packages.
     *
     * @return array{attribute_group_id: int}
     */
    public function hookActionFrontControllerSetVariables(): array
    {
        $idProduct = (int) Tools::getValue('id_product');
        $hasPackages = ($this->context->controller->php_self ?? '') === 'product'
            && $idProduct > 0
            && M4pPackagingPackage::getByProduct($idProduct) !== [];

        return ['attribute_group_id' => $hasPackages ? M4pPackagingAttributes::getGroupId() : 0];
    }

    public function hookActionFrontControllerSetMedia(): void
    {
        if (($this->context->controller->php_self ?? '') !== 'product') {
            return;
        }

        $this->context->controller->registerStylesheet(
            'm4p-packaging',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 150, 'version' => (string) filemtime(__DIR__ . '/views/css/front.css')]
        );
        $this->context->controller->registerJavascript(
            'm4p-packaging',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 150, 'version' => (string) filemtime(__DIR__ . '/views/js/front.js')]
        );
    }

    public function hookDisplayProductVariants(array $params): string
    {
        $idProduct = (int) ($params['product']['id_product'] ?? $params['product']['id'] ?? 0);
        $packages = M4pPackagingPackage::getByProduct($idProduct);
        if ($packages === []) {
            return '';
        }

        $threshold = M4pPackagingProductConfig::getThreshold($idProduct);
        $this->context->smarty->assign([
            'm4p_packages' => $this->presentPackages($idProduct, $packages, (int) ($params['product']['id_product_attribute'] ?? 0)),
            'm4p_attribute_group_id' => M4pPackagingAttributes::getGroupId(),
            'm4p_threshold' => $threshold,
            'm4p_cart_units' => $threshold > 0 ? M4pPackagingThreshold::getCartUnits($this->context->cart, $idProduct) : 0,
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/hook/package-selector.tpl');
    }

    /**
     * @param array<int, array<string, mixed>> $packages
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentPackages(int $idProduct, array $packages, int $idSelectedCombination): array
    {
        $useTax = !Product::getTaxCalculationMethod((int) $this->context->cookie->id_customer);
        $locale = Tools::getContextLocale($this->context);
        $isoCurrency = $this->context->currency->iso_code;
        $languageLocale = (string) $this->context->language->locale;
        $piecesPattern = $this->trans('{count, plural, one {# pc} other {# pcs}}', [], 'Modules.M4ppackaging.Shop');
        $moqPattern = $this->trans('Minimum order: {count, plural, one {# package} other {# packages}}', [], 'Modules.M4ppackaging.Shop');
        $selectedFound = in_array($idSelectedCombination, array_map('intval', array_column($packages, 'id_product_attribute')), true);

        $presented = [];
        foreach ($packages as $index => $package) {
            $idCombination = (int) $package['id_product_attribute'];
            $pieces = max(1, (int) $package['qty_per_package']);
            $moq = max(1, (int) $package['moq']);
            $price = (float) Product::getPriceStatic($idProduct, $useTax, $idCombination);

            $presented[] = [
                'id_attribute' => (int) $package['id_attribute'],
                'id_product_attribute' => $idCombination,
                'pieces' => $pieces,
                'moq' => $moq,
                'selected' => $selectedFound ? $idCombination === $idSelectedCombination : $index === 0,
                'label' => M4pPackagingPlural::format($piecesPattern, $pieces, $languageLocale),
                'price' => $locale->formatPrice($price, $isoCurrency),
                'unit_price' => $pieces > 1
                    ? $this->trans('%price% / pc', ['%price%' => $locale->formatPrice($price / $pieces, $isoCurrency)], 'Modules.M4ppackaging.Shop')
                    : '',
                'moq_label' => $moq > 1
                    ? M4pPackagingPlural::format($moqPattern, $moq, $languageLocale)
                    : '',
            ];
        }

        return $presented;
    }
}
