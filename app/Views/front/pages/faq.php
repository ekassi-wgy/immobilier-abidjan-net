<?php

/**
 * Page FAQ : thèmes et questions en accordéon (<details> natifs : fonctionne sans JavaScript,
 * une seule question ouverte à la fois par thème grâce à l'attribut `name`).
 *
 * Le contenu vient de la page `faq` (back-office) : h2 = thème, h3 = question, la suite = réponse.
 * Les réponses sont du HTML rédigé par l'équipe, comme toute page éditoriale.
 *
 * @var array $page   slug, code, title, content, updated_at
 * @var list<array{id: string, title: ?string, items: list<array{id: string, question: string, answer: string}>}> $groups
 */
$site = site();
$whatsapp = $site?->contactWhatsapp !== null ? 'https://wa.me/' . preg_replace('/\D+/', '', (string) $site->contactWhatsapp) : null;
$count = array_sum(array_map(static fn (array $group): int => count($group['items']), $groups));
?>
<section class="im-section im-section--tight im-faq-page">
  <div class="im-container im-faq-page__container">
    <header class="im-faq-page__head">
      <p class="im-eyebrow"><?= e(__('front.faq.eyebrow')) ?></p>
      <h1 class="im-h1 im-faq-page__title"><?= e($page['title']) ?></h1>
      <p class="im-lead"><?= e(__n('front.faq.lead', $count, ['site' => $site->name ?? ''])) ?></p>

      <?php if (count($groups) > 1): ?>
      <nav class="im-faq-page__topics" aria-label="<?= e(__('front.faq.topics')) ?>">
        <?php foreach ($groups as $group): if ($group['title'] === null) { continue; } ?>
        <a class="im-chip" href="#<?= e($group['id']) ?>"><?= e($group['title']) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
    </header>

    <?php foreach ($groups as $index => $group): ?>
    <section class="im-faq-group" id="<?= e($group['id']) ?>"<?= $group['title'] !== null ? ' aria-labelledby="' . e($group['id']) . '-title"' : '' ?>>
      <?php if ($group['title'] !== null): ?>
      <h2 class="im-faq-group__title" id="<?= e($group['id']) ?>-title">
        <span class="im-faq-group__index im-num"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
        <?= e($group['title']) ?>
      </h2>
      <?php endif; ?>

      <div class="im-faq">
        <?php foreach ($group['items'] as $item): ?>
        <details class="im-faq__item" id="<?= e($item['id']) ?>" name="faq-<?= e($group['id']) ?>">
          <summary class="im-faq__question">
            <span><?= e($item['question']) ?></span>
            <span class="im-faq__toggle" aria-hidden="true"><?= icon('plus') ?></span>
          </summary>
          <div class="im-faq__answer"><?= $item['answer'] ?></div>
        </details>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>

    <aside class="im-faq-help">
      <div>
        <p class="im-faq-help__title"><?= e(__('front.faq.help_title')) ?></p>
        <p class="im-faq-help__text"><?= e(__('front.faq.help_text', ['site' => $site->name ?? ''])) ?></p>
      </div>
      <div class="im-faq-help__actions">
        <a class="im-btn" href="<?= e(url('contact')) ?>"><?= icon('mail') ?> <?= e(__('front.faq.help_contact')) ?></a>
        <?php if ($whatsapp !== null): ?>
        <a class="im-faq-help__whatsapp" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</section>
