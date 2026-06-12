(() => {
  const dataNode = document.getElementById('queuePatientData');
  const form = document.getElementById('queueCreateForm');
  const searchInput = document.querySelector('[data-queue-patient-search]');
  const patientIdInput = document.querySelector('[data-queue-patient-id]');
  const resultsBox = document.querySelector('[data-queue-patient-results]');
  const selectedBox = document.querySelector('[data-queue-patient-selected]');
  const continuationCard = document.querySelector('[data-queue-continuation]');
  const quickRegisterForm = document.getElementById('queueQuickRegisterForm');
  const quickNameInput = document.querySelector('[data-quick-name]');
  const quickPhoneInput = document.querySelector('[data-quick-phone]');
  const quickDuplicateWarning = document.querySelector('[data-quick-duplicate-warning]');

  if (!dataNode || !form || !searchInput || !patientIdInput || !resultsBox || !selectedBox) {
    return;
  }

  let patients = [];
  try {
    patients = JSON.parse(dataNode.textContent || '[]');
  } catch (error) {
    patients = [];
  }

  const normalize = (value) => String(value || '').trim().toLowerCase();
  const labelFor = (patient) => `${patient.hn} - ${patient.name}${patient.phone ? ' / ' + patient.phone : ''}`;
  let currentMatches = [];

  function findNextCaseButton() {
    const closeAndNextButton = document.querySelector('[data-queue-close-next]');
    if (closeAndNextButton) {
      return closeAndNextButton;
    }

    const continuationButton = document.getElementById('queueContinueNextCase');
    if (continuationButton) {
      return continuationButton;
    }

    const redirectInput = document.querySelector('form input[name="redirect_to_visit"][value="1"]');
    return redirectInput?.closest('form')?.querySelector('button[type="submit"], button:not([type])') || null;
  }

  function setupQueueShortcuts() {
    document.addEventListener('keydown', (event) => {
      const key = event.key;
      const lowerKey = key.toLowerCase();

      if (!event.ctrlKey && !event.metaKey && !event.altKey && !event.shiftKey) {
        if (key === 'F1') {
          event.preventDefault();
          searchInput.focus();
          searchInput.select();
          return;
        }

        if (key === 'F2') {
          const newPatientLink = document.querySelector('[data-queue-new-patient]');
          const quickRegister = document.getElementById('snqQuickRegister');
          event.preventDefault();
          if (quickRegister) {
            quickRegister.open = true;
            document.getElementById('quickFullName')?.focus();
            return;
          }
          newPatientLink?.click();
          return;
        }

        if (key === 'F3') {
          const primaryAction = document.querySelector('[data-queue-primary-action], #queueCommandNextCase, #queueContinueNextCase');
          if (primaryAction) {
            event.preventDefault();
            primaryAction.click();
          }
          return;
        }

        if (key === 'F4') {
          const serviceAction = document.querySelector('[data-queue-primary-action][data-queue-primary-status="IN_SERVICE"]');
          if (serviceAction) {
            event.preventDefault();
            serviceAction.click();
          }
          return;
        }

        if (key === 'F5') {
          const nextButton = document.querySelector('[data-queue-close-next], [data-queue-call-next], [data-queue-start-next], #queueContinueNextCase');
          if (nextButton) {
            event.preventDefault();
            nextButton.click();
          }
          return;
        }

        if (key === 'F9') {
          const paymentAction = document.querySelector('[data-queue-primary-action][data-queue-primary-status="WAITING_PAYMENT"], [data-queue-payment-action]');
          if (paymentAction) {
            event.preventDefault();
            paymentAction.click();
          }
          return;
        }

        if (key === 'F6') {
          const labelAction = document.querySelector('[data-queue-label-action]');
          if (labelAction) {
            event.preventDefault();
            labelAction.click();
          }
          return;
        }
      }

      if (!event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
        return;
      }

      if (lowerKey === 's') {
        event.preventDefault();
        searchInput.focus();
        searchInput.select();
      }

      if (lowerKey === 'n') {
        const nextButton = findNextCaseButton();
        if (!nextButton) {
          return;
        }

        event.preventDefault();
        nextButton.click();
      }
    });
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderSelected(patient) {
    selectedBox.classList.remove('d-none');
    selectedBox.innerHTML = `
      <span>เลือกแล้ว</span>
      <strong>${escapeHtml(patient.name)}</strong>
      <small>${escapeHtml(patient.hn)}${patient.phone ? ' / ' + escapeHtml(patient.phone) : ''}</small>
    `;
  }

  function selectPatient(patient) {
    patientIdInput.value = patient.id;
    searchInput.value = labelFor(patient);
    renderSelected(patient);
    resultsBox.innerHTML = '';
    resultsBox.classList.remove('is-open');
  }

  function renderResults(matches, emptyMessage = 'ไม่พบคนไข้ที่ตรงกับคำค้น') {
    currentMatches = matches;

    if (matches.length === 0) {
      resultsBox.innerHTML = `<div class="queue-patient-empty">${escapeHtml(emptyMessage)}</div>`;
      resultsBox.classList.add('is-open');
      return;
    }

    resultsBox.innerHTML = matches.slice(0, 6).map((patient, index) => `
      <button type="button" class="queue-patient-option" data-patient-index="${index}">
        <span>
          <strong>${escapeHtml(patient.name || '-')}</strong>
          <small>${escapeHtml(patient.hn)}${patient.phone ? ' / ' + escapeHtml(patient.phone) : ''}</small>
        </span>
        <em>เลือก</em>
      </button>
    `).join('');
    resultsBox.classList.add('is-open');
  }

  function findMatches(query) {
    const keyword = normalize(query);

    if (!keyword) {
      return patients.slice(0, 5);
    }

    return patients.filter((patient) => {
      const haystack = normalize(`${patient.hn} ${patient.name} ${patient.phone}`);
      return haystack.includes(keyword);
    });
  }

  function refreshResults() {
    patientIdInput.value = '';
    selectedBox.classList.add('d-none');
    renderResults(findMatches(searchInput.value), searchInput.value ? 'ไม่พบคนไข้ที่ตรงกับคำค้น' : 'ยังไม่มีข้อมูลคนไข้');
  }

  function quickDuplicateMatches() {
    if (!quickRegisterForm || !quickDuplicateWarning) {
      return [];
    }

    const name = normalize(quickNameInput?.value || '');
    const phone = normalize(quickPhoneInput?.value || '');
    if (!name && !phone) {
      return [];
    }

    return patients.filter((patient) => {
      const patientName = normalize(patient.name);
      const patientPhone = normalize(patient.phone);
      return (phone && patientPhone && patientPhone === phone) || (name && patientName && patientName.includes(name));
    }).slice(0, 3);
  }

  function renderQuickDuplicateWarning() {
    if (!quickDuplicateWarning) {
      return;
    }

    const matches = quickDuplicateMatches();
    if (matches.length === 0) {
      quickDuplicateWarning.classList.add('d-none');
      quickDuplicateWarning.innerHTML = '';
      return;
    }

    quickDuplicateWarning.classList.remove('d-none');
    quickDuplicateWarning.innerHTML = `
      <strong>อาจเป็นคนไข้เดิม</strong>
      <span>${matches.map((patient) => escapeHtml(labelFor(patient))).join('<br>')}</span>
      <small>ถ้าเป็นคนไข้เดิม ให้ใช้ช่องค้นหาด้านบนแทนการลงทะเบียนใหม่</small>
    `;
  }

  searchInput.addEventListener('focus', () => {
    if (!patientIdInput.value) {
      renderResults(findMatches(searchInput.value), patients.length ? 'เลือกรายชื่อเพื่อรับเคส' : 'ยังไม่มีข้อมูลคนไข้');
    }
  });

  searchInput.addEventListener('input', refreshResults);
  quickNameInput?.addEventListener('input', renderQuickDuplicateWarning);
  quickPhoneInput?.addEventListener('input', renderQuickDuplicateWarning);
  renderQuickDuplicateWarning();

  if (continuationCard) {
    continuationCard.scrollIntoView({ block: 'nearest' });
  }

  setupQueueShortcuts();
  document.querySelectorAll('[data-queue-close-next-form], form[action*="queue-status"]').forEach((queueForm) => {
    queueForm.addEventListener('submit', () => {
      const submitButton = queueForm.querySelector('button[type="submit"], button:not([type])');
      if (!submitButton || submitButton.disabled) {
        return;
      }

      submitButton.disabled = true;
      submitButton.classList.add('is-loading');
    });
  });

  resultsBox.addEventListener('click', (event) => {
    const button = event.target.closest('[data-patient-index]');
    if (!button) {
      return;
    }

    const patient = currentMatches[Number(button.dataset.patientIndex)];
    if (patient) {
      selectPatient(patient);
    }
  });

  form.addEventListener('submit', (event) => {
    if (patientIdInput.value) {
      return;
    }

    const matches = findMatches(searchInput.value);
    if (matches.length === 1) {
      selectPatient(matches[0]);
      return;
    }

    event.preventDefault();
    renderResults(matches, 'ไม่พบคนไข้ที่ตรงกับคำค้น');
    searchInput.focus();
    searchInput.classList.add('is-invalid');
    window.setTimeout(() => searchInput.classList.remove('is-invalid'), 1800);
  });
})();
