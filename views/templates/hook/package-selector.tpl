{**
 * m4p_packaging
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 *}

<div class="m4p-packaging js-m4p-packaging"
     data-attribute-group="{$m4p_attribute_group_id|intval}"
     data-threshold="{$m4p_threshold|intval}"
     data-cart-units="{$m4p_cart_units|intval}"
     data-text-missing="{l s='%units% pcs with your cart – %missing% pcs to go.' d='Modules.M4ppackaging.Shop'}"
     data-text-reached="{l s='%units% pcs with your cart – the order is delivered on a date agreed with you.' d='Modules.M4ppackaging.Shop'}">
  <fieldset class="m4p-packaging__fieldset">
    <legend class="m4p-packaging__label">{l s='Choose a package' d='Modules.M4ppackaging.Shop'}</legend>
    <div class="m4p-packaging__options">
      {foreach $m4p_packages as $package}
        <label class="m4p-packaging__option">
          <input class="m4p-packaging__input" type="radio" name="m4p_package"
                 value="{$package.id_attribute|intval}"
                 data-id-product-attribute="{$package.id_product_attribute|intval}"
                 data-pieces="{$package.pieces|intval}"
                 {if $package.selected}checked{/if}>
          <span class="m4p-packaging__body">
            <span class="m4p-packaging__name">{$package.label|escape:'html':'UTF-8'}</span>
            {if $package.unit_price}
              <span class="m4p-packaging__unit-price">{$package.unit_price|escape:'html':'UTF-8'}</span>
            {/if}
            {if $package.moq_label}
              <span class="m4p-packaging__moq">{$package.moq_label|escape:'html':'UTF-8'}</span>
            {/if}
          </span>
          <span class="m4p-packaging__price">{$package.price|escape:'html':'UTF-8'}</span>
        </label>
      {/foreach}
    </div>
  </fieldset>
  {if $m4p_threshold > 0}
    <div class="m4p-packaging__threshold js-m4p-packaging-threshold">
      <p class="m4p-packaging__threshold-title">{l s='Arranged delivery from %threshold% pcs of this product – delivery date agreed after contact.' sprintf=['%threshold%' => $m4p_threshold] d='Modules.M4ppackaging.Shop'}</p>
      <p class="m4p-packaging__threshold-status js-m4p-packaging-status" aria-live="polite"></p>
    </div>
  {/if}
</div>
