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
 * JSON endpoint of the packages panel on the product page; needs the controller token and the right to edit products.
 */
class AdminAjaxM4pPackagingController extends ModuleAdminController
{
    private const MAX_PACKAGES = 50;

    public function __construct()
    {
        parent::__construct();
        $this->ajax = true;
    }

    public function postProcess(): void
    {
        $action = (string) Tools::getValue('m4p_action');
        $permission = $action === 'savePackages' ? 'UPDATE' : 'READ';

        if (!$this->isTokenValid() || !$this->canAccessProducts($permission)) {
            $this->jsonResponse(['success' => false, 'error' => $this->trans('Access denied.', [], 'Modules.M4ppackaging.Admin')], 403);
        }

        match ($action) {
            'getPackages' => $this->processGetPackages(),
            'savePackages' => $this->processSavePackages(),
            default => $this->jsonResponse(['success' => false, 'error' => $this->trans('Unknown action.', [], 'Modules.M4ppackaging.Admin')], 400),
        };
    }

    private function processGetPackages(): void
    {
        $idProduct = $this->getProductId();
        $packages = array_map(static fn (array $row): array => [
            'id' => (int) $row['id_m4p_package'],
            'qty_per_package' => (int) $row['qty_per_package'],
            'price_tax_incl' => round((float) $row['price_tax_incl'], 2),
            'moq' => (int) $row['moq'],
        ], M4pPackagingPackage::getByProduct($idProduct));

        $this->jsonResponse([
            'success' => true,
            'packages' => $packages,
            'threshold' => M4pPackagingProductConfig::getThreshold($idProduct),
        ]);
    }

    private function processSavePackages(): void
    {
        $idProduct = $this->getProductId();
        $threshold = Tools::getValue('threshold');
        if (!is_numeric($threshold) || (int) $threshold < 0) {
            $this->jsonResponse(['success' => false, 'error' => $this->trans('Arranged delivery threshold must be 0 or more pieces.', [], 'Modules.M4ppackaging.Admin')], 422);
        }

        $packages = $this->validatePackages(json_decode((string) Tools::getValue('packages', '[]'), true));

        try {
            M4pPackagingPackage::syncForProduct($idProduct, $packages);
            M4pPackagingProductConfig::setThreshold($idProduct, (int) $threshold);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('m4p_packaging: ' . $exception->getMessage(), 3, null, 'Product', $idProduct, true);
            $this->jsonResponse(['success' => false, 'error' => $this->trans('Saving failed. Details are in Advanced Parameters > Logs.', [], 'Modules.M4ppackaging.Admin')], 500);
        }

        $this->jsonResponse(['success' => true]);
    }

    /**
     * @return array<int, array{id: int, qty_per_package: int, price_tax_incl: float, moq: int}>
     */
    private function validatePackages(mixed $rows): array
    {
        if (!is_array($rows) || count($rows) > self::MAX_PACKAGES) {
            $this->jsonResponse(['success' => false, 'error' => $this->trans('Invalid package list.', [], 'Modules.M4ppackaging.Admin')], 422);
        }

        $packages = [];
        $piecesSeen = [];
        foreach ($rows as $row) {
            $pieces = filter_var($row['qty_per_package'] ?? null, FILTER_VALIDATE_INT);
            $price = filter_var($row['price_tax_incl'] ?? null, FILTER_VALIDATE_FLOAT);
            $moq = filter_var($row['moq'] ?? null, FILTER_VALIDATE_INT);

            if ($pieces === false || $pieces < 1) {
                $this->jsonResponse(['success' => false, 'error' => $this->trans('Pieces per package must be 1 or more.', [], 'Modules.M4ppackaging.Admin')], 422);
            }
            if ($price === false || $price < 0) {
                $this->jsonResponse(['success' => false, 'error' => $this->trans('Gross price per package must be 0 or more.', [], 'Modules.M4ppackaging.Admin')], 422);
            }
            if ($moq === false || $moq < 1) {
                $this->jsonResponse(['success' => false, 'error' => $this->trans('Minimum packages per order must be 1 or more.', [], 'Modules.M4ppackaging.Admin')], 422);
            }
            if (isset($piecesSeen[$pieces])) {
                $this->jsonResponse(['success' => false, 'error' => $this->trans('Each package needs a different number of pieces.', [], 'Modules.M4ppackaging.Admin')], 422);
            }

            $piecesSeen[$pieces] = true;
            $packages[] = [
                'id' => max(0, (int) ($row['id'] ?? 0)),
                'qty_per_package' => $pieces,
                'price_tax_incl' => round($price, 6),
                'moq' => $moq,
            ];
        }

        return $packages;
    }

    private function getProductId(): int
    {
        $idProduct = (int) Tools::getValue('id_product');
        if ($idProduct <= 0 || !Validate::isLoadedObject(new Product($idProduct))) {
            $this->jsonResponse(['success' => false, 'error' => $this->trans('Product not found.', [], 'Modules.M4ppackaging.Admin')], 404);
        }

        return $idProduct;
    }

    private function isTokenValid(): bool
    {
        $token = (string) Tools::getValue('token');

        return $token !== '' && hash_equals(Tools::getAdminTokenLite('AdminAjaxM4pPackaging'), $token);
    }

    private function canAccessProducts(string $permission): bool
    {
        $employee = $this->context->employee;

        return $employee instanceof Employee
            && Validate::isLoadedObject($employee)
            && Access::isGranted('ROLE_MOD_TAB_ADMINPRODUCTS_' . $permission, (int) $employee->id_profile);
    }

    private function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        exit(json_encode($data));
    }
}
