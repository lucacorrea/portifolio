(function () {
  'use strict';

  if (!Element.prototype.matches) {
    Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
  }

  if (!Element.prototype.closest) {
    Element.prototype.closest = function (selector) {
      var element = this;
      while (element && element.nodeType === 1) {
        if (element.matches(selector)) return element;
        element = element.parentElement || element.parentNode;
      }
      return null;
    };
  }

  var STORAGE_KEYS = {
    contacts: 'coari_por_elas_contacts_v3',
    history: 'coari_por_elas_history_v3'
  };

  var FALLBACK_LOCATION = {
    lat: -4.0853,
    lng: -63.1411
  };

  var SUPPORT_RADIUS_METERS = 6000;

  var CATEGORY_LABELS = {
    all: 'Todos',
    health: 'Saúde',
    social: 'Assistência social',
    safety: 'Segurança',
    agency: 'Agências',
    support: 'Apoio'
  };

  var CATEGORY_ICONS = {
    health: 'ri-hospital-line',
    social: 'ri-hand-heart-line',
    safety: 'ri-shield-user-line',
    agency: 'ri-building-4-line',
    support: 'ri-map-pin-line'
  };

  var DEMO_SUPPORT_POINTS = [
    {
      id: 'demo-health-1',
      name: 'UBS / Unidade de saúde próxima',
      category: 'health',
      lat: -4.0842,
      lng: -63.1391,
      address: 'Referência demonstrativa em Coari',
      source: 'Base demonstrativa'
    },
    {
      id: 'demo-social-1',
      name: 'CRAS / CREAS de referência',
      category: 'social',
      lat: -4.0884,
      lng: -63.1431,
      address: 'Ponto demonstrativo para assistência social',
      source: 'Base demonstrativa'
    },
    {
      id: 'demo-safety-1',
      name: 'Delegacia / segurança pública',
      category: 'safety',
      lat: -4.0819,
      lng: -63.1452,
      address: 'Ponto demonstrativo para apoio policial',
      source: 'Base demonstrativa'
    },
    {
      id: 'demo-agency-1',
      name: 'Órgão público / agência de apoio',
      category: 'agency',
      lat: -4.0908,
      lng: -63.1384,
      address: 'Referência demonstrativa para serviços públicos',
      source: 'Base demonstrativa'
    }
  ];

  var state = {
    currentScreen: 'inicio',
    historyStack: [],
    emergencyTimers: [],
    lastLocation: { lat: FALLBACK_LOCATION.lat, lng: FALLBACK_LOCATION.lng },
    supportMap: null,
    userMarker: null,
    supportMarkers: [],
    places: [],
    selectedPlaceId: null,
    placeFilter: 'all',
    placesLoaded: false,
    isLoadingPlaces: false
  };

  function $(selector, root) {
    return (root || document).querySelector(selector);
  }

  function $$(selector, root) {
    var list = (root || document).querySelectorAll(selector);
    return Array.prototype.slice.call(list);
  }

  function on(element, eventName, handler) {
    if (element) element.addEventListener(eventName, handler, false);
  }

  function twoDigits(value) {
    return value < 10 ? '0' + value : String(value);
  }

  function nowTime() {
    var date = new Date();
    return twoDigits(date.getHours()) + ':' + twoDigits(date.getMinutes());
  }

  function nowDateTime() {
    var date = new Date();
    return twoDigits(date.getDate()) + '/' + twoDigits(date.getMonth() + 1) + '/' + date.getFullYear() + ' ' + twoDigits(date.getHours()) + ':' + twoDigits(date.getMinutes());
  }

  function showToast(message, duration) {
    var toast = $('#toast');
    if (!toast) return;

    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(showToast.timer);
    showToast.timer = setTimeout(function () {
      toast.classList.remove('show');
    }, duration || 2200);
  }

  function safeJsonParse(value, fallback) {
    try {
      var parsed = JSON.parse(value);
      return parsed || fallback;
    } catch (error) {
      return fallback;
    }
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function storageGet(key, fallback) {
    try {
      return safeJsonParse(localStorage.getItem(key), fallback);
    } catch (error) {
      return fallback;
    }
  }

  function storageSet(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      showToast('Não foi possível salvar neste navegador.', 2400);
    }
  }

  function storageRemove(key) {
    try {
      localStorage.removeItem(key);
    } catch (error) {}
  }

  function getContacts() {
    var contacts = storageGet(STORAGE_KEYS.contacts, []);
    if (!Array.isArray(contacts)) return [];

    return contacts.filter(function (contact) {
      return Boolean(contact);
    });
  }

  function setContacts(contacts) {
    var clean = contacts.filter(function (contact) {
      return Boolean(contact);
    });

    storageSet(STORAGE_KEYS.contacts, clean);
    updateContactsStatus();
  }

  function getHistory() {
    var history = storageGet(STORAGE_KEYS.history, []);
    return Array.isArray(history) ? history : [];
  }

  function setHistory(history) {
    storageSet(STORAGE_KEYS.history, history.slice(0, 20));
    renderHistory();
  }

  function randomId() {
    return 'id_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
  }

  function addHistory(source) {
    var contacts = getContacts();
    var item = {
      id: randomId(),
      date: nowDateTime(),
      source: source || 'Botão de emergência',
      contacts: contacts.length,
      lat: state.lastLocation.lat,
      lng: state.lastLocation.lng,
      status: 'Simulado'
    };

    setHistory([item].concat(getHistory()));
  }

  function getScreenTitle(screenId) {
    var screen = $('#screen-' + screenId);
    return screen && screen.getAttribute('data-title') ? screen.getAttribute('data-title') : 'Coari por Elas';
  }

  function setActiveNav(screenId) {
    var buttons = $$('.bottom-nav [data-nav]');

    buttons.forEach(function (button) {
      var target = button.getAttribute('data-nav');
      var active = target === screenId ||
        (screenId === 'contatos' && target === 'rede') ||
        (screenId === 'orientacoes' && target === 'mais') ||
        (screenId === 'localizacao' && target === 'mais');

      button.classList.toggle('active', active);
    });
  }

  function goTo(screenId, push) {
    var target = $('#screen-' + screenId);
    var screens = $$('.screen');
    var headerTitle = $('#headerTitle');
    var btnBack = $('#btnBack');

    if (!target) return;

    if (push !== false && state.currentScreen !== screenId) {
      state.historyStack.push(state.currentScreen);
    }

    screens.forEach(function (screen) {
      screen.classList.remove('is-active');
    });

    target.classList.add('is-active');
    target.scrollTop = 0;
    state.currentScreen = screenId;

    if (headerTitle) headerTitle.textContent = getScreenTitle(screenId);
    setActiveNav(screenId);
    if (btnBack) btnBack.style.visibility = screenId === 'inicio' ? 'hidden' : 'visible';

    if (screenId === 'localizacao') {
      setTimeout(function () {
        loadNearbyPlaces(false);
      }, 180);
    }
  }

  function clearEmergencyTimers() {
    state.emergencyTimers.forEach(function (timer) {
      clearTimeout(timer);
    });
    state.emergencyTimers = [];
  }

  function resetEmergencyRoute() {
    $$('.route-step').forEach(function (step, index) {
      step.classList.toggle('done', index === 0);
      step.classList.remove('active');
      var time = $('time', step);
      if (time) time.textContent = index === 0 ? nowTime() : '--:--';
    });
  }

  function markEmergencyStep(index) {
    var step = $('.route-step[data-step="' + index + '"]');
    if (!step) return;

    step.classList.add('done');
    step.classList.remove('active');

    var time = $('time', step);
    if (time) time.textContent = nowTime();
  }

  function setRouteActive(index) {
    $$('.route-step').forEach(function (step) {
      step.classList.remove('active');
    });

    var step = $('.route-step[data-step="' + index + '"]');
    if (step) step.classList.add('active');
  }

  function updateLocation(callback) {
    if (!navigator.geolocation) {
      syncMapLinks();
      if (callback) callback(state.lastLocation);
      return;
    }

    navigator.geolocation.getCurrentPosition(
      function (position) {
        state.lastLocation = {
          lat: Number(position.coords.latitude.toFixed(6)),
          lng: Number(position.coords.longitude.toFixed(6))
        };
        syncMapLinks();
        if (callback) callback(state.lastLocation);
      },
      function () {
        syncMapLinks();
        if (callback) callback(state.lastLocation);
      },
      { enableHighAccuracy: true, timeout: 3500, maximumAge: 60000 }
    );
  }

  function mapsUrl() {
    return 'https://www.google.com/maps/search/?api=1&query=' +
      encodeURIComponent(state.lastLocation.lat + ',' + state.lastLocation.lng);
  }

  function selectedPlace() {
    for (var i = 0; i < state.places.length; i += 1) {
      if (state.places[i].id === state.selectedPlaceId) return state.places[i];
    }
    return null;
  }

  function routeUrl(place) {
    var selected = place || selectedPlace();
    var origin = state.lastLocation.lat + ',' + state.lastLocation.lng;

    if (!selected) return mapsUrl();

    var destination = selected.lat + ',' + selected.lng;
    return 'https://www.google.com/maps/dir/?api=1&origin=' + encodeURIComponent(origin) +
      '&destination=' + encodeURIComponent(destination) + '&travelmode=driving';
  }

  function syncMapLinks() {
    var linkOpenMap = $('#linkOpenMap');
    var linkOpenRoute = $('#linkOpenSelectedRoute');

    if (linkOpenMap) linkOpenMap.setAttribute('href', mapsUrl());
    if (linkOpenRoute) linkOpenRoute.setAttribute('href', routeUrl());
  }

  function distanceInMeters(origin, destination) {
    var earthRadius = 6371000;
    var toRad = function (value) { return value * Math.PI / 180; };
    var dLat = toRad(destination.lat - origin.lat);
    var dLng = toRad(destination.lng - origin.lng);
    var lat1 = toRad(origin.lat);
    var lat2 = toRad(destination.lat);
    var a = Math.pow(Math.sin(dLat / 2), 2) + Math.cos(lat1) * Math.cos(lat2) * Math.pow(Math.sin(dLng / 2), 2);
    return Math.round(earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
  }

  function formatDistance(meters) {
    var value = Number(meters || 0);
    if (value < 1000) return value + ' m';
    return (value / 1000).toFixed(1).replace('.', ',') + ' km';
  }

  function categoryFromTags(tags) {
    var amenity = tags.amenity || '';
    var healthcare = tags.healthcare || '';
    var office = tags.office || '';
    var socialFacility = tags.social_facility || '';

    if (amenity === 'police') return 'safety';
    if (['hospital', 'clinic', 'doctors', 'pharmacy', 'dentist'].indexOf(amenity) !== -1 || healthcare) return 'health';
    if (['social_facility', 'community_centre'].indexOf(amenity) !== -1 || socialFacility) return 'social';
    if (['bank', 'townhall', 'public_building'].indexOf(amenity) !== -1 || ['government', 'ngo', 'social'].indexOf(office) !== -1) return 'agency';
    return 'support';
  }

  function buildOverpassQuery(location, radius) {
    return '[out:json][timeout:20];(' +
      'node(around:' + radius + ',' + location.lat + ',' + location.lng + ')["amenity"~"hospital|clinic|doctors|pharmacy|police|social_facility|community_centre|townhall|bank|public_building"];' +
      'way(around:' + radius + ',' + location.lat + ',' + location.lng + ')["amenity"~"hospital|clinic|doctors|pharmacy|police|social_facility|community_centre|townhall|bank|public_building"];' +
      'relation(around:' + radius + ',' + location.lat + ',' + location.lng + ')["amenity"~"hospital|clinic|doctors|pharmacy|police|social_facility|community_centre|townhall|bank|public_building"];' +
      'node(around:' + radius + ',' + location.lat + ',' + location.lng + ')["healthcare"];' +
      'way(around:' + radius + ',' + location.lat + ',' + location.lng + ')["healthcare"];' +
      'relation(around:' + radius + ',' + location.lat + ',' + location.lng + ')["healthcare"];' +
      'node(around:' + radius + ',' + location.lat + ',' + location.lng + ')["office"~"government|ngo|social"];' +
      'way(around:' + radius + ',' + location.lat + ',' + location.lng + ')["office"~"government|ngo|social"];' +
      'relation(around:' + radius + ',' + location.lat + ',' + location.lng + ')["office"~"government|ngo|social"];' +
      ');out center tags 40;';
  }

  function normalizeOsmElements(elements) {
    var origin = state.lastLocation;
    var seen = {};
    var places = [];

    for (var i = 0; i < elements.length; i += 1) {
      var element = elements[i];
      var tags = element.tags || {};
      var lat = Number(element.lat || (element.center && element.center.lat));
      var lng = Number(element.lon || (element.center && element.center.lon));
      var name = tags.name || tags.official_name || tags.operator || '';

      if (!isFinite(lat) || !isFinite(lng) || !name) continue;

      var key = name.toLowerCase() + '_' + lat.toFixed(4) + '_' + lng.toFixed(4);
      if (seen[key]) continue;
      seen[key] = true;

      var addressParts = [
        tags['addr:street'],
        tags['addr:housenumber'],
        tags['addr:suburb'],
        tags['addr:city']
      ].filter(function (value) { return Boolean(value); });

      var category = categoryFromTags(tags);
      places.push({
        id: 'osm-' + element.type + '-' + element.id,
        name: name,
        category: category,
        lat: lat,
        lng: lng,
        address: addressParts.join(', ') || 'Endereço não informado no OpenStreetMap',
        source: 'OpenStreetMap',
        distance: distanceInMeters(origin, { lat: lat, lng: lng })
      });
    }

    places.sort(function (a, b) { return a.distance - b.distance; });
    return places.slice(0, 20);
  }

  function demoPointsWithDistance() {
    var origin = state.lastLocation;
    var places = DEMO_SUPPORT_POINTS.map(function (place) {
      var copy = {};
      for (var key in place) copy[key] = place[key];
      copy.distance = distanceInMeters(origin, place);
      copy.demo = true;
      return copy;
    });

    places.sort(function (a, b) { return a.distance - b.distance; });
    return places;
  }

  function fetchOsmPlaces(callback) {
    if (!window.XMLHttpRequest) {
      callback([]);
      return;
    }

    var xhr = new XMLHttpRequest();
    var finished = false;
    var timer = setTimeout(function () {
      if (finished) return;
      finished = true;
      try { xhr.abort(); } catch (error) {}
      callback([]);
    }, 9000);

    xhr.open('POST', 'https://overpass-api.de/api/interpreter', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4 || finished) return;
      finished = true;
      clearTimeout(timer);

      if (xhr.status < 200 || xhr.status >= 300) {
        callback([]);
        return;
      }

      try {
        var data = JSON.parse(xhr.responseText);
        callback(normalizeOsmElements(data.elements || []));
      } catch (error) {
        callback([]);
      }
    };

    try {
      xhr.send('data=' + encodeURIComponent(buildOverpassQuery(state.lastLocation, SUPPORT_RADIUS_METERS)));
    } catch (error) {
      clearTimeout(timer);
      callback([]);
    }
  }

  function showMapLoading(message, visible) {
    var loading = $('#mapLoading');
    if (!loading) return;

    loading.textContent = message || '';
    loading.classList.toggle('is-hidden', !visible);
  }

  function ensureSupportMap() {
    var mapElement = $('#supportMap');

    if (!mapElement || !window.L) {
      showMapLoading('Mapa indisponível. A lista continua funcionando.', true);
      return null;
    }

    if (!state.supportMap) {
      state.supportMap = L.map(mapElement, {
        zoomControl: false,
        attributionControl: false,
        tap: true
      }).setView([state.lastLocation.lat, state.lastLocation.lng], 14);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(state.supportMap);

      L.control.attribution({ prefix: false }).addTo(state.supportMap);
      L.control.zoom({ position: 'bottomright' }).addTo(state.supportMap);
    }

    setTimeout(function () {
      if (state.supportMap) state.supportMap.invalidateSize();
    }, 160);

    return state.supportMap;
  }

  function markerIcon(category, selected) {
    var icon = CATEGORY_ICONS[category] || CATEGORY_ICONS.support;

    return L.divIcon({
      className: '',
      html: '<span class="map-marker ' + escapeHtml(category) + (selected ? ' selected' : '') + '"><i class="' + escapeHtml(icon) + '"></i></span>',
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
    if (!state.supportMap || !window.L) return;

    var latLng = [state.lastLocation.lat, state.lastLocation.lng];

    if (!state.userMarker) {
      state.userMarker = L.marker(latLng, { icon: userIcon() })
        .addTo(state.supportMap)
        .bindPopup('<strong>Minha localização</strong>');
    } else {
      state.userMarker.setLatLng(latLng);
    }
  }

  function renderMapMarkers() {
    if (!state.supportMap || !window.L) return;

    state.supportMarkers.forEach(function (marker) {
      try { state.supportMap.removeLayer(marker); } catch (error) {}
    });
    state.supportMarkers = [];

    var filtered = filteredPlaces();
    var bounds = [[state.lastLocation.lat, state.lastLocation.lng]];

    filtered.forEach(function (place) {
      var isSelected = place.id === state.selectedPlaceId;
      var marker = L.marker([place.lat, place.lng], { icon: markerIcon(place.category, isSelected) })
        .addTo(state.supportMap)
        .bindPopup(
          '<strong>' + escapeHtml(place.name) + '</strong><br>' +
          '<small>' + escapeHtml(CATEGORY_LABELS[place.category] || 'Apoio') + ' • ' + escapeHtml(formatDistance(place.distance)) + '</small><br>' +
          '<a href="' + escapeHtml(routeUrl(place)) + '" target="_blank" rel="noopener noreferrer">Abrir rota</a>'
        );

      marker.on('click', function () {
        selectPlace(place.id, { pan: false });
      });

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
    return state.places.filter(function (place) {
      return place.category === state.placeFilter;
    });
  }

  function renderSelectedPlace() {
    var name = $('#selectedPlaceName');
    var meta = $('#selectedPlaceMeta');
    var place = selectedPlace();

    if (!name || !meta) return;

    if (!place) {
      name.textContent = 'Nenhuma unidade selecionada';
      meta.textContent = 'Selecione um ponto no mapa ou na lista.';
      syncMapLinks();
      return;
    }

    name.textContent = place.name;
    meta.textContent = (CATEGORY_LABELS[place.category] || 'Apoio') + ' • ' + formatDistance(place.distance) + ' • ' + (place.demo ? 'referência demonstrativa' : place.source);
    syncMapLinks();
  }

  function renderPlacesList() {
    var list = $('#placesList');
    if (!list) return;

    var places = filteredPlaces();

    if (!places.length) {
      list.innerHTML = '<article class="places-empty">Nenhum serviço encontrado para este filtro. Tente outro filtro ou atualize o GPS.</article>';
      renderSelectedPlace();
      return;
    }

    var html = places.map(function (place) {
      var icon = CATEGORY_ICONS[place.category] || CATEGORY_ICONS.support;
      var active = place.id === state.selectedPlaceId ? ' is-active' : '';
      var source = place.demo ? 'Demonstração' : place.source;

      return '<button class="place-card' + active + '" type="button" data-place-id="' + escapeHtml(place.id) + '">' +
        '<span class="place-type-icon ' + escapeHtml(place.category) + '"><i class="' + escapeHtml(icon) + '" aria-hidden="true"></i></span>' +
        '<span><strong>' + escapeHtml(place.name) + '</strong>' +
        '<small>' + escapeHtml(CATEGORY_LABELS[place.category] || 'Apoio') + ' • ' + escapeHtml(place.address) + ' • ' + escapeHtml(source) + '</small></span>' +
        '<em class="place-distance">' + escapeHtml(formatDistance(place.distance)) + '</em>' +
        '</button>';
    }).join('');

    list.innerHTML = html;
    renderSelectedPlace();
  }

  function selectPlace(placeId, options) {
    var place = null;

    for (var i = 0; i < state.places.length; i += 1) {
      if (state.places[i].id === placeId) {
        place = state.places[i];
        break;
      }
    }

    if (!place) return;

    state.selectedPlaceId = place.id;
    renderPlacesList();
    renderMapMarkers();

    if (!options) options = {};
    if (options.pan !== false && state.supportMap) {
      state.supportMap.setView([place.lat, place.lng], Math.max(state.supportMap.getZoom(), 15), { animate: true });
    }

    syncMapLinks();
  }

  function loadNearbyPlaces(force) {
    if (state.isLoadingPlaces) return;

    var map = ensureSupportMap();

    if (state.placesLoaded && !force) {
      if (map) {
        setTimeout(function () {
          if (state.supportMap) state.supportMap.invalidateSize();
        }, 160);
        renderMapMarkers();
      }
      renderPlacesList();
      syncMapLinks();
      return;
    }

    state.isLoadingPlaces = true;
    showMapLoading('Buscando unidades próximas...', true);

    updateLocation(function () {
      ensureSupportMap();
      setUserMarker();

      fetchOsmPlaces(function (places) {
        if (!places || !places.length) {
          places = demoPointsWithDistance();
          showToast('Exibindo pontos demonstrativos no mapa real.', 2600);
        } else {
          showToast(places.length + ' unidade' + (places.length > 1 ? 's' : '') + ' encontrada' + (places.length > 1 ? 's' : '') + '.', 2200);
        }

        state.places = places;
        state.selectedPlaceId = places[0] ? places[0].id : null;
        state.placesLoaded = true;
        state.isLoadingPlaces = false;

        renderPlacesList();
        renderMapMarkers();
        showMapLoading('', false);
        syncMapLinks();
      });
    });
  }

  function startEmergency(source) {
    clearEmergencyTimers();
    resetEmergencyRoute();

    var contacts = getContacts();
    var contactsLabel = $('#contactsAlertLabel');

    if (contactsLabel) {
      contactsLabel.textContent = contacts.length > 0
        ? contacts.length + ' contato' + (contacts.length > 1 ? 's' : '') + ' avisado' + (contacts.length > 1 ? 's' : '')
        : 'Nenhum contato cadastrado';
    }

    goTo('acionamento');
    showToast('Acionamento demonstrativo iniciado. Nenhum SMS real será enviado.', 2800);

    updateLocation(function () {
      var sequence = [
        { index: 1, delay: 650, message: 'Localização preparada para compartilhamento.' },
        { index: 2, delay: 1400, message: contacts.length ? 'Rede de apoio notificada na simulação.' : 'Cadastre contatos para o fluxo real.' },
        { index: 3, delay: 2200, message: 'Ligação para 190 simulada.' }
      ];

      sequence.forEach(function (item) {
        var timer = setTimeout(function () {
          setRouteActive(item.index);
          markEmergencyStep(item.index);
          showToast(item.message, 1600);
        }, item.delay);
        state.emergencyTimers.push(timer);
      });

      state.emergencyTimers.push(setTimeout(function () {
        addHistory(source || 'Botão de emergência');
        showToast('Registro salvo no histórico local.', 1800);
      }, 2600));
    });
  }

  function formatPhone(value) {
    var digits = String(value || '').replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 2) return digits;
    if (digits.length <= 7) return '(' + digits.slice(0, 2) + ') ' + digits.slice(2);
    return '(' + digits.slice(0, 2) + ') ' + digits.slice(2, 7) + '-' + digits.slice(7);
  }

  function phoneIsValid(value) {
    var digits = String(value || '').replace(/\D/g, '');
    return digits.length >= 10 && digits.length <= 11;
  }

  function loadContactsIntoForm() {
    var contacts = getContacts();
    ['contact1', 'contact2', 'contact3'].forEach(function (id, index) {
      var input = $('#' + id);
      if (input) input.value = contacts[index] || '';
    });
    updateContactsStatus();
  }

  function updateContactsStatus() {
    var contacts = getContacts();
    var status = $('#contactsStatus');

    if (!status) return;

    status.textContent = contacts.length
      ? contacts.length + ' contato' + (contacts.length > 1 ? 's' : '') + ' cadastrado' + (contacts.length > 1 ? 's' : '')
      : 'Nenhum contato cadastrado';
  }

  function renderHistory() {
    var list = $('#historyList');
    if (!list) return;

    var history = getHistory();
    if (!history.length) {
      list.innerHTML = '<article class="history-empty"><strong>Nenhum acionamento registrado</strong><small>Os registros desta MVP ficam apenas no navegador.</small></article>';
      return;
    }

    list.innerHTML = history.map(function (item) {
      var coords = encodeURIComponent(item.lat + ',' + item.lng);
      return '<article class="history-item">' +
        '<div><strong>' + escapeHtml(item.source) + '</strong>' +
        '<small>' + escapeHtml(item.date) + ' • ' + Number(item.contacts || 0) + ' contato' + (Number(item.contacts || 0) === 1 ? '' : 's') + ' • ' + escapeHtml(item.status) + '</small></div>' +
        '<a href="https://www.google.com/maps/search/?api=1&query=' + coords + '" target="_blank" rel="noopener noreferrer">Mapa</a>' +
        '</article>';
    }).join('');
  }

  function setupContactForm() {
    ['contact1', 'contact2', 'contact3'].forEach(function (id) {
      var input = $('#' + id);
      on(input, 'input', function () {
        input.value = formatPhone(input.value);
      });
    });

    on($('#contactForm'), 'submit', function (event) {
      event.preventDefault();

      var values = ['contact1', 'contact2', 'contact3'].map(function (id) {
        var input = $('#' + id);
        return input ? input.value.trim() : '';
      }).filter(function (value) {
        return Boolean(value);
      });

      var invalid = false;
      values.forEach(function (value) {
        if (!phoneIsValid(value)) invalid = true;
      });

      if (invalid) {
        showToast('Revise os telefones. Use DDD + número.', 2400);
        return;
      }

      setContacts(values);
      showToast('Contatos salvos neste navegador.');
    });

    on($('#btnClearContacts'), 'click', function () {
      setContacts([]);
      loadContactsIntoForm();
      showToast('Contatos removidos da demonstração.');
    });
  }

  function wipeLocalData() {
    storageRemove(STORAGE_KEYS.contacts);
    storageRemove(STORAGE_KEYS.history);
    loadContactsIntoForm();
    renderHistory();
    showToast('Dados locais apagados.');
  }

  function copyText(text, callback) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        if (callback) callback(true);
      }).catch(function () {
        copyTextFallback(text, callback);
      });
      return;
    }

    copyTextFallback(text, callback);
  }

  function copyTextFallback(text, callback) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    var success = false;
    try {
      success = document.execCommand('copy');
    } catch (error) {
      success = false;
    }

    document.body.removeChild(textarea);
    if (callback) callback(success);
  }

  function setupNavigation() {
    document.addEventListener('click', function (event) {
      var navTrigger = event.target.closest('[data-nav]');
      if (navTrigger) {
        event.preventDefault();
        goTo(navTrigger.getAttribute('data-nav'));
        return;
      }

      var filterTrigger = event.target.closest('[data-place-filter]');
      if (filterTrigger) {
        event.preventDefault();
        state.placeFilter = filterTrigger.getAttribute('data-place-filter');
        $$('.map-filters button').forEach(function (button) {
          button.classList.toggle('active', button === filterTrigger);
        });

        var firstFiltered = filteredPlaces()[0];
        state.selectedPlaceId = firstFiltered ? firstFiltered.id : null;
        renderPlacesList();
        renderMapMarkers();
        syncMapLinks();
        return;
      }

      var placeTrigger = event.target.closest('[data-place-id]');
      if (placeTrigger) {
        event.preventDefault();
        selectPlace(placeTrigger.getAttribute('data-place-id'));
        return;
      }

      var toastTrigger = event.target.closest('[data-toast]');
      if (toastTrigger) {
        showToast(toastTrigger.getAttribute('data-toast'));
      }
    }, false);

    on($('#btnBack'), 'click', function () {
      var previous = state.historyStack.pop();
      goTo(previous || 'inicio', false);
    });

    on($('#btnMore'), 'click', function () {
      goTo('mais');
    });
  }

  function setupActions() {
    on($('#btnEmergency'), 'click', function () {
      startEmergency('Botão de emergência');
    });

    on($('#btnShakeDemo'), 'click', function () {
      startEmergency('Shake simulado');
    });

    on($('#btnSafe'), 'click', function () {
      clearEmergencyTimers();
      addHistory('Confirmação de segurança');
      showToast('Status registrado como em segurança.');
      goTo('inicio');
    });

    on($('#btnCancelAction'), 'click', function () {
      clearEmergencyTimers();
      showToast('Acionamento cancelado na demonstração.');
      goTo('inicio');
    });

    on($('#btnLocateMe'), 'click', function () {
      showMapLoading('Atualizando GPS...', true);
      updateLocation(function () {
        setUserMarker();
        state.placesLoaded = false;
        loadNearbyPlaces(true);
      });
    });

    on($('#btnRefreshPlaces'), 'click', function () {
      state.placesLoaded = false;
      loadNearbyPlaces(true);
    });

    on($('#linkOpenMap'), 'click', function () {
      syncMapLinks();
    });

    on($('#linkOpenSelectedRoute'), 'click', function () {
      syncMapLinks();
    });

    on($('#btnCopyMap'), 'click', function () {
      updateLocation(function () {
        copyText(routeUrl(), function (success) {
          showToast(success ? 'Link da rota copiado.' : 'Não foi possível copiar o link.');
        });
      });
    });

    on($('#btnDemoHistory'), 'click', function () {
      addHistory('Registro demonstrativo');
      showToast('Histórico demonstrativo adicionado.');
    });

    on($('#btnClearHistory'), 'click', function () {
      setHistory([]);
      showToast('Histórico limpo.');
    });

    on($('#btnQuickExit'), 'click', function () {
      var stealth = $('#stealth');
      if (stealth) stealth.removeAttribute('hidden');
    });

    on($('#btnReturn'), 'click', function () {
      var stealth = $('#stealth');
      if (stealth) stealth.setAttribute('hidden', '');
      goTo('inicio');
    });

    on($('#btnWipeData'), 'click', wipeLocalData);
  }

  function init() {
    var btnBack = $('#btnBack');
    if (btnBack) btnBack.style.visibility = 'hidden';

    setupNavigation();
    setupContactForm();
    setupActions();
    loadContactsIntoForm();
    renderHistory();
    resetEmergencyRoute();
    syncMapLinks();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, false);
  } else {
    init();
  }
})();
