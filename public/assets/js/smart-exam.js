(() => {
  const form = document.getElementById('smartExamForm');
  if (!form) {
    return;
  }

  const runtime = document.getElementById('smartExamRuntime');
  const dxSuggest = document.getElementById('dxSuggest');
  const examAlert = document.getElementById('smartExamAlert');
  const finishPaymentButton = document.getElementById('smartFinishPayment');
  const finishWaitPaymentButton = document.getElementById('smartFinishWaitPayment');
  const finishNoChargeButton = document.getElementById('smartFinishNoCharge');
  const finishNote = document.getElementById('smartFinishNote');
  const paymentDiscountInput = document.getElementById('smartPaymentDiscount');
  const paymentPaidInput = document.getElementById('smartPaymentPaid');
  const paymentNetTotal = document.getElementById('smartPaymentNet');
  const paymentChangeTotal = document.getElementById('smartPaymentChange');
  const paymentWarning = document.getElementById('smartPaymentWarning');
  const readinessCc = document.getElementById('smartReadinessCc');
  const readinessDx = document.getElementById('smartReadinessDx');
  const readinessBilling = document.getElementById('smartReadinessBilling');
  const readinessStock = document.getElementById('smartReadinessStock');
  const stepPreset = document.getElementById('smartExamStepPreset');
  const stepClinical = document.getElementById('smartExamStepClinical');
  const stepFinish = document.getElementById('smartExamStepFinish');
  const compactStepPreset = document.getElementById('smartExamStepPresetCompact');
  const compactStepClinical = document.getElementById('smartExamStepClinicalCompact');
  const compactStepFinish = document.getElementById('smartExamStepFinishCompact');

  const serviceForm = document.querySelector('form.smart-add-line-form:not(.smart-add-line-form-items)');
  const itemForm = document.querySelector('form.smart-add-line-form-items');
  const serviceSearchInput = document.getElementById('smartServiceSearch');
  const itemSearchInput = document.getElementById('smartItemSearch');
  const serviceCountLabel = document.getElementById('smartServiceCountLabel');
  const itemCountLabel = document.getElementById('smartItemCountLabel');
  const serviceLineList = document.getElementById('smartServiceLineList');
  const itemLineList = document.getElementById('smartItemLineList');
  const summaryServiceCount = document.getElementById('smartSummaryServiceCount');
  const summaryItemCount = document.getElementById('smartSummaryItemCount');
  const summaryServiceLines = document.getElementById('smartSummaryServiceLines');
  const summaryItemLines = document.getElementById('smartSummaryItemLines');
  const summaryServiceTotal = document.getElementById('smartSummaryServiceTotal');
  const summaryItemTotal = document.getElementById('smartSummaryItemTotal');
  const summaryGrandTotal = document.getElementById('smartSummaryGrandTotal');

  const servicePresetButtons = Array.from(document.querySelectorAll('.smart-service-card[data-preset-key]'));
  const appendPresetButtons = Array.from(document.querySelectorAll('[data-append-target]'));
  const templateButtons = Array.from(document.querySelectorAll('[data-template]'));
  const serviceFilterTargets = Array.from(document.querySelectorAll('[data-smart-filter-text]'));
  const orderSelects = [document.getElementById('smartServiceSelect'), document.getElementById('smartItemSelect')].filter(Boolean);

  const clinicalFieldIds = ['cc', 'pi', 'pe', 'dx'];
  const vitalFieldIds = ['weight_kg', 'temp_c', 'pulse_rate', 'resp_rate', 'bp_systolic', 'bp_diastolic', 'spo2'];
  const trackedFieldIds = [...vitalFieldIds, ...clinicalFieldIds];
  const historyStack = [];

  const templates = {
    uri: {
      cc: 'ไข้ ไอ มีน้ำมูก',
      pi: 'มีไข้ ไอ มีน้ำมูก เจ็บคอเล็กน้อย ไม่มีหอบเหนื่อย',
      pe: 'Throat mildly injected, chest clear',
      dx: 'URI'
    },
    wound: {
      cc: 'มีแผล',
      pi: 'มีแผลจากอุบัติเหตุ ไม่มีเลือดออกมาก',
      pe: 'Wound clean, no active bleeding',
      dx: 'Wound'
    },
    gastritis: {
      cc: 'ปวดท้อง',
      pi: 'ปวดท้องบริเวณลิ้นปี่ คลื่นไส้เล็กน้อย ไม่มีถ่ายดำ',
      pe: 'Abdomen soft, mild epigastric tenderness',
      dx: 'Gastritis'
    },
    iv: {
      cc: 'อ่อนเพลีย',
      pi: 'อ่อนเพลีย รับประทานได้น้อย ไม่มีอาการหอบเหนื่อย',
      pe: 'General appearance fair, no respiratory distress',
      dx: 'Dehydration mild'
    }
  };

  function getField(id) {
    return document.getElementById(id);
  }

  function focusField(field, options = {}) {
    if (!field) {
      return false;
    }

    field.focus();

    if (options.select && typeof field.select === 'function') {
      field.select();
    }

    if (typeof field.scrollIntoView === 'function') {
      field.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    return true;
  }

  function fieldValue(id) {
    return (getField(id)?.value || '').trim();
  }

  function getLineCounts() {
    const serviceCount = Number.parseInt(runtime?.dataset.serviceCount || '0', 10) || 0;
    const itemCount = Number.parseInt(runtime?.dataset.itemCount || '0', 10) || 0;

    return {
      serviceCount,
      itemCount,
      hasBillable: serviceCount + itemCount > 0
    };
  }

  function formatMoney(value) {
    return Number(value || 0).toFixed(2);
  }

  function csrfValue() {
    return form.querySelector('[name="_csrf"]')?.value || '';
  }

  function visitIdValue() {
    return form.querySelector('[name="visit_id"]')?.value || '';
  }

  function setText(element, value) {
    if (element) {
      element.textContent = value;
    }
  }

  function createHiddenInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
  }

  function createRemoveForm(type, id) {
    const removeForm = document.createElement('form');
    removeForm.method = 'post';
    removeForm.action = type === 'service' ? (runtime?.dataset.removeServiceUrl || '') : (runtime?.dataset.removeItemUrl || '');
    removeForm.append(
      createHiddenInput('_csrf', csrfValue()),
      createHiddenInput('return_to', 'queue-exam'),
      createHiddenInput('visit_id', visitIdValue()),
      createHiddenInput(type === 'service' ? 'service_line_id' : 'usage_id', String(id))
    );

    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'btn btn-link btn-sm text-danger p-0';
    button.textContent = 'ลบ';
    removeForm.append(button);

    return removeForm;
  }

  function createMainLine(line, type) {
    const row = document.createElement('div');
    row.className = 'smart-line-item';

    const detail = document.createElement('div');
    const name = document.createElement('strong');
    name.textContent = line.name || '-';
    const qty = document.createElement('span');
    qty.textContent = type === 'service'
      ? `จำนวน ${line.qtyText || line.qty || ''}`
      : `จำนวน ${line.qtyText || line.qty || ''} ${line.unitName || ''}`.trim();
    detail.append(name, qty);

    const price = document.createElement('div');
    price.className = 'smart-line-price';
    const total = document.createElement('strong');
    total.textContent = line.lineTotalText || formatMoney(line.lineTotal);
    price.append(total, createRemoveForm(type, line.id));

    row.append(detail, price);
    return row;
  }

  function createSummaryLine(line, type) {
    const row = document.createElement('div');
    row.className = 'smart-summary-line';

    const label = document.createElement('span');
    label.textContent = type === 'service'
      ? `${line.name || '-'} x${line.qtyText || line.qty || ''}`
      : `${line.name || '-'} x${line.qtyText || line.qty || ''}`;
    const total = document.createElement('strong');
    total.textContent = line.lineTotalText || formatMoney(line.lineTotal);

    row.append(label, total);
    return row;
  }

  function renderEmpty(container, text) {
    if (!container) {
      return;
    }

    container.innerHTML = '';
    const empty = document.createElement('div');
    empty.className = 'smart-summary-empty';
    empty.textContent = text;
    container.append(empty);
  }

  function renderLines(container, lines, type, emptyText, createLine) {
    if (!container) {
      return;
    }

    container.innerHTML = '';
    if (!lines.length) {
      renderEmpty(container, emptyText);
      return;
    }

    lines.forEach((line) => container.append(createLine(line, type)));
  }

  function applyOrderSummary(summary) {
    if (!summary || !runtime) {
      return;
    }

    runtime.dataset.serviceCount = String(summary.serviceCount || 0);
    runtime.dataset.itemCount = String(summary.itemCount || 0);
    runtime.dataset.grandTotal = String(summary.grandTotal || 0);

    setText(serviceCountLabel, `${summary.serviceCount || 0} รายการ`);
    setText(itemCountLabel, `${summary.itemCount || 0} รายการ`);
    setText(summaryServiceCount, String(summary.serviceCount || 0));
    setText(summaryItemCount, String(summary.itemCount || 0));
    setText(summaryServiceTotal, summary.serviceTotalText || formatMoney(summary.serviceTotal));
    setText(summaryItemTotal, summary.itemTotalText || formatMoney(summary.itemTotal));
    setText(summaryGrandTotal, summary.grandTotalText || formatMoney(summary.grandTotal));

    renderLines(serviceLineList, summary.services || [], 'service', 'ยังไม่มีบริการในเคสนี้', createMainLine);
    renderLines(itemLineList, summary.items || [], 'item', 'ยังไม่มียา/เวชภัณฑ์ในเคสนี้', createMainLine);
    renderLines(summaryServiceLines, summary.services || [], 'service', 'ยังไม่มีบริการ', createSummaryLine);
    renderLines(summaryItemLines, summary.items || [], 'item', 'ยังไม่มีอุปกรณ์ที่ใช้', createSummaryLine);

    if (paymentPaidInput && Number.parseFloat(paymentPaidInput.value || '0') <= 0) {
      paymentPaidInput.value = formatMoney(summary.grandTotal || 0);
    }

    syncVisualState();
  }

  function applyClinicalSummary(clinical) {
    if (!clinical) {
      return;
    }

    const fieldMap = {
      cc: clinical.cc,
      pi: clinical.pi,
      pe: clinical.pe,
      dx: clinical.dx,
      advice: clinical.advice,
      followup_date: clinical.followup_date
    };

    Object.entries(fieldMap).forEach(([id, value]) => {
      const field = getField(id);
      if (field) {
        field.value = value || '';
      }
    });

    autoDxSuggest();
    syncVisualState();
  }

  function getPaymentState() {
    const baseTotal = Number.parseFloat(runtime?.dataset.grandTotal || '0') || 0;
    const discount = Math.max(0, Number.parseFloat(paymentDiscountInput?.value || '0') || 0);
    const paid = Math.max(0, Number.parseFloat(paymentPaidInput?.value || '0') || 0);
    const net = Math.max(0, baseTotal - discount);
    const change = Math.max(0, paid - net);

    return {
      baseTotal,
      discount,
      paid,
      net,
      change,
      isValid: baseTotal > 0 && paid >= net
    };
  }

  function syncPaymentPreview() {
    const payment = getPaymentState();

    if (paymentNetTotal) {
      paymentNetTotal.textContent = formatMoney(payment.net);
    }

    if (paymentChangeTotal) {
      paymentChangeTotal.textContent = formatMoney(payment.change);
    }

    if (paymentWarning) {
      paymentWarning.hidden = payment.isValid || payment.baseTotal <= 0;
    }
  }

  function snapshot() {
    const values = {};
    trackedFieldIds.forEach((id) => {
      values[id] = fieldValue(id);
    });
    historyStack.push(values);
  }

  function restoreLast() {
    if (!historyStack.length) {
      return;
    }

    const values = historyStack.pop();
    trackedFieldIds.forEach((id) => {
      const field = getField(id);
      if (field) {
        field.value = values[id] ?? '';
      }
    });

    autoDxSuggest();
    syncVisualState();
  }

  function showSuggestion(text) {
    if (!dxSuggest) {
      return;
    }

    if (!text) {
      dxSuggest.hidden = true;
      dxSuggest.textContent = '';
      return;
    }

    dxSuggest.hidden = false;
    dxSuggest.textContent = text;
  }

  function showExamAlert(message) {
    if (!examAlert) {
      window.alert(message);
      return;
    }

    examAlert.hidden = false;
    examAlert.textContent = message;
  }

  function hideExamAlert() {
    if (!examAlert) {
      return;
    }

    examAlert.hidden = true;
    examAlert.textContent = '';
  }

  function setButtonSelection(buttons, selectedButton) {
    buttons.forEach((button) => {
      button.classList.toggle('is-selected', button === selectedButton);
    });
  }

  function focusNextClinicalField() {
    const firstEmpty = clinicalFieldIds.find((id) => fieldValue(id) === '');
    focusField(getField(firstEmpty || 'dx'));
  }

  function focusBillingEntry() {
    if (focusField(getField('smartServiceSelect'))) {
      return true;
    }

    return focusField(getField('smartItemSelect'));
  }

  function focusFinishAction() {
    if (finishPaymentButton && !finishPaymentButton.disabled) {
      return focusField(finishPaymentButton);
    }

    if (finishWaitPaymentButton && !finishWaitPaymentButton.disabled) {
      return focusField(finishWaitPaymentButton);
    }

    if (finishNoChargeButton && !finishNoChargeButton.disabled) {
      return focusField(finishNoChargeButton);
    }

    return false;
  }

  function moveVitalFocus(currentId) {
    const currentIndex = vitalFieldIds.indexOf(currentId);
    if (currentIndex === -1) {
      return false;
    }

    const nextId = vitalFieldIds[currentIndex + 1];
    if (!nextId) {
      return focusField(getField('cc'));
    }

    return focusField(getField(nextId), { select: true });
  }

  function mergeValue(current, incoming, separator = ', ') {
    const currentValue = (current || '').trim();
    const incomingValue = (incoming || '').trim();

    if (incomingValue === '') {
      return currentValue;
    }

    if (currentValue === '') {
      return incomingValue;
    }

    if (currentValue.includes(incomingValue)) {
      return currentValue;
    }

    return currentValue + separator + incomingValue;
  }

  function mergeFieldValue(id, incoming, separator = ', ') {
    const field = getField(id);
    if (!field) {
      return;
    }

    field.value = mergeValue(field.value, incoming, separator);
  }

  function appendPreset(targetId, text, button = null) {
    const field = getField(targetId);
    if (!field || text === '') {
      return;
    }

    snapshot();
    field.value = mergeValue(field.value, text, ', ');

    if (button) {
      button.classList.add('is-selected');
    }

    autoDxSuggest();
    syncVisualState();

    if (targetId === 'cc' && fieldValue('pi') === '') {
      focusField(getField('pi'));
    }
  }

  function applyTemplate(name, button = null) {
    const template = templates[name];
    if (!template) {
      return;
    }

    snapshot();
    mergeFieldValue('cc', template.cc, ', ');
    mergeFieldValue('pi', template.pi, '\n');
    mergeFieldValue('pe', template.pe, '\n');
    mergeFieldValue('dx', template.dx, ' / ');

    setButtonSelection(templateButtons, button);
    autoDxSuggest();
    syncVisualState();
    focusField(getField('dx'));
  }

  function autoDxSuggest() {
    const cc = fieldValue('cc').toLowerCase();
    const pi = fieldValue('pi').toLowerCase();
    const text = cc + ' ' + pi;

    let suggestion = '';
    if (text.includes('ไข้') && text.includes('ไอ') && text.includes('น้ำมูก')) {
      suggestion = 'Suggestion: Dx = URI';
    } else if (text.includes('ปวดท้อง')) {
      suggestion = 'Suggestion: Dx = Gastritis';
    } else if (text.includes('แผล')) {
      suggestion = 'Suggestion: Dx = Wound';
    }

    showSuggestion(suggestion);
  }

  function checkTempSuggestion() {
    const temp = Number.parseFloat(getField('temp_c')?.value || '');
    if (!Number.isFinite(temp)) {
      autoDxSuggest();
      syncVisualState();
      return;
    }

    if (temp >= 37.5) {
      showSuggestion('Suggestion: มีไข้');
      return;
    }

    autoDxSuggest();
    syncVisualState();
  }

  function clearExam() {
    if (!window.confirm('ต้องการล้างข้อมูลใน Smart Exam หรือไม่?')) {
      return;
    }

    snapshot();
    trackedFieldIds.forEach((id) => {
      const field = getField(id);
      if (field) {
        field.value = '';
      }
    });

    if (runtime) {
      runtime.dataset.currentPreset = '';
    }

    hideExamAlert();
    showSuggestion('');
    syncVisualState();
    focusField(getField('cc'));
  }

  function setReadinessItem(element, ready, readyText, pendingText) {
    if (!element) {
      return;
    }

    element.classList.toggle('is-ready', ready);
    element.classList.toggle('is-pending', !ready);

    const status = element.querySelector('strong');
    if (status) {
      status.textContent = ready ? readyText : pendingText;
    }
  }

  function selectedItemStockState() {
    const itemSelect = getField('smartItemSelect');
    const qtyInput = getField('smartItemQtyInput');
    const selectedOption = itemSelect?.selectedOptions?.[0] || null;

    if (!selectedOption || selectedOption.value === '') {
      return {
        hasSelection: false,
        isValid: true,
        stockBalance: null,
        qty: 0
      };
    }

    const stockBalance = Number.parseFloat(selectedOption.dataset.stockBalance || '0') || 0;
    const qty = Math.max(0, Number.parseFloat(qtyInput?.value || '0') || 0);

    return {
      hasSelection: true,
      isValid: qty > 0 && stockBalance >= qty,
      stockBalance,
      qty
    };
  }

  function filterShortcutTargets(input, targets) {
    const query = (input?.value || '').trim().toLowerCase();
    targets.forEach((target) => {
      const label = (target.dataset.smartFilterText || '').toLowerCase();
      target.hidden = query !== '' && !label.includes(query);
    });
  }

  function filterSelectOptions(input, select) {
    const query = (input?.value || '').trim().toLowerCase();
    if (!select) {
      return;
    }

    Array.from(select.options).forEach((option) => {
      if (option.value === '') {
        option.hidden = false;
        return;
      }

      const label = (option.dataset.filterText || option.textContent || '').toLowerCase();
      option.hidden = query !== '' && !label.includes(query);
    });
  }

  function syncOrderFilters() {
    filterShortcutTargets(serviceSearchInput, serviceFilterTargets.filter((target) => target.querySelector('[name="service_id"]')));
    filterShortcutTargets(itemSearchInput, serviceFilterTargets.filter((target) => target.querySelector('[name="item_id"]')));
    filterSelectOptions(serviceSearchInput, getField('smartServiceSelect'));
    filterSelectOptions(itemSearchInput, getField('smartItemSelect'));
  }

  function syncAppendButtonState() {
    appendPresetButtons.forEach((button) => {
      const targetId = button.dataset.appendTarget || '';
      const appendText = button.dataset.appendText || '';
      const value = fieldValue(targetId);
      button.classList.toggle('is-selected', appendText !== '' && value.includes(appendText));
    });
  }

  function syncServicePresetState() {
    const currentPreset = runtime?.dataset.currentPreset || '';
    servicePresetButtons.forEach((button) => {
      button.classList.toggle('is-active', currentPreset !== '' && button.dataset.presetKey === currentPreset);
    });
  }

  function setStepState(element, state) {
    if (!element) {
      return;
    }

    element.classList.toggle('active', state === 'active');
    element.classList.toggle('is-complete', state === 'complete');
    element.classList.toggle('is-idle', state === 'idle');
  }

  function setStepGroupState(elements, state) {
    elements.forEach((element) => setStepState(element, state));
  }

  function updateStepState(clinicalReady, paymentReady) {
    const currentPreset = runtime?.dataset.currentPreset || '';
    const hasClinicalInput = clinicalFieldIds.some((id) => fieldValue(id) !== '');

    if (currentPreset || hasClinicalInput) {
      setStepGroupState([stepPreset, compactStepPreset], 'complete');
    } else {
      setStepGroupState([stepPreset, compactStepPreset], 'active');
    }

    if (paymentReady) {
      setStepGroupState([stepClinical, compactStepClinical], 'complete');
      setStepGroupState([stepFinish, compactStepFinish], 'active');
      return;
    }

    if (clinicalReady) {
      setStepGroupState([stepClinical, compactStepClinical], 'complete');
      setStepGroupState([stepFinish, compactStepFinish], 'active');
      return;
    }

    if (currentPreset || hasClinicalInput) {
      setStepGroupState([stepClinical, compactStepClinical], 'active');
      setStepGroupState([stepFinish, compactStepFinish], 'idle');
      return;
    }

    setStepGroupState([stepClinical, compactStepClinical], 'idle');
    setStepGroupState([stepFinish, compactStepFinish], 'idle');
  }

  function syncFinishState() {
    const ccReady = fieldValue('cc') !== '';
    const dxReady = fieldValue('dx') !== '';
    const clinicalReady = ccReady && dxReady;
    const { hasBillable } = getLineCounts();
    const paymentReady = clinicalReady && hasBillable;
    const payment = getPaymentState();
    const stock = selectedItemStockState();

    syncPaymentPreview();

    setReadinessItem(readinessCc, ccReady, 'พร้อม', 'ยังไม่กรอก');
    setReadinessItem(readinessDx, dxReady, 'พร้อม', 'ยังไม่กรอก');
    setReadinessItem(readinessBilling, hasBillable, 'พร้อม', 'ยังไม่มี');
    setReadinessItem(readinessStock, stock.isValid, 'พร้อม', 'ไม่พอ');

    if (finishPaymentButton) {
      finishPaymentButton.disabled = !paymentReady || !payment.isValid || !stock.isValid;
    }

    if (finishWaitPaymentButton) {
      finishWaitPaymentButton.disabled = !paymentReady || !stock.isValid;
    }

    if (finishNoChargeButton) {
      finishNoChargeButton.disabled = !clinicalReady || !stock.isValid;
    }

    if (finishNote) {
      if (!ccReady && !dxReady) {
        finishNote.textContent = 'เริ่มจากกรอก CC และ Dx ก่อน ระบบจะเปิดให้จบเคสเมื่อข้อมูลหลักครบ';
      } else if (!ccReady) {
        finishNote.textContent = 'ยังขาด CC กรุณาระบุอาการสำคัญก่อนจบเคส';
      } else if (!dxReady) {
        finishNote.textContent = 'ยังขาด Dx กรุณาระบุวินิจฉัยเบื้องต้นก่อนจบเคส';
      } else if (!hasBillable) {
        finishNote.textContent = 'ถ้าจะส่งชำระเงิน ต้องเพิ่มบริการหรือยาอย่างน้อย 1 รายการ หรือใช้ปุ่มปิดเคสแบบไม่มีค่าใช้จ่าย';
      } else if (!stock.isValid) {
        finishNote.textContent = 'Stock ไม่พอสำหรับรายการยาที่เลือก กรุณาลดจำนวนหรือเลือกรายการอื่น';
      } else if (!payment.isValid) {
        finishNote.textContent = 'ยอดรับชำระยังน้อยกว่ายอดสุทธิ สามารถแก้ยอดรับหรือกดบันทึกรอชำระไว้ก่อนได้';
      } else {
        finishNote.textContent = 'ข้อมูลหลักครบและมีรายการคิดเงินแล้ว พร้อมบันทึกและจบเคส';
      }
    }

    updateStepState(clinicalReady, paymentReady);
  }

  function syncVisualState() {
    syncAppendButtonState();
    syncServicePresetState();
    syncFinishState();
  }

  function handleTextareaShortcut(event) {
    if (event.key !== 'Enter' || (!event.ctrlKey && !event.metaKey)) {
      return;
    }

    const nextMap = {
      cc: 'pi',
      pi: 'pe',
      pe: 'dx'
    };

    const nextId = nextMap[event.target.id];
    if (!nextId) {
      return;
    }

    event.preventDefault();
    focusField(getField(nextId));
  }

  function handleEnterProgression(event) {
    if (
      event.key !== 'Enter' ||
      event.shiftKey ||
      event.ctrlKey ||
      event.metaKey ||
      event.altKey
    ) {
      return;
    }

    const target = event.target;
    if (!(target instanceof HTMLElement) || target.tagName === 'TEXTAREA') {
      return;
    }

    const targetId = target.id || '';

    if (moveVitalFocus(targetId)) {
      event.preventDefault();
      return;
    }

    if (targetId === 'dx') {
      event.preventDefault();
      const { hasBillable } = getLineCounts();
      if (hasBillable) {
        focusFinishAction();
      } else {
        focusBillingEntry();
      }
      return;
    }

    if (targetId === 'smartServiceSelect') {
      event.preventDefault();
      focusField(getField('smartServiceQtyInput'), { select: true });
      return;
    }

    if (targetId === 'smartServiceQtyInput') {
      event.preventDefault();
      serviceForm?.requestSubmit();
      return;
    }

    if (targetId === 'smartItemSelect') {
      event.preventDefault();
      focusField(getField('smartItemQtyInput'), { select: true });
      return;
    }

    if (targetId === 'smartItemQtyInput') {
      event.preventDefault();
      focusField(getField('smartItemNoteInput'), { select: true });
      return;
    }

    if (targetId === 'smartItemNoteInput') {
      event.preventDefault();
      itemForm?.requestSubmit();
      return;
    }

    if (targetId === 'smartPaymentDiscount') {
      event.preventDefault();
      focusField(getField('smartPaymentPaid'), { select: true });
      return;
    }

    if (targetId === 'smartPaymentPaid') {
      event.preventDefault();
      focusFinishAction();
    }
  }

  function handleGlobalShortcut(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      if (!focusField(serviceSearchInput || servicePresetButtons[0])) {
        focusField(getField('cc'));
      }
      return;
    }

    if (event.key === 'F2') {
      event.preventDefault();
      if (!focusField(serviceSearchInput)) {
        focusBillingEntry();
      }
      return;
    }

    if (event.key === 'F9') {
      event.preventDefault();
      focusFinishAction();
      return;
    }

    if (event.key === 'Escape') {
      hideExamAlert();
      showSuggestion('');
      if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
      }
    }
  }

  function validateSelectedItemStock(event) {
    const stock = selectedItemStockState();
    if (!stock.hasSelection || stock.isValid) {
      return true;
    }

    event.preventDefault();
    showExamAlert('Stock ไม่พอสำหรับรายการยาที่เลือก กรุณาลดจำนวนหรือเลือกรายการอื่น');
    focusField(getField('smartItemQtyInput'), { select: true });
    syncVisualState();
    return false;
  }

  function isOrderAction(action) {
    return [
      'visit-add-service',
      'visit-remove-service',
      'visit-add-item',
      'visit-remove-item'
    ].some((page) => action.includes(`page=${page}`));
  }

  async function submitOrderFormAjax(orderForm, submitter = null) {
    if (!orderForm || !isOrderAction(orderForm.action || '')) {
      return false;
    }

    if (orderForm.classList.contains('smart-add-line-form-items')) {
      const syntheticEvent = { preventDefault() {}, target: orderForm };
      if (validateSelectedItemStock(syntheticEvent) === false) {
        return true;
      }
    }

    const button = submitter || orderForm.querySelector('button[type="submit"]');
    button?.setAttribute('disabled', 'disabled');
    orderForm.classList.add('is-loading');
    hideExamAlert();

    try {
      const response = await fetch(orderForm.action, {
        method: 'POST',
        body: new FormData(orderForm),
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });
      const contentType = response.headers.get('content-type') || '';
      const payload = contentType.includes('application/json') ? await response.json() : null;

      if (!response.ok || !payload?.ok) {
        throw new Error(payload?.message || 'ไม่สามารถอัปเดตรายการได้');
      }

      applyOrderSummary(payload.summary);

      if (orderForm.matches('.smart-add-line-form')) {
        const qtyInput = orderForm.querySelector('[name="qty"]');
        const noteInput = orderForm.querySelector('[name="usage_note"]');
        if (qtyInput) {
          qtyInput.value = orderForm.classList.contains('smart-add-line-form-items') ? '1' : '1';
        }
        if (noteInput) {
          noteInput.value = '';
        }
      }

      if (payload.message) {
        showExamAlert(payload.message);
        window.setTimeout(hideExamAlert, 1400);
      }
    } catch (error) {
      showExamAlert(error instanceof Error ? error.message : 'ไม่สามารถอัปเดตรายการได้');
    } finally {
      button?.removeAttribute('disabled');
      orderForm.classList.remove('is-loading');
      syncVisualState();
    }

    return true;
  }

  async function submitPresetAjax(submitter) {
    if (!submitter || submitter.name !== 'preset_key') {
      return false;
    }

    const action = submitter.formAction || form.action;
    const payload = new FormData(form);
    payload.set('preset_key', submitter.value || submitter.dataset.presetKey || '');

    submitter.setAttribute('disabled', 'disabled');
    submitter.classList.add('is-loading');
    hideExamAlert();

    try {
      const response = await fetch(action, {
        method: 'POST',
        body: payload,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });
      const contentType = response.headers.get('content-type') || '';
      const responsePayload = contentType.includes('application/json') ? await response.json() : null;

      if (!response.ok || !responsePayload?.ok) {
        throw new Error(responsePayload?.message || 'ไม่สามารถใช้ preset ได้');
      }

      runtime.dataset.currentPreset = responsePayload.preset?.key || submitter.value || '';
      applyClinicalSummary(responsePayload.clinical);
      applyOrderSummary(responsePayload.summary);
      setButtonSelection(servicePresetButtons, submitter);

      if (responsePayload.message) {
        showExamAlert(responsePayload.message);
        window.setTimeout(hideExamAlert, 1600);
      }

      focusNextClinicalField();
    } catch (error) {
      showExamAlert(error instanceof Error ? error.message : 'ไม่สามารถใช้ preset ได้');
    } finally {
      submitter.removeAttribute('disabled');
      submitter.classList.remove('is-loading');
      syncVisualState();
    }

    return true;
  }

  function handleOrderSubmit(event) {
    const orderForm = event.target;
    if (event.defaultPrevented) {
      return;
    }

    if (!(orderForm instanceof HTMLFormElement) || !isOrderAction(orderForm.action || '')) {
      return;
    }

    event.preventDefault();
    submitOrderFormAjax(orderForm, event.submitter);
  }

  appendPresetButtons.forEach((button) => {
    button.addEventListener('click', () => {
      appendPreset(button.dataset.appendTarget || '', button.dataset.appendText || '', button);
    });
  });

  templateButtons.forEach((button) => {
    button.addEventListener('click', () => {
      applyTemplate(button.dataset.template || '', button);
    });
  });

  document.getElementById('smartExamUndo')?.addEventListener('click', restoreLast);
  document.getElementById('smartExamClear')?.addEventListener('click', clearExam);
  getField('temp_c')?.addEventListener('input', checkTempSuggestion);
  paymentDiscountInput?.addEventListener('input', syncVisualState);
  paymentPaidInput?.addEventListener('input', syncVisualState);
  serviceSearchInput?.addEventListener('input', () => {
    syncOrderFilters();
    syncVisualState();
  });
  itemSearchInput?.addEventListener('input', () => {
    syncOrderFilters();
    syncVisualState();
  });
  getField('smartItemSelect')?.addEventListener('change', syncVisualState);
  getField('smartItemQtyInput')?.addEventListener('input', syncVisualState);
  itemForm?.addEventListener('submit', validateSelectedItemStock);
  document.addEventListener('submit', handleOrderSubmit);
  document.addEventListener('keydown', handleGlobalShortcut);

  clinicalFieldIds.forEach((id) => {
    getField(id)?.addEventListener('input', () => {
      hideExamAlert();
      autoDxSuggest();
      syncVisualState();
    });
  });

  ['cc', 'pi', 'pe'].forEach((id) => {
    getField(id)?.addEventListener('keydown', handleTextareaShortcut);
  });

  [
    ...vitalFieldIds,
    'dx',
    'smartServiceSelect',
    'smartServiceQtyInput',
    'smartItemSelect',
    'smartItemQtyInput',
    'smartItemNoteInput',
    'smartPaymentDiscount',
    'smartPaymentPaid'
  ].forEach((id) => {
    getField(id)?.addEventListener('keydown', handleEnterProgression);
  });

  form.addEventListener('submit', (event) => {
    const submitter = event.submitter;
    if (submitter?.name === 'preset_key') {
      event.preventDefault();
      submitPresetAjax(submitter);
      return;
    }

    const finishMode = submitter?.name === 'finish_mode' ? submitter.value : '';

    if (!finishMode) {
      return;
    }

    const cc = fieldValue('cc');
    const dx = fieldValue('dx');
    const { hasBillable } = getLineCounts();
    const stock = selectedItemStockState();

    if (!cc || !dx) {
      event.preventDefault();
      showExamAlert('กรุณากรอก CC และ Dx ก่อนบันทึกและจบเคส เพื่อป้องกันการปิดเคสไม่ครบข้อมูล');

      const focusTarget = !cc ? getField('cc') : getField('dx');
      focusField(focusTarget);
      focusTarget?.classList.add('is-invalid');
      window.setTimeout(() => focusTarget?.classList.remove('is-invalid'), 1800);
      syncVisualState();
      return;
    }

    if (['payment', 'waiting_payment', 'receive_payment'].includes(finishMode) && !hasBillable) {
      event.preventDefault();
      showExamAlert('ยังไม่มีรายการคิดเงิน กรุณาเพิ่มบริการหรือยาอย่างน้อย 1 รายการ หรือใช้ปุ่มปิดเคสแบบไม่มีค่าใช้จ่าย');
      focusBillingEntry();
      syncVisualState();
      return;
    }

    if (!stock.isValid) {
      event.preventDefault();
      showExamAlert('Stock ไม่พอสำหรับรายการยาที่เลือก กรุณาลดจำนวนหรือเลือกรายการอื่น');
      focusField(getField('smartItemQtyInput'), { select: true });
      syncVisualState();
      return;
    }

    if (finishMode === 'receive_payment' && !getPaymentState().isValid) {
      event.preventDefault();
      showExamAlert('ยอดรับชำระน้อยกว่ายอดสุทธิ กรุณาตรวจสอบก่อนรับเงินและปิดเคส');
      focusField(getField('smartPaymentPaid'), { select: true });
      syncVisualState();
      return;
    }

    hideExamAlert();
  });

  autoDxSuggest();
  syncOrderFilters();
  syncVisualState();

  if (runtime?.dataset.currentPreset) {
    window.setTimeout(() => {
      focusNextClinicalField();
    }, 120);
  }
})();
