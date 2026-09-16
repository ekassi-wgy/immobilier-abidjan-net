/**
 * immobilier.abidjan.net — fiche annonce : visionneuse de galerie, carte, partage.
 *
 * Sans JavaScript la page reste complète : chaque photo de la mosaïque est un lien vers
 * l'image, la localisation est décrite en texte et le partage passe par des liens classiques.
 */
(function () {
  'use strict';

  /* Visionneuse (galerie) ------------------------------------------------------------- */
  var box = document.querySelector('[data-lightbox]');
  var gallery = document.querySelector('[data-gallery]');

  if (box && gallery) {
    var images = [];
    try { images = JSON.parse(box.getAttribute('data-lightbox-images')) || []; } catch (e) { images = []; }

    if (images.length) {
      var labels = {
        close: box.getAttribute('data-label-close') || 'Fermer',
        previous: box.getAttribute('data-label-previous') || 'Précédent',
        next: box.getAttribute('data-label-next') || 'Suivant'
      };
      var current = 0;
      var opener = null;

      box.innerHTML =
        '<button class="im-lightbox__close" type="button" data-lightbox-close aria-label="' + labels.close + '">'
        + '<svg class="im-icon" aria-hidden="true" focusable="false"><use href="#i-close"></use></svg></button>'
        + '<button class="im-lightbox__nav im-lightbox__nav--prev" type="button" data-lightbox-prev aria-label="' + labels.previous + '">'
        + '<svg class="im-icon" aria-hidden="true" focusable="false"><use href="#i-arrow-left"></use></svg></button>'
        + '<figure class="im-lightbox__figure"><img class="im-lightbox__image" alt=""></figure>'
        + '<button class="im-lightbox__nav im-lightbox__nav--next" type="button" data-lightbox-next aria-label="' + labels.next + '">'
        + '<svg class="im-icon" aria-hidden="true" focusable="false"><use href="#i-arrow-right"></use></svg></button>'
        + '<p class="im-lightbox__counter" data-lightbox-counter></p>';

      // Le sprite est chargé par une requête séparée : on réutilise l'URL déjà présente dans la page.
      var sprite = document.querySelector('.im-icon use');
      if (sprite) {
        var base = (sprite.getAttribute('href') || '').split('#')[0];
        box.querySelectorAll('use').forEach(function (use) {
          use.setAttribute('href', base + use.getAttribute('href'));
        });
      }

      var picture = box.querySelector('.im-lightbox__image');
      var counter = box.querySelector('[data-lightbox-counter]');
      var single = images.length < 2;
      box.querySelectorAll('.im-lightbox__nav').forEach(function (nav) { nav.hidden = single; });

      function render() {
        picture.setAttribute('src', images[current].src);
        picture.setAttribute('alt', images[current].alt || '');
        counter.textContent = single ? '' : (current + 1) + ' / ' + images.length;
      }

      function open(index) {
        current = Math.min(Math.max(index, 0), images.length - 1);
        opener = document.activeElement;
        render();
        box.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        box.querySelector('[data-lightbox-close]').focus();
      }

      function close() {
        box.hidden = true;
        document.documentElement.style.overflow = '';
        if (opener) { opener.focus(); }
      }

      function step(delta) {
        current = (current + delta + images.length) % images.length;
        render();
      }

      gallery.addEventListener('click', function (ev) {
        var item = ev.target.closest('[data-gallery-item]');
        if (item) {
          ev.preventDefault();
          open(parseInt(item.getAttribute('data-gallery-item'), 10) || 0);
          return;
        }
        if (ev.target.closest('[data-gallery-open]')) { open(0); }
      });

      box.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-lightbox-close]') || ev.target === box) { close(); }
        else if (ev.target.closest('[data-lightbox-prev]')) { step(-1); }
        else if (ev.target.closest('[data-lightbox-next]')) { step(1); }
      });

      document.addEventListener('keydown', function (ev) {
        if (box.hidden) { return; }
        if (ev.key === 'Escape') { close(); }
        else if (ev.key === 'ArrowLeft' && !single) { step(-1); }
        else if (ev.key === 'ArrowRight' && !single) { step(1); }
        else if (ev.key === 'Tab') {
          // Le focus reste dans la visionneuse tant qu'elle est ouverte.
          var items = Array.prototype.filter.call(box.querySelectorAll('button'), function (el) { return !el.hidden; });
          var first = items[0];
          var last = items[items.length - 1];
          if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
          else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
        }
      });
    }
  }

  /* Partage : copie du lien ------------------------------------------------------------ */
  var share = document.querySelector('[data-share]');
  if (share) {
    var copyButton = share.querySelector('[data-share-copy]');
    if (copyButton && navigator.clipboard) {
      copyButton.addEventListener('click', function () {
        navigator.clipboard.writeText(share.getAttribute('data-url')).then(function () {
          var previous = copyButton.getAttribute('aria-label');
          copyButton.classList.add('is-copied');
          copyButton.setAttribute('aria-label', share.getAttribute('data-copied'));
          setTimeout(function () {
            copyButton.classList.remove('is-copied');
            copyButton.setAttribute('aria-label', previous);
          }, 2000);
        }, function () { /* presse-papiers refusé : les liens de partage restent disponibles */ });
      });
    } else if (copyButton) {
      copyButton.hidden = true;
    }
  }

  /* Carte de localisation --------------------------------------------------------------- */
  var mapElement = document.getElementById('im-map');
  if (!mapElement || typeof window.L === 'undefined') { return; }

  var point = null;
  try { point = JSON.parse(mapElement.getAttribute('data-point')); } catch (e) { point = null; }
  if (!point) { return; }

  var L = window.L;
  L.Icon.Default.imagePath = mapElement.getAttribute('data-map-images') || '/assets/vendors/leaflet/images/';

  var map = L.map(mapElement, { scrollWheelZoom: false, zoomControl: true })
    .setView([point.lat, point.lng], point.exact ? 16 : 14);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>'
  }).addTo(map);

  if (point.exact) {
    L.marker([point.lat, point.lng]).addTo(map);
  } else {
    // Localisation non publique : un cercle, jamais un point qui ferait croire à une adresse.
    L.circle([point.lat, point.lng], {
      radius: 700,
      color: '#143D8A',
      weight: 1,
      fillColor: '#2650DB',
      fillOpacity: 0.12
    }).addTo(map);
  }

  map.on('focus', function () { map.scrollWheelZoom.enable(); });
  map.on('blur', function () { map.scrollWheelZoom.disable(); });
})();
