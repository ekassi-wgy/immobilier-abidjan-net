<?php
/**
 * Guide d'utilisation du back-office. Page de lecture seule, sans formulaire.
 *
 * Le contenu vient de `config/cmsadmin-guide.php` : il est écrit par l'équipe de développement,
 * jamais saisi par un utilisateur. Les clés `html` et les éléments de `items` / `rows` sont donc
 * volontairement rendus **sans échappement** — ils contiennent de la mise en forme (<strong>,
 * <em>, <p>). Les titres, eux, passent par e() : ils n'ont aucune raison de porter du balisage.
 *
 * @var list<array<string, mixed>> $sections
 */
$noteIcon = [
    'primary' => 'mdi-star-outline',
    'warning' => 'mdi-alert-outline',
    'info' => 'mdi-information-outline',
];
?>
<?= cmsadmin_partial('page-header', [
    'title' => __('guide.title'),
    'subtitle' => __('guide.subtitle'),
    'breadcrumb' => [['label' => __('auth.dashboard'), 'url' => '/'], ['label' => __('guide.title')]],
]) ?>

<div class="im-form__layout im-form__layout--read-first">
  <div class="im-form__main">
    <?php foreach ($sections as $index => $section): ?>
    <section class="card im-form-section im-guide-section" id="guide-<?= e($section['key']) ?>" aria-labelledby="guide-<?= e($section['key']) ?>-title">
      <header class="im-form-section__head">
        <span class="im-form-section__index"><?= e(format_number($index + 1)) ?></span>
        <h2 class="im-panel__title" id="guide-<?= e($section['key']) ?>-title">
          <span class="mdi <?= e($section['icon']) ?>" aria-hidden="true"></span> <?= e($section['title']) ?>
        </h2>
      </header>

      <?php if (!empty($section['intro'])): ?>
      <p class="im-guide__intro"><?= e($section['intro']) ?></p>
      <?php endif; ?>

      <?php foreach ($section['blocks'] as $block): ?>
        <?php // Un encadré porte son titre à l'intérieur : pas de h3 au-dessus. ?>
        <?php if (!empty($block['title']) && $block['type'] !== 'note'): ?>
        <h3 class="im-guide__heading"><?= e($block['title']) ?></h3>
        <?php endif; ?>

        <?php if ($block['type'] === 'text'): ?>
        <div class="im-prose"><?= $block['html'] ?></div>

        <?php elseif ($block['type'] === 'list'): ?>
        <ul class="im-guide__list">
          <?php foreach ($block['items'] as $item): ?><li><?= $item ?></li><?php endforeach; ?>
        </ul>

        <?php elseif ($block['type'] === 'steps'): ?>
        <ol class="im-steps-list im-guide__steps">
          <?php foreach ($block['items'] as $item): ?><li><?= $item ?></li><?php endforeach; ?>
        </ol>

        <?php elseif ($block['type'] === 'table'): ?>
        <div class="table-responsive">
          <table class="table im-table im-guide__table">
            <thead>
              <tr><?php foreach ($block['head'] as $heading): ?><th scope="col"><?= e($heading) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
              <?php foreach ($block['rows'] as $row): ?>
              <tr><?php foreach ($row as $cell): ?><td><?= $cell ?></td><?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php elseif ($block['type'] === 'note'): ?>
        <aside class="im-note im-guide__note im-note--<?= e($block['tone'] ?? 'info') ?>">
          <span class="mdi <?= e($noteIcon[$block['tone'] ?? 'info'] ?? 'mdi-information-outline') ?>" aria-hidden="true"></span>
          <div>
            <?php if (!empty($block['title'])): ?><p class="im-guide__note-title"><?= e($block['title']) ?></p><?php endif; ?>
            <?= $block['html'] ?>
          </div>
        </aside>
        <?php endif; ?>
      <?php endforeach; ?>
    </section>
    <?php endforeach; ?>
  </div>

  <aside class="im-form__aside">
    <div class="im-sticky">
      <nav class="im-toc" aria-label="<?= e(__('guide.summary')) ?>">
        <p class="im-toc__title"><?= e(__('guide.summary')) ?></p>
        <ol>
          <?php foreach ($sections as $section): ?>
          <li><a href="#guide-<?= e($section['key']) ?>"><?= e($section['title']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </nav>
    </div>
  </aside>
</div>
