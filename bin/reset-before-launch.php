<?php

declare(strict_types=1);

/**
 * Remise à zéro avant l'ouverture du site, à lancer en SSH sur le serveur.
 *
 * Deux niveaux :
 *
 *  (défaut)  Données de démonstration seulement : exactement ce que bin/seed-demo.php a créé
 *            (registre `demo.registry`) — partenaires, annonces et photos, contacts, dossiers,
 *            actualités. Le mode démonstration est levé : indexation et mesure d'audience reprennent.
 *
 *  --all     En plus, TOUTES les données d'exploitation créées pendant les essais : annonces,
 *            photos, contacts, statistiques, partenaires et leurs comptes, dossiers de partenariat
 *            et pièces, comptes particuliers et biens confiés, notifications, journal d'activité,
 *            tentatives de connexion. Les compteurs repartent de 1 (première annonce : IAN-10001).
 *
 * Toujours conservés : pays, sites et domaines, paramètres, référentiels (géographie, catégories,
 * critères, équipements), pages éditoriales et légales, bannières, actualités non fictives,
 * référencement (balises, redirections) et comptes internes (Super Admin, Admin Pays).
 *
 * Sans --confirm, rien n'est modifié : le script affiche ce qui serait supprimé.
 *
 * Usage : php bin/reset-before-launch.php [--all] [--confirm]
 */

use App\Core\App;

