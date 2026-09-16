# immobilier.abidjan.net

Plateforme d'annonces immobilières **agrégatrice et multisite** : des agences partenaires déposent leurs
biens, l'équipe les vérifie, les visiteurs cherchent et contactent directement les agences.
Première mise en ligne en **Côte d'Ivoire** ; l'architecture est prévue pour ouvrir d'autres pays
(Sénégal, Cameroun, Togo, Bénin…) sur le même code, avec un domaine, un référentiel et une devise par pays.

Production : **https://immobilier.abidjan.net** (sous-domaine, hébergement Plesk).

> Il n'existe **aucune inscription publique ni compte visiteur**. Seuls les comptes internes
> (Super Admin, Admin Pays, agences partenaires) se connectent, au back-office `/cmsadmin`.

## Documentation

| Document | Contenu |
|---|---|
| [`docs/cahier-des-charges.md`](docs/cahier-des-charges.md) | Périmètre fonctionnel. **Fait foi.** |
| [`docs/PLAN.md`](docs/PLAN.md) | Plan par phases et lots, état d'avancement, pré-requis client |
| [`docs/database.md`](docs/database.md) | Schéma de la base, choix de conception, règles métier, migrations |
| [`CLAUDE.md`](CLAUDE.md) | Conventions de développement, direction artistique, méthode de travail |
| `/styleguide` (en local) | Charte graphique de référence : couleurs, typographie, composants `im-` |

## Stack

- **PHP 8.2+ natif**, MVC maison (Router, Controllers, Models, Services, Middlewares), autoload Composer PSR-4, PSR-12, `declare(strict_types=1)`.
- **MySQL 8 / MariaDB via PDO** : requêtes préparées systématiques, `utf8mb4`, dates en UTC.
- Front : HTML5 sémantique, **Bootstrap 5.3 comme socle technique** compilé en SCSS, JavaScript **vanilla**, **zéro CDN** (polices, icônes, scripts auto-hébergés). Cartographie : Leaflet + OpenStreetMap.
- Images : **GD** — redimensionnement et conversion WebP (`App\Services\ImageUploader`).
- Emails : PHPMailer (SMTP), pilote `log` en développement.
- Dépendances volontairement minimales : `phpmailer/phpmailer` ; en développement `scssphp/scssphp` et `twbs/bootstrap`.

## Installation locale (MAMP)

Environnement de référence : **MAMP**, Apache 2.4, PHP 8.3, MySQL 8 sur le port 8889 → http://localhost:8888/
Le `DocumentRoot` pointe sur la racine du projet ; le `.htaccess` racine sert tout depuis `public/`,
qui est le `DocumentRoot` en production.

```bash
# 1. Dépendances
/Applications/MAMP/bin/php/composer install

# 2. Configuration
cp .env.example .env          # puis renseigner DB_*, APP_URL, SMTP_*

# 3. Base de données
MYSQL=/Applications/MAMP/Library/bin/mysql80/bin/mysql
$MYSQL -uroot -proot -h127.0.0.1 -P8889 -e "CREATE DATABASE immobilier_abidjan_net CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
$MYSQL -uroot -proot -h127.0.0.1 -P8889 --default-character-set=utf8mb4 immobilier_abidjan_net < database/schema.sql
$MYSQL -uroot -proot -h127.0.0.1 -P8889 --default-character-set=utf8mb4 immobilier_abidjan_net < database/seed.sql
for m in database/migrations/*.sql; do $MYSQL -uroot -proot -h127.0.0.1 -P8889 immobilier_abidjan_net < "$m"; done

# 4. Premier compte d'administration
php bin/create-user.php --role=super_admin --email=… --first-name=… --last-name=…
```

Sans MAMP : `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8765 -t public bin/dev-server.php`
(plusieurs workers obligatoires : les requêtes en arrière-plan bloqueraient un serveur mono-processus).

## Commandes

```bash
php bin/build-css.php           # resources/scss → public/assets/css/app.css (--dev : lisible)
php bin/build-icons.php         # régénère le sprite d'icônes Phosphor
php bin/create-user.php         # compte interne (--role=super_admin|country_admin --country=CI)
php bin/cache-clear.php         # vide storage/cache (après déploiement ou écriture directe en base)
php bin/expire-listings.php     # CRON quotidien : expiration des annonces + relances (--dry-run)
php bin/cleanup-uploads.php     # CRON quotidien : purge des photos jamais rattachées (--hours=24)
php bin/mail-test.php --to=…    # vérification de la configuration SMTP
php -l <fichier>                # contrôle de syntaxe avant commit
```

**Pendant un test qui crée des comptes, des agences ou des annonces : passer `MAIL_MAILER=log` dans `.env`**
(les messages sont écrits dans `storage/mail/*.eml`), puis rétablir `smtp`.

## Organisation du code

```
app/
  Core/          Noyau : routeur, PDO, session, CSRF, vues, i18n, erreurs, journal
  Controllers/   Front/ (site public) · Cmsadmin/ (back-office) · Preview/ (maquettes locales)
  Services/      Dépôts et règles métier (annonces, agences, contacts, statistiques, envoi d'images…)
  Models/        Site, Country, User
  Middlewares/   SiteResolver et CSRF (globaux) · Authenticate, RequireRole…
  Views/         front/ · cmsadmin/ · emails/
  Support/       Validator, Paginator, Str, helpers (e(), __(), url(), site()…)
bin/             Scripts CLI et CRON
config/          app, auth, mail, database, menu du back-office
database/        schema.sql (référence) · seed.sql (référentiels CI) · migrations/
lang/            fr.php (référence) · en.php — aucune chaîne d'interface en dur dans les vues
public/          DOCUMENT ROOT : front controller, assets compilés, uploads
resources/scss/  Sources de style ; _tokens.scss est la source unique des couleurs et de la typographie
routes/          web.php (site public) · cmsadmin.php (back-office)
```

## État d'avancement

Phase 1 (MVP Côte d'Ivoire) : **lots 0.1 à 1.10 terminés** — socle applicatif, multisite, authentification,
référentiels, agences et comptes, module annonces avec workflow de validation et révisions, espace agence,
accueil du site public, page de résultats (filtres multicritères, tri, pagination, vues grille / liste /
carte, favoris visiteurs) et fiche annonce (galerie, critères, situation juridique, carte, demande de
contact, biens similaires). Lot suivant : **1.11, annuaire des agences, pages statiques et formulaires
publics**. Le détail lot par lot est dans [`docs/PLAN.md`](docs/PLAN.md).

## Règles à respecter

- Toute table métier porte `country_id` ; **toute requête publique filtre sur le pays du site courant**.
- Jeton CSRF sur chaque formulaire, échappement de sortie systématique (`e()`), contrôle d'accès côté
  serveur sur chaque action (rôle + pays + agence propriétaire) — l'interface n'est jamais une protection.
- `property_private_details` (notaire, référence de dossier) n'est **jamais** joint à une requête publique.
- Toute évolution du schéma passe par une migration numérotée dans `database/migrations/`, répercutée
  dans `schema.sql` et documentée dans `docs/database.md`.
- Travail par lots directement sur `main`, commits atomiques en français.

## Licence

Projet propriétaire — Weblogy. Les dépendances tierces conservent leur licence d'origine
(Bootstrap MIT, Phosphor Icons MIT, Plus Jakarta Sans SIL OFL 1.1, Leaflet BSD-2-Clause,
StarAdmin 2 pour le back-office :
voir `public/cmsadmin/assets/THIRD-PARTY-LICENSES.md`).
