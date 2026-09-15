/**
 * cmsadmin — comportements communs du back-office immobilier.abidjan.net
 */
(function ($) {
  'use strict';

  $(function () {
    // Select2 en français, habillé par cmsadmin.css
    if ($.fn.select2) {
      $('[data-im-select]').each(function () {
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

    // Raccourci « / » : focus sur la recherche globale
    $(document).on('keydown', function (ev) {
      if (ev.key !== '/' || ev.ctrlKey || ev.metaKey || ev.altKey) {
        return;
      }
      var tag = (ev.target.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || ev.target.isContentEditable) {
        return;
      }
      var search = document.getElementById('im-global-search');
      if (search && search.offsetParent !== null) {
        ev.preventDefault();
        search.focus();
      }
    });

    // Slug proposé à partir du nom : <input data-slug-source="slug"> remplit le champ name="slug"
    // tant que celui-ci est vide ou n'a pas été modifié à la main.
    var slugify = function (value) {
      return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
        .replace(/['’]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
    };
    $('[data-slug-source]').each(function () {
      var $source = $(this);
      var $target = $source.closest('form').find('[name="' + $source.data('slug-source') + '"]');
      if (!$target.length) {
        return;
      }
      var auto = $target.val() === '';
      $target.on('input', function () { auto = $target.val() === ''; });
      $source.on('input', function () {
        if (auto) {
          $target.val(slugify($source.val()));
        }
      });
    });

    // Lignes répétables (options d'un critère…) : <template data-repeat-template="id"> + <button data-repeat-add="id">
    $(document).on('click', '[data-repeat-add]', function () {
      var id = $(this).data('repeat-add');
      var template = document.querySelector('template[data-repeat-template="' + id + '"]');
      var list = document.querySelector('[data-repeat-list="' + id + '"]');
      if (!template || !list) {
        return;
      }
      var index = 'n' + Date.now();
      var html = template.innerHTML.replace(/__INDEX__/g, index);
      list.insertAdjacentHTML('beforeend', html);
      var first = list.lastElementChild && list.lastElementChild.querySelector('input:not([type=hidden])');
      if (first) {
        first.focus();
      }
    });
    $(document).on('click', '[data-repeat-remove]', function () {
      $(this).closest('[data-repeat-item]').remove();
    });

    // Aperçu d'icône du sprite public : <select data-icon-preview="id-du-conteneur">
    $(document).on('change', '[data-icon-preview]', function () {
      var preview = document.getElementById($(this).data('icon-preview'));
      if (!preview || !preview.dataset.sprite) {
        return;
      }
      preview.innerHTML = this.value ? '<svg><use href="' + preview.dataset.sprite.replace(/"/g, '') + '#i-' + this.value.replace(/[^a-z0-9-]/g, '') + '"></use></svg>' : '';
    });

    // Affiche la section des options uniquement pour les types « liste » : <select data-toggle-options="id-section">
    $(document).on('change', '[data-toggle-options]', function () {
      var section = document.getElementById($(this).data('toggle-options'));
      if (section) {
        section.hidden = ['select', 'multiselect'].indexOf(this.value) === -1;
      }
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
