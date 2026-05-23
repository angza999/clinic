(function () {
  const root = document.querySelector('[data-service-workstation]');
  if (!root) return;

  const rows = Array.from(document.querySelectorAll('[data-service-row]'));
  const tbody = document.querySelector('[data-service-tbody]');
  const emptyRow = document.querySelector('[data-service-empty-row]');
  const visibleCount = document.querySelector('[data-service-visible-count]');
  const searchInput = document.getElementById('serviceSearch');
  const categoryFilter = document.getElementById('serviceCategoryFilter');
  const statusButtons = Array.from(document.querySelectorAll('[data-service-filter]'));
  const kpiButtons = Array.from(document.querySelectorAll('[data-service-kpi]'));
  const form = document.querySelector('[data-service-form]');
  const modeLabel = document.querySelector('[data-service-panel-mode]');
  const panelTitle = document.querySelector('[data-service-panel-title]');
  const prefixMapEl = document.getElementById('servicePrefixMap');
  const historyMapEl = document.getElementById('servicePriceHistoryMap');
  const selectedInsight = document.querySelector('[data-service-selected-insight]');
  const priceHistoryBox = document.querySelector('[data-service-price-history]');
  const prefixMap = prefixMapEl ? JSON.parse(prefixMapEl.textContent || '{}') : {};
  const priceHistoryMap = historyMapEl ? JSON.parse(historyMapEl.textContent || '{}') : {};

  const fields = form ? {
    code: form.querySelector('[data-service-code]'),
    name: form.querySelector('[data-service-name]'),
    category: form.querySelector('[data-service-category]'),
    price: form.querySelector('[data-service-price]'),
    active: form.querySelector('[data-service-active]'),
    submit: form.querySelector('[data-service-submit]'),
    codeValidation: form.querySelector('[data-service-code-validation]'),
    nameValidation: form.querySelector('[data-service-name-validation]'),
    priceValidation: form.querySelector('[data-service-price-validation]'),
    suggestion: form.querySelector('[data-service-suggestion]'),
  } : {};

  const preview = {
    code: document.querySelector('[data-preview-code]'),
    name: document.querySelector('[data-preview-name]'),
    category: document.querySelector('[data-preview-category]'),
    price: document.querySelector('[data-preview-price]'),
    status: document.querySelector('[data-preview-status]'),
  };

  let statusFilter = 'all';
  let sortState = { key: 'name', direction: 1 };
  let formMode = 'add';
  let originalCode = '';

  function normalize(value) {
    return String(value || '').trim().toLowerCase();
  }

  function money(value) {
    const amount = Number.parseFloat(value || '0');
    return Number.isFinite(amount) ? amount.toFixed(2) : '0.00';
  }

  function number(row, key) {
    return Number.parseFloat(row.dataset[key] || '0') || 0;
  }

  function rowMatchesStatus(row) {
    if (statusFilter === 'all') return true;
    if (statusFilter === 'free') return number(row, 'price') <= 0;
    return row.dataset.status === statusFilter;
  }

  function applyFilters() {
    const query = normalize(searchInput ? searchInput.value : '');
    const category = categoryFilter ? categoryFilter.value : 'all';
    let count = 0;

    rows.forEach((row) => {
      const matchesSearch = !query || normalize(row.dataset.search).includes(query);
      const matchesCategory = category === 'all' || row.dataset.category === category;
      const visible = matchesSearch && matchesCategory && rowMatchesStatus(row);
      row.classList.toggle('d-none', !visible);
      if (visible) count += 1;
    });

    if (visibleCount) visibleCount.textContent = String(count);
    if (emptyRow) emptyRow.classList.toggle('d-none', count > 0);
  }

  function setStatus(nextStatus) {
    statusFilter = nextStatus || 'all';
    statusButtons.forEach((button) => {
      button.classList.toggle('is-active', (button.dataset.serviceFilter || 'all') === statusFilter);
    });
    applyFilters();
  }

  function sortRows(key, forcedDirection) {
    sortState.direction = forcedDirection || (sortState.key === key ? sortState.direction * -1 : 1);
    sortState.key = key;

    const sorted = rows.slice().sort((a, b) => {
      const numericKeys = ['price', 'usage', 'income'];
      const aValue = numericKeys.includes(key) ? number(a, key) : normalize(a.dataset[key]);
      const bValue = numericKeys.includes(key) ? number(b, key) : normalize(b.dataset[key]);

      if (aValue < bValue) return -1 * sortState.direction;
      if (aValue > bValue) return 1 * sortState.direction;
      return 0;
    });

    sorted.forEach((row) => tbody?.insertBefore(row, emptyRow || null));
  }

  function setSelected(row) {
    rows.forEach((item) => item.classList.toggle('is-selected', item === row));
  }

  function categoryFromName(name) {
    const text = String(name || '');
    if (text.includes('ฉีด')) return 'ฉีดยา';
    if (text.includes('แผล')) return 'ทำแผล';
    if (text.includes('ตรวจ')) return 'ตรวจทั่วไป';
    return '';
  }

  function existingCodeConflict(code) {
    const nextCode = normalize(code);
    if (!nextCode) return false;

    return rows.some((row) => {
      const rowCode = normalize(row.dataset.code);
      if (rowCode !== nextCode) return false;
      return formMode !== 'edit' || rowCode !== normalize(originalCode);
    });
  }

  function updatePreviewAndValidation() {
    if (!form) return;

    const code = (fields.code?.value || '').trim().toUpperCase();
    const name = (fields.name?.value || '').trim();
    const suggestedCategory = categoryFromName(name);
    const category = (fields.category?.value || suggestedCategory || 'อื่น ๆ').trim();
    const price = Number.parseFloat(fields.price?.value || '0');
    const validPrice = Number.isFinite(price) && price >= 0;
    const hasName = name.length > 0;
    const codeConflict = existingCodeConflict(code);
    const active = Boolean(fields.active?.checked);

    if (fields.code && fields.code.value !== code) fields.code.value = code;
    if (preview.code) preview.code.textContent = code || 'SRV---';
    if (preview.name) preview.name.textContent = name || 'ชื่อบริการ';
    if (preview.category) preview.category.textContent = category;
    if (preview.price) preview.price.textContent = money(validPrice ? price : 0);
    if (preview.status) preview.status.textContent = active ? 'เปิดใช้งาน' : 'ปิดใช้งาน';

    if (fields.codeValidation) {
      fields.codeValidation.textContent = codeConflict ? 'รหัสนี้มีอยู่แล้ว ถ้าบันทึกจะเป็นการแก้ไขรายการเดิม' : '';
      fields.codeValidation.classList.toggle('is-warning', codeConflict);
    }

    if (fields.nameValidation) {
      const suggestion = suggestedCategory && fields.category?.value !== suggestedCategory
        ? `แนะนำหมวดหมู่: ${suggestedCategory}`
        : '';
      fields.nameValidation.textContent = hasName ? suggestion : 'กรุณากรอกชื่อบริการ';
      fields.nameValidation.classList.toggle('is-warning', !hasName || Boolean(suggestion));
    }

    if (fields.priceValidation) {
      let message = '';
      if (!validPrice) message = 'ราคาต้องเป็นตัวเลขและห้ามติดลบ';
      else if (price > 1000) message = 'ราคาสูงกว่าปกติ โปรดตรวจสอบ';
      else if (price === 0) message = 'ไม่มีค่าใช้จ่าย';

      fields.priceValidation.textContent = message;
      fields.priceValidation.classList.toggle('is-warning', !validPrice || price > 1000);
      fields.priceValidation.classList.toggle('is-ok', validPrice && price === 0);
    }

    if (fields.suggestion) {
      const prefix = prefixMap[category] || 'SRV';
      fields.suggestion.textContent = suggestedCategory
        ? `ระบบแนะนำหมวดหมู่ "${suggestedCategory}" และ prefix ${prefix}`
        : `Prefix สำหรับหมวดนี้: ${prefix}`;
    }

    if (fields.submit) {
      fields.submit.disabled = !code || !hasName || !validPrice;
    }
  }

  function updateSelectedInsight(row) {
    if (!selectedInsight || !row) return;

    const usage = money(row.dataset.usage);
    const income = money(row.dataset.income);
    const lastUsed = row.dataset.lastUsed || 'ยังไม่มีประวัติ';
    const smartExamCount = Number.parseInt(row.dataset.smartExam || '0', 10) || 0;

    selectedInsight.innerHTML = `
      <span>บริการที่เลือก</span>
      <strong>${row.dataset.name || '-'}</strong>
      <small>ใช้แล้ว ${usage} ครั้ง · รายได้ ${income} บาท · ${lastUsed}</small>
      <small>${smartExamCount > 0 ? 'ผูกกับ Smart Exam preset แล้ว' : 'ยังไม่ผูกกับ Smart Exam preset'}</small>
    `;
  }

  function updatePriceHistory(row) {
    if (!priceHistoryBox || !row) return;

    const serviceId = row.dataset.id || '';
    const history = priceHistoryMap[serviceId] || [];
    if (!history.length) {
      priceHistoryBox.innerHTML = `
        <span>ประวัติราคา</span>
        <strong>${row.dataset.name || '-'}</strong>
        <small>ยังไม่มีประวัติการเปลี่ยนราคา</small>
      `;
      return;
    }

    const items = history.map((entry) => `
      <div class="service-history-row">
        <strong>${money(entry.old_price)} → ${money(entry.new_price)}</strong>
        <small>${entry.changed_at || '-'} · ${entry.changed_by_name || '-'}</small>
        ${entry.note ? `<small>${entry.note}</small>` : ''}
      </div>
    `).join('');

    priceHistoryBox.innerHTML = `
      <span>ประวัติราคา</span>
      <strong>${row.dataset.name || '-'}</strong>
      ${items}
    `;
  }

  function fillFormFromRow(mode, row) {
    if (!row) return;

    if (!form) {
      updateSelectedInsight(row);
      updatePriceHistory(row);
      setSelected(row);
      return;
    }

    formMode = mode;
    originalCode = mode === 'edit' ? (row.dataset.code || '') : '';

    if (mode === 'edit') {
      if (modeLabel) modeLabel.textContent = 'Edit Service';
      if (panelTitle) panelTitle.textContent = 'แก้ไขบริการ';
      fields.code.value = row.dataset.code || '';
    } else if (mode === 'duplicate') {
      if (modeLabel) modeLabel.textContent = 'Duplicate Service';
      if (panelTitle) panelTitle.textContent = 'Duplicate จากบริการเดิม';
      fields.code.value = '';
      fields.code.placeholder = 'ใส่รหัสใหม่ หรือกด Auto';
    } else {
      if (modeLabel) modeLabel.textContent = mode === 'history' ? 'Price History' : 'Readonly Detail';
      if (panelTitle) panelTitle.textContent = mode === 'history' ? 'ประวัติราคาบริการ' : 'รายละเอียดบริการ';
      fields.code.value = row.dataset.code || '';
    }

    fields.name.value = row.dataset.name || '';
    fields.category.value = row.dataset.category || '';
    fields.price.value = money(row.dataset.price);
    fields.active.checked = row.dataset.status === 'active';

    setSelected(row);
    updateSelectedInsight(row);
    updatePriceHistory(row);
    updatePreviewAndValidation();
  }

  function resetForm() {
    if (!form) return;

    formMode = 'add';
    originalCode = '';
    form.reset();
    fields.price.value = '0.00';
    fields.active.checked = true;
    fields.code.placeholder = 'SRV001';
    if (modeLabel) modeLabel.textContent = 'Add Service';
    if (panelTitle) panelTitle.textContent = 'เพิ่มบริการใหม่';
    setSelected(null);
    updatePreviewAndValidation();
  }

  function nextCode(category) {
    const prefix = prefixMap[category] || 'SRV';
    let max = 0;

    rows.forEach((row) => {
      const code = row.dataset.code || '';
      if (!code.startsWith(prefix)) return;
      const numeric = Number.parseInt(code.slice(prefix.length).replace(/\D/g, ''), 10);
      if (Number.isFinite(numeric)) max = Math.max(max, numeric);
    });

    return `${prefix}${String(max + 1).padStart(3, '0')}`;
  }

  searchInput?.addEventListener('input', applyFilters);
  categoryFilter?.addEventListener('change', applyFilters);

  statusButtons.forEach((button) => {
    button.addEventListener('click', () => setStatus(button.dataset.serviceFilter || 'all'));
  });

  kpiButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const action = button.dataset.serviceKpi;
      if (action === 'all') {
        if (searchInput) searchInput.value = '';
        if (categoryFilter) categoryFilter.value = 'all';
        setStatus('all');
      } else if (action === 'active' || action === 'inactive') {
        setStatus(action);
      } else if (action === 'category') {
        sortRows('category', 1);
      } else if (action === 'price') {
        sortRows('price', -1);
      }
    });
  });

  document.querySelectorAll('[data-service-sort]').forEach((button) => {
    button.addEventListener('click', () => sortRows(button.dataset.serviceSort || 'name'));
  });

  rows.forEach((row) => {
    row.addEventListener('click', (event) => {
      const actionButton = event.target.closest('[data-service-row-action]');
      if (actionButton) {
        const action = actionButton.dataset.serviceRowAction;
        if (['detail', 'edit', 'duplicate', 'history'].includes(action)) fillFormFromRow(action, row);
        return;
      }

      fillFormFromRow('detail', row);
    });
  });

  document.querySelector('[data-service-new]')?.addEventListener('click', resetForm);
  document.querySelector('[data-service-reset]')?.addEventListener('click', resetForm);

  document.querySelector('[data-service-generate-code]')?.addEventListener('click', () => {
    if (!form || !fields.code) return;
    const category = fields.category?.value || categoryFromName(fields.name?.value) || 'อื่น ๆ';
    fields.code.value = nextCode(category);
    updatePreviewAndValidation();
  });

  if (form) {
    [fields.code, fields.name, fields.category, fields.price, fields.active].forEach((field) => {
      field?.addEventListener('input', updatePreviewAndValidation);
      field?.addEventListener('change', updatePreviewAndValidation);
    });

    form.addEventListener('submit', (event) => {
      updatePreviewAndValidation();
      if (fields.submit?.disabled) {
        event.preventDefault();
      }
    });
  }

  updatePreviewAndValidation();
  applyFilters();
})();
