<?php

declare(strict_types=1);

/**
 * Contrôle de l'environnement avant d'ouvrir le site au public (lot 3.1).
 *
 * À lancer **sur le serveur**, depuis la racine du projet, après le premier déploiement puis
 * après chaque changement d'hébergement ou de configuration :
 *
 *   php bin/check-deploy.php [--host=immobilier.abidjan.net]
 *
 * Le script ne modifie rien. Il sort en code 1 dès qu'un contrôle bloquant échoue, pour pouvoir
 * être enchaîné dans un script de déploiement.
 *
 * Trois niveaux :
 *   ÉCHEC   le site ne doit pas être ouvert en l'état ;
 *   ALERTE  à regarder, mais n'empêche pas de servir le site ;
 *   OK      contrôle passé.
 *
 * Ce que le script ne peut PAS vérifier depuis la ligne de commande, et qui reste à contrôler
 * depuis un navigateur (liste rappelée en fin d'exécution) : la compression gzip, les en-têtes de
 * cache, le certificat HTTPS et l'application effective des `.htaccess` par le serveur web.
 */

/** @var App\Core\App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

$options = getopt('', ['host::']);
$expectedHost = trim((string) ($options['host'] ?? ''));

$failures = 0;
$warnings = 0;

$check = static function (string $label, bool $ok, string $detail = '', bool $blocking = true) use (&$failures, &$warnings): bool {
    if ($ok) {
        printf("  \033[32mOK\033[0m      %s\n", $label);

        return true;
    }
    if ($blocking) {
        $failures++;
        printf("  \033[31mÉCHEC\033[0m   %s%s\n", $label, $detail !== '' ? "\n          → {$detail}" : '');
    } else {
        $warnings++;
        printf("  \033[33mALERTE\033[0m  %s%s\n", $label, $detail !== '' ? "\n          → {$detail}" : '');
    }

    return false;
};

$section = static function (string $title): void {
    printf("\n\033[1m%s\033[0m\n", $title);
};

// -- 1. PHP et extensions ---------------------------------------------------------------------

$section('1. PHP et extensions');

$check(
    'PHP ' . PHP_VERSION . ' (8.2 minimum)',
    PHP_VERSION_ID >= 80200,
    'Choisir PHP 8.2 ou plus récent dans Plesk, pour le web ET pour la ligne de commande.'
);

foreach (['gd', 'intl', 'mbstring', 'pdo_mysql', 'fileinfo'] as $extension) {
    $check(
        "Extension {$extension}",
        extension_loaded($extension),
        "Activer l'extension {$extension} (Plesk → Paramètres PHP)."
    );
}

$check(
    'GD sait écrire du WebP',
    function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false),
    'Sans WebP, aucune photo ne peut être enregistrée : recompiler GD avec le support WebP.'
);

$check(
    'Composer : dépendances installées',
    is_file($app->root . '/vendor/autoload.php'),
    'Lancer « composer install --no-dev --optimize-autoloader » sur le serveur.'
);

$check(
    'Dépendances de développement absentes',
    !is_dir($app->root . '/vendor/scssphp'),
    "scssphp et twbs/bootstrap n'ont rien à faire en production : « composer install --no-dev ».",
    false
);

// -- 2. Configuration -------------------------------------------------------------------------

$section('2. Configuration (.env)');

$env = (string) $app->config->get('app.env');
$check("Environnement « {$env} »", $env === 'production', 'APP_ENV=production dans .env.');
$check('Débogage désactivé', !$app->config->get('app.debug'), 'APP_DEBUG=false : un détail d\'exception ne doit jamais s\'afficher en ligne.');
$check('Prévisualisation désactivée', !$app->config->get('app.preview'), 'APP_PREVIEW=false : les écrans de démonstration ne doivent pas être servis.');

$url = (string) $app->config->get('app.url');
$check('APP_URL en HTTPS', str_starts_with($url, 'https://'), "APP_URL = « {$url} » : les URL absolues (emails, sitemap, Open Graph) en héritent.");

$secureCookie = $app->config->get('app.session.secure');
$check(
    'Cookie de session forcé en HTTPS (SESSION_SECURE=true)',
    in_array(strtolower((string) var_export($secureCookie, true)), ['true', "'true'", "'1'", '1'], true),
    'SESSION_SECURE=true : derrière le proxy nginx de Plesk, la détection « auto » du HTTPS peut échouer et le cookie partirait sans l\'attribut Secure.',
    false
);

$ttl = $app->config->get('app.cache.sites_ttl');
$check(
    'Cache des sites actif (' . var_export($ttl, true) . ')',
    is_int($ttl) && $ttl > 0,
    'CACHE_SITES_TTL=600 : sans cache, chaque requête relit les sites, les domaines et les paramètres.'
);

$check(
    'Pilote email = smtp',
    (string) $app->config->get('mail.mailer') === 'smtp',
    'MAIL_MAILER=smtp : en pilote « log », aucune invitation ni notification ne part.'
);
$check(
    'SMTP renseigné',
    trim((string) $app->config->get('mail.smtp.host')) !== '' && trim((string) $app->config->get('mail.smtp.password')) !== '',
    'Renseigner SMTP_HOST, SMTP_USERNAME et SMTP_PASSWORD. Rappel : régénérer le mot de passe d\'application Gmail, celui du développement a circulé en clair.'
);

$check(
    'Fichier .env hors du dossier public',
    !is_file($app->root . '/public/.env'),
    'Le .env ne doit jamais se trouver sous la racine web.'
);

// -- 3. Droits d'écriture ---------------------------------------------------------------------

$section('3. Droits d\'écriture');

foreach ([
    'storage/logs' => 'journal des erreurs',
    'storage/cache' => 'cache des sites, des pages et des redirections',
    'public/uploads' => 'photos des annonces et logos des agences',
    'storage/private' => 'pièces justificatives et fichiers des biens confiés (hors racine web)',
] as $relative => $usage) {
    $path = $app->root . '/' . $relative;
    $check(
        "{$relative} inscriptible ({$usage})",
        is_dir($path) && is_writable($path),
        "Créer le dossier et donner les droits d'écriture à l'utilisateur du serveur web."
    );
}

$check(
    'public/uploads/.htaccess présent',
    is_file($app->root . '/public/uploads/.htaccess'),
    "Sans ce fichier, un script envoyé dans uploads/ pourrait être exécuté. Le récupérer depuis le dépôt."
);

// -- 4. Base de données -----------------------------------------------------------------------

$section('4. Base de données');

try {
    $tables = (int) $app->db()->scalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
    $check('Connexion établie', true);
    $check("Schéma installé ({$tables} tables)", $tables >= 38, 'Importer database/schema.sql puis database/seed.sql, et appliquer les migrations.');

    // Migrations appliquées. Le nombre de tables ne les distingue pas : leur DDL est répercuté dans
    // schema.sql, et les migrations de données (textes des pages, commission) ne créent aucune table.
    // On contrôle donc un marqueur que seule la migration concernée peut avoir posé.
    $migrations = glob($app->root . '/database/migrations/*.sql') ?: [];
    printf("  \033[36mINFO\033[0m    %d migration(s) dans database/migrations/.\n", count($migrations));

    $hasTable = static fn (string $table): bool => (int) $app->db()->scalar(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$table]
    ) > 0;
    $hasColumn = static fn (string $table, string $column): bool => (int) $app->db()->scalar(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        [$table, $column]
    ) > 0;
    $hasSetting = static fn (string $key): bool => (int) $app->db()->scalar(
        'SELECT COUNT(*) FROM settings WHERE setting_key = ?',
        [$key]
    ) > 0;

    $applyMigrations = 'Appliquer les migrations de database/migrations/ dans l\'ordre (docs/deploiement.md § 2).';

    // Structure : ces objets viennent d'une migration, mais schema.sql les porte aussi. Sur une
    // installation neuve ils sont donc toujours présents — ils ne prouvent pas que les migrations
    // ont été jouées, ils détectent une base ancienne restée en arrière du schéma.
    $schema = [
        'révisions d\'annonces (0002)' => $hasTable('property_revisions'),
        'coordonnées du site (0006)' => $hasColumn('sites', 'latitude'),
        'réseaux sociaux (0007)' => $hasColumn('sites', 'social_links'),
        'intermédiaire exclusif (0008)' => $hasTable('property_submissions') && $hasColumn('properties', 'deactivated_by_partner'),
        'mesure d\'audience (0011)' => $hasColumn('sites', 'analytics_id'),
    ];
    foreach ($schema as $label => $present) {
        $check(
            "Schéma à jour : {$label}",
            $present,
            'Base en retard sur schema.sql : appliquer la migration correspondante. ' . $applyMigrations
        );
    }

    // Données : seules ces valeurs prouvent que les migrations ont réellement été appliquées,
    // puisque seed.sql ne les pose pas. Les pages éditoriales sont contrôlées plus bas.
    $check('Migration 0005 (commission) appliquée', $hasSetting('commission.base'), $applyMigrations);

    $admins = (int) $app->db()->scalar("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1");
    $check("Super Admin actif ({$admins})", $admins > 0, 'Créer un compte avec « php bin/create-user.php --role=super_admin … ».');

    $demo = $app->db()->scalar("SELECT value FROM settings WHERE site_id IS NULL AND setting_key = 'demo.active'");
    $check(
        'Aucune donnée de démonstration',
        $demo === null || json_decode((string) $demo) !== true,
        'Données fictives présentes (bin/seed-demo.php) : site non indexé et sans mesure d\'audience. Avant l\'ouverture : php bin/reset-before-launch.php --confirm.',
        false
    );

    $sites = $app->db()->select('SELECT s.id, s.name, s.status FROM sites s');
    $check('Au moins un site déclaré', $sites !== [], 'Renseigner la table sites et ses domaines.');

    foreach ($sites as $site) {
        $check(
            "Site « {$site['name']} » actif (statut : {$site['status']})",
            $site['status'] === 'active',
            'Un site en « maintenance » répond 503 côté public ; « disabled » répond 404.',
            $site['status'] !== 'maintenance'
        );
    }

    $domains = $app->db()->select('SELECT host, environment, is_primary FROM site_domains');
    $hosts = array_column($domains, 'host');
    printf("  \033[36mINFO\033[0m    Domaines déclarés : %s\n", $hosts === [] ? 'aucun' : implode(', ', $hosts));

    if ($expectedHost !== '') {
        $matching = array_values(array_filter($domains, static fn (array $d): bool => $d['host'] === $expectedHost));
        $found = $check(
            "Domaine « {$expectedHost} » déclaré",
            $matching !== [],
            "Ajouter ce domaine dans site_domains (Pays & sites), sinon le site répond 404 sur cet hôte."
        );
        if ($found) {
            $check(
                "« {$expectedHost} » en environnement « production »",
                $matching[0]['environment'] === 'production',
                "Hors production, tout le site est en noindex et robots.txt interdit l'indexation."
            );
        }
    } else {
        printf("  \033[36mINFO\033[0m    Relancer avec --host=immobilier.abidjan.net pour vérifier le domaine de production.\n");
    }

    $published = (int) $app->db()->scalar("SELECT COUNT(*) FROM properties WHERE status = 'published' AND deleted_at IS NULL");
    printf("  \033[36mINFO\033[0m    %d annonce(s) en ligne.\n", $published);

    // Pages éditoriales et légales. seed.sql ne les crée que vides et non publiées : un contenu
    // manquant signale des migrations non appliquées (0004 et 0009 à 0014) et bloque, alors qu'une
    // page rédigée mais non publiée reste une décision éditoriale, donc un simple avertissement.
    $expectedPages = ['about', 'how_it_works', 'faq', 'legal_notice', 'terms', 'privacy', 'cookies'];
    $legal = $app->db()->select(
        "SELECT code, slug, is_published, CHAR_LENGTH(COALESCE(content, '')) AS length FROM pages WHERE code IS NOT NULL"
    );
    $pagesByCode = array_column($legal, null, 'code');

    foreach ($expectedPages as $code) {
        $page = $pagesByCode[$code] ?? null;
        if ($page === null) {
            $check("Page « {$code} » présente", false, $applyMigrations);
            continue;
        }
        if ((int) $page['length'] === 0) {
            $check("Page « {$page['slug']} » rédigée", false, $applyMigrations);
            continue;
        }
        $check(
            "Page « {$page['slug']} » publiée",
            (int) $page['is_published'] === 1,
            'Une page légale non publiée répond 404 et son lien disparaît du pied de page.',
            false
        );
    }

    foreach ($pagesByCode as $code => $page) {
        if (!in_array($code, $expectedPages, true)) {
            printf("  \033[36mINFO\033[0m    Page système supplémentaire : %s.\n", $page['slug']);
        }
    }
} catch (Throwable $e) {
    $check('Connexion à la base', false, $e->getMessage());
}

// -- 5. Tâches planifiées ---------------------------------------------------------------------

$section('5. Tâches planifiées (à créer dans Plesk, une fois par jour)');

printf("  \033[36mINFO\033[0m    cd %s && php bin/expire-listings.php\n", $app->root);
printf("  \033[36mINFO\033[0m    cd %s && php bin/cleanup-uploads.php\n", $app->root);
printf("            Lancer expire-listings en premier, cleanup-uploads ensuite.\n");
printf("            Essai à blanc, sans rien modifier : ajouter --dry-run.\n");

// -- Bilan ------------------------------------------------------------------------------------

$section('À vérifier depuis un navigateur (hors de portée de ce script)');

foreach ([
    'Certificat HTTPS valide, et redirection de http:// vers https://',
    'Compression : curl -H "Accept-Encoding: gzip" -I https://…/assets/css/app.css → Content-Encoding: gzip',
    'Cache : le même appel renvoie Cache-Control: public, max-age=31536000, immutable',
    'https://…/.env et https://…/app/bootstrap.php répondent 403 ou 404, jamais le contenu du fichier',
    'https://…/robots.txt autorise l\'indexation et pointe vers le sitemap',
    'https://…/sitemap.xml se charge et liste les annonces',
    'Une page publique ne pose PAS de cookie de session',
    'Envoi d\'un email de test : php bin/mail-test.php --to=…',
] as $item) {
    printf("  ▢ %s\n", $item);
}

printf("\n\033[1mBilan : %d échec(s), %d alerte(s).\033[0m\n", $failures, $warnings);

if ($failures > 0) {
    echo "Le site ne doit pas être ouvert au public tant que les échecs ne sont pas corrigés.\n";
    exit(1);
}

echo $warnings > 0
    ? "Aucun blocage. Regarder les alertes avant l'ouverture.\n"
    : "Environnement conforme.\n";
