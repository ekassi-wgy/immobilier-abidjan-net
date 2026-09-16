/**
 * immobilier.abidjan.net — page de résultats : panneau de filtres, tri, vue carte.
 *
 * Tout fonctionne sans JavaScript : le panneau est un formulaire GET classique, le tri et les
 * présentations sont des liens. Ce fichier n'apporte que du confort (tiroir mobile, envoi
 * automatique, carte Leaflet).
 */
(function () {
  'use strict';

  /* Panneau de filtres : tiroir sous 992 px ------------------------------------------- */
  var panel = document.querySelector('[data-filters-panel]');
  var openers = document.querySelectorAll('[data-filters-open]');
  var lastFocus = null;

  function isDrawer() {
    return window.matchMedia('(max-width: 991.98px)').matches;
  }

  function openPanel() {
    if (!panel) { return; }
    lastFocus = document.activeElement;
    panel.hidden = false;
    panel.classList.add('is-drawer');
    document.documentElement.style.overflow = 'hidden';
    openers.forEach(function (btn) { btn.setAttribute('aria-expanded', 'true'); });
    var first = panel.querySelector('button, [href], input, select, textarea');
    if (first) { first.focus(); }
  }

  function closePanel() {
    if (!panel) { return; }
    panel.hidden = true;
    document.documentElement.style.overflow = '';
    openers.forEach(function (btn) { btn.setAttribute('aria-expanded', 'false'); });
    if (lastFocus) { lastFocus.focus(); }
  }

  /**
   * Bascule entre les deux états du panneau : visible au fil du contenu sur grand écran,
   * tiroir refermé sous 992 px. Le bouton « Filtres » n'apparaît que dans ce second cas,
   * pour qu'une page sans JavaScript ne propose jamais une commande inopérante.
   */
  function syncPanel() {
    if (!panel) { return; }
    var drawer = isDrawer();

    panel.classList.toggle('is-drawer', drawer);
    openers.forEach(function (btn) {
      btn.hidden = !drawer;
      btn.setAttribute('aria-expanded', 'false');
    });

    if (!drawer) {
      panel.hidden = false;
      document.documentElement.style.overflow = '';
    } else if (document.documentElement.style.overflow !== 'hidden') {
      panel.hidden = true;
    }
  }

  if (panel) {
    syncPanel();
    window.addEventListener('resize', syncPanel);
    openers.forEach(function (btn) { btn.addEventListener('click', openPanel); });
    panel.querySelectorAll('[data-filters-close]').forEach(function (btn) {
      btn.addEventListener('click', closePanel);
    });
    panel.addEventListener('click', function (ev) {
      if (ev.target === panel && isDrawer()) { closePanel(); }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && isDrawer() && !panel.hidden) { closePanel(); }
    });
  }

  /* Listes liées : changer de ville réinitialise la commune et le quartier ------------- */
  var form = document.querySelector('[data-filters]');
  if (form) {
    var dependents = { ville: ['commune', 'quartier'], commune: ['quartier'] };

    form.querySelectorAll('[data-filters-auto]').forEach(function (select) {
      select.addEventListener('change', function () {
        (dependents[select.name] || []).forEach(function (name) {
          var child = form.querySelector('[name="' + name + '"]');
          if (child) { child.value = ''; }
        });
        form.submit();
      });
    });
  }

  /* Tri : la valeur de chaque option est l'URL de la recherche triée -------------------- */
  var sort = document.querySelector('[data-sort]');
  if (sort) {
    sort.addEventListener('change', function () {
      if (sort.value) { window.location.href = sort.value; }
    });
  }

  /* Vue carte -------------------------------------------------------------------------- */
  var mapElement = document.getElementById('im-map');
  if (!mapElement || typeof window.L === 'undefined') { return; }

  var points = [];
  try { points = JSON.parse(mapElement.getAttribute('data-map')) || []; } catch (e) { points = []; }
  if (!points.length) { return; }

  var L = window.L;
  L.Icon.Default.imagePath = mapElement.getAttribute('data-map-images') || '/assets/vendors/leaflet/images/';

  var map = L.map(mapElement, { scrollWheelZoom: false, attributionControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>'
  }).addTo(map);

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
    });
  }

  function popup(point) {
    return '<a class="im-map-card" href="' + escapeHtml(point.url) + '">'
      + '<img class="im-map-card__image" src="' + escapeHtml(point.image) + '" alt="" loading="lazy" width="240" height="120">'
      + '<span class="im-map-card__body">'
      + '<span class="im-map-card__price">' + escapeHtml(point.price) + '</span>'
      + '<span class="im-map-card__title">' + escapeHtml(point.title) + '</span>'
      + '<span class="im-map-card__location">' + escapeHtml(point.location) + '</span>'
      + '</span></a>';
  }

  var group = typeof L.markerClusterGroup === 'function'
    ? L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 48 })
    : L.layerGroup();

  points.forEach(function (point) {
    L.marker([point.lat, point.lng], { title: point.title })
      .bindPopup(popup(point), { minWidth: 240, closeButton: true })
      .addTo(group);
  });

  map.addLayer(group);
  map.fitBounds(L.latLngBounds(points.map(function (point) { return [point.lat, point.lng]; })), {
    padding: [32, 32],
    maxZoom: 15
  });

  // Le défilement de la page reste prioritaire tant que la carte n'a pas le focus.
  map.on('focus', function () { map.scrollWheelZoom.enable(); });
  map.on('blur', function () { map.scrollWheelZoom.disable(); });
})();
