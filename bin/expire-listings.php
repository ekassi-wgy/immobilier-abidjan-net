<?php

declare(strict_types=1);

/**
 * Expiration des annonces et relance avant échéance (à lancer une fois par jour en CRON) :
 *
 *   php bin/expire-listings.php            # applique
 *   php bin/expire-listings.php --dry-run  # simulation, n'écrit rien
 *
 * Durée de vie et délai de relance : paramètres listing.lifetime_days et listing.expiry_reminder_days.
 * Les notifications et emails partent vers l'agence (responsable, agent en charge, auteur).
 */

/** @var App\Core\App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);
$properties = $app->properties();
$workflow = $app->workflow();

// Le site du pays sert à composer les liens et les emails
$sitesByCountry = [];
foreach ($app->db()->select("SELECT s.id, c.id AS country_id FROM sites s JOIN countries c ON c.id = s.country_id WHERE s.status = 'active'") as $row) {
    $sitesByCountry[(int) $row['country_id']] ??= (int) $row['id'];
}
$siteFor = static function (int $countryId) use ($app, $sitesByCountry) {
    $siteId = $sitesByCountry[$countryId] ?? null;
    if ($siteId === null) {
        return null;
    }
    foreach ($app->db()->select('SELECT host FROM site_domains WHERE site_id = :id ORDER BY is_primary DESC, id LIMIT 1', ['id' => $siteId]) as $domain) {
        return $app->sites()->findByHost((string) $domain['host'], true);
    }

    return null;
};

$expired = 0;
foreach ($properties->dueForExpiry() as $row) {
    $expired++;
    echo 'Expirée : ', $row['reference'], ' — ', $row['title'], PHP_EOL;
    if (!$dryRun) {
        $workflow->expire($row, $siteFor((int) $row['country_id']));
    }
}

$reminded = 0;
$days = max(1, (int) $app->settings()->get('listing.expiry_reminder_days', 7));
foreach ($properties->dueForReminder($days) as $row) {
    $reminded++;
    echo 'Relance : ', $row['reference'], ' — expire le ', substr((string) $row['expires_at'], 0, 10), PHP_EOL;
    if (!$dryRun) {
        $workflow->remind($row, $siteFor((int) $row['country_id']));
    }
}

printf("%s%d annonce(s) expirée(s), %d relance(s) à %d jour(s) de l'échéance.\n", $dryRun ? '[simulation] ' : '', $expired, $reminded, $days);
