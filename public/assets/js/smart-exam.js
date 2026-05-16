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
  const stepPreset = document.getElementById('smartExamStepPreset');
  const stepClinical = document.getElementById('smartExamStepClinical');
  const stepFinish = document.getElementById('smartExamStepFinish');

  const serviceForm = document.querySelector('form.smart-add-line-form:not(.smart-add-line-form-items)');
  const itemForm = document.querySelector('form.smart-add-line-form-items');

  const servicePresetButtons = Array.from(document.querySelectorAll('.smart-service-card[data-preset-key]'));
  const appendPresetButtons = Array.from(document.querySelectorAll('[data-append-target]'));
  const templateButtons = Array.from(document.querySelectorAll('[data-template]'));

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

  function appendPreset(targetId, text, button = null) {
    const field = getField(targetId);
    if (!field || text === '') {
      return;
    }

    snapshot();

    if (field.value.trim() === '') {
      field.value = text;
    } else if (!field.value.includes(text)) {
      field.value += ', ' + text;
    }

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
    getField('cc').value = template.cc;
    getField('pi').value = template.pi;
    getField('pe').value = template.pe;
    getField('dx').value = template.dx;

    setButtonSelection(templateButtons, button);
    autoDxSuggest();
    syncVisualState();
    focusField(getField('dx'));
  }

  function autoDxSuggest() {
    const cc = fieldValue('cc');
    const pi = fieldValue('pi');

    let suggestion = '';
    if (cc.includes('ไข้') && (cc.includes('ไอ') || pi.includes('น้ำมูก'))) {
      suggestion = 'แนะนำ Dx: URI';
    } else if (cc.includes('ปวดท้อง')) {
      suggestion = 'แนะนำ Dx: Gastritis';
    } else if (cc.includes('มีแผล')) {
      suggestion = 'แนะนำ Dx: Wound';
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

    if (temp >= 37.5 && !fieldValue('cc').includes('ไข้')) {
      appendPreset('cc', 'ไข้');
      showSuggestion('แนะนำ: มีไข้ ควรพิจารณา URI หรือ Flu ตามอาการร่วม');
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

    if (finishMode === 'receive_payment' && !getPaymentState().isValid) {
      event.preventDefault();
      showExamAlert('ยอดรับชำระน้อยกว่ายอดสุทธิ กรุณาตรวจสอบก่อนรับเงินและปิดเคส');
      focusField(getField('smartPaymentPaid'), { select: true });
      syncVisualState();
      return;
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

  function updateStepState(clinicalReady, paymentReady) {
    const currentPreset = runtime?.dataset.currentPreset || '';
    const hasClinicalInput = clinicalFieldIds.some((id) => fieldValue(id) !== '');

    if (currentPreset || hasClinicalInput) {
      setStepState(stepPreset, 'complete');
    } else {
      setStepState(stepPreset, 'active');
    }

    if (paymentReady) {
      setStepState(stepClinical, 'complete');
      setStepState(stepFinish, 'active');
      return;
    }

    if (clinicalReady) {
      setStepState(stepClinical, 'complete');
      setStepState(stepFinish, 'active');
      return;
    }

    if (currentPreset || hasClinicalInput) {
      setStepState(stepClinical, 'active');
      setStepState(stepFinish, 'idle');
      return;
    }

    setStepState(stepClinical, 'idle');
    setStepState(stepFinish, 'idle');
  }

  function syncFinishState() {
    const ccReady = fieldValue('cc') !== '';
    const dxReady = fieldValue('dx') !== '';
    const clinicalReady = ccReady && dxReady;
    const { hasBillable } = getLineCounts();
    const paymentReady = clinicalReady && hasBillable;
    const payment = getPaymentState();

    syncPaymentPreview();

    setReadinessItem(readinessCc, ccReady, 'พร้อม', 'ยังไม่กรอก');
    setReadinessItem(readinessDx, dxReady, 'พร้อม', 'ยังไม่กรอก');
    setReadinessItem(readinessBilling, hasBillable, 'พร้อม', 'ยังไม่มี');

    if (finishPaymentButton) {
      finishPaymentButton.disabled = !paymentReady || !payment.isValid;
    }

    if (finishWaitPaymentButton) {
      finishWaitPaymentButton.disabled = !paymentReady;
    }

    if (finishNoChargeButton) {
      finishNoChargeButton.disabled = !clinicalReady;
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
    const finishMode = submitter?.name === 'finish_mode' ? submitter.value : '';

    if (!finishMode) {
      return;
    }

    const cc = fieldValue('cc');
    const dx = fieldValue('dx');
    const { hasBillable } = getLineCounts();

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

    hideExamAlert();
  });

  autoDxSuggest();
  syncVisualState();

  if (runtime?.dataset.currentPreset) {
    window.setTimeout(() => {
      focusNextClinicalField();
    }, 120);
  }
})();
