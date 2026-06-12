(function () {
  const workstation = document.querySelector('[data-inventory-workstation]');
  if (!workstation) {
    return;
  }

  const searchInput = document.getElementById('inventorySearch');
  const barcodeInput = document.querySelector('[data-barcode-input]');
  const filters = Array.from(document.querySelectorAll('[data-inventory-filter]'));
  const kpiFilters = Array.from(document.querySelectorAll('[data-inventory-filter-jump]'));
  const rows = Array.from(document.querySelectorAll('[data-inventory-row]'));
  const emptyRow = document.querySelector('[data-inventory-empty-row]');
  const visibleCount = document.querySelector('[data-inventory-visible-count]');
  const modals = Array.from(document.querySelectorAll('[data-inventory-modal]'));
  const movementFilters = Array.from(document.querySelectorAll('[data-movement-filter]'));
  const movementRows = Array.from(document.querySelectorAll('[data-movement-row]'));

  let activeFilter = 'all';

  function normalize(value) {
    return String(value || '').toLowerCase().trim();
  }

  function numberFrom(value) {
    const parsed = Number.parseFloat(String(value || '0'));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function formatNumber(value) {
    return new Intl.NumberFormat('th-TH', { maximumFractionDigits: 2 }).format(value);
  }

  function matchesFilter(row) {
    if (activeFilter === 'all') {
      return true;
    }
    if (activeFilter === 'DRUG' || activeFilter === 'SUPPLY') {
      return row.dataset.itemType === activeFilter;
    }
    return row.dataset.itemStatus === activeFilter;
  }

  function applyTableFilters() {
    const query = normalize(searchInput ? searchInput.value : '');
    let count = 0;

    rows.forEach((row) => {
      const text = normalize(row.dataset.itemSearch);
      const isVisible = matchesFilter(row) && (!query || text.includes(query));
      row.classList.toggle('d-none', !isVisible);
      if (isVisible) {
        count += 1;
      }
    });

    if (visibleCount) {
      visibleCount.textContent = String(count);
    }
    if (emptyRow) {
      emptyRow.classList.toggle('d-none', count > 0);
    }
  }

  function setFilter(filterValue) {
    activeFilter = filterValue || 'all';
    filters.forEach((candidate) => {
      candidate.classList.toggle('is-active', candidate.dataset.inventoryFilter === activeFilter);
    });
    applyTableFilters();
    const table = document.querySelector('.inventory-table-panel');
    if (table) {
      table.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }
  }

  function openModal(name) {
    const modal = modals.find((candidate) => candidate.dataset.inventoryModal === name);
    if (!modal) {
      return;
    }
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('inventory-modal-open');
    const focusTarget = modal.querySelector('select, input, textarea, button');
    if (focusTarget) {
      window.setTimeout(() => focusTarget.focus(), 40);
    }
  }

  function closeModal(modal) {
    if (!modal) {
      modals.forEach(closeModal);
      return;
    }
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!modals.some((candidate) => candidate.classList.contains('is-open'))) {
      document.body.classList.remove('inventory-modal-open');
    }
  }

  function findItemOption(select, itemId) {
    if (!select || !itemId) {
      return null;
    }
    return Array.from(select.options).find((option) => option.value === itemId) || null;
  }

  function selectItemForReceive(itemId) {
    const select = document.querySelector('[data-receive-item]');
    const option = findItemOption(select, itemId);
    if (!select || !option) {
      return;
    }
    select.value = itemId;
    select.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function selectBatchForAdjust(itemId) {
    const select = document.querySelector('[data-adjust-batch]');
    if (!select || !itemId) {
      return;
    }
    const option = Array.from(select.options).find((candidate) => candidate.dataset.itemId === itemId);
    if (!option) {
      return;
    }
    select.value = option.value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function prefillItemForm(row) {
    const modal = document.querySelector('[data-inventory-modal="item"]');
    if (!modal || !row) {
      return;
    }
    const code = modal.querySelector('[data-item-code-input]');
    const type = modal.querySelector('[data-item-type-input]');
    const name = modal.querySelector('[data-item-name-input]');
    const unit = modal.querySelector('[data-item-unit-input]');
    const cost = modal.querySelector('[data-item-cost-input]');
    const price = modal.querySelector('[data-item-price-input]');

    if (code) code.value = row.querySelector('.inventory-code') ? row.querySelector('.inventory-code').textContent.trim() : '';
    if (type) type.value = row.dataset.itemType || 'DRUG';
    if (name) name.value = row.dataset.itemName || '';
    if (unit) unit.value = row.dataset.itemUnit || '';
    if (cost) cost.value = row.dataset.itemCost || '0';
    if (price) price.value = row.dataset.itemPrice || '0';
  }

  function updateReceivePreview() {
    const receiveItem = document.querySelector('[data-receive-item]');
    const receiveQty = document.querySelector('[data-receive-qty]');
    const receiveCost = document.querySelector('[data-receive-cost]');
    const receiveOld = document.querySelector('[data-receive-old]');
    const receiveAdd = document.querySelector('[data-receive-add]');
    const receiveNew = document.querySelector('[data-receive-new]');
    if (!receiveItem) {
      return;
    }
    const option = receiveItem.selectedOptions[0];
    const oldStock = numberFrom(option ? option.dataset.stock : 0);
    const qty = numberFrom(receiveQty ? receiveQty.value : 0);
    if (receiveCost && option && (!receiveCost.value || receiveCost.value === '0')) {
      receiveCost.value = option.dataset.cost || '0';
    }
    if (receiveOld) receiveOld.textContent = formatNumber(oldStock);
    if (receiveAdd) receiveAdd.textContent = '+' + formatNumber(qty);
    if (receiveNew) receiveNew.textContent = formatNumber(oldStock + qty);
  }

  function updateAdjustPreview() {
    const adjustBatch = document.querySelector('[data-adjust-batch]');
    const adjustQty = document.querySelector('[data-adjust-qty]');
    const adjustOld = document.querySelector('[data-adjust-old]');
    const adjustChange = document.querySelector('[data-adjust-change]');
    const adjustNew = document.querySelector('[data-adjust-new]');
    const adjustWarning = document.querySelector('[data-adjust-warning]');
    const adjustForm = document.querySelector('[data-adjust-form]');
    const adjustSubmit = adjustForm ? adjustForm.querySelector('button[type="submit"]') : null;

    if (!adjustBatch) {
      return;
    }
    const option = adjustBatch.selectedOptions[0];
    const oldStock = numberFrom(option ? option.dataset.stock : 0);
    const qty = numberFrom(adjustQty ? adjustQty.value : 0);
    const newStock = oldStock + qty;
    const invalid = newStock < 0;

    if (adjustOld) adjustOld.textContent = formatNumber(oldStock);
    if (adjustChange) adjustChange.textContent = (qty > 0 ? '+' : '') + formatNumber(qty);
    if (adjustNew) adjustNew.textContent = formatNumber(newStock);
    if (adjustWarning) adjustWarning.classList.toggle('d-none', !invalid);
    if (adjustSubmit) adjustSubmit.disabled = invalid;
  }

  filters.forEach((filter) => {
    filter.addEventListener('click', () => setFilter(filter.dataset.inventoryFilter || 'all'));
  });

  kpiFilters.forEach((filter) => {
    filter.addEventListener('click', () => setFilter(filter.dataset.inventoryFilterJump || 'all'));
  });

  if (searchInput) {
    searchInput.addEventListener('input', applyTableFilters);
    searchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const firstVisible = rows.find((row) => !row.classList.contains('d-none'));
        if (firstVisible) {
          firstVisible.scrollIntoView({ block: 'center', behavior: 'smooth' });
          firstVisible.classList.add('table-active');
          window.setTimeout(() => firstVisible.classList.remove('table-active'), 1200);
        }
      }
    });
  }

  if (barcodeInput && searchInput) {
    barcodeInput.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') {
        return;
      }
      event.preventDefault();
      searchInput.value = barcodeInput.value;
      applyTableFilters();
      searchInput.focus();
    });
  }

  document.querySelectorAll('[data-open-inventory-modal]').forEach((button) => {
    button.addEventListener('click', () => openModal(button.dataset.openInventoryModal || 'receive'));
  });

  document.querySelectorAll('[data-close-inventory-modal]').forEach((button) => {
    button.addEventListener('click', () => closeModal(button.closest('[data-inventory-modal]')));
  });

  rows.forEach((row) => {
    row.addEventListener('click', (event) => {
      const button = event.target.closest('[data-row-action]');
      if (!button) {
        return;
      }
      const action = button.dataset.rowAction;
      const itemId = row.dataset.itemId || '';
      const menu = button.closest('details');
      if (menu) {
        menu.removeAttribute('open');
      }

      if (action === 'receive') {
        openModal('receive');
        selectItemForReceive(itemId);
      } else if (action === 'adjust') {
        openModal('adjust');
        selectBatchForAdjust(itemId);
      } else if (action === 'item') {
        prefillItemForm(row);
        openModal('item');
      } else if (action === 'label') {
        window.alert('ฟีเจอร์พิมพ์ฉลากรายการคลังจะเชื่อมกับระบบสติ๊กเกอร์ยาในเฟสถัดไป');
      } else {
        const history = document.querySelector('.inventory-history-panel');
        if (history) {
          history.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
      }
    });
  });

  const receiveItem = document.querySelector('[data-receive-item]');
  const receiveQty = document.querySelector('[data-receive-qty]');
  const adjustBatch = document.querySelector('[data-adjust-batch]');
  const adjustQty = document.querySelector('[data-adjust-qty]');

  if (receiveItem) receiveItem.addEventListener('change', updateReceivePreview);
  if (receiveQty) receiveQty.addEventListener('input', updateReceivePreview);
  if (adjustBatch) adjustBatch.addEventListener('change', updateAdjustPreview);
  if (adjustQty) adjustQty.addEventListener('input', updateAdjustPreview);

  movementFilters.forEach((button) => {
    button.addEventListener('click', () => {
      const type = button.dataset.movementFilter || 'all';
      movementFilters.forEach((candidate) => candidate.classList.toggle('is-active', candidate === button));
      movementRows.forEach((row) => {
        row.classList.toggle('d-none', type !== 'all' && row.dataset.movementType !== type);
      });
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal();
    }
    if (event.key === 'F1' && searchInput) {
      event.preventDefault();
      searchInput.focus();
    }
    if (event.key === 'F2') {
      event.preventDefault();
      openModal('receive');
    }
    if (event.key === 'F3') {
      event.preventDefault();
      openModal('adjust');
    }
  });

  applyTableFilters();
  updateReceivePreview();
  updateAdjustPreview();
})();
