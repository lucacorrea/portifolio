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

  const SUPPORT_RADIUS_METERS = 5000;

  const CATEGORY_LABELS = {
    all: 'Todos',
    health: 'Saúde',
    social: 'Assistência social',
    safety: 'Segurança',
    agency: 'Agências',
    support: 'Apoio'
  };

  const CATEGORY_ICONS = {
    health: 'ri-hospital-line',
    social: 'ri-hand-heart-line',
    safety: 'ri-shield-user-line',
    agency: 'ri-building-4-line',
    support: 'ri-map-pin-line'
  };

  const DEMO_SUPPORT_POINTS = [
    {
      id: 'demo-health-1',
      name: 'Ponto de saúde — demonstração',
      category: 'health',
      lat: -4.0842,
      lng: -63.1391,
      address: 'Referência visual próxima ao centro de Coari',
      source: 'Base demonstrativa'
    },
    {
      id: 'demo-social-1',
      name: 'CRAS / CREAS — demonstração',
      category: 'social',
      lat: -4.0884,
      lng: -63.1431,
      address: 'Referência visual para assistência social',
      source: 'Base demonstrativa'
    },
    {
      id: 'demo-safety-1',
      name: 'Segurança pública — demonstração',
      category: 'safety',
      lat: -4.0819,
      lng: -63.1452,
      address: 'Referência visual para delegacia/patrulha',
      source: 'Base demonstrativa'
    },
    {
      id: 'demo-agency-1',
      name: 'Órgão ou agência — demonstração',
      category: 'agency',
      lat: -4.0908,
      lng: -63.1384,
      address: 'Referência visual para órgãos e agências próximas',
      source: 'Base demonstrativa'
    }
  ];

  const state = {
    currentScreen: 'inicio',
    historyStack: [],
    emergencyTimers: [],
    lastLocation: { ...FALLBACK_LOCATION },
    supportMap: null,
    userMarker: null,
    supportMarkers: [],
    places: [],
    selectedPlaceId: null,
    placeFilter: 'all',
    placesLoaded: false,
    isLoadingPlaces: false
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

  function escapeHtml(value = '') {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
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
      const active = target === screenId
        || (screenId === 'contatos' && target === 'rede')
        || (screenId === 'orientacoes' && target === 'mais')
        || (screenId === 'localizacao' && target === 'mais');
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

    if (screenId === 'localizacao') {
      setTimeout(() => loadNearbyPlaces({ force: false }), 120);
    }
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
        { enableHighAccuracy: true, timeout: 3500, maximumAge: 60000 }
      );
    });
  }

  function mapsUrl() {
    const { lat, lng } = state.lastLocation;
    return `https://maps.google.com/?q=${lat},${lng}`;
  }

  function selectedPlace() {
    return state.places.find(place => place.id === state.selectedPlaceId) || null;
  }

  function routeUrl(place = selectedPlace()) {
    const { lat, lng } = state.lastLocation;
    if (!place) return mapsUrl();
    return `https://www.google.com/maps/dir/?api=1&origin=${lat},${lng}&destination=${place.lat},${place.lng}&travelmode=driving`;
  }

  function distanceInMeters(origin, destination) {
    const earthRadius = 6371000;
    const toRad = value => value * Math.PI / 180;
    const dLat = toRad(destination.lat - origin.lat);
    const dLng = toRad(destination.lng - origin.lng);
    const lat1 = toRad(origin.lat);
    const lat2 = toRad(destination.lat);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
    return Math.round(earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
  }

  function formatDistance(meters = 0) {
    if (meters < 1000) return `${meters} m`;
    return `${(meters / 1000).toFixed(1).replace('.', ',')} km`;
  }

  function categoryFromTags(tags = {}) {
    const amenity = tags.amenity || '';
    const healthcare = tags.healthcare || '';
    const office = tags.office || '';
    const socialFacility = tags.social_facility || '';

    if (amenity === 'police') return 'safety';
    if (['hospital', 'clinic', 'doctors', 'pharmacy', 'dentist'].includes(amenity) || healthcare) return 'health';
    if (['social_facility', 'community_centre'].includes(amenity) || socialFacility) return 'social';
    if (['bank', 'townhall', 'public_building'].includes(amenity) || ['government', 'ngo', 'social'].includes(office)) return 'agency';
    return 'support';
  }

  function buildOverpassQuery({ lat, lng }, radius = SUPPORT_RADIUS_METERS) {
    return `
      [out:json][timeout:25];
      (
        node(around:${radius},${lat},${lng})["amenity"~"hospital|clinic|doctors|pharmacy|police|social_facility|community_centre|townhall|bank|public_building"];
        way(around:${radius},${lat},${lng})["amenity"~"hospital|clinic|doctors|pharmacy|police|social_facility|community_centre|townhall|bank|public_building"];
        relation(around:${radius},${lat},${lng})["amenity"~"hospital|clinic|doctors|pharmacy|police|social_facility|community_centre|townhall|bank|public_building"];
        node(around:${radius},${lat},${lng})["healthcare"];
        way(around:${radius},${lat},${lng})["healthcare"];
        relation(around:${radius},${lat},${lng})["healthcare"];
        node(around:${radius},${lat},${lng})["office"~"government|ngo|social"];
        way(around:${radius},${lat},${lng})["office"~"government|ngo|social"];
        relation(around:${radius},${lat},${lng})["office"~"government|ngo|social"];
      );
      out center tags 40;
    `;
  }

  function normalizeOsmElements(elements = []) {
    const origin = state.lastLocation;
    const seen = new Set();

    return elements
      .map(element => {
        const tags = element.tags || {};
        const lat = Number(element.lat ?? element.center?.lat);
        const lng = Number(element.lon ?? element.center?.lon);
        const name = tags.name || tags.official_name || tags.operator || '';
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || !name) return null;

        const category = categoryFromTags(tags);
        const key = `${name.toLowerCase()}_${lat.toFixed(4)}_${lng.toFixed(4)}`;
        if (seen.has(key)) return null;
        seen.add(key);

        const addressParts = [
          tags['addr:street'],
          tags['addr:housenumber'],
          tags['addr:suburb'],
          tags['addr:city']
        ].filter(Boolean);

        return {
          id: `osm-${element.type}-${element.id}`,
          name,
          category,
          lat,
          lng,
          address: addressParts.join(', ') || 'Endereço não informado no OpenStreetMap',
          source: 'OpenStreetMap',
          distance: distanceInMeters(origin, { lat, lng })
        };
      })
      .filter(Boolean)
      .sort((a, b) => a.distance - b.distance)
      .slice(0, 20);
  }

  function demoPointsWithDistance() {
    const origin = state.lastLocation;
    return DEMO_SUPPORT_POINTS.map(place => ({
      ...place,
      distance: distanceInMeters(origin, place),
      demo: true
    })).sort((a, b) => a.distance - b.distance);
  }

  async function fetchOsmPlaces() {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 9000);

    try {
      const response = await fetch('https://overpass-api.de/api/interpreter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: new URLSearchParams({ data: buildOverpassQuery(state.lastLocation) }),
        signal: controller.signal
      });

      if (!response.ok) throw new Error('Falha ao buscar dados no OpenStreetMap.');
      const data = await response.json();
      return normalizeOsmElements(data.elements || []);
    } finally {
      clearTimeout(timeout);
    }
  }

  function showMapLoading(message, visible = true) {
    const loading = $('#mapLoading');
    if (!loading) return;
    loading.textContent = message;
    loading.classList.toggle('is-hidden', !visible);
  }

  function ensureSupportMap() {
    const mapElement = $('#supportMap');
    if (!mapElement || !globalThis.L) {
      showMapLoading('Mapa indisponível. Verifique a internet.', true);
      return null;
    }

    if (!state.supportMap) {
      state.supportMap = L.map(mapElement, {
        zoomControl: false,
        attributionControl: false
      }).setView([state.lastLocation.lat, state.lastLocation.lng], 14);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(state.supportMap);

      L.control.attribution({ prefix: false }).addTo(state.supportMap);
      L.control.zoom({ position: 'bottomright' }).addTo(state.supportMap);
    }

    setTimeout(() => state.supportMap?.invalidateSize(), 120);
    return state.supportMap;
  }

  function markerIcon(category, selected = false) {
    const icon = CATEGORY_ICONS[category] || CATEGORY_ICONS.support;
    return L.divIcon({
      className: '',
      html: `<span class="map-marker ${category} ${selected ? 'selected' : ''}"><i class="${icon}"></i></span>`,
      iconSize: selected ? [42, 42] : [34, 34],
      iconAnchor: selected ? [21, 42] : [17, 34],
      popupAnchor: [0, -34]
    });
  }

  function userIcon() {
    return L.divIcon({
      className: '',
      html: '<span class="user-marker"><i class="ri-user-location-line"></i></span>',
      iconSize: [34, 34],
      iconAnchor: [17, 34],
      popupAnchor: [0, -34]
    });
  }

  function setUserMarker() {
    if (!state.supportMap || !globalThis.L) return;
    const latLng = [state.lastLocation.lat, state.lastLocation.lng];

    if (!state.userMarker) {
      state.userMarker = L.marker(latLng, { icon: userIcon() })
        .addTo(state.supportMap)
        .bindPopup('<strong>Minha localização</strong>');
    } else {
      state.userMarker.setLatLng(latLng);
    }
  }

  function renderMapMarkers() {
    if (!state.supportMap || !globalThis.L) return;

    state.supportMarkers.forEach(marker => marker.remove());
    state.supportMarkers = [];

    const filtered = filteredPlaces();
    const bounds = [[state.lastLocation.lat, state.lastLocation.lng]];

    filtered.forEach(place => {
      const isSelected = place.id === state.selectedPlaceId;
      const marker = L.marker([place.lat, place.lng], { icon: markerIcon(place.category, isSelected) })
        .addTo(state.supportMap)
        .bindPopup(`
          <strong>${escapeHtml(place.name)}</strong><br>
          <small>${escapeHtml(CATEGORY_LABELS[place.category] || 'Apoio')} • ${escapeHtml(formatDistance(place.distance))}</small><br>
          <a href="${routeUrl(place)}" target="_blank" rel="noopener">Abrir rota</a>
        `);

      marker.on('click', () => selectPlace(place.id, { pan: false }));
      state.supportMarkers.push(marker);
      bounds.push([place.lat, place.lng]);
    });

    if (bounds.length > 1) {
      state.supportMap.fitBounds(bounds, { padding: [28, 28], maxZoom: 15 });
    } else {
      state.supportMap.setView([state.lastLocation.lat, state.lastLocation.lng], 14);
    }
  }

  function filteredPlaces() {
    if (state.placeFilter === 'all') return state.places;
    return state.places.filter(place => place.category === state.placeFilter);
  }

  function renderSelectedPlace() {
    const name = $('#selectedPlaceName');
    const meta = $('#selectedPlaceMeta');
    const place = selectedPlace();

    if (!name || !meta) return;

    if (!place) {
      name.textContent = 'Nenhuma unidade selecionada';
      meta.textContent = 'Selecione um ponto no mapa ou na lista.';
      return;
    }

    name.textContent = place.name;
    meta.textContent = `${CATEGORY_LABELS[place.category] || 'Apoio'} • ${formatDistance(place.distance)} • ${place.demo ? 'referência demonstrativa' : place.source}`;
  }

  function renderPlacesList() {
    const list = $('#placesList');
    if (!list) return;

    const places = filteredPlaces();

    if (!places.length) {
      list.innerHTML = `
        <article class="places-empty">
          Nenhum serviço encontrado para este filtro. Tente outro filtro ou atualize o GPS.
        </article>
      `;
      renderSelectedPlace();
      return;
    }

    list.innerHTML = places.map(place => {
      const icon = CATEGORY_ICONS[place.category] || CATEGORY_ICONS.support;
      const active = place.id === state.selectedPlaceId ? ' is-active' : '';
      const source = place.demo ? 'Demonstração' : place.source;
      return `
        <button class="place-card${active}" type="button" data-place-id="${escapeHtml(place.id)}">
          <span class="place-type-icon ${escapeHtml(place.category)}"><i class="${escapeHtml(icon)}" aria-hidden="true"></i></span>
          <span>
            <strong>${escapeHtml(place.name)}</strong>
            <small>${escapeHtml(CATEGORY_LABELS[place.category] || 'Apoio')} • ${escapeHtml(place.address)} • ${escapeHtml(source)}</small>
          </span>
          <em class="place-distance">${escapeHtml(formatDistance(place.distance))}</em>
        </button>
      `;
    }).join('');

    renderSelectedPlace();
  }

  function selectPlace(placeId, options = {}) {
    const place = state.places.find(item => item.id === placeId);
    if (!place) return;

    state.selectedPlaceId = place.id;
    renderPlacesList();
    renderMapMarkers();

    if (options.pan !== false && state.supportMap) {
      state.supportMap.setView([place.lat, place.lng], Math.max(state.supportMap.getZoom(), 15), { animate: true });
    }
  }

  async function loadNearbyPlaces({ force = false } = {}) {
    if (state.isLoadingPlaces) return;

    const map = ensureSupportMap();
    if (!map) return;

    if (state.placesLoaded && !force) {
      setTimeout(() => state.supportMap?.invalidateSize(), 120);
      renderMapMarkers();
      return;
    }

    state.isLoadingPlaces = true;
    showMapLoading('Buscando unidades próximas...', true);

    try {
      await updateLocation();
      ensureSupportMap();
      setUserMarker();

      let places = [];
      try {
        places = await fetchOsmPlaces();
      } catch (_) {
        places = [];
      }

      if (!places.length) {
        places = demoPointsWithDistance();
        showToast('Não encontrei dados OSM próximos. Exibindo pontos demonstrativos.', 3200);
      } else {
        showToast(`${places.length} unidade${places.length > 1 ? 's' : ''} encontrada${places.length > 1 ? 's' : ''} no mapa real.`, 2200);
      }

      state.places = places;
      state.selectedPlaceId = places[0]?.id || null;
      state.placesLoaded = true;
      renderPlacesList();
      renderMapMarkers();
      showMapLoading('', false);
    } finally {
      state.isLoadingPlaces = false;
    }
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
          <strong>${escapeHtml(item.source)}</strong>
          <small>${escapeHtml(item.date)} • ${item.contacts} contato${item.contacts === 1 ? '' : 's'} • ${escapeHtml(item.status)}</small>
        </div>
        <a href="https://maps.google.com/?q=${item.lat},${item.lng}" target="_blank" rel="noopener">Mapa</a>
      </article>
    `).join('');
  }

  function setupContactForm() {
    ['contact1', 'contact2', 'contact3'].forEach(id => {
      const input = $(`#${id}`);
      input?.addEventListener('input', () => {
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

      const filterTrigger = event.target.closest('[data-place-filter]');
      if (filterTrigger) {
        state.placeFilter = filterTrigger.dataset.placeFilter;
        $$('.map-filters button').forEach(button => button.classList.toggle('active', button === filterTrigger));
        const firstFiltered = filteredPlaces()[0];
        state.selectedPlaceId = firstFiltered?.id || null;
        renderPlacesList();
        renderMapMarkers();
        return;
      }

      const placeTrigger = event.target.closest('[data-place-id]');
      if (placeTrigger) {
        selectPlace(placeTrigger.dataset.placeId);
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

    $('#btnLocateMe')?.addEventListener('click', async () => {
      showMapLoading('Atualizando GPS...', true);
      await updateLocation();
      setUserMarker();
      state.placesLoaded = false;
      await loadNearbyPlaces({ force: true });
    });

    $('#btnRefreshPlaces')?.addEventListener('click', () => loadNearbyPlaces({ force: true }));

    $('#btnOpenMap')?.addEventListener('click', () => {
      updateLocation().then(() => window.open(routeUrl(), '_blank', 'noopener'));
    });

    $('#btnOpenSelectedRoute')?.addEventListener('click', () => {
      updateLocation().then(() => window.open(routeUrl(), '_blank', 'noopener'));
    });

    $('#btnCopyMap')?.addEventListener('click', () => {
      updateLocation()
        .then(() => copyText(routeUrl()))
        .then(() => showToast('Link da rota copiado.'))
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
