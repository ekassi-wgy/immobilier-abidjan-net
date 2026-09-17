/**
 * immobilier.abidjan.net — comportements communs du site public (JavaScript natif)
 */
(function () {
  'use strict';

  /* En-tête : opaque dès que la page défile ---------------------------------- */
  var header = document.querySelector('[data-header]');
  if (header && header.classList.contains('im-header--overlay')) {
    var updateHeader = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 24);
    };
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
  }

  /* Menu mobile ------------------------------------------------------------------- */
  var menu = document.querySelector('[data-menu]');
  var openers = document.querySelectorAll('[data-menu-open]');
  var lastFocus = null;

  function focusables(root) {
    return Array.prototype.filter.call(
      root.querySelectorAll('a[href], button:not([disabled]), input, select, textarea'),
      function (el) { return el.offsetParent !== null; }
    );
  }

  function openMenu() {
    lastFocus = document.activeElement;
    menu.hidden = false;
    requestAnimationFrame(function () { menu.classList.add('is-open'); });
    document.documentElement.style.overflow = 'hidden';
    openers.forEach(function (btn) { btn.setAttribute('aria-expanded', 'true'); });
    var first = focusables(menu)[0];
    if (first) { first.focus(); }
  }

  function closeMenu() {
    menu.classList.remove('is-open');
    document.documentElement.style.overflow = '';
    openers.forEach(function (btn) { btn.setAttribute('aria-expanded', 'false'); });
    setTimeout(function () { menu.hidden = true; }, 260);
    if (lastFocus) { lastFocus.focus(); }
  }

  if (menu) {
    openers.forEach(function (btn) { btn.addEventListener('click', openMenu); });
    menu.querySelectorAll('[data-menu-close]').forEach(function (btn) { btn.addEventListener('click', closeMenu); });
    menu.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { closeMenu(); return; }
      if (ev.key !== 'Tab') { return; }
      var items = focusables(menu);
      var first = items[0];
      var last = items[items.length - 1];
      if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
      else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
    });
  }

  /* Favoris (sans compte : mémorisés dans le navigateur) ------------------------------ */
  var STORAGE_KEY = 'ian.favorites';
  var FAVORITES_COOKIE = 'ian_fav';
  var FAVORITES_MAX = 60;

  function readFavorites() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch (e) { return []; }
  }

  function writeFavorites(list) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); } catch (e) { /* stockage indisponible */ }
    writeFavoritesCookie(list);
  }

  /**
   * Recopie la liste dans un cookie : c'est la seule façon pour le serveur de rendre la page
   * /favoris. Aucune donnée personnelle, uniquement des références d'annonces publiques.
   */
  function writeFavoritesCookie(list) {
    var value = encodeURIComponent(list.slice(0, FAVORITES_MAX).join(','));
    var secure = window.location.protocol === 'https:' ? '; secure' : '';
    document.cookie = FAVORITES_COOKIE + '=' + value + '; path=/; max-age=' + (list.length ? 31536000 : 0) + '; samesite=lax' + secure;
  }

  function renderFavorites() {
    var list = readFavorites();
    document.querySelectorAll('[data-favorite]').forEach(function (btn) {
      var active = list.indexOf(btn.getAttribute('data-favorite')) !== -1;
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-favorites-count]').forEach(function (badge) {
      badge.textContent = list.length ? String(list.length) : '';
      badge.setAttribute('data-count', String(list.length));
    });
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-favorite]');
    if (!btn) { return; }
    ev.preventDefault();
    var ref = btn.getAttribute('data-favorite');
    var list = readFavorites();
    var index = list.indexOf(ref);
    if (index === -1) { list.unshift(ref); } else { list.splice(index, 1); }
    writeFavorites(list);
    renderFavorites();
  });

  renderFavorites();
  writeFavoritesCookie(readFavorites()); // le cookie peut expirer alors que le stockage local persiste

  /* Bandeau cookies ----------------------------------------------------------------------
   * Sans tag Google : simple information (aucun traceur).
   * Avec tag Google (data-analytics-id) : consentement. Le script Google n'est chargé qu'après
   * « Accepter » ; le choix est gardé six mois ; « Gestion des cookies » rouvre le bandeau. */
  var cookies = document.querySelector('[data-cookies]');
  if (cookies) {
    var analyticsId = cookies.getAttribute('data-analytics-id');

    if (!analyticsId) {
      var COOKIES_KEY = 'ian.cookies';
      var seen = false;
      try { seen = localStorage.getItem(COOKIES_KEY) === '1'; } catch (e) { seen = false; }
      if (!seen) {
        cookies.hidden = false;
        cookies.querySelectorAll('[data-cookies-accept]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            cookies.hidden = true;
            try { localStorage.setItem(COOKIES_KEY, '1'); } catch (e) { /* stockage indisponible */ }
          });
        });
      }
    } else {
      var CONSENT_KEY = 'ian.consent';
      var CONSENT_MAX_AGE = 1000 * 60 * 60 * 24 * 182; // six mois
      var analyticsLoaded = false;

      var readConsent = function () {
        try {
          var stored = JSON.parse(localStorage.getItem(CONSENT_KEY) || 'null');
          if (stored && (stored.value === 'granted' || stored.value === 'denied') && Date.now() - stored.at < CONSENT_MAX_AGE) {
            return stored.value;
          }
        } catch (e) { /* stockage indisponible ou valeur illisible */ }
        return null;
      };

      var saveConsent = function (value) {
        try { localStorage.setItem(CONSENT_KEY, JSON.stringify({ value: value, at: Date.now() })); } catch (e) { /* choix valable pour cette page seulement */ }
      };

      var loadAnalytics = function () {
        window['ga-disable-' + analyticsId] = false;
        if (analyticsLoaded) { return; }
        analyticsLoaded = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        // Mesure d'audience seulement : le tag du site principal est relié à Google Ads, dont le
        // cookie publicitaire (_gcl_au) et les signaux de personnalisation restent refusés.
        window.gtag('consent', 'default', { analytics_storage: 'granted', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
        window.gtag('js', new Date());
        // Cookies limités à 13 mois ; IP anonymisée (Universal Analytics, sans effet en GA4 qui ne la conserve pas).
        window.gtag('config', analyticsId, { anonymize_ip: true, cookie_expires: 60 * 60 * 24 * 395, allow_google_signals: false, allow_ad_personalization_signals: false });
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(analyticsId);
        document.head.appendChild(script);
      };

      // Retrait du consentement : Google ne mesure plus rien et ses cookies sont supprimés, sur
      // l'hôte comme sur les domaines parents (Google les pose sur « .abidjan.net »).
      var removeAnalytics = function () {
        window['ga-disable-' + analyticsId] = true;
        var parts = location.hostname.split('.');
        var domains = [''];
        for (var i = 0; i < parts.length - 1; i++) { domains.push('; domain=.' + parts.slice(i).join('.')); }
        document.cookie.split(';').forEach(function (cookie) {
          var name = cookie.split('=')[0].trim();
          if (/^_ga($|_)|^_gid$|^_gat|^_gcl_/.test(name)) {
            domains.forEach(function (domain) {
              document.cookie = name + '=; path=/; max-age=0' + domain;
            });
          }
        });
      };

      var choose = function (value) {
        saveConsent(value);
        cookies.hidden = true;
        if (value === 'granted') { loadAnalytics(); } else { removeAnalytics(); }
      };

      cookies.querySelectorAll('[data-cookies-accept]').forEach(function (btn) {
        btn.addEventListener('click', function () { choose('granted'); });
      });
      cookies.querySelectorAll('[data-cookies-refuse]').forEach(function (btn) {
        btn.addEventListener('click', function () { choose('denied'); });
      });
      document.querySelectorAll('[data-cookies-manage]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          cookies.hidden = false;
          var first = cookies.querySelector('button');
          if (first) { first.focus(); }
        });
      });

      var consent = readConsent();
      if (consent === 'granted') {
        loadAnalytics();
      } else if (consent === null) {
        cookies.hidden = false;
      }
    }
  }

  /* Recherche : changement de transaction ---------------------------------------------- */
  document.querySelectorAll('[data-search]').forEach(function (form) {
    var budgets = {};
    try { budgets = JSON.parse(form.getAttribute('data-budgets')) || {}; } catch (e) { budgets = {}; }
    var budgetSelect = form.querySelector('[data-search-budget]');
    var tabs = form.querySelectorAll('[data-search-transaction]');

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (other) { other.setAttribute('aria-selected', other === tab ? 'true' : 'false'); });
        form.setAttribute('action', tab.getAttribute('data-action'));

        var options = budgets[tab.getAttribute('data-search-transaction')] || {};
        if (budgetSelect) {
          budgetSelect.length = 1;
          Object.keys(options).forEach(function (value) {
            budgetSelect.add(new Option(options[value], value));
          });
        }
      });
    });
  });
})();
