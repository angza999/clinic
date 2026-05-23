(function () {
  const workstation = document.querySelector('[data-inventory-workstation]');
  if (!workstation) {
    return;
  }

  const searchInput = document.getElementById('inventorySearch');
  const filters = Array.from(document.querySelectorAll('[data-inventory-filter]'));
  const rows = Array.from(document.querySelectorAll('[data-inventory-row]'));
  const emptyRow = document.querySelector('[data-inventory-empty-row]');
  const visibleCount = document.querySelector('[data-inventory-visible-count]');
  const tabs = Array.from(document.querySelectorAll('[data-inventory-action]'));
  const panels = Array.from(document.querySelectorAll('[data-inventory-action-panel]'));

  let activeFilter = 'all';

  function normalize(value) {
    return String(value || '').toLowerCase().trim();
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

  function setActionPanel(action) {
    tabs.forEach((tab) => {
      tab.classList.toggle('is-active', tab.dataset.inventoryAction === action);
    });
    panels.forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.inventoryActionPanel === action);
    });
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

  filters.forEach((filter) => {
    filter.addEventListener('click', () => {
      activeFilter = filter.dataset.inventoryFilter || 'all';
      filters.forEach((candidate) => candidate.classList.toggle('is-active', candidate === filter));
      applyTableFilters();
    });
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

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => setActionPanel(tab.dataset.inventoryAction || 'history'));
  });

  rows.forEach((row) => {
    row.addEventListener('click', (event) => {
      const button = event.target.closest('[data-row-action]');
      if (!button) {
        return;
      }
      const action = button.dataset.rowAction;
      const itemId = row.dataset.itemId || '';

      if (action === 'receive') {
        setActionPanel('receive');
        selectItemForReceive(itemId);
      } else if (action === 'adjust') {
        setActionPanel('adjust');
        selectBatchForAdjust(itemId);
      } else {
        setActionPanel('history');
      }
    });
  });

  const receiveItem = document.querySelector('[data-receive-item]');
  const receiveQty = document.querySelector('[data-receive-qty]');
  const receiveCost = document.querySelector('[data-receive-cost]');
  const receiveOld = document.querySelector('[data-receive-old]');
  const receiveAdd = document.querySelector('[data-receive-add]');
  const receiveNew = document.querySelector('[data-receive-new]');

  function numberFrom(value) {
    const parsed = Number.parseFloat(String(value || '0'));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function formatNumber(value) {
    return new Intl.NumberFormat('th-TH', { maximumFractionDigits: 2 }).format(value);
  }

  function updateReceivePreview() {
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

  if (receiveItem) receiveItem.addEventListener('change', updateReceivePreview);
  if (receiveQty) receiveQty.addEventListener('input', updateReceivePreview);
  updateReceivePreview();

  const adjustBatch = document.querySelector('[data-adjust-batch]');
  const adjustQty = document.querySelector('[data-adjust-qty]');
  const adjustOld = document.querySelector('[data-adjust-old]');
  const adjustChange = document.querySelector('[data-adjust-change]');
  const adjustNew = document.querySelector('[data-adjust-new]');
  const adjustWarning = document.querySelector('[data-adjust-warning]');
  const adjustForm = document.querySelector('[data-adjust-form]');
  const adjustSubmit = adjustForm ? adjustForm.querySelector('button[type="submit"]') : null;

  function updateAdjustPreview() {
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

  if (adjustBatch) adjustBatch.addEventListener('change', updateAdjustPreview);
  if (adjustQty) adjustQty.addEventListener('input', updateAdjustPreview);
  updateAdjustPreview();

  window.addEventListener('keydown', (event) => {
    if (event.key === 'F1' && searchInput) {
      event.preventDefault();
      searchInput.focus();
    }
    if (event.key === 'F2') {
      event.preventDefault();
      setActionPanel('receive');
    }
    if (event.key === 'F3') {
      event.preventDefault();
      setActionPanel('adjust');
    }
  });
})();
