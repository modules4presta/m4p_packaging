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
 * A package variant of a product, mirrored by one combination in the packaging attribute group.
 */
class M4pPackagingPackage extends ObjectModel
{
    /** @var int */
    public $id_product = 0;

    /** @var int */
    public $id_product_attribute = 0;

    /** @var int */
    public $id_attribute = 0;

    /** @var int */
    public $qty_per_package = 1;

    /** @var float */
    public $price_tax_incl = 0.0;

    /** @var int */
    public $moq = 1;

    /** @var int */
    public $position = 0;

    /** @var bool */
    public $active = true;

    public static $definition = [
        'table' => 'm4p_package',
        'primary' => 'id_m4p_package',
        'fields' => [
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'qty_per_package' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'price_tax_incl' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'moq' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getByProduct(int $idProduct): array
    {
        if ($idProduct <= 0) {
            return [];
        }

        $query = new DbQuery();
        $query->select('*')
            ->from('m4p_package')
            ->where('id_product = ' . $idProduct . ' AND active = 1')
            ->orderBy('position ASC, qty_per_package ASC');

        return Db::getInstance()->executeS($query) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getByProductAttribute(int $idProductAttribute): ?array
    {
        if ($idProductAttribute <= 0) {
            return null;
        }

        $query = new DbQuery();
        $query->select('*')
            ->from('m4p_package')
            ->where('id_product_attribute = ' . $idProductAttribute);

        return Db::getInstance()->getRow($query) ?: null;
    }

    /**
     * @return int[]
     */
    public static function getProductIds(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT id_product FROM `' . _DB_PREFIX_ . 'm4p_package`'
        ) ?: [];

        return array_map('intval', array_column($rows, 'id_product'));
    }

    /**
     * Replaces the packages of a product: rows missing from $packages are deleted with their combinations.
     *
     * @param array<int, array{id: int, qty_per_package: int, price_tax_incl: float, moq: int}> $packages validated rows
     */
    public static function syncForProduct(int $idProduct, array $packages): void
    {
        $product = new Product($idProduct);
        if (!Validate::isLoadedObject($product)) {
            throw new PrestaShopException('Product not found');
        }

        $submittedIds = array_filter(array_map(static fn (array $row): int => $row['id'], $packages));

        foreach (self::getByProduct($idProduct) as $existing) {
            if (!in_array((int) $existing['id_m4p_package'], $submittedIds, true)) {
                self::deletePackage(new self((int) $existing['id_m4p_package']));
            }
        }

        foreach (array_values($packages) as $position => $row) {
            $package = new self($row['id']);
            if (!Validate::isLoadedObject($package) || (int) $package->id_product !== $idProduct) {
                $package = new self();
            }

            $package->id_product = $idProduct;
            $package->qty_per_package = $row['qty_per_package'];
            $package->price_tax_incl = $row['price_tax_incl'];
            $package->moq = $row['moq'];
            $package->position = $position;
            $package->active = true;
            $package->syncCombination($product);
            $package->save();
        }

        Product::updateDefaultAttribute($idProduct);
        Product::resetStaticCache();
    }

    /**
     * Rewrites the price impact of every package combination, e.g. after the base price or tax rule changed.
     */
    public static function syncPricesForProduct(Product $product): void
    {
        foreach (self::getByProduct((int) $product->id) as $row) {
            $combination = new Combination((int) $row['id_product_attribute']);
            if (!Validate::isLoadedObject($combination)) {
                continue;
            }

            $impact = self::getPriceImpact($product, (float) $row['price_tax_incl']);
            if (abs((float) $combination->price - $impact) < 0.000001) {
                continue;
            }

            $combination->price = $impact;
            $combination->update();
        }
    }

    public static function deleteByProduct(int $idProduct): void
    {
        foreach (self::getByProduct($idProduct) as $row) {
            self::deletePackage(new self((int) $row['id_m4p_package']));
        }

        Db::getInstance()->delete('m4p_package', 'id_product = ' . $idProduct);
    }

    /**
     * Net price impact of a combination whose gross price per package is $priceTaxIncl.
     */
    public static function getPriceImpact(Product $product, float $priceTaxIncl): float
    {
        $taxRatePercent = (float) $product->getTaxesRate();
        $priceTaxExcl = $priceTaxIncl / (1 + $taxRatePercent / 100);

        return round($priceTaxExcl - (float) $product->price, 6);
    }

    private function syncCombination(Product $product): void
    {
        $idAttribute = M4pPackagingAttributes::getOrCreateValue($this->qty_per_package);
        $impact = self::getPriceImpact($product, (float) $this->price_tax_incl);

        $combination = new Combination((int) $this->id_product_attribute);
        if (Validate::isLoadedObject($combination) && (int) $this->id_attribute === $idAttribute) {
            $combination->price = $impact;
            $combination->minimal_quantity = $this->moq;
            $combination->update();

            return;
        }

        if (Validate::isLoadedObject($combination)) {
            $combination->delete();
        }

        $combination = new Combination();
        $combination->id_product = (int) $product->id;
        $combination->price = $impact;
        $combination->minimal_quantity = $this->moq;
        $combination->default_on = null;
        $combination->add();
        $combination->setAttributes([$idAttribute]);

        StockAvailable::setQuantity((int) $product->id, (int) $combination->id, 0);

        $this->id_product_attribute = (int) $combination->id;
        $this->id_attribute = $idAttribute;
    }

    private static function deletePackage(self $package): void
    {
        if (!Validate::isLoadedObject($package)) {
            return;
        }

        $combination = new Combination((int) $package->id_product_attribute);
        if (Validate::isLoadedObject($combination)) {
            $combination->delete();
        }

        $package->delete();
    }
}
