/*!
 * Dérivé de StarAdmin 2 (BootstrapDash, licence MIT) — version nettoyée pour immobilier.abidjan.net.
 * Supprimé : menu horizontal, datepicker de démo, panneau de thème, détection du lien actif par URL
 * (l'état actif du menu est désormais calculé côté serveur).
 */
(function ($) {
  'use strict';

  $(function () {
    var body = $('body');
    var sidebar = $('.sidebar');

    // Un seul sous-menu ouvert à la fois
    sidebar.on('show.bs.collapse', '.collapse', function () {
      sidebar.find('.collapse.show').collapse('hide');
    });

    // Barre de défilement du menu latéral fixe
    if (body.hasClass('sidebar-fixed') && $('#sidebar').length && window.PerfectScrollbar) {
      new PerfectScrollbar('#sidebar .nav');
    }

    // Réduction du menu latéral (desktop)
    $('[data-bs-toggle="minimize"]').on('click', function () {
      if (body.hasClass('sidebar-toggle-display') || body.hasClass('sidebar-absolute')) {
        body.toggleClass('sidebar-hidden');
      } else {
        body.toggleClass('sidebar-icon-only');
      }
      try {
        localStorage.setItem('cmsadmin.sidebar', body.hasClass('sidebar-icon-only') ? 'icon-only' : 'full');
      } catch (e) {}
    });

    // Aide visuelle des cases à cocher / boutons radio du template
    $('.form-check label, .form-radio label').append('<i class="input-helper"></i>');
  });
})(jQuery);
