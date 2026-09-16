<?php

use App\Support\Paginator;

/**
 * Liste des actualités immobilières (lot 2.2).
 *
 * @var list<array<string,mixed>> $posts
 * @var int                       $total
 * @var Paginator                 $paginator
 * @var string                    $baseUrl
 * @var array                     $query
 * @var string|null               $seoIntro
 */
$seoIntro ??= null;
?>
<section class="im-section im-section--tight">
  <div class="im-container">
    <div class="im-section-head">
      <div class="im-section-head__text">
        <p class="im-eyebrow"><?= e(__('front.posts.eyebrow')) ?></p>
        <h1 class="im-h2 im-section-head__title"><?= e(__('front.posts.title')) ?></h1>
        <p class="im-lead im-section-head__lead"><?= e($seoIntro ?? __('front.posts.lead')) ?></p>
      </div>
    </div>

    <?php if ($posts === []): ?>
    <div class="im-empty">
      <span class="im-empty__icon" aria-hidden="true"><?= icon('info') ?></span>
      <p class="im-h3"><?= e(__('front.posts.empty_title')) ?></p>
      <p class="im-lead"><?= e(__('front.posts.empty_text')) ?></p>
    </div>
    <?php else: ?>
    <div class="im-grid-cards">
      <?php foreach ($posts as $index => $post): ?>
      <article class="im-post">
        <a class="im-post__link" href="<?= e(url('actualites/' . $post['slug'])) ?>">
          <span class="im-post__media">
            <?php if (!empty($post['cover_image_path'])): ?>
            <img src="<?= e(url((string) $post['cover_image_path'])) ?>" alt="" width="1600" height="1067"
                 sizes="(min-width: 992px) 33vw, 100vw" decoding="async"<?= $index < 3 ? '' : ' loading="lazy"' ?>>
            <?php else: ?>
            <span class="im-post__placeholder" aria-hidden="true"><?= icon('images') ?></span>
            <?php endif; ?>
          </span>
          <span class="im-post__body">
            <time class="im-post__date" datetime="<?= e(substr((string) $post['published_at'], 0, 10)) ?>"><?= e(format_date((string) $post['published_at'])) ?></time>
            <span class="im-post__title"><?= e($post['title']) ?></span>
            <?php if (!empty($post['excerpt'])): ?><span class="im-post__excerpt"><?= e($post['excerpt']) ?></span><?php endif; ?>
          </span>
        </a>
      </article>
      <?php endforeach; ?>
    </div>

    <?= render_view('front/partials/pagination', ['paginator' => $paginator, 'baseUrl' => $baseUrl, 'query' => $query]) ?>
    <?php endif; ?>
  </div>
</section>
