/**
 * m4p_packaging
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

(function () {
  const QUANTITY_SELECTOR = '#quantity_wanted, .js-quantity-wanted';

  function getRoots() {
    return document.querySelectorAll('.js-m4p-packaging');
  }

  function getSelectedInput(root) {
    return root.querySelector('.m4p-packaging__input:checked') || root.querySelector('.m4p-packaging__input');
  }

  function getNativeControls(root) {
    const groupId = root.dataset.attributeGroup;
    const form = root.closest('form') || document;

    return Array.prototype.filter.call(
      form.querySelectorAll(`[name="group[${groupId}]"]`),
      (control) => !root.contains(control),
    );
  }

  function hideNativeGroup(root) {
    getNativeControls(root).forEach((control) => {
      const item = control.closest('.product-variants-item, .product-variants > div');
      if (item && !item.hidden) {
        item.hidden = true;
      }
    });
  }

  function getQuantity(root) {
    const form = root.closest('form') || document;
    const input = form.querySelector(QUANTITY_SELECTOR);
    const quantity = input ? parseInt(input.value, 10) : 1;

    return quantity > 0 ? quantity : 1;
  }

  function format(text, values) {
    return Object.keys(values).reduce((result, key) => result.split(`%${key}%`).join(String(values[key])), text || '');
  }

  function updateThreshold(root) {
    const threshold = parseInt(root.dataset.threshold, 10) || 0;
    const status = root.querySelector('.js-m4p-packaging-status');
    const selected = getSelectedInput(root);
    if (threshold <= 0 || !status || !selected) {
      return;
    }

    const units = (parseInt(root.dataset.cartUnits, 10) || 0)
      + getQuantity(root) * (parseInt(selected.dataset.pieces, 10) || 1);
    const missing = Math.max(0, threshold - units);
    const reached = missing === 0;
    const progressPercent = Math.min(100, Math.round((units * 100) / threshold));

    status.textContent = format(reached ? root.dataset.textReached : root.dataset.textMissing, {
      units,
      missing,
      threshold,
    });
    root.classList.toggle('is-reached', reached);

    root.querySelectorAll('.js-m4p-packaging-progress').forEach((progress) => {
      progress.setAttribute('aria-valuenow', String(progressPercent));
      const bar = progress.querySelector('.js-m4p-packaging-progress-bar');
      if (bar) {
        bar.style.width = `${progressPercent}%`;
      }
    });
  }

  function syncFromNative(root) {
    getNativeControls(root).forEach((control) => {
      if ((control.type === 'radio' && !control.checked) || !control.value) {
        return;
      }
      const input = root.querySelector(`.m4p-packaging__input[value="${control.value}"]`);
      if (input) {
        input.checked = true;
      }
    });
  }

  function selectNative(root, idAttribute) {
    getNativeControls(root).forEach((control) => {
      const isRadio = control.type === 'radio';
      if (isRadio && control.value !== idAttribute) {
        return;
      }
      if ((isRadio && control.checked) || (!isRadio && control.value === idAttribute)) {
        return;
      }
      if (isRadio) {
        control.checked = true;
      } else {
        control.value = idAttribute;
      }
      control.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function refresh() {
    Array.prototype.forEach.call(getRoots(), (root) => {
      hideNativeGroup(root);
      syncFromNative(root);
      updateThreshold(root);
    });
  }

  function updateCartUnits(cart) {
    if (!cart || !Array.isArray(cart.products)) {
      return;
    }

    Array.prototype.forEach.call(getRoots(), (root) => {
      const piecesByCombination = {};
      root.querySelectorAll('.m4p-packaging__input').forEach((input) => {
        piecesByCombination[input.dataset.idProductAttribute] = parseInt(input.dataset.pieces, 10) || 0;
      });

      root.dataset.cartUnits = String(cart.products.reduce((units, line) => {
        const pieces = piecesByCombination[String(line.id_product_attribute)] || 0;

        return units + pieces * (parseInt(line.cart_quantity || line.quantity, 10) || 0);
      }, 0));
      updateThreshold(root);
    });
  }

  document.addEventListener('change', (event) => {
    const input = event.target.closest ? event.target.closest('.m4p-packaging__input') : null;
    if (input) {
      const root = input.closest('.js-m4p-packaging');
      selectNative(root, input.value);
      updateThreshold(root);
    }
  });

  document.addEventListener('input', (event) => {
    if (event.target.matches && event.target.matches(QUANTITY_SELECTOR)) {
      refresh();
    }
  });

  if (window.jQuery) {
    window.jQuery(document).on('change touchspin.on.stopspin', QUANTITY_SELECTOR, refresh);
  }

  if (window.prestashop && typeof window.prestashop.on === 'function') {
    window.prestashop.on('updatedProduct', refresh);
    window.prestashop.on('updateCart', (event) => {
      updateCartUnits(event && event.resp ? event.resp.cart : null);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refresh);
  } else {
    refresh();
  }
}());
