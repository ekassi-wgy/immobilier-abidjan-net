/* Page Contact : carte de localisation des bureaux (Leaflet auto-hébergé, tuiles OpenStreetMap).
   Sans JavaScript, l'adresse et le lien d'itinéraire restent affichés sur la fiche posée sur la carte. */
(function () {
  'use strict';

  var element = document.getElementById('im-contact-map');
  if (!element || typeof window.L === 'undefined') { return; }

  var point;
  try { point = JSON.parse(element.getAttribute('data-point')); } catch (e) { point = null; }
  if (!point || typeof point.lat !== 'number' || typeof point.lng !== 'number') { return; }

  L.Icon.Default.imagePath = element.getAttribute('data-map-images') || '/assets/vendors/leaflet/images/';

  var map = L.map(element, { scrollWheelZoom: false, zoomControl: false })
    .setView([point.lat, point.lng], 16);

  L.control.zoom({ position: 'bottomright' }).addTo(map);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>'
  }).addTo(map);

  L.marker([point.lat, point.lng], { keyboard: false }).addTo(map);

  // Sur grand écran, la fiche couvre la moitié gauche de la carte : on décale le point vers la droite.
  function recenter() {
    var wide = window.matchMedia('(min-width: 992px)').matches;
    map.setView([point.lat, point.lng], 16, { animate: false });
    if (wide) { map.panBy([-element.clientWidth * 0.2, 0], { animate: false }); }
  }
  recenter();
  window.addEventListener('resize', recenter);

  // La molette ne zoome qu'après un clic dans la carte : la page doit rester défilable.
  map.on('click focus', function () { map.scrollWheelZoom.enable(); });
  map.on('mouseout blur', function () { map.scrollWheelZoom.disable(); });
})();
