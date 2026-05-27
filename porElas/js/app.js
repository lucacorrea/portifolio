(function () {
  'use strict';

  const STORAGE_KEYS = {
    contacts: 'coariPorElas.contacts.v1',
    history: 'coariPorElas.history.v1',
    settings: 'coariPorElas.settings.v1'
  };

  const demoLocation = {
    lat: -4.0853,
    lng: -63.1411
  };

  const els = {
    screens: document.querySelectorAll('.app-screen'),
    navButtons: document.querySelectorAll('[data-nav]'),
    bottomNavItems: document.querySelectorAll('.bottom-nav .nav-item'),
    toast: document.getElementById('toast'),
    contactsForm: document.getElementById('contactsForm'),
    contactInputs: [
      document.getElementById('contact1'),
      document.getElementById('contact2'),
      document.getElementById('contact3')
    ],
    contactsSummary: document.getElementById('contactsSummary'),
    btnClearContacts: document.getElementById('btnClearContacts'),
    btnPanic: document.getElementById('btnPanic'),
    btnShakeDemo: document.getElementById('btnShakeDemo'),
    panicModal: document.getElementById('panicModal'),
    btnCloseModal: document.getElementById('btnCloseModal'),
    modalText: document.getElementById('panicModalText'),
    modalProgress: document.getElementById('modalProgress'),
    stepList: document.getElementById('stepList'),
    historyList: document.getElementById('historyList'),
    btnAddDemoHistory: document.getElementById('btnAddDemoHistory'),
    btnClearHistory: document.getElementById('btnClearHistory'),
    btnCopyLocation: document.getElementById('btnCopyLocation'),
    btnQuickExit: document.getElementById('btnQuickExit'),
    stealthScreen: document.getElementById('stealthScreen'),
    btnReturnApp: document.getElementById('btnReturnApp'),
    btnWipeData: document.getElementById('btnWipeData'),
    demoCallButtons: document.querySelectorAll('[data-demo-call]'),
    toggleDiscreet: document.getElementById('toggleDiscreet'),
    toggleShake: document.getElementById('toggleShake')
  };

  const getMapUrl = () => `https://maps.google.com/?q=${demoLocation.lat},${demoLocation.lng}`;
  const getMessage = () => `SOCORRO! App Coari por Elas. Estou em perigo! Minha localização: ${getMapUrl()}`;

  let toastTimeout = null;
  let modalTimer = null;

  function safeParse(raw, fallback) {
    try {
      return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
      return fallback;
    }
  }

  function loadContacts() {
    return safeParse(localStorage.getItem(STORAGE_KEYS.contacts), ['', '', '']);
  }

  function saveContacts(contacts) {
    localStorage.setItem(STORAGE_KEYS.contacts, JSON.stringify(contacts));
  }

  function loadHistory() {
    return safeParse(localStorage.getItem(STORAGE_KEYS.history), []);
  }

  function saveHistory(history) {
    localStorage.setItem(STORAGE_KEYS.history, JSON.stringify(history));
  }

  function normalizePhone(value) {
    return String(value || '').replace(/\D/g, '').slice(0, 11);
  }

  function formatPhone(value) {
    const digits = normalizePhone(value);
    if (digits.length <= 2) return digits;
    if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
  }

  function isValidPhone(value) {
    const digits = normalizePhone(value);
    return digits.length === 10 || digits.length === 11;
  }

  function showToast(message) {
    window.clearTimeout(toastTimeout);
    els.toast.textContent = message;
    els.toast.classList.add('is-visible');
    toastTimeout = window.setTimeout(() => {
      els.toast.classList.remove('is-visible');
    }, 1800);
  }

  function goTo(screenName) {
    const target = document.getElementById(`screen-${screenName}`);
    if (!target) return;

    els.screens.forEach((screen) => screen.classList.toggle('is-active', screen === target));
    els.bottomNavItems.forEach((item) => item.classList.toggle('is-active', item.dataset.nav === screenName));
  }

  function hydrateContacts() {
    const contacts = loadContacts();
    els.contactInputs.forEach((input, index) => {
      input.value = contacts[index] ? formatPhone(contacts[index]) : '';
    });
    updateContactsSummary();
  }

  function getValidContacts() {
    return loadContacts().map(normalizePhone).filter(Boolean).filter(isValidPhone);
  }

  function updateContactsSummary() {
    const total = getValidContacts().length;
    if (!total) {
      els.contactsSummary.textContent = 'Nenhum contato cadastrado';
      return;
    }
    els.contactsSummary.textContent = `${total} contato${total > 1 ? 's' : ''} pronto${total > 1 ? 's' : ''} para simulação`;
  }

  function renderHistory() {
    const history = loadHistory();
    els.historyList.innerHTML = '';

    if (!history.length) {
      const empty = document.createElement('div');
      empty.className = 'timeline-empty';
      empty.textContent = 'Nenhum alerta registrado ainda.';
      els.historyList.appendChild(empty);
      return;
    }

    history
      .slice()
      .reverse()
      .forEach((item) => {
        const row = document.createElement('article');
        row.className = 'timeline-item';
        row.innerHTML = `
          <strong>${item.title}</strong>
          <small>${item.when}</small>
          <small>${item.details}</small>
        `;
        els.historyList.appendChild(row);
      });
  }

  function addHistory(details) {
    const now = new Date();
    const history = loadHistory();
    history.push({
      id: crypto.randomUUID ? crypto.randomUUID() : String(Date.now()),
      title: 'Alerta simulado registrado',
      when: now.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }),
      details: details || `${getValidContacts().length} contato(s) + localização ${demoLocation.lat}, ${demoLocation.lng}`
    });
    saveHistory(history.slice(-20));
    renderHistory();
  }

  function resetModal() {
    window.clearTimeout(modalTimer);
    els.modalProgress.style.width = '0%';
    els.stepList.querySelectorAll('li').forEach((li) => li.classList.remove('done'));
    els.modalText.textContent = 'Validando contatos e localização...';
  }

  function openModal() {
    resetModal();
    els.panicModal.hidden = false;
  }

  function closeModal() {
    resetModal();
    els.panicModal.hidden = true;
  }

  function simulatePanicFlow(source) {
    const contacts = getValidContacts();

    if (!contacts.length) {
      showToast('Cadastre pelo menos 1 contato para simular o alerta.');
      goTo('contatos');
      return;
    }

    openModal();

    const steps = [
      'GPS validado com coordenadas de Coari.',
      'Mensagem montada com link do Google Maps.',
      'SMS para Patrulha Maria da Penha simulado.',
      `SMS para ${contacts.length} contato(s) simulado.`,
      source === 'shake' ? 'Histórico registrado via shake simulado.' : 'Histórico registrado pelo botão de apoio.'
    ];

    let index = 0;

    function advance() {
      const li = els.stepList.querySelector(`[data-step="${index}"]`);
      if (li) li.classList.add('done');
      els.modalText.textContent = steps[index];
      els.modalProgress.style.width = `${((index + 1) / steps.length) * 100}%`;

      index += 1;
      if (index < steps.length) {
        modalTimer = window.setTimeout(advance, 720);
      } else {
        addHistory(source === 'shake' ? 'Acionamento por shake simulado.' : 'Acionamento pelo botão principal simulado.');
        showToast('Fluxo de apoio simulado com sucesso.');
      }
    }

    modalTimer = window.setTimeout(advance, 420);
  }

  function bindEvents() {
    els.navButtons.forEach((button) => {
      button.addEventListener('click', () => goTo(button.dataset.nav));
    });

    els.contactInputs.forEach((input) => {
      input.addEventListener('input', () => {
        input.value = formatPhone(input.value);
      });
    });

    els.contactsForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const values = els.contactInputs.map((input) => normalizePhone(input.value));
      const filled = values.filter(Boolean);
      const invalid = filled.filter((phone) => !isValidPhone(phone));

      if (invalid.length) {
        showToast('Revise os números. Use DDD + telefone.');
        return;
      }

      saveContacts(values);
      updateContactsSummary();
      showToast('Contatos salvos no navegador.');
    });

    els.btnClearContacts.addEventListener('click', () => {
      saveContacts(['', '', '']);
      hydrateContacts();
      showToast('Contatos removidos.');
    });

    els.btnPanic.addEventListener('click', () => simulatePanicFlow('button'));
    els.btnShakeDemo.addEventListener('click', () => {
      if (!els.toggleShake.checked) {
        showToast('Shake está desativado nas configurações.');
        return;
      }
      simulatePanicFlow('shake');
    });

    els.btnCloseModal.addEventListener('click', closeModal);
    els.panicModal.addEventListener('click', (event) => {
      if (event.target === els.panicModal) closeModal();
    });

    els.btnAddDemoHistory.addEventListener('click', () => {
      addHistory('Registro manual de demonstração.');
      showToast('Histórico demo adicionado.');
    });

    els.btnClearHistory.addEventListener('click', () => {
      saveHistory([]);
      renderHistory();
      showToast('Histórico limpo.');
    });

    els.btnCopyLocation.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(getMapUrl());
        showToast('Link copiado.');
      } catch (error) {
        showToast(getMapUrl());
      }
    });

    els.demoCallButtons.forEach((button) => {
      button.addEventListener('click', () => {
        showToast(`Demonstração: chamada para ${button.dataset.demoCall}.`);
      });
    });

    els.btnQuickExit.addEventListener('click', () => {
      els.stealthScreen.hidden = false;
    });

    els.btnReturnApp.addEventListener('click', () => {
      els.stealthScreen.hidden = true;
    });

    els.btnWipeData.addEventListener('click', () => {
      localStorage.removeItem(STORAGE_KEYS.contacts);
      localStorage.removeItem(STORAGE_KEYS.history);
      localStorage.removeItem(STORAGE_KEYS.settings);
      hydrateContacts();
      renderHistory();
      showToast('Dados locais apagados.');
    });
  }

  function init() {
    hydrateContacts();
    renderHistory();
    bindEvents();
    document.getElementById('messagePreview').textContent = getMessage();
  }

  init();
})();
