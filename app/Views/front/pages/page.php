<?php

/**
 * Page éditoriale ou légale (table `pages`). Le contenu est rédigé depuis le back-office
 * (lot 2.2) ; il est déjà en HTML et n'est donc pas échappé, mais seules des personnes de
 * l'équipe peuvent l'écrire.
 *
 * @var array $page slug, code, title, content, updated_at
 */
?>
<section class="im-section im-section--tight">
  <div class="im-container">
    <article class="im-page">
      <h1 class="im-h2 im-page__title"><?= e($page['title']) ?></h1>
      <?php if (!empty($page['updated_at'])): ?>
      <p class="im-small im-muted"><?= e(__('front.pages.updated_at', ['date' => format_date((string) $page['updated_at'])])) ?></p>
      <?php endif; ?>
      <div class="im-page__content im-prose"><?= $page['content'] ?></div>
    </article>
  </div>
</section>
