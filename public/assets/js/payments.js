document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('paymentSearch');
  const dateFilter = document.getElementById('paymentDateFilter');
  const methodFilter = document.getElementById('paymentMethodFilter');
  const cards = Array.from(document.querySelectorAll('[data-payment-card]'));
  const rows = Array.from(document.querySelectorAll('[data-payment-row]'));

  const formatMoney = (value) => Number(value || 0).toFixed(2);
  const parseMoney = (value) => {
    const parsed = Number.parseFloat(String(value || '0').replace(/,/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
  };

  const applyFilters = () => {
    const query = (searchInput?.value || '').trim().toLowerCase();
    const date = dateFilter?.value || '';
    const method = methodFilter?.value || '';

    const filterElement = (element, includeMethod) => {
      const haystack = String(element.dataset.search || '').toLowerCase();
      const elementDate = element.dataset.date || '';
      const elementMethod = element.dataset.method || '';
      const matchesQuery = !query || haystack.includes(query);
      const matchesDate = !date || elementDate === date || includeMethod === false;
      const matchesMethod = !includeMethod || !method || elementMethod === method;
      element.classList.toggle('is-hidden', !(matchesQuery && matchesDate && matchesMethod));
    };

    cards.forEach((card) => filterElement(card, false));
    rows.forEach((row) => filterElement(row, true));
  };

  [searchInput, dateFilter, methodFilter].forEach((control) => {
    control?.addEventListener('input', applyFilters);
    control?.addEventListener('change', applyFilters);
  });

  document.querySelectorAll('[data-payment-focus]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const selector = trigger.dataset.paymentFocus;
      const target = selector ? document.querySelector(selector) : null;
      if (!target) {
        return;
      }

      if (target instanceof HTMLInputElement) {
        target.focus();
        target.select();
        return;
      }

      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  document.querySelectorAll('[data-payment-method-shortcut]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      if (!methodFilter) {
        return;
      }

      methodFilter.value = trigger.dataset.paymentMethodShortcut || '';
      document.getElementById('receiptHistory')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      applyFilters();
    });
  });

  document.querySelectorAll('.cashier-payment-form').forEach((form) => {
    const baseTotal = parseMoney(form.dataset.baseTotal);
    const methodInput = form.querySelector('.payment-method');
    const discountInput = form.querySelector('.payment-discount');
    const paidInput = form.querySelector('.payment-paid');
    const netTotalEl = form.querySelector('.payment-net-total');
    const changeTotalEl = form.querySelector('.payment-change-total');
    const warningEl = form.querySelector('.payment-warning');
    const submitButton = form.querySelector('.payment-submit');

    const updatePreview = () => {
      const method = methodInput?.value || 'CASH';
      const discount = Math.max(0, parseMoney(discountInput?.value));
      const netTotal = Math.max(0, baseTotal - discount);

      if (method !== 'CASH' && paidInput) {
        paidInput.value = formatMoney(netTotal);
        paidInput.readOnly = true;
      } else if (paidInput) {
        paidInput.readOnly = false;
      }

      const paid = Math.max(0, parseMoney(paidInput?.value));
      const change = method === 'CASH' ? Math.max(0, paid - netTotal) : 0;
      const isInvalid = discount > baseTotal || (method === 'CASH' && paid < netTotal);

      if (netTotalEl) {
        netTotalEl.textContent = formatMoney(netTotal);
      }
      if (changeTotalEl) {
        changeTotalEl.textContent = formatMoney(change);
      }
      warningEl?.classList.toggle('d-none', !isInvalid);
      if (submitButton) {
        submitButton.disabled = isInvalid;
      }
    };

    methodInput?.addEventListener('change', updatePreview);
    discountInput?.addEventListener('input', updatePreview);
    paidInput?.addEventListener('input', updatePreview);

    form.addEventListener('submit', (event) => {
      const submitter = event.submitter;
      if (submitter?.hasAttribute('data-skip-payment-confirm')) {
        return;
      }

      updatePreview();
      if (submitButton?.disabled) {
        event.preventDefault();
        return;
      }

      const confirmed = window.confirm('ยืนยันรับชำระและปิดเคสนี้ใช่ไหม?');
      if (!confirmed) {
        event.preventDefault();
      }
    });

    updatePreview();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'F1') {
      event.preventDefault();
      searchInput?.focus();
      searchInput?.select();
    }

    if (event.key === 'F9') {
      event.preventDefault();
      document.getElementById('paymentQueue')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    if (event.key === 'Escape') {
      if (searchInput) {
        searchInput.value = '';
      }
      if (methodFilter) {
        methodFilter.value = '';
      }
      applyFilters();
    }
  });

  applyFilters();
});
