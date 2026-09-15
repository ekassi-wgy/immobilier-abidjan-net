<?php
/**
 * Détail d'une exception — développement uniquement (app.debug, jamais en production).
 * Page autonome : ne dépend ni des layouts ni des traductions, qui peuvent être la cause de l'erreur.
 *
 * @var Throwable                $exception
 * @var App\Core\Request|null    $request
 */
$root = APP_ROOT . '/';
$relative = static fn (string $file): string => str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;

$chain = [];
for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
    $chain[] = $current;
}

$excerpt = static function (string $file, int $line): array {
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
    $start = max(1, $line - 6);

    return array_slice($lines, $start - 1, 13, true);
};
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($exception::class) ?> · debug</title>
  <style>
    :root { --ink: #1B2540; --muted: #5E6B85; --line: #E5E7EB; --snow: #F6F9FC; --navy: #143D8A; --red: #E02020; --tint: #FDECEC; }
    * { box-sizing: border-box; }
    body { margin: 0; font: 15px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--snow); }
    main { max-width: 72rem; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; }
    .tag { display: inline-block; padding: .2rem .55rem; border-radius: 6px; background: var(--tint); color: var(--red); font-size: .75rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; }
    h1 { margin: .75rem 0 .5rem; font-size: 1.6rem; line-height: 1.25; letter-spacing: -.02em; overflow-wrap: anywhere; }
    .meta { color: var(--muted); font-size: .875rem; overflow-wrap: anywhere; }
    section { margin-top: 2rem; }
    h2 { margin: 0 0 .75rem; font-size: .8rem; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
    pre { margin: 0; padding: 1rem; overflow-x: auto; border: 1px solid var(--line); border-radius: 10px; background: #fff; font: 13px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; }
    .line { display: block; }
    .line.is-error { background: var(--tint); }
    .line b { display: inline-block; width: 3.5rem; color: var(--muted); font-weight: 400; user-select: none; }
    dl { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1.25rem; margin: 0; padding: 1rem; border: 1px solid var(--line); border-radius: 10px; background: #fff; font-size: .875rem; }
    dt { color: var(--muted); } dd { margin: 0; overflow-wrap: anywhere; }
  </style>
</head>
<body>
<main>
  <?php foreach ($chain as $index => $item): ?>
  <section<?= $index === 0 ? ' style="margin-top:0"' : '' ?>>
    <span class="tag"><?= $index === 0 ? 'Exception' : 'Cause' ?></span>
    <h1><?= e($item->getMessage() !== '' ? $item->getMessage() : $item::class) ?></h1>
    <p class="meta"><?= e($item::class) ?> · <?= e($relative($item->getFile())) ?>:<?= e($item->getLine()) ?></p>

    <?php $lines = $excerpt($item->getFile(), $item->getLine()); if ($lines !== []): ?>
    <pre><?php foreach ($lines as $number => $code): ?><span class="line<?= $number + 1 === $item->getLine() ? ' is-error' : '' ?>"><b><?= $number + 1 ?></b><?= e($code) ?></span><?php endforeach; ?></pre>
    <?php endif; ?>

    <section>
      <h2>Pile d’appels</h2>
      <pre><?= e(str_replace($root, '', $item->getTraceAsString())) ?></pre>
    </section>
  </section>
  <?php endforeach; ?>

  <?php if ($request !== null): ?>
  <section>
    <h2>Requête</h2>
    <dl>
      <dt>Méthode</dt><dd><?= e($request->method()) ?></dd>
      <dt>Chemin</dt><dd><?= e($request->path()) ?></dd>
      <dt>Hôte</dt><dd><?= e($request->host()) ?></dd>
      <dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd>
    </dl>
  </section>
  <?php endif; ?>
</main>
</body>
</html>
