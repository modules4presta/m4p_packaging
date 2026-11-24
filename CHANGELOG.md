# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the module uses
[semantic versioning](https://semver.org/).

## [1.1.0] - 2026-11-23

First open-source release, published under the MIT license.

### Added

- Package selector on the product page: one tile per package with the price PrestaShop charges for it, the
  price per piece and the minimum order (shown when it is above one package). Arrow keys switch packages.
- Arranged delivery: the module's carrier is the only delivery option when the cart holds at least the
  product's threshold of pieces (packages x pieces per package) of a packaged product, and is hidden otherwise. The product page shows
  how many pieces the cart and the current selection add up to.
- Gross price per package in the back office; the combination price is derived from it with the product's tax
  rule and recalculated whenever the product is saved.
- Input validation, product edit permission and token check in the back-office endpoint.
- `actionFrontControllerSetVariables` exposes `$modules.m4p_packaging.attribute_group_id`, so a theme can hide
  the core attribute selector of the packaging group without a flash (the module script hides it otherwise).
- English source strings with en-US and pl-PL catalogues (new translation system).
- MIT headers, `LICENSE`, `README`, `SECURITY`, `CONTRIBUTING`, `config.xml`.

### Changed

- Settings use the module prefix: `M4P_PACKAGING_ATTRIBUTE_GROUP`, `M4P_PACKAGING_CARRIER_REFERENCE` (carrier
  stored by reference). Columns: `m4p_package.price_tax_incl`, `m4p_product_config.arranged_delivery_threshold`.
- Installing reuses an existing packaging attribute group and the module's carrier.

### Upgrade

`upgrade/upgrade-1.1.0.php` renames the settings and columns, swaps the hooks (`displayHeader`,
`actionProductSave`, `actionCarrierUpdate` out; `actionFrontControllerSetMedia`,
`actionFrontControllerSetVariables`, `actionObjectProductUpdateAfter` in), translates the attribute value names
per language and recalculates the combination prices from the stored package prices, read as gross. The Cart
override is not installed by an upgrade: copy `override/classes/Cart.php` or reset the module.

## [1.0.0] - 2026-04-28

- Packages as combinations with a minimum order and an arranged delivery carrier.