/** @var App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

$options = getopt('', ['all', 'confirm', 'help']);
if (isset($options['help'])) {
    echo "Usage : php bin/reset-before-launch.php [--all] [--confirm]\n";
    exit(0);
}
$all = isset($options['all']);
$confirm = isset($options['confirm']);
$db = $app->db();
$root = $app->root;

$in = static fn (array $ids): string => $ids === [] ? '0' : implode(',', array_map('intval', $ids));
$count = static fn (string $sql): int => (int) $db->scalar($sql);

// -- Registre des données de démonstration ------------------------------------------------------

$rawRegistry = $db->scalar("SELECT value FROM settings WHERE site_id IS NULL AND setting_key = 'demo.registry'");
$registry = is_string($rawRegistry) ? (json_decode($rawRegistry, true) ?: []) : [];
$demo = [
    'agencies' => array_map('intval', $registry['agencies'] ?? []),
    'properties' => array_map('intval', $registry['properties'] ?? []),
    'leads' => array_map('intval', $registry['leads'] ?? []),
    'partner_requests' => array_map('intval', $registry['partner_requests'] ?? []),
    'posts' => array_map('intval', $registry['posts'] ?? []),
    'files' => array_values(array_filter($registry['files'] ?? [], 'is_string')),
];

// Une annonce réelle rattachée à un partenaire fictif bloque sa suppression : on le signale plutôt que de la perdre.
if (!$all && $demo['agencies'] !== []) {
    $foreign = $count("SELECT COUNT(*) FROM properties WHERE agency_id IN ({$in($demo['agencies'])}) AND id NOT IN ({$in($demo['properties'])})");
    $accounts = $count("SELECT COUNT(*) FROM users WHERE agency_id IN ({$in($demo['agencies'])})");
    if ($foreign > 0 || $accounts > 0) {
        fwrite(STDERR, "✗ {$foreign} annonce(s) et {$accounts} compte(s) non fictifs sont rattachés à des partenaires de démonstration.\n");
        fwrite(STDERR, "  Les réaffecter ou les supprimer dans le back-office, ou utiliser --all pour tout remettre à zéro.\n");
        exit(1);
    }
}

// -- Inventaire --------------------------------------------------------------------------------

$staffRoles = "'super_admin','country_admin'";
if ($all) {
    $plan = [
        'Annonces (photos, critères, historique, statistiques)' => $count('SELECT COUNT(*) FROM properties'),
        'Demandes de contact' => $count('SELECT COUNT(*) FROM leads'),
        'Partenaires' => $count('SELECT COUNT(*) FROM agencies'),
        'Comptes partenaires et particuliers' => $count("SELECT COUNT(*) FROM users WHERE role NOT IN ({$staffRoles})"),
        'Dossiers de partenariat (et pièces)' => $count('SELECT COUNT(*) FROM partner_requests'),
        'Biens confiés (et fichiers)' => $count('SELECT COUNT(*) FROM property_submissions'),
        'Actualités de démonstration' => count($demo['posts']),
        'Notifications' => $count('SELECT COUNT(*) FROM notifications'),
        'Entrées du journal d\'activité' => $count('SELECT COUNT(*) FROM activity_logs'),
        'Tentatives de connexion' => $count('SELECT COUNT(*) FROM login_attempts'),
    ];
} else {
    $plan = [
        'Partenaires fictifs' => count($demo['agencies']),
        'Annonces fictives (photos, critères, statistiques)' => count($demo['properties']),
        'Demandes de contact fictives' => count($demo['leads']) + $count("SELECT COUNT(*) FROM leads WHERE property_id IN ({$in($demo['properties'])}) AND id NOT IN ({$in($demo['leads'])})"),
        'Dossiers de partenariat fictifs' => count($demo['partner_requests']),
        'Actualités fictives' => count($demo['posts']),
    ];
}

echo $all ? "Remise à zéro COMPLÈTE avant ouverture\n" : "Suppression des données de démonstration\n";
echo str_repeat('─', 60) . "\n";
foreach ($plan as $label => $n) {
    printf("  %s%s %6d\n", $label, str_repeat(' ', max(1, 52 - mb_strlen($label))), $n);
}
echo str_repeat('─', 60) . "\n";
echo "Conservés : pays, sites, domaines, paramètres, référentiels, pages, bannières,\n";
echo "            actualités réelles, référencement, comptes Super Admin et Admin Pays.\n\n";

if (!$confirm) {
    $database = (string) $app->config->get('database.database', 'base');
    echo "Simulation : rien n'a été modifié.\n";
    echo "1. Sauvegarder d'abord la base et les fichiers, par exemple :\n";
    echo "   mysqldump -u <utilisateur> -p {$database} > sauvegarde-avant-ouverture-" . gmdate('Ymd-His') . ".sql\n";
    echo "   tar czf fichiers-avant-ouverture.tar.gz public/uploads storage/private\n";
    echo '2. Puis exécuter : php bin/reset-before-launch.php' . ($all ? ' --all' : '') . " --confirm\n";
    exit(0);
}

// -- Suppression en base -----------------------------------------------------------------------

$deleteTree = static function (string $path, bool $keepRoot) use (&$deleteTree): void {
    if (!is_dir($path)) {
        if (is_file($path)) {
            @unlink($path);
        }

        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || ($keepRoot && $entry === '.htaccess')) {
            continue;
        }
        $deleteTree($path . '/' . $entry, false);
    }
    if (!$keepRoot) {
        @rmdir($path);
    }
};

$db->transaction(static function () use ($db, $all, $demo, $in, $staffRoles): void {
    if ($all) {
        $db->execute('DELETE FROM leads');
        $db->execute('DELETE FROM property_submissions');
        $db->execute('DELETE FROM properties');
        $db->execute('UPDATE partner_requests SET agency_id = NULL');
        $db->execute('UPDATE agencies SET partner_request_id = NULL');
        $db->execute('DELETE FROM partner_requests');
        $db->execute("DELETE FROM users WHERE role NOT IN ({$staffRoles})");
        $db->execute('DELETE FROM agencies');
        $db->execute('DELETE FROM notifications');
        $db->execute('DELETE FROM activity_logs');
        $db->execute('DELETE FROM login_attempts');
        $db->execute('DELETE FROM email_verifications');
        $db->execute('DELETE FROM password_resets WHERE used_at IS NOT NULL OR expires_at < UTC_TIMESTAMP()');
    } else {
        $db->execute("DELETE FROM leads WHERE id IN ({$in($demo['leads'])}) OR property_id IN ({$in($demo['properties'])})");
        $db->execute("DELETE FROM properties WHERE id IN ({$in($demo['properties'])})");
        $db->execute("UPDATE agencies SET partner_request_id = NULL WHERE partner_request_id IN ({$in($demo['partner_requests'])})");
        $db->execute("DELETE FROM partner_requests WHERE id IN ({$in($demo['partner_requests'])})");
        $db->execute("DELETE FROM agencies WHERE id IN ({$in($demo['agencies'])})");
    }
    $db->execute("DELETE FROM blog_posts WHERE id IN ({$in($demo['posts'])})");
    $db->execute("DELETE FROM settings WHERE site_id IS NULL AND setting_key IN ('demo.active', 'demo.registry')");
});

if ($all) {
    // Les références publiques et les compteurs repartent de 1 (InnoDB retient max(id) + 1).
    foreach (['properties', 'property_images', 'property_revisions', 'leads', 'agencies', 'partner_requests', 'partner_request_files', 'property_submissions', 'property_submission_files', 'notifications', 'activity_logs', 'login_attempts'] as $table) {
        $db->execute("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
    }
}

// -- Fichiers ----------------------------------------------------------------------------------

$removedFiles = 0;
foreach ($demo['files'] as $relative) {
    if (!str_starts_with($relative, 'uploads/') || str_contains($relative, '..')) {
        continue;
    }
    $path = $root . '/public/' . $relative;
    if (file_exists($path)) {
        $deleteTree($path, false);
        $removedFiles++;
    }
}
if ($all) {
    foreach (glob($root . '/public/uploads/*', GLOB_ONLYDIR) ?: [] as $countryDir) {
        foreach (['annonces', 'agences', 'tmp'] as $folder) {
            $deleteTree($countryDir . '/' . $folder, false);
        }
    }
    $deleteTree($root . '/storage/private', true);
}

$app->sites()->flush();
$app->cache()->clear();
$app->activity()->log(
    $all ? 'system.launch_reset' : 'system.demo_purged',
    null,
    null,
    null,
    null,
    $all ? 'Remise à zéro complète avant ouverture' : 'Données de démonstration supprimées'
);

echo "✓ Terminé" . ($removedFiles > 0 ? " ({$removedFiles} dossier(s) ou fichier(s) de démonstration supprimés)" : '') . ".\n";
echo "  Mode démonstration levé : indexation et mesure d'audience actives sur le domaine de production.\n";
echo "  Vérifier : php bin/check-deploy.php --host=<domaine>\n";
