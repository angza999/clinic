(function () {
  const page = document.querySelector('.pharmacy-workstation-page');
  if (!page) return;

  const rows = Array.from(page.querySelectorAll('[data-pharmacy-profile-row]'));
  const searchInput = page.querySelector('[data-pharmacy-profile-search]');
  const statusSelect = page.querySelector('[data-pharmacy-profile-status]');
  const form = page.querySelector('.pharmacy-editor-form');
  const previewName = page.querySelector('[data-pharmacy-preview-name]');
  const previewInstruction = page.querySelector('[data-pharmacy-preview-instruction]');
  const queueSurface = page.querySelector('.pharmacy-queue-surface');
  const recentLog = page.querySelector('.pharmacy-log-mini');

  const fields = {};
  if (form) {
    form.querySelectorAll('[data-pharmacy-field]').forEach((field) => {
      fields[field.dataset.pharmacyField] = field;
    });
  }

  const parseProfile = (row) => {
    try {
      return JSON.parse(row.dataset.profile || '{}');
    } catch (error) {
      return {};
    }
  };

  const buildInstruction = () => {
    const manual = fields.default_instruction?.value.trim();
    if (manual) return manual;

    const dose = [fields.default_dose_qty?.value.trim(), fields.default_dose_unit?.value.trim()]
      .filter(Boolean)
      .join(' ');
    const frequency = fields.default_frequency?.value.trim();
    const timing = fields.default_timing?.value.trim();
    const parts = [];

    if (dose) parts.push(`รับประทานครั้งละ ${dose}`);
    if (frequency) parts.push(frequency);
    if (timing) parts.push(timing);

    return parts.join(' ') || 'ยังไม่ตั้ง default วิธีใช้';
  };

  const refreshPreview = () => {
    if (previewName) {
      previewName.textContent =
        fields.drug_short_name?.value.trim() ||
        fields.item_id?.selectedOptions?.[0]?.textContent?.trim() ||
        '-';
    }
    if (previewInstruction) {
      previewInstruction.textContent = buildInstruction();
    }
  };

  const selectProfile = (row) => {
    const profile = parseProfile(row);
    rows.forEach((item) => item.classList.toggle('is-selected', item === row));

    Object.entries(fields).forEach(([key, field]) => {
      if (key === 'is_active') {
        field.checked = Number(profile.profile_active || 0) === 1;
        return;
      }

      if (field.tagName === 'SELECT') {
        const value = String(profile[key] ?? '');
        const option = Array.from(field.options).find((entry) => entry.value === value);
        field.value = option ? value : String(profile[key] ?? field.value);
        return;
      }

      field.value = String(profile[key] ?? '');
    });

    refreshPreview();
  };

  const applyFilters = () => {
    const term = (searchInput?.value || '').trim().toLowerCase();
    const status = statusSelect?.value || 'all';

    rows.forEach((row) => {
      const matchesTerm = !term || (row.dataset.search || '').includes(term);
      const matchesStatus = status === 'all' || row.dataset.status === status;
      row.hidden = !(matchesTerm && matchesStatus);
    });
  };

  rows.forEach((row) => {
    row.addEventListener('click', () => selectProfile(row));
  });

  searchInput?.addEventListener('input', applyFilters);
  statusSelect?.addEventListener('change', applyFilters);

  page.querySelectorAll('[data-pharmacy-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      const filter = button.dataset.pharmacyFilter;
      if (filter === 'pending') {
        queueSurface?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      if (filter === 'printed') {
        recentLog?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      if (statusSelect) statusSelect.value = filter === 'risk' ? 'risk' : 'all';
      if (searchInput) searchInput.value = '';
      applyFilters();
    });
  });

  ['default_dose_qty', 'default_dose_unit', 'default_frequency', 'default_timing', 'default_instruction', 'drug_short_name'].forEach((key) => {
    fields[key]?.addEventListener('input', refreshPreview);
    fields[key]?.addEventListener('change', refreshPreview);
  });

  fields.item_id?.addEventListener('change', () => {
    const matchingRow = rows.find((row) => String(parseProfile(row).item_id) === fields.item_id.value);
    if (matchingRow) selectProfile(matchingRow);
  });

  refreshPreview();
})();
