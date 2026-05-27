(() => {
  'use strict';

  const STORAGE_KEYS = {
    contacts: 'coari_por_elas_contacts_v2',
    history: 'coari_por_elas_history_v2'
  };

  const FALLBACK_LOCATION = {
    lat: -4.0853,
    lng: -63.1411
  };

  const state = {
    currentScreen: 'inicio',
    historyStack: [],
    emergencyTimers: [],
    lastLocation: { ...FALLBACK_LOCATION }
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  const screens = $$('.screen');
  const headerTitle = $('#headerTitle');
  const btnBack = $('#btnBack');
  const toast = $('#toast');
  const navButtons = $$('.bottom-nav [data-nav]');

  function nowTime() {
    return new Intl.DateTimeFormat('pt-BR', {
      hour: '2-digit',
      minute: '2-digit'
    }).format(new Date());
  }

  function nowDateTime() {
    return new Intl.DateTimeFormat('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(new Date());
  }

  function showToast(message, duration = 2200) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(showToast.timer);
    showToast.timer = setTimeout(() => toast.classList.remove('show'), duration);
  }

  function safeJsonParse(value, fallback) {
    try {
      return JSON.parse(value) ?? fallback;
    } catch (_) {
      return fallback;
    }
  }

  function getContacts() {
    const contacts = safeJsonParse(localStorage.getItem(STORAGE_KEYS.contacts), []);
    return Array.isArray(contacts) ? contacts.filter(Boolean) : [];
  }

  function setContacts(contacts) {
    localStorage.setItem(STORAGE_KEYS.contacts, JSON.stringify(contacts.filter(Boolean)));
    updateContactsStatus();
  }

  function getHistory() {
    const history = safeJsonParse(localStorage.getItem(STORAGE_KEYS.history), []);
    return Array.isArray(history) ? history : [];
  }

  function setHistory(history) {
    localStorage.setItem(STORAGE_KEYS.history, JSON.stringify(history.slice(0, 20)));
    renderHistory();
  }

  function addHistory(source = 'Botão de emergência') {
    const contacts = getContacts();
    const item = {
      id: globalThis.crypto?.randomUUID?.() || String(Date.now()),
      date: nowDateTime(),
      source,
      contacts: contacts.length,
      lat: state.lastLocation.lat,
      lng: state.lastLocation.lng,
      status: 'Simulado'
    };
    setHistory([item, ...getHistory()]);
  }

  function getScreenTitle(screenId) {
    const screen = $(`#screen-${screenId}`);
    return screen?.dataset?.title || 'Coari por Elas';
  }

  function setActiveNav(screenId) {
    navButtons.forEach(button => {
      const target = button.dataset.nav;
      const active = target === screenId || (screenId === 'contatos' && target === 'rede') || (screenId === 'orientacoes' && target === 'mais') || (screenId === 'localizacao' && target === 'mais');
      button.classList.toggle('active', active);
    });
  }

  function goTo(screenId, push = true) {
    const target = $(`#screen-${screenId}`);
    if (!target) return;

    if (push && state.currentScreen !== screenId) {
      state.historyStack.push(state.currentScreen);
    }

    screens.forEach(screen => screen.classList.remove('is-active'));
    target.classList.add('is-active');
    target.scrollTop = 0;

    state.currentScreen = screenId;
    headerTitle.textContent = getScreenTitle(screenId);
    setActiveNav(screenId);
    btnBack.style.visibility = screenId === 'inicio' ? 'hidden' : 'visible';
  }

  function clearEmergencyTimers() {
    state.emergencyTimers.forEach(timer => clearTimeout(timer));
    state.emergencyTimers = [];
  }

  function resetEmergencyRoute() {
    $$('.route-step').forEach((step, index) => {
      step.classList.toggle('done', index === 0);
      step.classList.remove('active');
      const time = $('time', step);
      if (time) time.textContent = index === 0 ? nowTime() : '--:--';
    });
  }

  function markEmergencyStep(index) {
    const step = $(`.route-step[data-step="${index}"]`);
    if (!step) return;
    step.classList.add('done');
    step.classList.remove('active');
    const time = $('time', step);
    if (time) time.textContent = nowTime();
  }

  function setRouteActive(index) {
    $$('.route-step').forEach(step => step.classList.remove('active'));
    const step = $(`.route-step[data-step="${index}"]`);
    step?.classList.add('active');
  }

  function updateLocation() {
    if (!('geolocation' in navigator)) {
      return Promise.resolve(state.lastLocation);
    }

    return new Promise(resolve => {
      navigator.geolocation.getCurrentPosition(
        position => {
          state.lastLocation = {
            lat: Number(position.coords.latitude.toFixed(6)),
            lng: Number(position.coords.longitude.toFixed(6))
          };
          resolve(state.lastLocation);
        },
        () => resolve(state.lastLocation),
        { enableHighAccuracy: true, timeout: 2500, maximumAge: 60000 }
      );
    });
  }

  function mapsUrl() {
    const { lat, lng } = state.lastLocation;
    return `https://maps.google.com/?q=${lat},${lng}`;
  }

  function startEmergency(source = 'Botão de emergência') {
    clearEmergencyTimers();
    resetEmergencyRoute();

    const contacts = getContacts();
    const contactsLabel = $('#contactsAlertLabel');
    if (contactsLabel) {
      contactsLabel.textContent = contacts.length > 0
        ? `${contacts.length} contato${contacts.length > 1 ? 's' : ''} avisado${contacts.length > 1 ? 's' : ''}`
        : 'Nenhum contato cadastrado';
    }

    goTo('acionamento');
    showToast('Acionamento demonstrativo iniciado. Nenhum SMS real será enviado.', 2800);

    updateLocation().then(() => {
      const sequence = [
        { index: 1, delay: 650, message: 'Localização preparada para compartilhamento.' },
        { index: 2, delay: 1400, message: contacts.length ? 'Rede de apoio notificada na simulação.' : 'Cadastre contatos para o fluxo real.' },
        { index: 3, delay: 2200, message: 'Ligação para 190 simulada.' }
      ];

      sequence.forEach(({ index, delay, message }) => {
        state.emergencyTimers.push(setTimeout(() => {
          setRouteActive(index);
          markEmergencyStep(index);
          showToast(message, 1600);
        }, delay));
      });

      state.emergencyTimers.push(setTimeout(() => {
        addHistory(source);
        showToast('Registro salvo no histórico local.', 1800);
      }, 2600));
    });
  }

  function formatPhone(value) {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 2) return digits;
    if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
  }

  function phoneIsValid(value) {
    const digits = value.replace(/\D/g, '');
    return digits.length >= 10 && digits.length <= 11;
  }

  function loadContactsIntoForm() {
    const contacts = getContacts();
    ['contact1', 'contact2', 'contact3'].forEach((id, index) => {
      const input = $(`#${id}`);
      if (input) input.value = contacts[index] || '';
    });
    updateContactsStatus();
  }

  function updateContactsStatus() {
    const contacts = getContacts();
    const status = $('#contactsStatus');
    if (!status) return;
    status.textContent = contacts.length
      ? `${contacts.length} contato${contacts.length > 1 ? 's' : ''} cadastrado${contacts.length > 1 ? 's' : ''}`
      : 'Nenhum contato cadastrado';
  }

  function renderHistory() {
    const list = $('#historyList');
    if (!list) return;

    const history = getHistory();
    if (!history.length) {
      list.innerHTML = `
        <article class="history-empty">
          <strong>Nenhum acionamento registrado</strong>
          <small>Os registros desta MVP ficam apenas no navegador.</small>
        </article>
      `;
      return;
    }

    list.innerHTML = history.map(item => `
      <article class="history-item">
        <div>
          <strong>${item.source}</strong>
          <small>${item.date} • ${item.contacts} contato${item.contacts === 1 ? '' : 's'} • ${item.status}</small>
        </div>
        <a href="https://maps.google.com/?q=${item.lat},${item.lng}" target="_blank" rel="noopener">Mapa</a>
      </article>
    `).join('');
  }

  function setupContactForm() {
    ['contact1', 'contact2', 'contact3'].forEach(id => {
      const input = $(`#${id}`);
      input?.addEventListener('input', () => {
        const cursor = input.selectionStart;
        input.value = formatPhone(input.value);
        input.setSelectionRange(input.value.length, input.value.length);
      });
    });

    $('#contactForm')?.addEventListener('submit', event => {
      event.preventDefault();
      const values = ['contact1', 'contact2', 'contact3']
        .map(id => $(`#${id}`)?.value.trim() || '')
        .filter(Boolean);

      const invalid = values.find(value => !phoneIsValid(value));
      if (invalid) {
        showToast('Revise os telefones. Use DDD + número.', 2400);
        return;
      }

      setContacts(values);
      showToast('Contatos salvos com segurança neste navegador.');
    });

    $('#btnClearContacts')?.addEventListener('click', () => {
      setContacts([]);
      loadContactsIntoForm();
      showToast('Contatos removidos da demonstração.');
    });
  }

  function wipeLocalData() {
    localStorage.removeItem(STORAGE_KEYS.contacts);
    localStorage.removeItem(STORAGE_KEYS.history);
    loadContactsIntoForm();
    renderHistory();
    showToast('Dados locais apagados.');
  }

  function copyText(text) {
    if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(text);

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
    return Promise.resolve();
  }

  function setupNavigation() {
    document.addEventListener('click', event => {
      const navTrigger = event.target.closest('[data-nav]');
      if (navTrigger) {
        goTo(navTrigger.dataset.nav);
        return;
      }

      const toastTrigger = event.target.closest('[data-toast]');
      if (toastTrigger) {
        showToast(toastTrigger.dataset.toast);
      }
    });

    btnBack?.addEventListener('click', () => {
      const previous = state.historyStack.pop();
      goTo(previous || 'inicio', false);
    });

    $('#btnMore')?.addEventListener('click', () => goTo('mais'));
  }

  function setupActions() {
    $('#btnEmergency')?.addEventListener('click', () => startEmergency('Botão de emergência'));
    $('#btnShakeDemo')?.addEventListener('click', () => startEmergency('Shake simulado'));

    $('#btnSafe')?.addEventListener('click', () => {
      clearEmergencyTimers();
      addHistory('Confirmação de segurança');
      showToast('Status registrado como em segurança.');
      goTo('inicio');
    });

    $('#btnCancelAction')?.addEventListener('click', () => {
      clearEmergencyTimers();
      showToast('Acionamento cancelado na demonstração.');
      goTo('inicio');
    });

    $('#btnOpenMap')?.addEventListener('click', () => {
      updateLocation().then(() => window.open(mapsUrl(), '_blank', 'noopener'));
    });

    $('#btnCopyMap')?.addEventListener('click', () => {
      updateLocation()
        .then(() => copyText(mapsUrl()))
        .then(() => showToast('Link de localização copiado.'))
        .catch(() => showToast('Não foi possível copiar o link.'));
    });

    $('#btnDemoHistory')?.addEventListener('click', () => {
      addHistory('Registro demonstrativo');
      showToast('Histórico demonstrativo adicionado.');
    });

    $('#btnClearHistory')?.addEventListener('click', () => {
      setHistory([]);
      showToast('Histórico limpo.');
    });

    $('#btnQuickExit')?.addEventListener('click', () => {
      $('#stealth')?.removeAttribute('hidden');
    });

    $('#btnReturn')?.addEventListener('click', () => {
      $('#stealth')?.setAttribute('hidden', '');
      goTo('inicio');
    });

    $('#btnWipeData')?.addEventListener('click', wipeLocalData);
  }

  function init() {
    btnBack.style.visibility = 'hidden';
    setupNavigation();
    setupContactForm();
    setupActions();
    loadContactsIntoForm();
    renderHistory();
    resetEmergencyRoute();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
