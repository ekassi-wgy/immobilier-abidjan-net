/**
 * Formulaire d'annonce : critères selon la catégorie, listes liées (ville → commune → quartier),
 * carte de localisation (Leaflet + OpenStreetMap) et galerie photo (envoi immédiat, ordre, suppression).
 *
 * Le formulaire reste utilisable sans JavaScript, à l'exception du choix des photos et de la carte.
 */
(function ($) {
  'use strict';

  $(function () {
    var form = document.getElementById('property-form');
    if (!form) {
      return;
    }

    var texts = {
      uploading: 'Envoi en cours…',
      failed: 'Envoi impossible : ',
      tooMany: 'Nombre maximal de photos atteint.'
    };

    // --- Critères dépendant de la catégorie -------------------------------------------------
    var $category = $('[data-category-select]');
    var $transaction = $('[name="transaction_type_id"]');
    var container = form.querySelector('[data-criteria-container]');

    $category.on('change', function () {
      var categoryId = this.value;
      if (!categoryId) {
        return;
      }
      var url = form.dataset.criteriaUrl + '?categorie=' + encodeURIComponent(categoryId)
        + (form.dataset.reference ? '&annonce=' + encodeURIComponent(form.dataset.reference) : '');

      container.setAttribute('aria-busy', 'true');
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (response) { return response.ok ? response.text() : Promise.reject(response.status); })
        .then(function (html) { container.innerHTML = html; })
        .catch(function () { /* la validation serveur reprendra la main */ })
        .then(function () { container.removeAttribute('aria-busy'); });

      // Transactions autorisées pour cette catégorie
      fetch(form.dataset.optionsUrl + '?transactions=' + encodeURIComponent(categoryId), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data || !data.transactions) {
            return;
          }
          var current = $transaction.val();
          $transaction.empty().append(new Option($transaction.data('placeholder') || '', ''));
          data.transactions.forEach(function (item) {
            var option = new Option(item.name, item.id, false, String(item.id) === String(current));
            $transaction.append(option);
          });
        });
    });

    // --- Listes liées : ville → commune → quartier, agence → agents ---------------------------
    var fill = function ($select, items, keep) {
      var placeholder = $select.find('option[value=""]').first().text();
      $select.empty().append(new Option(placeholder, ''));
      items.forEach(function (item) {
        $select.append(new Option(item.name, item.id, false, String(item.id) === String(keep)));
      });
      if ($select.data('select2')) {
        $select.trigger('change.select2');
      }
    };

    var loadOptions = function (params, handler) {
      fetch(form.dataset.optionsUrl + '?' + new URLSearchParams(params).toString(), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { if (data) { handler(data); } });
    };

    $('[data-city-select]').on('change', function () {
      var cityId = this.value;
      loadOptions({ ville: cityId }, function (data) {
        fill($('[data-commune-select]'), data.communes, '');
        fill($('[data-district-select]'), [], '');
        if (data.center) { window.imPropertyMap && window.imPropertyMap.center(data.center); }
      });
    });

    $('[data-commune-select]').on('change', function () {
      var communeId = this.value;
      loadOptions({ commune: communeId }, function (data) {
        fill($('[data-district-select]'), data.districts, '');
        if (data.center) { window.imPropertyMap && window.imPropertyMap.center(data.center); }
      });
    });

    $('[data-district-select]').on('change', function () {
      if (!this.value) { return; }
      loadOptions({ quartier: this.value }, function (data) {
        if (data.center) { window.imPropertyMap && window.imPropertyMap.center(data.center); }
      });
    });

    $('[data-agency-select]').on('change', function () {
      loadOptions({ agence: this.value }, function (data) {
        fill($('[name="agent_user_id"]'), data.agents, '');
      });
    });

    // Origine de l'annonce : agence ou bien de particulier
    $('[data-source-select]').on('change', function () {
      var value = this.value;
      form.querySelectorAll('[data-agency-field]').forEach(function (el) { el.hidden = value !== 'agency'; });
      form.querySelectorAll('[data-owner-field]').forEach(function (el) { el.hidden = value !== 'private_owner'; });
    });

    // --- Carte : clic ou glisser du repère → coordonnées ---------------------------------------
    var mapEl = document.getElementById('property-map');
    var latInput = form.querySelector('[data-lat-input]');
    var lngInput = form.querySelector('[data-lng-input]');

    if (mapEl && window.L) {
      var map = L.map(mapEl).setView([parseFloat(mapEl.dataset.lat), parseFloat(mapEl.dataset.lng)], parseInt(mapEl.dataset.zoom, 10) || 12);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);

      var marker = null;
      var setMarker = function (lat, lng) {
        if (!marker) {
          marker = L.marker([lat, lng], { draggable: true }).addTo(map);
          marker.on('dragend', function () {
            var position = marker.getLatLng();
            latInput.value = position.lat.toFixed(6);
            lngInput.value = position.lng.toFixed(6);
          });
        } else {
          marker.setLatLng([lat, lng]);
        }
        latInput.value = Number(lat).toFixed(6);
        lngInput.value = Number(lng).toFixed(6);
      };

      if (mapEl.dataset.hasMarker === '1') {
        setMarker(parseFloat(latInput.value), parseFloat(lngInput.value));
      }
      map.on('click', function (event) { setMarker(event.latlng.lat, event.latlng.lng); });

      form.querySelector('[data-clear-marker]').addEventListener('click', function () {
        if (marker) { map.removeLayer(marker); marker = null; }
        latInput.value = '';
        lngInput.value = '';
      });

      [latInput, lngInput].forEach(function (input) {
        input.addEventListener('change', function () {
          var lat = parseFloat(latInput.value);
          var lng = parseFloat(lngInput.value);
          if (!isNaN(lat) && !isNaN(lng)) { setMarker(lat, lng); map.setView([lat, lng], 16); }
        });
      });

      window.imPropertyMap = {
        center: function (center) {
          map.setView([center.lat, center.lng], center.zoom);
          if (!marker) { return; }
        }
      };
      setTimeout(function () { map.invalidateSize(); }, 200);
    }

    // --- Photos : envoi immédiat, ordre, suppression --------------------------------------------
    var list = form.querySelector('[data-photo-list]');
    var template = form.querySelector('[data-photo-template]');
    var input = form.querySelector('[data-photo-input]');
    var status = form.querySelector('[data-photo-status]');
    var dropzone = form.querySelector('[data-dropzone]');
    var maxPhotos = parseInt(form.dataset.maxPhotos, 10) || 30;

    var reindex = function () {
      list.querySelectorAll('[data-photo-item]').forEach(function (item, index) {
        item.querySelectorAll('input').forEach(function (field) {
          field.name = field.name.replace(/photos\[[^\]]*\]/, 'photos[' + index + ']');
        });
        item.classList.toggle('is-cover', index === 0);
      });
    };

    var upload = function (file) {
      if (list.querySelectorAll('[data-photo-item]').length >= maxPhotos) {
        status.textContent = texts.tooMany;
        return Promise.resolve();
      }
      var data = new FormData();
      data.append('photo', file);
      data.append('_csrf', form.querySelector('input[name="_csrf"]').value);
      status.textContent = texts.uploading;

      return fetch(form.dataset.photosUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json().then(function (json) { return { ok: response.ok, json: json }; }); })
        .then(function (result) {
          if (!result.ok) {
            status.textContent = texts.failed + (result.json.error || '');
            return;
          }
          var html = template.innerHTML
            .replace(/__INDEX__/g, String(list.querySelectorAll('[data-photo-item]').length))
            .replace(/__TOKEN__/g, result.json.token)
            .replace(/__THUMB__/g, result.json.thumb);
          list.insertAdjacentHTML('beforeend', html);
          reindex();
          status.textContent = '';
        })
        .catch(function () { status.textContent = texts.failed; });
    };

    var uploadAll = function (files) {
      Array.prototype.slice.call(files).reduce(function (chain, file) {
        return chain.then(function () { return upload(file); });
      }, Promise.resolve());
    };

    if (input) {
      input.addEventListener('change', function () { uploadAll(this.files); this.value = ''; });
    }
    if (dropzone) {
      ['dragenter', 'dragover'].forEach(function (type) {
        dropzone.addEventListener(type, function (event) { event.preventDefault(); dropzone.classList.add('is-over'); });
      });
      ['dragleave', 'drop'].forEach(function (type) {
        dropzone.addEventListener(type, function (event) { event.preventDefault(); dropzone.classList.remove('is-over'); });
      });
      dropzone.addEventListener('drop', function (event) {
        if (event.dataTransfer && event.dataTransfer.files.length) { uploadAll(event.dataTransfer.files); }
      });
    }

    list.addEventListener('click', function (event) {
      var item = event.target.closest('[data-photo-item]');
      if (!item) { return; }
      if (event.target.closest('[data-photo-remove]')) {
        item.remove();
      } else if (event.target.closest('[data-photo-up]') && item.previousElementSibling) {
        item.parentNode.insertBefore(item, item.previousElementSibling);
      } else if (event.target.closest('[data-photo-down]') && item.nextElementSibling) {
        item.parentNode.insertBefore(item.nextElementSibling, item);
      } else {
        return;
      }
      reindex();
    });

    // Réordonnancement par glisser-déposer (les boutons restent disponibles au clavier)
    var dragged = null;
    list.addEventListener('dragstart', function (event) {
      dragged = event.target.closest('[data-photo-item]');
      if (dragged) { dragged.classList.add('is-dragging'); }
    });
    list.addEventListener('dragend', function () {
      if (dragged) { dragged.classList.remove('is-dragging'); }
      dragged = null;
      reindex();
    });
    list.addEventListener('dragover', function (event) {
      event.preventDefault();
      var target = event.target.closest('[data-photo-item]');
      if (!dragged || !target || target === dragged) { return; }
      var rect = target.getBoundingClientRect();
      var after = (event.clientX - rect.left) > rect.width / 2;
      target.parentNode.insertBefore(dragged, after ? target.nextSibling : target);
    });

    reindex();
  });
})(jQuery);
