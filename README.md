# Package variants for PrestaShop 9

**Sell a product by the package – 1 piece, a box of 9 – with a minimum order per package and an arranged delivery option for large orders.**

> **Meta description (155 chars):** Sell PrestaShop products in packages with a gross price per package, price per piece, minimum order and arranged delivery from N pieces. Free, MIT.

---

## Why packages matter for your store

Bakeries, confectioners and wholesalers sell the same product loose and in boxes. PrestaShop can model a box as a combination, but the back office asks for a net price impact relative to the base price, the product page shows a generic attribute list, and nothing ties a large order to a different way of delivering it.

- **Prices as you set them** – enter what the customer pays for a package, tax included.
- **A clear choice for the customer** – one tile per package with its price, price per piece and minimum order.
- **Large orders delivered your way** – an "arranged delivery" carrier appears at checkout only once the cart holds enough pieces.

## What the module does

Each package of a product becomes a combination in one attribute group ("Package (pieces)"). The module keeps the combination price and minimum quantity in line with the package settings and replaces the core attribute list of that group with a package selector. It does not change stock, other attribute groups or other carriers.

### Key features

- **Packages per product** – pieces per package, gross price per package and minimum number of packages.
- **Real prices on the product page** – tiles show the price PrestaShop charges for the combination (tax display, specific prices and group reductions included) and the price per piece.
- **Minimum order** – stored as the combination's minimal quantity, so PrestaShop enforces it in the cart; the tile shows it when it is above one package.
- **Arranged delivery** – the module's carrier is offered only when the cart reaches a product's threshold.
- **Keyboard and screen readers** – the selector is a native radio group in a fieldset.

### How packages map to combinations

| Package setting | PrestaShop |
|---|---|
| Pieces per package | attribute value in the packaging group ("9 pcs"), one combination per package |
| Gross price per package | combination price impact = gross / (1 + tax rate) − product net price |
| Minimum packages per order | combination minimal quantity |

The combination price is recalculated whenever the product is saved (`actionObjectProductUpdateAfter`), so a new base price or tax rule keeps the package prices. Edit packages in the module panel, not in the Combinations tab: the next product save restores the module's price.

### Arranged delivery rule

The carrier created by the module is removed from the delivery options unless **at least one** product with packages reaches its threshold: pieces of that product in the cart (packages x pieces per package, summed over its packages) ≥ threshold. Other products in the cart do not count towards or against it. A threshold of 0 never unlocks the carrier. When the rule is met, the carrier is the only delivery option.

### Uninstalling

Uninstalling deletes the module's tables and settings and marks its carrier as deleted. The attribute group, its values and the product combinations stay, because orders refer to them. Installing again reuses the group (found by name) and restores the carrier.

## Compatibility

| | |
|---|---|
| PrestaShop | 9.0+ (tested on 9.1) |
| PHP | 8.1+ (`intl` extension, required by PrestaShop) |
| Requirements | none |
| Multistore | Not tested |
| Themes | Any theme with the core `displayProductVariants` hook; see "Theme integration" |

The module overrides `Cart::getDeliveryOptionList()` and creates two tables (`m4p_package`, `m4p_product_config`).

## Installation

1. Upload and install the module from **Modules → Module Manager**.
2. Open a product in **Catalog → Products**, tab **Modules**, and click **Configure packages**.
3. Add the packages and the arranged delivery threshold, then save and reload the product page.
4. Check the carrier "Arranged delivery" (**Shipping → Carriers**): name, zones and customer groups.

If installation fails on the override, another module already overrides `Cart::getDeliveryOptionList()`; merge the two methods by hand. Upgrading from 1.0.0 does not install overrides: copy `override/classes/Cart.php` to the shop or reset the module.

## Configuration options

Per product, in the packages panel:

| Setting | Description |
|---|---|
| **Arranged delivery from (pieces)** | Pieces of this product in the cart that unlock the arranged delivery carrier. 0 – never. |
| **Pieces per package** | Number of pieces in the package; each package needs a different number. |
| **Gross price per package** | Price of one package with tax, in the default currency. |
| **Minimum packages per order** | Smallest number of this package in the cart. |

## Theme integration

The module template `views/templates/hook/package-selector.tpl` uses neutral classes (`.m4p-packaging__…`) and zero-specificity CSS, so any theme style wins. Override it in `themes/<theme>/modules/m4p_packaging/views/templates/hook/package-selector.tpl`, e.g. to add a progress bar: the script fills `.js-m4p-packaging-status`, sets `aria-valuenow` on `.js-m4p-packaging-progress` and the width of `.js-m4p-packaging-progress-bar`, and toggles `.is-reached` on the root.

To hide the core attribute list of the packaging group before the page renders, add this to the group wrapper in `templates/catalog/_partials/product-variants.tpl` (otherwise the module script hides it after load):

```smarty
{if !empty($modules.m4p_packaging.attribute_group_id) && $id_attribute_group == $modules.m4p_packaging.attribute_group_id} hidden{/if}
```

The hidden radio inputs stay in the form: PrestaShop reads them to switch the combination.

## Translations

English is the source language. Catalogues in `translations/en-US` and `translations/pl-PL`, domains `Modules.M4ppackaging.Admin` and `Modules.M4ppackaging.Shop`; plural forms use ICU syntax (`{count, plural, …}`). The carrier name and delay, the attribute group and the value names are created in every installed language.

## Frequently asked questions

**Is the price on the tile the price in the cart?**
Yes. The tile reads the combination price from PrestaShop, with the same tax display and reductions as the cart.

**Does arranged delivery require all products to reach a threshold?**
No. One packaged product at its threshold is enough; the order then can use arranged delivery for everything in it.

**Can I edit the combinations in the Combinations tab?**
Stock, images and references – yes. Price and minimal quantity come from the package settings.

---

**Keywords:** prestashop packages, prestashop box price, sell by the box prestashop, minimum order quantity prestashop, price per piece prestashop, arranged delivery carrier

## License

MIT – see [LICENSE](LICENSE). Free to use commercially, fork and modify; keep the copyright notice.

## Contributing

Bug reports and pull requests are welcome – see [CONTRIBUTING.md](CONTRIBUTING.md). For security
issues, follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

---

Built by [Nice Code](https://nice-code.com/pl/produkty/sklep-b2b-prestashop) – we build and maintain PrestaShop stores.

© Nice Code sp. z o.o. (Modules4Presta) – released under the MIT license.
