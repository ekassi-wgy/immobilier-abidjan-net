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

  function readFavorites() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch (e) { return []; }
  }

  function writeFavorites(list) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); } catch (e) { /* stockage indisponible */ }
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
    if (index === -1) { list.push(ref); } else { list.splice(index, 1); }
    writeFavorites(list);
    renderFavorites();
  });

  renderFavorites();

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
