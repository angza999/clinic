(function () {
  const trigger = document.querySelector('[data-smart-card-trigger]');
  const state = document.querySelector('[data-smart-card-state]');
  const form = document.querySelector('.patient-intake-form');
  const searchInput = document.querySelector('.patient-command-search input[name="keyword"]');

  if (!form) {
    return;
  }

  const birthDateField = form.querySelector('[name="birth_date"]');
  const ageField = form.querySelector('[name="calculated_age"]');
  const cardPhotoWrap = form.querySelector('[data-card-photo-wrap]');
  const cardPhotoPreview = form.querySelector('[data-card-photo-preview]');
  const cardPhotoStatus = form.querySelector('[data-card-photo-status]');
  const cardPhotoField = form.querySelector('[name="card_photo"]');
  const duplicateAlert = document.querySelector('[data-patient-duplicate-alert]');
  const duplicateText = document.querySelector('[data-patient-duplicate-text]');
  const duplicateLink = document.querySelector('[data-patient-duplicate-link]');

  const parseBirthDateInput = (value) => {
    const rawValue = String(value || '').trim();
    if (!rawValue) {
      return null;
    }

    let day = 0;
    let month = 0;
    let year = 0;
    const normalized = rawValue.replace(/\s+/g, '');
    const dateParts = normalized.match(/^(\d{1,4})[\/.-](\d{1,2})[\/.-](\d{1,4})$/);

    if (dateParts) {
      const first = Number(dateParts[1]);
      const second = Number(dateParts[2]);
      const third = Number(dateParts[3]);

      if (dateParts[1].length === 4) {
        year = first;
        month = second;
        day = third;
      } else {
        day = first;
        month = second;
        year = third;
      }
    } else {
      const digits = normalized.replace(/\D/g, '');
      if (digits.length !== 8) {
        return null;
      }

      const firstFour = Number(digits.slice(0, 4));
      if (firstFour >= 1900) {
        year = firstFour;
        month = Number(digits.slice(4, 6));
        day = Number(digits.slice(6, 8));
      } else {
        day = Number(digits.slice(0, 2));
        month = Number(digits.slice(2, 4));
        year = Number(digits.slice(4, 8));
      }
    }

    if (year > 2400) {
      year -= 543;
    }

    const date = new Date(year, month - 1, day);
    if (
      date.getFullYear() !== year ||
      date.getMonth() !== month - 1 ||
      date.getDate() !== day
    ) {
      return null;
    }

    return { date, day, month, year };
  };

  const formatThaiBirthDate = (parsed) => {
    if (!parsed) {
      return '';
    }

    return [
      String(parsed.day).padStart(2, '0'),
      String(parsed.month).padStart(2, '0'),
      String(parsed.year + 543),
    ].join('/');
  };

  const updateCalculatedAge = () => {
    if (!birthDateField || !ageField) {
      return;
    }

    const rawValue = birthDateField.value;
    if (!rawValue) {
      ageField.value = '';
      return;
    }

    const parsedBirthDate = parseBirthDateInput(rawValue);
    const birthDate = parsedBirthDate ? parsedBirthDate.date : null;
    const today = new Date();
    if (!birthDate || birthDate > today) {
      ageField.value = '';
      return;
    }

    let years = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    const dayDiff = today.getDate() - birthDate.getDate();
    if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
      years -= 1;
    }

    ageField.value = `${years} ปี`;
  };

  if (birthDateField) {
    birthDateField.addEventListener('input', updateCalculatedAge);
    birthDateField.addEventListener('change', updateCalculatedAge);
    birthDateField.addEventListener('blur', () => {
      const parsedBirthDate = parseBirthDateInput(birthDateField.value);
      if (parsedBirthDate) {
        birthDateField.value = formatThaiBirthDate(parsedBirthDate);
      }
      updateCalculatedAge();
    });
    updateCalculatedAge();
  }

  if (!trigger || !state) {
    return;
  }

  const setState = (mode, title, message) => {
    state.classList.remove('is-loading', 'is-success', 'is-error', 'is-warning');
    if (mode) {
      state.classList.add(`is-${mode}`);
    }
    state.innerHTML = `<strong>${escapeHtml(title)}</strong><span>${escapeHtml(message)}</span>`;
  };

  const setField = (name, value) => {
    const field = form.querySelector(`[name="${name}"]`);
    if (!field || value === undefined || value === null || String(value).trim() === '') {
      return;
    }
    field.value = name === 'birth_date'
      ? formatThaiBirthDate(parseBirthDateInput(value)) || String(value).trim()
      : String(value).trim();
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const openExtraPanel = () => {
    const details = form.querySelector('.patient-extra-info');
    if (details) {
      details.open = true;
    }
  };

  const setCardPhoto = (photo) => {
    const value = typeof photo === 'string' ? photo.trim() : '';
    if (!cardPhotoWrap || !cardPhotoPreview || !cardPhotoField) {
      return;
    }

    if (!value || !value.startsWith('data:image/')) {
      cardPhotoWrap.classList.remove('has-photo');
      cardPhotoPreview.removeAttribute('src');
      cardPhotoField.value = '';
      if (cardPhotoStatus) {
        cardPhotoStatus.textContent = 'ยังไม่ได้รับรูปจากบัตร ระบบจะเติมข้อมูลตัวอักษรได้ตามปกติ';
      }
      return;
    }

    cardPhotoPreview.src = value;
    cardPhotoField.value = value;
    cardPhotoWrap.classList.add('has-photo');
    if (cardPhotoStatus) {
      cardPhotoStatus.textContent = 'ดึงรูปจากบัตรแล้ว ตรวจสอบตัวตนก่อนเปิดตรวจ';
    }
  };

  const fillForm = (card) => {
    setField('citizen_id', card.citizen_id);
    setField('title_name', card.title_name);
    setField('first_name', card.first_name);
    setField('last_name', card.last_name);
    setField('gender', card.gender);
    setField('birth_date', card.birth_date);
    setField('address', card.address);
    setCardPhoto(card.photo);

    if (card.citizen_id || card.address || card.photo) {
      openExtraPanel();
    }
  };

  const friendlyErrorMessage = (payload) => {
    const message = payload && payload.message ? String(payload.message) : '';
    const attempts = Array.isArray(payload && payload.attempts) ? payload.attempts : [];
    const bridgeAttempt = attempts.find((attempt) => String(attempt.endpoint || '').includes('127.0.0.1:8189'));
    const sawBridge = Boolean(bridgeAttempt && bridgeAttempt.ok);

    if (message.includes('หลังจากเริ่มอ่าน') || message.includes('ข้อมูลบัตรใหม่')) {
      return 'ยังไม่พบบัตรที่เสียบใหม่ กรุณากดอ่านบัตร แล้วถอดบัตรเสียบใหม่ตอนที่ระบบกำลังรออ่าน';
    }

    if (sawBridge) {
      return 'โปรแกรมอ่านบัตรเปิดอยู่ แต่ยังไม่ได้รับข้อมูลจากบัตร กรุณาถอดบัตรเสียบใหม่ แล้วลองอ่านอีกครั้ง';
    }

    if (message.includes('HTTP API') || message.includes('MQTT') || message.includes('service')) {
      return 'ยังไม่ได้เปิดโปรแกรมช่วยอ่านบัตร กรุณาเปิด Dongmahawan Smart Card Bridge แล้วลองใหม่';
    }

    return message || 'อ่านบัตรไม่สำเร็จ กรุณาตรวจสอบเครื่องอ่านบัตร แล้วลองใหม่อีกครั้ง';
  };

  trigger.addEventListener('click', async () => {
    const endpoint = trigger.dataset.smartCardUrl;
    if (!endpoint) {
      setState('error', 'ยังอ่านบัตรไม่ได้', 'ระบบยังไม่ได้ตั้งค่าปุ่มอ่านบัตร กรุณาติดต่อผู้ดูแลระบบ');
      return;
    }

    trigger.disabled = true;
    const startedAt = new Date().toISOString();
    setState('loading', 'กำลังรออ่านบัตร', 'กรุณาเสียบบัตรประชาชนตอนนี้ ระบบจะรอข้อมูลบัตรใหม่ประมาณ 10 วินาที');

    try {
      const url = new URL(endpoint, window.location.href);
      url.searchParams.set('started_at', startedAt);

      const response = await fetch(url.toString(), {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      const payload = await response.json();

      if (!response.ok || !payload.success) {
        setState('error', 'ยังอ่านบัตรไม่ได้', friendlyErrorMessage(payload));
        return;
      }

      fillForm(payload.card || {});

      if (payload.existing_patient) {
        const patient = payload.existing_patient;
        const patientName = `${patient.hn || ''} ${patient.first_name || ''} ${patient.last_name || ''}`.trim();
        if (searchInput && payload.card && payload.card.citizen_id) {
          searchInput.value = payload.card.citizen_id;
        }
        if (duplicateAlert) {
          duplicateAlert.hidden = false;
          if (duplicateText) {
            duplicateText.textContent = `${patientName || 'ผู้รับบริการเดิม'} กรุณาตรวจสอบก่อนสร้างแฟ้มใหม่`;
          }
          if (duplicateLink && patient.id) {
            duplicateLink.href = `index.php?page=patient-show&id=${encodeURIComponent(patient.id)}`;
          }
        }
        setState('warning', 'พบแฟ้มผู้รับบริการเดิม', `${patientName || 'ผู้รับบริการเดิม'} กรุณาตรวจสอบก่อนสร้างแฟ้มใหม่`);
        return;
      }

      if (duplicateAlert) {
        duplicateAlert.hidden = true;
      }
      setState('success', 'อ่านบัตรสำเร็จ', 'เติมข้อมูลให้แล้ว กรุณาตรวจสอบชื่อและเลขบัตรก่อนเปิดตรวจ');
    } catch (error) {
      setState('error', 'ยังอ่านบัตรไม่ได้', 'ติดต่อโปรแกรมช่วยอ่านบัตรไม่ได้ กรุณาเปิด Dongmahawan Smart Card Bridge แล้วลองใหม่');
    } finally {
      trigger.disabled = false;
    }
  });

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
})();
