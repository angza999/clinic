(() => {
  const page = document.querySelector('.pharmacy-label-page');
  const printButton = document.querySelector('[data-pharmacy-print]');
  const logForm = document.getElementById('pharmacyPrintLogForm');

  if (!page || !printButton || !logForm) {
    return;
  }

  function setButtonState(isBusy) {
    printButton.disabled = isBusy;
    printButton.dataset.busy = isBusy ? '1' : '0';
    printButton.innerHTML = isBusy
      ? '<i class="bi bi-hourglass-split"></i> กำลังบันทึก'
      : '<i class="bi bi-printer-fill"></i> พิมพ์สติ๊กเกอร์ยา';
  }

  async function recordPrintLog() {
    const url = page.dataset.printLogUrl || '';
    const body = new FormData(logForm);
    const response = await fetch(url, {
      method: 'POST',
      body,
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) {
      throw new Error(payload.message || 'บันทึกประวัติการพิมพ์ไม่สำเร็จ');
    }

    return payload;
  }

  function applyPrintPageSize() {
    const size = logForm.querySelector('[name="label_size"]')?.value || '58x40';
    const pageSize = {
      '58x40': '58mm 40mm',
      '80x50': '80mm 50mm',
      '100x75': '100mm 75mm'
    }[size] || '58mm 40mm';
    let style = document.getElementById('pharmacyDynamicPrintSize');
    if (!style) {
      style = document.createElement('style');
      style.id = 'pharmacyDynamicPrintSize';
      document.head.append(style);
    }
    style.textContent = `@media print { @page { size: ${pageSize}; margin: 0; } }`;
  }

  printButton.addEventListener('click', async () => {
    if (printButton.dataset.busy === '1') {
      return;
    }

    setButtonState(true);
    try {
      await recordPrintLog();
      applyPrintPageSize();
      window.print();
    } catch (error) {
      alert(error.message || 'ไม่สามารถพิมพ์สติ๊กเกอร์ยาได้');
    } finally {
      setButtonState(false);
    }
  });
})();
