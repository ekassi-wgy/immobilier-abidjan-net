<?php
/**
 * « Confiez-nous votre bien » : présentation du service aux particuliers.
 * Le particulier ne publie pas : il confie son bien à Weblogy, qui le présente et le commercialise.
 *
 * @var App\Models\User|null $user Particulier connecté, sinon null
 */
$siteName = site()->name ?? '';
?>
<section class="im-section im-section--tight im-entrust">
  <div class="im-container">
    <div class="im-entrust__hero">
      <div>
        <p class="im-eyebrow"><?= e(__('owner.landing.eyebrow')) ?></p>
        <h1 class="im-h1 im-entrust__title"><?= e(__('owner.landing.title')) ?></h1>
        <p class="im-lead"><?= e(__('owner.landing.lead', ['site' => $siteName])) ?></p>
        <div class="im-entrust__actions">
          <?php if ($user !== null): ?>
          <a class="im-btn im-btn--lg" href="<?= e(url('mon-espace/biens/nouveau')) ?>"><?= icon('key') ?> <?= e(__('owner.landing.cta_submit')) ?></a>
          <a class="im-link" href="<?= e(url('mon-espace')) ?>"><?= e(__('owner.landing.cta_space')) ?> <?= icon('arrow-right', 'im-icon--arrow') ?></a>
          <?php else: ?>
          <a class="im-btn im-btn--lg" href="<?= e(url('mon-espace/inscription')) ?>"><?= e(__('owner.landing.cta_register')) ?></a>
          <a class="im-link" href="<?= e(url('mon-espace/connexion')) ?>"><?= e(__('owner.landing.cta_login')) ?> <?= icon('arrow-right', 'im-icon--arrow') ?></a>
          <?php endif; ?>
        </div>
      </div>

      <ol class="im-entrust__steps">
        <?php foreach (['step_1', 'step_2', 'step_3', 'step_4'] as $index => $key): ?>
        <li>
          <span class="im-entrust__number im-num"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <span>
            <strong><?= e(__('owner.landing.' . $key . '_title')) ?></strong>
            <?= e(__('owner.landing.' . $key . '_text', ['site' => $siteName])) ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="im-entrust__columns">
      <section>
        <h2 class="im-h4"><?= e(__('owner.landing.we_do_title', ['site' => $siteName])) ?></h2>
        <ul class="im-checklist">
          <?php foreach (['we_do_1', 'we_do_2', 'we_do_3', 'we_do_4'] as $key): ?>
          <li><?= icon('check') ?> <?= e(__('owner.landing.' . $key)) ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <section>
        <h2 class="im-h4"><?= e(__('owner.landing.you_provide_title')) ?></h2>
        <ul class="im-checklist">
          <?php foreach (['you_provide_1', 'you_provide_2', 'you_provide_3', 'you_provide_4'] as $key): ?>
          <li><?= icon('check') ?> <?= e(__('owner.landing.' . $key)) ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>

    <aside class="im-note-band">
      <p><?= icon('buildings') ?> <?= e(__('owner.landing.pro_note')) ?></p>
      <a class="im-link" href="<?= e(url('devenir-partenaire')) ?>"><?= e(__('front.nav.become_partner')) ?> <?= icon('arrow-right', 'im-icon--arrow') ?></a>
    </aside>
  </div>
</section>
