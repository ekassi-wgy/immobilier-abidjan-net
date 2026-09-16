<?php

/**
 * Article d'actualité (lot 2.2). Le contenu est rédigé depuis le back-office : il est déjà en
 * HTML et n'est donc pas échappé, mais seule l'équipe peut l'écrire.
 *
 * @var array<string,mixed> $post
 * @var string|null         $cover
 */
?>
<article class="im-section im-section--tight">
  <div class="im-container">
    <nav class="im-breadcrumb" aria-label="<?= e(__('front.results.breadcrumb_label')) ?>">
      <ol>
        <li><a href="<?= e(url()) ?>"><?= e(__('front.nav.home')) ?></a> <?= icon('caret-right') ?></li>
        <li><a href="<?= e(url('actualites')) ?>"><?= e(__('front.posts.title')) ?></a> <?= icon('caret-right') ?></li>
        <li><span aria-current="page"><?= e($post['title']) ?></span></li>
      </ol>
    </nav>

    <div class="im-page">
      <p class="im-post__date">
        <time datetime="<?= e(substr((string) $post['published_at'], 0, 10)) ?>"><?= e(format_date((string) $post['published_at'])) ?></time>
        <?php if (!empty($post['author'])): ?> · <?= e($post['author']) ?><?php endif; ?>
      </p>
      <h1 class="im-h2 im-page__title"><?= e($post['title']) ?></h1>
      <?php if (!empty($post['excerpt'])): ?>
      <p class="im-lead"><?= e($post['excerpt']) ?></p>
      <?php endif; ?>

      <?php if ($cover !== null): ?>
      <img class="im-post__cover" src="<?= e(url($cover)) ?>" alt="" width="1600" height="1067"
           sizes="(min-width: 768px) 46rem, 100vw" fetchpriority="high">
      <?php endif; ?>

      <div class="im-page__content im-prose"><?= $post['content'] ?></div>

      <p class="im-page__back"><a class="im-link" href="<?= e(url('actualites')) ?>"><?= icon('arrow-left') ?> <?= e(__('front.posts.back')) ?></a></p>
    </div>
  </div>
</article>
