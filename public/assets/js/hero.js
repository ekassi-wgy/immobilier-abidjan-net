/**
 * immobilier.abidjan.net — diaporama du hero (JavaScript natif)
 * Fondu enchaîné, pause au survol / au focus / au bouton, navigation clavier,
 * arrêt automatique si l'utilisateur préfère réduire les animations.
 */
(function () {
  'use strict';

  var hero = document.querySelector('[data-hero]');
  if (!hero) { return; }

  var slides = hero.querySelectorAll('.im-hero__slide');
  var steps = hero.querySelectorAll('[data-hero-step]');
  var total = slides.length;
  if (total < 2) { return; }

  var caption = hero.querySelector('[data-hero-caption]');
  var captionWrap = hero.querySelector('[data-hero-caption-wrap]');
  var counter = hero.querySelector('[data-hero-current]');
  var toggle = hero.querySelector('[data-hero-toggle]');
  var iconPause = hero.querySelector('[data-hero-icon-pause]');
  var iconPlay = hero.querySelector('[data-hero-icon-play]');

  var interval = parseInt(getComputedStyle(hero).getPropertyValue('--im-hero-interval'), 10) || 7000;
  var fade = parseInt(getComputedStyle(hero).getPropertyValue('--im-hero-fade'), 10) || 1400;
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  var current = 0;
  var timer = null;
  var startedAt = 0;
  var remaining = interval;
  var pausedByUser = reduceMotion.matches;
  var pausedByHover = false;

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  function restartAnimation(el) {
    // Relance l'animation CSS de la barre de progression
    el.classList.remove('is-active');
    void el.offsetWidth;
    el.classList.add('is-active');
  }

  function show(index) {
    if (index === current) { return; }
    var previous = slides[current];
    var next = slides[index];

    previous.classList.remove('is-active');
    previous.classList.add('is-leaving');
    previous.setAttribute('aria-hidden', 'true');
    next.classList.add('is-active');
    next.removeAttribute('aria-hidden');
    setTimeout(function () { previous.classList.remove('is-leaving'); }, fade);

    steps.forEach(function (step, i) {
      step.classList.toggle('is-done', i < index);
      step.classList.remove('is-active');
      step.removeAttribute('aria-current');
    });
    steps[index].setAttribute('aria-current', 'true');
    restartAnimation(steps[index]);

    var text = next.getAttribute('data-caption') || '';
    if (caption && captionWrap) {
      captionWrap.classList.add('is-changing');
      setTimeout(function () {
        caption.textContent = text;
        captionWrap.hidden = text === '';
        captionWrap.classList.remove('is-changing');
      }, 250);
    }
    if (counter) { counter.textContent = pad(index + 1); }

    current = index;
    remaining = interval;
  }

  function schedule(delay) {
    clearTimeout(timer);
    startedAt = Date.now();
    remaining = delay;
    timer = setTimeout(function () {
      show((current + 1) % total);
      if (isRunning()) { schedule(interval); }
    }, delay);
  }

  function isRunning() { return !pausedByUser && !pausedByHover; }

  function sync() {
    var running = isRunning();
    hero.classList.toggle('is-paused', !running);
    if (running) {
      schedule(remaining);
    } else {
      clearTimeout(timer);
      remaining = Math.max(0, remaining - (Date.now() - startedAt));
    }
    if (toggle) {
      toggle.setAttribute('aria-label', pausedByUser ? 'Relancer le diaporama' : 'Mettre le diaporama en pause');
      iconPause.hidden = pausedByUser;
      iconPlay.hidden = !pausedByUser;
    }
  }

  steps.forEach(function (step) {
    step.addEventListener('click', function () {
      show(parseInt(step.getAttribute('data-hero-step'), 10));
      remaining = interval;
      if (isRunning()) { schedule(interval); }
    });
  });

  if (toggle) {
    toggle.addEventListener('click', function () {
      pausedByUser = !pausedByUser;
      sync();
    });
  }

  // Pause pendant la lecture du contenu (survol ou focus clavier dans le hero)
  hero.addEventListener('mouseenter', function () { pausedByHover = true; sync(); });
  hero.addEventListener('mouseleave', function () { pausedByHover = false; sync(); });
  hero.addEventListener('focusin', function () { pausedByHover = true; sync(); });
  hero.addEventListener('focusout', function (ev) {
    if (!hero.contains(ev.relatedTarget)) { pausedByHover = false; sync(); }
  });

  hero.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowRight') { show((current + 1) % total); }
    if (ev.key === 'ArrowLeft') { show((current - 1 + total) % total); }
  });

  // Onglet masqué : inutile de consommer des ressources
  document.addEventListener('visibilitychange', function () {
    pausedByHover = document.hidden;
    sync();
  });

  reduceMotion.addEventListener('change', function (ev) {
    pausedByUser = ev.matches;
    sync();
  });

  // Préchargement discret des diapositives suivantes après le chargement de la page
  window.addEventListener('load', function () {
    slides.forEach(function (slide) {
      var img = slide.querySelector('img[loading="lazy"]');
      if (img) { img.loading = 'eager'; }
    });
  });

  sync();
})();
