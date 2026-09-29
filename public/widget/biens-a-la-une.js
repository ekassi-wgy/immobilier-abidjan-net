/*! Abidjan.net Immobilier · widget « Biens à la une » (lot 2.6) */
/*
 * Variante JavaScript de l'inclusion côté serveur, pour une page d'Abidjan.net qui ne peut pas faire
 * d'include PHP. Intégration :
 *
 *   <div data-ian-widget="a-la-une" data-limit="3">
 *     <a href="https://immobilier.abidjan.net/">Abidjan.net Immobilier : toutes les annonces</a>
 *   </div>
 *   <script src="https://immobilier.abidjan.net/widget/biens-a-la-une.js" async></script>
 *
 * Le script charge le fragment `/widget/biens-a-la-une` (le même que l'inclusion PHP) quand la zone
 * approche de l'écran, et l'affiche dans une racine fantôme : le CSS de la page ne l'altère pas.
 * Sans JavaScript, sans réponse ou sans annonce à la une, le lien placé dans la zone reste affiché.
 */
(function () {
  'use strict';

  var script = document.currentScript;
  var origin = script && script.src ? new URL(script.src).origin : 'https://immobilier.abidjan.net';

  function load(host) {
    var limit = parseInt(host.getAttribute('data-limit'), 10) || 3;

    fetch(origin + '/widget/biens-a-la-une?limit=' + limit, { credentials: 'omit' })
      .then(function (response) { return response.ok ? response.text() : ''; })
      .then(function (html) {
        if (html.trim() === '') {
          return;
        }
        // innerHTML n'active pas une racine fantôme déclarative : on la reconstruit à la main
        var parsed = document.createElement('template');
        parsed.innerHTML = html;
        var shadow = parsed.content.querySelector('template[shadowrootmode]');
        if (!shadow) {
          return;
        }
        // Police : une @font-face n'est prise en compte qu'au niveau du document
        var fonts = parsed.content.querySelector('style');
        if (fonts && !document.getElementById('ian-widget-fonts')) {
          fonts.id = 'ian-widget-fonts';
          document.head.appendChild(fonts);
        }
        var root = host.shadowRoot || host.attachShadow({ mode: 'open' });
        root.replaceChildren(document.importNode(shadow.content, true));
      })
      .catch(function () { /* le lien de secours reste en place */ });
  }

  function init() {
    var hosts = document.querySelectorAll('[data-ian-widget="a-la-une"]:not([data-ian-widget-loaded])');
    if (!hosts.length || !('attachShadow' in Element.prototype) || !window.fetch) {
      return;
    }

    var observer = 'IntersectionObserver' in window
      ? new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            observer.unobserve(entry.target);
            load(entry.target);
          }
        });
      }, { rootMargin: '600px 0px' })
      : null;

    Array.prototype.forEach.call(hosts, function (host) {
      host.setAttribute('data-ian-widget-loaded', '');
      if (observer) {
        observer.observe(host);
      } else {
        load(host);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
