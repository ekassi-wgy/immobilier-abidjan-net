/**
 * cmsadmin — comportements communs du back-office immobilier.abidjan.net
 */
(function ($) {
  'use strict';

  $(function () {
    // Select2 en français, habillé par cmsadmin.css
    if ($.fn.select2) {
      $('[data-select2]').each(function () {
        var $el = $(this);
        $el.select2({
          width: '100%',
          placeholder: $el.data('placeholder') || '',
          allowClear: !$el.prop('required') && !$el.prop('multiple'),
          language: {
            noResults: function () { return 'Aucun résultat'; },
            searching: function () { return 'Recherche…'; },
            inputTooShort: function () { return 'Saisissez au moins un caractère'; }
          }
        });
      });
    }

    // Confirmation des actions sensibles : <button data-confirm="Message">
    $(document).on('click', '[data-confirm]', function (ev) {
      if (!window.confirm($(this).data('confirm'))) {
        ev.preventDefault();
        ev.stopImmediatePropagation();
      }
    });

    // Affichage / masquage du mot de passe : <button data-toggle-password="#id">
    $(document).on('click', '[data-toggle-password]', function () {
      var $input = $($(this).data('toggle-password'));
      var visible = $input.attr('type') === 'text';
      $input.attr('type', visible ? 'password' : 'text');
      $(this).attr('aria-pressed', String(!visible))
        .find('.mdi').toggleClass('mdi-eye-outline', visible).toggleClass('mdi-eye-off-outline', !visible);
    });

    // Fermeture automatique des messages flash de succès
    setTimeout(function () {
      $('.im-flash--success').fadeOut(300);
    }, 5000);
    $(document).on('click', '.im-flash__close', function () {
      $(this).closest('.im-flash').fadeOut(200);
    });
  });
})(jQuery);
