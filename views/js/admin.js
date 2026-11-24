/**
 * m4p_packaging
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('m4p-packaging-root');
  const cfg = window.m4pPackagingConfig || {};
  const t = cfg.i18n || {};
  const pathMatch = window.location.pathname.match(/\/products\/(\d+)/);
  const idProduct = pathMatch ? parseInt(pathMatch[1], 10) : 0;

  if (!root || !cfg.ajaxUrl || !idProduct) {
    return;
  }

  function el(tag, attributes, text) {
    const node = document.createElement(tag);
    Object.keys(attributes || {}).forEach((name) => {
      node.setAttribute(name, attributes[name]);
    });
    if (text !== undefined) {
      node.textContent = text;
    }

    return node;
  }

  function request(action, fields) {
    const body = new URLSearchParams();
    body.append('m4p_action', action);
    body.append('id_product', String(idProduct));
    Object.keys(fields || {}).forEach((name) => {
      body.append(name, fields[name]);
    });

    return fetch(cfg.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' })
      .then((response) => response.json());
  }

  const trigger = el('button', { type: 'button', class: 'btn btn-outline-primary' }, t.configure);
  root.appendChild(trigger);

  const modal = el('div', {
    id: 'm4p-pkg-modal', class: 'modal fade', tabindex: '-1', role: 'dialog', 'aria-labelledby': 'm4p-pkg-title',
  });
  const dialog = el('div', { class: 'modal-dialog modal-lg', role: 'document' });
  const content = el('div', { class: 'modal-content' });
  const header = el('div', { class: 'modal-header' });
  header.appendChild(el('h4', { class: 'modal-title', id: 'm4p-pkg-title' }, t.title));
  const closeX = el('button', { type: 'button', class: 'close', 'aria-label': t.close }, '×');
  header.appendChild(closeX);
  const body = el('div', { class: 'modal-body' });
  const footer = el('div', { class: 'modal-footer' });
  const status = el('span', { class: 'mr-2', role: 'status' });
  const closeBtn = el('button', { type: 'button', class: 'btn btn-outline-secondary' }, t.close);
  const saveBtn = el('button', { type: 'button', class: 'btn btn-primary' }, t.save);
  footer.append(status, closeBtn, saveBtn);
  content.append(header, body, footer);
  dialog.appendChild(content);
  modal.appendChild(dialog);
  document.body.appendChild(modal);

  let thresholdInput;
  let tbody;

  function setStatus(text, isError) {
    status.textContent = text || '';
    status.className = `mr-2 ${isError ? 'text-danger' : 'text-success'}`;
  }

  function fieldCell(className, value, min, step, label) {
    const td = el('td');
    td.appendChild(el('input', {
      type: 'number',
      class: `form-control ${className}`,
      value: String(value),
      min,
      step,
      'aria-label': label,
      required: 'required',
    }));

    return td;
  }

  function appendRow(pkg) {
    const tr = el('tr', { class: 'm4p-pkg-row', 'data-id': String(pkg.id || 0) });
    tr.appendChild(fieldCell('m4p-qty', pkg.qty_per_package || 1, '1', '1', t.qtyPerPkg));
    tr.appendChild(fieldCell('m4p-price', pkg.price_tax_incl || 0, '0', '0.01', t.price));
    tr.appendChild(fieldCell('m4p-moq', pkg.moq || 1, '1', '1', t.moq));
    const actions = el('td');
    const remove = el('button', { type: 'button', class: 'btn btn-sm btn-outline-danger' }, t.remove);
    remove.addEventListener('click', () => { tr.remove(); });
    actions.appendChild(remove);
    tr.appendChild(actions);
    tbody.appendChild(tr);
  }

  function renderForm(packages, threshold) {
    body.textContent = '';
    body.appendChild(el('p', { class: 'text-muted' }, t.intro));

    const group = el('div', { class: 'form-group' });
    group.appendChild(el('label', { for: 'm4p-threshold-input', class: 'form-control-label' }, t.threshold));
    thresholdInput = el('input', {
      type: 'number',
      id: 'm4p-threshold-input',
      class: 'form-control',
      min: '0',
      step: '1',
      value: String(threshold),
      'aria-describedby': 'm4p-threshold-help',
    });
    const thresholdHelp = el('small', { id: 'm4p-threshold-help', class: 'form-text text-muted' }, t.thresholdHelp);
    group.append(thresholdInput, thresholdHelp);
    body.appendChild(group);

    const table = el('table', { class: 'table' });
    const headRow = el('tr');
    [t.qtyPerPkg, t.price, t.moq, ''].forEach((label) => {
      headRow.appendChild(el('th', {}, label));
    });
    const thead = el('thead');
    thead.appendChild(headRow);
    tbody = el('tbody');
    table.append(thead, tbody);
    body.appendChild(table);
    body.appendChild(el('small', { class: 'form-text text-muted mb-3' }, t.priceHelp));

    packages.forEach(appendRow);

    const add = el('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm' }, t.addVariant);
    add.addEventListener('click', () => { appendRow({}); });
    body.appendChild(add);
  }

  function collectRows() {
    return Array.prototype.map.call(tbody.querySelectorAll('.m4p-pkg-row'), (tr) => ({
      id: parseInt(tr.dataset.id, 10) || 0,
      qty_per_package: parseInt(tr.querySelector('.m4p-qty').value, 10),
      price_tax_incl: parseFloat(tr.querySelector('.m4p-price').value),
      moq: parseInt(tr.querySelector('.m4p-moq').value, 10),
    }));
  }

  function showModal(show) {
    if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
      window.jQuery(modal).modal(show ? 'show' : 'hide');
    }
  }

  trigger.addEventListener('click', () => {
    body.textContent = t.loading;
    saveBtn.hidden = true;
    setStatus('');
    showModal(true);

    request('getPackages')
      .then((data) => {
        if (!data.success) {
          body.textContent = data.error || t.loadError;
          return;
        }
        renderForm(data.packages || [], data.threshold || 0);
        saveBtn.hidden = false;
      })
      .catch(() => {
        body.textContent = t.connError;
      });
  });

  saveBtn.addEventListener('click', () => {
    setStatus(t.saving);
    saveBtn.disabled = true;

    request('savePackages', { threshold: thresholdInput.value, packages: JSON.stringify(collectRows()) })
      .then((data) => {
        setStatus(data.success ? t.saved : `${t.error} ${data.error || ''}`, !data.success);
      })
      .catch(() => {
        setStatus(t.connError, true);
      })
      .finally(() => {
        saveBtn.disabled = false;
      });
  });

  [closeX, closeBtn].forEach((button) => {
    button.addEventListener('click', () => { showModal(false); });
  });
});
