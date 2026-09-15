# CLAUDE.md — immobilier.abidjan.net

Plateforme d'annonces immobilières **agrégatrice et multisite** (Côte d'Ivoire d'abord, puis Sénégal, Cameroun, Togo, Bénin…).
Production : **https://immobilier.abidjan.net** (sous-domaine, hébergement Plesk).
Langue du projet : **français** (code en anglais, textes, commentaires métier et commits en français).

## Documents de référence — à relire avant toute fonctionnalité

| Document | Rôle |
|---|---|
| `docs/cahier-des-charges.md` | **Périmètre fonctionnel. Fait foi.** Ne rien ajouter hors périmètre sans demander. |
| `docs/PLAN.md` | Plan de projet par phases/lots + état d'avancement (à tenir à jour). |
| `docs/database.md` | Schéma de BDD : tables, relations, choix de conception, conventions, migrations. **À relire avant toute requête ou migration.** |
| `docs/brand/` | Logo complet + symbole (maison dans un cercle). |
| Captures laforet.com (hors dépôt) | `/Users/emmanuelkassi/Documents/WP-WEBLOGY/WebSite/Immobilier Abidjan.net/*.png` — accueil, liste, fiche, connexion, inscription. **Inspiration ergonomique uniquement.** |
| Template admin source | `/Users/emmanuelkassi/Documents/KP/Templates/staradmin-2-free/dist` (base de `cmsadmin`). |

## État d'avancement (à tenir à jour à chaque lot)

- **Terminés** : 0.1 cadrage · 0.2 schéma BDD · 0.3 charte graphique · 0.4 back-office StarAdmin rethémé · 1.1 socle applicatif · 1.2 multisite · 1.3 authentification · 1.4 référentiels (pays & sites, géographie, catalogue) · 1.5 agences, comptes, demandes de partenariat · 1.6 module annonces (formulaire dynamique, photos, carte, validation, révisions, expiration) · 1.7 espace agence (tableau de bord, profil de l'agence, demandes de contact). Détail et livrables : `docs/PLAN.md`.
- **Prochain lot : 1.8 — accueil du site public** (en-tête, hero diaporama + recherche, biens à la une, dernières annonces, catégories phares, chiffres clés, agences en vedette, pied de page SEO). Le socle est prêt : maquette `front/pages/home-mockup.php` et `partials/hero.php` (lot 0.3) à brancher sur des données réelles, `PropertyRepository`, `StatsRepository`, `CatalogRepository`, `AgencyRepository` ; `HomeController` renvoie encore un 503 hors prévisualisation. **Rappel : requêtes publiques toujours `WHERE country_id = :country` et `revision_id IS NULL`, jamais de jointure sur `property_private_details`.**
- **Reprise d'une session** : `git log --oneline -5` pour le contexte, MAMP démarré (http://localhost:8888), se connecter à `/cmsadmin` avec son compte. **Avant tout test créant des comptes, agences ou annonces : `MAIL_MAILER=log` dans `.env`**, puis rétablir `smtp` et supprimer ses données de test à la fin.
- **Décisions en attente du client** (ne pas trancher seul) :
  - taux et mode de commission, durée de vie d’une annonce (défaut 90 j), photos d’Abidjan libres de droits, accès Plesk/DNS, textes légaux, relecture du référentiel des quartiers.
- **Décidé par le client (15/09/2026)** : modification d’une annonce publiée = **révision** — la version en ligne reste visible jusqu’à validation (migration `0002`, table `property_revisions`).
- **Choix pris par défaut, signalés au client, à confirmer** : sous-catégorie sans transaction = transactions de sa famille ; agences gérées sur le pays du site connecté (pas de sélecteur de pays, contrairement à la géographie) ; comptes activés uniquement par invitation email (pas de mot de passe provisoire affiché, sauf `bin/create-user.php`) ; **au moins une photo obligatoire pour enregistrer une annonce** (à assouplir si le client le souhaite, par exemple pour les terrains).
- **Avant la mise en production (lot 3.1)** : régénérer le mot de passe d’application Gmail (communiqué en clair pendant le développement) et remplacer `SMTP_PASSWORD` ; `CACHE_SITES_TTL=600`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_PREVIEW=false` ; CRON quotidiens `php bin/expire-listings.php` puis `php bin/cleanup-uploads.php` ; vérifier que `public/uploads/` est inscriptible et que son `.htaccess` est bien appliqué (Plesk/nginx).
- **Base locale** : schéma + seed **et le compte Super Admin du client** (`ekassi@weblogy.com`). Ne plus la réinstaller sans demander : supprimer uniquement ses propres données de test. Après une réinstallation, recréer un compte avec `bin/create-user.php`.

## Règles métier non négociables

- **Aucune inscription publique, aucun compte visiteur.** Les pages « Créer un compte » de Laforêt ne sont PAS reprises côté public. La seule connexion est celle du back-office (`/cmsadmin`) pour les rôles internes. Favoris visiteurs = cookie/localStorage.
- Rôles : **Super Admin** · **Admin Pays / Modérateur** (limité à son `country_id`) · **Agence partenaire** (compte créé manuellement, ne voit que SES annonces) · **Visiteur**.
- Workflow annonce : agence crée/modifie → `pending` → `published` | `rejected` (motif obligatoire) → `unpublished` | `archived` (vendu/loué) | `expired` (durée configurable, 90 j par défaut). Annonces du Super Admin : publiées directement.
- Toute table métier (annonces, agences, catégories, leads…) porte `country_id`. Le site courant est résolu depuis `$_SERVER['HTTP_HOST']` → table `sites` → config pays (devise XOF/FCFA, langue, contacts, référentiel géo).
- Catégories et critères **dynamiques** (arborescence `property_categories` + attributs EAV léger) : jamais de champ spécifique codé en dur par type de bien.
- Critères juridiques ivoiriens (TF, ACD, ADU, lettre d'attribution, coutumier, litige) = champs de premier plan. Notaire / référence dossier = **jamais affichés en public**.
- Pas de paiement en ligne en v1. Alertes email = v2.

## Stack technique

- **PHP 8.2+ natif**, MVC maison (Router, Controllers, Models, Views, Services, Middlewares), autoload **Composer PSR-4**, style **PSR-12**, `declare(strict_types=1);`.
- **MySQL/MariaDB via PDO** uniquement, requêtes préparées **systématiques**, `utf8mb4`, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`.
- Dépendances Composer **minimales et justifiées** : `phpmailer/phpmailer` (emails, lot 1.3) ; en dev `scssphp/scssphp` (compilation Sass en PHP — pas de Node sur le poste) et `twbs/bootstrap` (sources SCSS). Pas de framework. Composer : `php /Applications/MAMP/bin/php/composer …`, `composer.lock` commité, `vendor/` ignoré.
- Front : HTML5 sémantique, **Bootstrap 5.3 comme socle technique uniquement** (grille, reboot, utilitaires, modal/offcanvas/collapse) compilé en SCSS avec les seuls modules utiles, JavaScript **vanilla** (Alpine.js toléré si justifié). Pas de jQuery côté public.
- Carte : **Leaflet + OpenStreetMap + markercluster**. Slider/galerie/lightbox : librairies légères **hébergées localement**.
- Images : **GD** (Imagick non disponible) — redimensionnement + WebP via `App\Services\ImageUploader`, stockage `public/uploads/{iso2}/…` (logos : `agences/{id}/`, annonces au lot 1.6).
- **Zéro CDN / zéro ressource externe** (polices, JS, CSS, icônes : tout est auto-hébergé), sauf tuiles OSM et services explicitement validés.

## Arborescence

Légende : ✅ existe · ⬜ à créer (lot indiqué dans `docs/PLAN.md`).

```
.htaccess             ✅ dev MAMP uniquement : bloque les dossiers internes, sert tout depuis public/
.env.example          ✅ modèle de configuration (copier en .env, jamais commité)
app/
  bootstrap.php       ✅ amorçage commun (autoload, .env, erreurs, UTC) → retourne App\Core\App
  Core/               ✅ App (noyau + services), Router, Request, Response, Config, Env, Database (PDO), Cache (fichiers), Session, Csrf, View, Translator, Logger, ErrorHandler, Middleware, Exceptions/HttpException
  Support/            ✅ Str (slug, code), Validator (formulaires), Paginator
  Support/helpers.php ✅ e(), __(), config(), env(), site(), settings(), url(), absolute_url(), route(), asset(), icon(), csrf_field(), csrf_token(), render_view(), cmsadmin_*(), format_*() (autoload Composer « files »)
  Views/cmsadmin/     ✅ layouts/ (app, auth) · partials/ (head, navbar, sidebar, footer, scripts, flash, page-header, status-badge, pagination, empty-state, auth-aside, password-field, field, switch, state-badge, row-actions) · pages/ (dashboard/{index (maquette), agency}, properties/{index,show,form,criteria}, auth, account, geo, catalog/{categories,attributes,features}, sites, agencies/{index,form,profile}, leads/{index,show}, users, partner-requests, errors)
  Views/front/        ✅ layouts/app · partials/ (header, footer, hero, search, property-card) · pages/ (home-mockup, styleguide, errors/error)
  Views/errors/debug.php ✅ détail d’exception (app.debug uniquement, jamais en production)
  Views/emails/       ✅ layout (HTML en ligne) · password-reset, invitation, notification (+ .text)
  Controllers/        ✅ Controller (base) · Front/HomeController (503 « en préparation » jusqu’au lot 1.8) · Cmsadmin/ (Controller de base, Dashboard, Auth, Password, Account, Site, Lead, Geo/{City,Commune,District}, Catalog/{Category,Attribute,Feature}, Agencies/{Agency,AgencyProfile,AgencyAccount,StaffUser,PartnerRequest}, Properties/{Property,PropertyAction,PropertyMedia}, Notification) · Preview/ (PROVISOIRE : tableau de bord fictif de l’équipe interne)
                      ⬜ Front (Search, Property, Agency, Page, Lead…) + Cmsadmin/ (Dashboard, Leads, Seo, Settings…)
  Middlewares/        ✅ SiteResolver, VerifyCsrfToken (globaux) · Authenticate, RedirectIfAuthenticated, RequireRole
  Models/             ✅ Site, Country, User · ⬜ modèles métier
  Services/           ✅ SiteRepository, Settings, CountryRepository, GeoRepository, CatalogRepository, AgencyRepository, PartnerRequestRepository, PropertyRepository, PropertyForm, PropertyWorkflow, PropertyPresenter, PendingUploads, LeadRepository, StatsRepository, Notifier, ImageUploader · Auth, UserRepository, PasswordHasher, PasswordReset, LoginThrottle, RateLimiter, ActivityLogger, Mailer, IpAddress · ⬜
bin/
  preview/            ✅ PROVISOIRE : données fictives (fixtures.php, front-fixtures.php) des contrôleurs Preview/, retirées module par module
  build-css.php       ✅ compile resources/scss → public/assets/css/app.css
  build-icons.php     ✅ génère le sprite d’icônes
  create-user.php     ✅ crée un Super Admin / Admin Pays (mot de passe provisoire affiché une fois)
  mail-test.php       ✅ envoie un email de test (vérification SMTP)
  expire-listings.php ✅ CRON quotidien : expiration des annonces + relance avant échéance (--dry-run)
  cleanup-uploads.php ✅ CRON quotidien : supprime les photos envoyées jamais rattachées (uploads/*/tmp)
  cache-clear.php     ✅ vide storage/cache (après déploiement ou modification directe en base)
  dev-server.php      ✅ routeur pour le serveur PHP intégré (alternative à MAMP)
config/
  app.php             ✅ environnement, debug, URL, langue, prévisualisation, session, cache, journaux
  auth.php            ✅ inactivité, « Rester connecté », lien de réinitialisation, longueur des mots de passe
  mail.php            ✅ pilote log (storage/mail/*.eml) | smtp, expéditeur
  database.php        ✅ connexion MySQL/MariaDB
  cmsadmin-menu.php   ✅ menu du back-office par rôle
  countries.php       ⬜
routes/               ✅ web.php (site public) · cmsadmin.php (back-office)
public/               DOCUMENT ROOT en production
  index.php, .htaccess  ✅ front controller (toutes les URL non statiques)
  assets/fonts/       ✅ Plus Jakarta Sans (woff2 variable + OFL)
  assets/css/app.css  ✅ CSS compilé (commité) — ne jamais éditer à la main
  assets/js/          ✅ site.js (en-tête, menu mobile, favoris, recherche) · hero.js (diaporama)
  assets/img/         ✅ icons.svg (sprite Phosphor) · brand/ (logos) · placeholder/ (photos PROVISOIRES, voir CREDITS.md)
  cmsadmin/assets/    ✅ back-office (StarAdmin 2 nettoyé + cmsadmin.css, cmsadmin.js, dashboard.js)
  uploads/            ✅ fichiers envoyés, git-ignorés sauf .htaccess (aucune exécution de script) · {pays}/agences/{id}/logo-*.webp · {pays}/annonces/{id}/{aléatoire}-{1600,800,400}.webp + document-*.pdf · {pays}/tmp/ (photos en attente)
resources/scss/       ✅ app.scss · abstracts/_tokens.scss (SOURCE UNIQUE des couleurs, typo, espacements) · abstracts/_mixins.scss · vendor/_bootstrap.scss · base/ · layout/ · components/ · pages/
lang/                 ✅ fr.php (référence et repli), en.php — aucune chaîne d'interface en dur dans les nouvelles vues
database/             ✅ schema.sql (référence v1, 38 tables) · seed.sql (référentiels CI) · migrations/ (évolutions 0002+) — voir docs/database.md
storage/              ✅ logs/app-AAAA-MM-JJ.log · cache/ (sites.php, ratelimit_…) · mail/ (emails en pilote log) — créés automatiquement, git-ignorés
docs/                 ✅ cahier-des-charges.md, PLAN.md, database.md, brand/
```

## Commandes

```bash
# `php` dans le PATH = Homebrew PHP 8.4 (CLI) ; MAMP sert le site en PHP 8.3.14 → rester compatible 8.2+
/Applications/MAMP/bin/php/composer install
php bin/build-css.php            # compile resources/scss → public/assets/css/app.css (minifié) · --dev : lisible
php bin/build-icons.php          # régénère public/assets/img/icons.svg (liste des icônes dans le script)
php bin/create-user.php --role=super_admin --email=… --first-name=… --last-name=…   # compte interne (Admin Pays : --role=country_admin --country=CI)
php bin/cache-clear.php          # vide storage/cache
php bin/expire-listings.php      # expiration + relances (CRON quotidien) · --dry-run
php bin/cleanup-uploads.php      # purge des photos temporaires (CRON quotidien) · --hours=24 --dry-run
php bin/mail-test.php --to=…     # email de test avec la configuration courante
php -l <fichier>                 # vérif syntaxe avant commit
```

**Environnement local : MAMP → http://localhost:8888/** (Apache 2.4, PHP 8.3.14, MySQL 8 sur le port 8889).
Le `DocumentRoot` MAMP pointe sur la racine du projet : le `.htaccess` racine bloque les dossiers internes (`app/`, `bin/`, `config/`, `docs/`, fichiers cachés…) et sert tout depuis `public/`, qui est le `DocumentRoot` en production. `public/.htaccess` envoie les URL non statiques vers `public/index.php`.

Prévisualisation avec **données fictives** (contrôleurs `app/Controllers/Preview/` + `bin/preview/`) ; seul le site courant (nom, pays, devise) vient de la base locale, nécessaire depuis le lot 1.2, routes déclarées **uniquement si `APP_ENV=local` et `APP_PREVIEW=true`** (ailleurs : accueil en 503, maquettes en 404) :
- Site public : http://localhost:8888/ (maquette d’accueil : hero + recherche + biens à la une) · http://localhost:8888/styleguide (**charte graphique de référence**)
- Back-office (**connexion réelle obligatoire** depuis le lot 1.3 : créer d’abord un compte avec `bin/create-user.php`) : http://localhost:8888/cmsadmin · `/cmsadmin/annonces` · `/cmsadmin/annonces/nouvelle` · `/cmsadmin/annonces/IAN-24531/modifier?erreurs=1` · `/cmsadmin/erreur-500`
- Rôle affiché = celui du compte connecté ; un Super Admin peut prévisualiser un autre rôle avec `?role=country_admin|agency|super_admin` (cookie, écrans fictifs uniquement — sans effet sur les droits réels).
- Emails : SMTP Gmail configuré dans `.env` (`MAIL_MAILER=smtp`, jamais commité). **Pendant des tests créant des comptes ou des demandes : passer `MAIL_MAILER=log`** (les messages sont alors écrits dans `storage/mail/*.eml`, sinon de vrais emails partent vers les adresses de test), puis revenir à `smtp`. Test d’envoi : `php bin/mail-test.php --to=…`. Avec Gmail, l’expéditeur (`MAIL_FROM_ADDRESS`) doit être le compte authentifié ou un alias validé ; quota d’envoi journalier limité (à surveiller pour les alertes v2).
- Chaque écran fictif est supprimé quand son module réel est livré (annonces → 1.6, tableau de bord → 1.12, accueil → 1.8).
- Alternative sans MAMP : `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8765 -t public bin/dev-server.php` (plusieurs workers obligatoires).
Base locale : **`immobilier_abidjan_net`** (MySQL MAMP, `root`/`root`, `127.0.0.1:8889`). Réinstallation : commandes dans `docs/database.md`. Client : `/Applications/MAMP/Library/bin/mysql80/bin/mysql` (en zsh, passer la commande dans un tableau, pas dans une chaîne).
`.env` (jamais commité, modèle `.env.example`) : `APP_ENV` (local|staging|production), `APP_DEBUG`, `APP_URL`, `APP_BASE_PATH`, `APP_LOCALE`, `APP_PREVIEW`, `DB_*`, `CACHE_SITES_TTL` (vide en local = pas de cache ; 600 en production), `SESSION_*`, `AUTH_IDLE_TIMEOUT`, `AUTH_REMEMBER_DAYS`, `MAIL_MAILER` (log|smtp), `SMTP_*`, `MAIL_FROM_*`. Une variable définie par le serveur (Plesk) prime sur `.env`.

### Socle applicatif (lot 1.1) — conventions

- **Route** : `routes/web.php` ou `routes/cmsadmin.php` → `$router->get('/annonces/{slug}-ref{id:\d+}', [PropertyController::class, 'show'], 'property.show')` ; groupes `['prefix', 'as', 'middleware']` ; URL via `route('property.show', [...])` (paramètres en trop → chaîne de requête). Les « / » finaux sont redirigés en 301.
- **Contrôleur** : étend `App\Controllers\Controller`, action `(Request $request, string $slug…): Response` (paramètres de route nommés) ; `$this->page($layout, $view, $data, $layoutData)`, `redirectToRoute()`, `flash()`. Services : `$this->app->db()`, `session()`, `csrf()`, `translator()`, `logger()`, `config`.
- **Middleware** : implémente `App\Core\Middleware` ; déclaration `Classe::class` ou `'Classe:arg1,arg2'`. `VerifyCsrfToken` est global : tout POST/PUT/PATCH/DELETE sans `csrf_field()` (ou en-tête `X-CSRF-Token`) → 419.
- **Erreurs** : lever `HttpException(404|403|419|429|503…)` ; toute autre exception → journal `storage/logs/` + page 500 (détail seulement si `APP_DEBUG` hors production). AJAX (`Accept: application/json`) → réponse JSON.
- **Session** : ouverte seulement si nécessaire (jeton CSRF, flash, connexion) ou si le navigateur en a déjà une ; les pages publiques sans formulaire restent sans cookie (cachables).
- **BDD** : `$db->select/selectOne/scalar/execute/insert/transaction` — requêtes préparées, session SQL en UTC. Connexion ouverte à la première requête.
- **Multisite (lot 1.2)** : `SiteResolver` (global, avant le routage) cherche l’hôte sans port dans `site_domains` → `site()` (`App\Models\Site`, avec `->country` : devise, indicatif, fuseau). Hôte inconnu ou site `disabled` → 404 ; `maintenance` → 503 côté public, /cmsadmin accessible ; domaines `local` acceptés seulement si `APP_ENV=local` ; alias de production non principal → 301 vers le domaine principal ; domaines hors production → `noindex`. Langue de l’interface = `sites.default_locale`. Requêtes publiques : **toujours** `WHERE country_id = :country` avec `site()->country->id`. Paramètres : `settings('listing.lifetime_days', 90)` (global surchargé par site ; NULL en base = défaut). `format_price()` prend la devise du pays. URL absolues : `absolute_url()` (jamais `$_SERVER['HTTP_HOST']`). Après écriture dans `sites`, `site_domains`, `countries` ou `settings` : `app()->sites()->flush()`.
- **Authentification (lot 1.3)** : routes du back-office dans le groupe `Authenticate` (écrans hors session : `RedirectIfAuthenticated`) ; rôles par route avec `'RequireRole:super_admin'`, `'RequireRole:staff'` (Super Admin + Admin Pays) ou `'RequireRole:agency'` (responsable + agent) **et** contrôle de propriété dans l’action (`$user->canAccessCountry()`, `agency_id`). Contrôleurs du back-office : étendre `App\Controllers\Cmsadmin\Controller` (`$this->user($request)`, `render()` = layout avec menu, `renderAuth()` = écran hors session). Rôles en base `super_admin|country_admin|agency_owner|agency_agent` ; rôle du menu `User::menuRole()` = `super_admin|country_admin|agency`. Un compte Admin Pays ou agence ne peut se connecter que sur le site de son pays. Mots de passe : Argon2id (bcrypt à défaut), 12 caractères minimum, provisoire → changement forcé. Blocage : `settings` `security.login_max_attempts` / `security.login_lockout_minutes` (+ plafond par IP). « Rester connecté » : sélecteur/validateur haché, rotation à chaque usage, révocation totale en cas de réutilisation. Changement ou réinitialisation du mot de passe → autres sessions et jetons révoqués. Journal : `app()->activity()->log('entite.action', $userId, $countryId, 'entite', $id, …, request: $request)` pour **toute** écriture du back-office.
- **Écrans CRUD du back-office (lot 1.4, modèles à copier)** : liste `pages/geo/index.php` (onglets, filtres GET, pagination serveur, actions de ligne), formulaire `pages/catalog/attributes/form.php` (sections numérotées, colonne « Publication », lignes répétables). Contrôleur : lecture/validation via `App\Support\Validator` (`$v->required('name')->maxLength('name', 100)…`, erreurs `[champ => message]`, réaffichage en 422), écriture par un dépôt `Services/*Repository`, puis `$this->log($request, 'entite.action', 'entite', $id, $libelle, $this->diff($avant, $apres))`, `$this->flash()` et redirection 303 (`$this->backTo()` conserve les filtres via `_back`/`retour`). Champs : `cmsadmin_partial('field', [...])`, `'switch'`, `'state-badge'`, `'row-actions'` (actions POST avec `csrf_field()` et `data-confirm`). JS : `data-slug-source`, `data-repeat-add|list|template|remove`, `data-icon-preview`, `data-toggle-options`.
- **Règles des référentiels** : un élément utilisé (annonces, agences, sous-niveaux) **ne se supprime pas**, il se désactive (message expliquant l’usage) ; codes techniques (catégories, critères, options utilisées, équipements, pays ISO, sites) **fixés à la création** ; slug modifiable avec avertissement (URL publiques) ; type de saisie d’un critère verrouillé dès qu’une valeur existe ; stockage `column` limité aux 5 colonnes indexées. Géographie : rôle `staff`, toujours limitée au pays (Admin Pays : le sien ; Super Admin : pays choisi, mémorisé en session). Catalogue et Pays & sites : `super_admin`. Transactions : déclarées sur la famille, une sous-catégorie sans transaction reprend celles de sa famille ou les restreint. Pays & sites : impossible de désactiver le site ou de supprimer le domaine de la session en cours, ni de désactiver un pays qui a un site actif ; toute écriture vide le cache des sites.
- **Agences & comptes (lot 1.5)** : agences, comptes d’agence et demandes de partenariat = rôle `staff`, **toujours limités au pays du site** (`site()->country->id`, jamais un paramètre de requête) ; utilisateurs internes = `super_admin`. Un compte est créé **sans mot de passe utilisable** puis reçoit une **invitation** (`PasswordReset::invite()`, lien 72 h vers l’écran de réinitialisation) ; « Renvoyer l’invitation » en émet une nouvelle. Garde-fous : pas de modification de son propre rôle, pas de désactivation/suppression de soi-même, toujours un Super Admin actif. Suppression d’un compte = logique, adresse email libérée (`supprime-{id}-…@invalid.local`) ; désactivation ou changement d’email/rôle = jetons révoqués. Agence suspendue/fermée/supprimée : ses comptes perdent l’accès à la requête suivante ; suppression seulement sans annonce. Une demande de partenariat est approuvée en créant l’agence depuis la demande (`/cmsadmin/agences/ajouter?demande={id}`).
- **Envoi d’images** : `app()->images()->check($request->file('champ'), $maxOctets)` (clé d’erreur `upload.*` ou `'none'`), puis `storeWebp($file, '{iso2}/…', 'prefixe', $largeur, $hauteur)` → chemin relatif à `public/` à stocker en base ; `delete($ancienChemin)` au remplacement. Type réel par finfo, ré-encodage GD (EXIF supprimé, orientation appliquée), nom aléatoire. Formulaire en `enctype="multipart/form-data"`.
- **Pastilles du menu** : `Cmsadmin\Controller::counters()` (demandes de partenariat nouvelles ; annonces en attente et contacts à ajouter avec leurs modules) ; une entrée parente affiche le total de ses sous-entrées.
- **Annonces (lot 1.6)** : `PropertyRepository` (lecture/écriture), `PropertyForm` (validation → « charge utile » fields/attributes/features/images/private/document/featured), `PropertyWorkflow` (règles métier + historique + notifications), `PropertyPresenter` (affichage et différences). Statuts : `pending → published | rejected → unpublished | archived | expired`. Création : agence → en attente ; équipe → publiée si `settings` `workflow.auto_publish_*`. **Modification d’une annonce publiée par une agence = révision** (`property_revisions`, la version en ligne reste visible ; photos proposées portées par `property_images.revision_id`, jamais publiques) ; une modification par l’équipe s’applique directement et rend la révision caduque (`superseded`). Rejet = motif obligatoire. Prolongation (`listing.lifetime_days`) sans revalidation, archivage vendu/loué, mise en avant réservée à l’équipe et aux annonces publiées. Suppression : logique ; une agence ne supprime que ses annonces jamais publiées.
- **Critères dynamiques** : `CatalogRepository::formSchema($categorieId, $paysId)` = transactions autorisées (héritées de la famille) + critères de la famille et de la sous-catégorie avec options actives ; le formulaire recharge le fragment `pages/properties/criteria.php` quand la catégorie change. Stockage : `storage = 'column'` → colonne indexée de `properties`, sinon `property_attribute_values` (une ligne par option pour un multi-choix).
- **Photos** : envoyées en arrière-plan (`POST /cmsadmin/annonces/photos`), ré-encodées en 3 largeurs WebP (1600/800/400) dans `uploads/{pays}/tmp/`, identifiées par un jeton lié à la session (`PendingUploads`) ; elles ne sont rattachées qu’à l’enregistrement (`uploads/{pays}/annonces/{id}/`). `property_images.path` ne contient pas le suffixe de taille : l’URL s’écrit `{path}-800.webp`. Requêtes publiques : **toujours** `revision_id IS NULL`.
- **Notifications** : `app()->notifier()->notify($userIds, $type, $titre, $corps, $lien, site(), $emailTo)` → table `notifications` (cloche de la barre supérieure) + email via `emails/notification`. Destinataires : `staffRecipients($paysId)` (Admins Pays, sinon Super Admins) et `propertyRecipients($annonce)` (responsables de l’agence, agent en charge, auteur).
- **Carte** : Leaflet auto-hébergé (`vendors/leaflet`, plugin `'leaflet'`), tuiles OpenStreetMap (seule ressource externe autorisée).
- **Espace agence (lot 1.7)** : tableau de bord réel pour les comptes agence (`DashboardController::index` ; l'équipe interne garde la maquette de prévisualisation jusqu'au lot 1.12) — annonces par statut, audience 30 jours (`StatsRepository`, `property_stats_daily`), rejets à corriger, annonces qui expirent, derniers contacts, rappel du profil incomplet. **Profil de l'agence** (`/cmsadmin/profil-agence`) : le responsable (`agency_owner`) modifie présentation, logo, coordonnées et zones ; l'agent est en lecture seule (POST → 403) ; nom, RCCM, statut et vérification restent à l'équipe (journal `agency.profile_updated` + notification de la cloche). **Demandes de contact** (`/cmsadmin/contacts`, `LeadRepository`) : liste filtrée et paginée, détail, suivi (`new → read → in_progress → closed | spam`) et affectation ; l'équipe voit le pays, une agence uniquement ses demandes (autre agence → 404) ; pastille `new_leads`. Les formulaires publics qui créent ces demandes arrivent au lot 1.11 : d'ici là les écrans sont vides (états vides explicites, pas de courbe à zéro).
- **Motifs de route** : jamais d’accolade de quantificateur dans un paramètre (`{id:\d{1,10}}` est refusé par le routeur) — écrire `{reference:[A-Z][A-Z0-9]*-[0-9]+}`.
- **Emails** : `app()->mailer()->send($to, $sujet, $html, $texte)` ; vues dans `app/Views/emails/` (styles en ligne, version texte obligatoire). Rendu HTML : `$view->page('emails/layout', 'emails/…', $data, ['site' => site(), 'preheader' => …])`.
- **Traductions** : `__('errors.404.title', ['name' => …])` ; toute clé ajoutée dans `lang/fr.php` l’est aussi dans `lang/en.php`. Les vues maquettes existantes seront traduites quand leur module réel sera réalisé.
- En-têtes de sécurité posés par `App` (nosniff, `X-Frame-Options: SAMEORIGIN`, Referrer-Policy, Permissions-Policy, HSTS en HTTPS, `X-Robots-Tag: noindex` sur /cmsadmin).

## Sécurité — checklist à chaque lot

- Jeton **CSRF** sur tout formulaire POST ; échappement de sortie systématique via un helper `e()` (`htmlspecialchars` ENT_QUOTES, UTF-8).
- `password_hash`/`password_verify`, `session_regenerate_id` à la connexion, cookies `HttpOnly`/`Secure`/`SameSite=Lax`, **limitation des tentatives** de connexion.
- Contrôle d'accès **côté serveur** sur chaque action (rôle + `country_id` + `agency_id` propriétaire) — ne jamais se fier à l'UI.
- Uploads : vérification MIME réelle (`finfo`), ré-encodage GD, nom aléatoire, aucune exécution PHP dans `uploads/`.
- Honeypot (+ reCAPTCHA si validé) sur formulaires publics. `/cmsadmin` en `noindex` et exclu du sitemap.
- Journal `activity_logs` pour toute action d'écriture du back-office.

## SEO & performance (mobile 3G/4G = cible prioritaire)

- URLs propres : `/acheter/appartement/abidjan/cocody`, `/louer/…`, `/annonces/villa-4-pieces-cocody-abidjan-ref123`, `/agences/{slug}`.
- `sitemap.xml` dynamique, Schema.org `RealEstateListing`, Open Graph, balises canoniques, un seul `h1` par page.
- Budget : LCP < 2,5 s en 4G, CSS critique inline pour le hero, `loading="lazy"` hors écran, `srcset` WebP, polices `woff2` en `font-display: swap` (2 graisses max préchargées).

---

## Direction artistique — À APPLIQUER SUR TOUT LE PROJET (front ET cmsadmin)

**Posture : webdesigner / expert UI-UX senior.** Rendu **moderne, minimaliste, épuré, élégant, premium, naturel**. Inspiré de laforet.com (ergonomie, hiérarchie, structure des fiches et filtres) **sans copier** ni code ni mise en page à l'identique, et **plus impressionnant** esthétiquement.
**Interdits absolus : aspect « template Bootstrap » et aspect « design généré par IA ».**

### Palette (couleurs laforet.com, relevées sur les captures)

```scss
$navy:        #143D8A; // titres, top-bar, footer, texte fort (≈ bleu du logo #003488)
$blue:        #2650DB; // CTA principaux, onglet actif, liens d'action
$blue-bright: #106AFF; // hover, focus ring, mots mis en relief dans un titre
$blue-tint:   #E0EEFF; // fonds de badges/puces, états sélectionnés
$mist:        #E8EFF6; // fonds de section alternés, bandeaux de services
$snow:        #F6F9FC; // fond de page secondaire, footer
$line:        #E5E7EB; // bordures, séparateurs
$red:         #E02020; // badges « Nouveauté » uniquement (≈ rouge logo #F40024)
$ink:         #1B2540; $muted: #5E6B85; $white: #FFFFFF;
```
Couleurs définies **une seule fois** en variables SCSS + custom properties CSS ; aucune couleur en dur dans les composants. Le rouge reste rare (badges, erreurs).

### Typographie
**Plus Jakarta Sans** (validée, variable woff2 auto-hébergée dans `public/assets/fonts/`). Échelle fluide définie dans `_tokens.scss` et exposée en classes : `.im-display` (40→76 px), `.im-h1` (32→52), `.im-h2` (26→38), `.im-h3`, `.im-h4`, `.im-lead`, `.im-small`, `.im-eyebrow`. Titres serrés (−2 à −4,5 %), corps 16 px, chiffres tabulaires (`.im-num`) pour prix et surfaces. Pas d'Inter, Roboto, Poppins ou Open Sans.

### Principes qui évitent l'effet template / IA
- Bootstrap = moteur invisible : **aucune** classe visuelle Bootstrap laissée telle quelle (`.btn-primary`, `.card`, `.navbar` par défaut, `.badge`, `.shadow`…). Composants maison préfixés **`im-`** (`.im-btn`, `.im-card-property`, `.im-hero`…).
- Photographie réelle et locale (Abidjan : Cocody, Plateau, Riviera, Assinie…) comme matière principale ; pas d'illustrations 3D, pas de stock générique.
- **Refusés** : dégradés violets/néon, glassmorphism généralisé, blobs, orbes lumineux, icônes « sparkles ✨ », emojis, cartes toutes identiques avec pastille d'icône + titre + 2 lignes, ombres lourdes, arrondis excessifs partout, textes marketing creux (« Découvrez l'excellence… »), animations gratuites.
- **Recherchés** : grille rigoureuse avec respirations généreuses, asymétries maîtrisées, contrastes d'échelle typographique, filets fins (1 px `$line`), rayons sobres (8–14 px), ombres très diffuses et rares, micro-interactions discrètes (150–250 ms, `ease-out`), respect de `prefers-reduced-motion`.
- Densité d'information à la Laforêt sur les cartes annonce (type, prix, ville/quartier, surface • pièces • chambres, CTA Message/WhatsApp/Appeler) mais présentation plus aérée et hiérarchisée.
- Icônes : **un seul jeu** côté public, **Phosphor Icons « light »** en sprite local (`<?= icon('nom') ?>`, liste sur /styleguide ; en ajouter via `bin/build-icons.php`). Le back-office garde Material Design Icons. Accessibilité AA (contrastes, focus visibles, `aria-*`, cibles tactiles ≥ 44 px).
- **Avant de créer un composant, vérifier /styleguide** : réutiliser `im-btn`, `im-badge`, `im-chip`, `im-field`/`im-control`, `im-tabs`, `im-card`, `im-search`, `im-section-head`… ; tout nouveau composant est ajouté à la charte. Couleurs et tailles uniquement via les variables de `_tokens.scss` (jamais de valeur en dur).
- **Photos** : `public/assets/img/placeholder/` = photos libres **provisoires** pour les maquettes (crédits dans `CREDITS.md`), jamais pour de vraies annonces ; les légendes de lieu ne sont affichées que pour des photos réellement prises à l’endroit indiqué.

### Hero de l'accueil (slide)
Plein écran sobre (≈ 88vh desktop, 70vh mobile) : **slides photo en fondu lent** (crossfade + léger zoom Ken Burns ≤ 1,06), voile navy très subtil pour la lisibilité, titre éditorial court + compteur d'annonces réelles, **module de recherche flottant** (onglets Acheter / Louer / Location meublée · type de bien · ville/commune · budget → Rechercher). Indicateurs de slide minimalistes (fines barres de progression + n°/total), légende discrète du lieu photographié. Pause au survol/focus, image 1 servie en priorité (LCP), les suivantes en lazy. Aucune flèche ni point Bootstrap par défaut.
**Réalisé (lot 0.3)** : `app/Views/front/partials/hero.php` + `components/_hero.scss` + `assets/js/hero.js` — titre en deux graisses (« Votre prochaine adresse » / « à Abidjan. »), bouton pause (WCAG 2.2.2), flèches clavier, `prefers-reduced-motion`, images WebP 1920 (desktop) / 960×1200 (mobile). Les diapositives viendront de la table `banners` (placement `home_hero`).

### Back-office `cmsadmin`
Même palette et même typographie que le front, UI calme et dense, lisible. StarAdmin 2 fournit la **structure** (layout, sidebar, tables, formulaires) ; l'**habillage** est réécrit via une feuille d'override. Menu latéral construit selon le rôle connecté.

- **Ne jamais modifier** `public/cmsadmin/assets/css/style.css` (template compilé/minifié) : tout passe par `css/cmsadmin.css`, qui ne contient des couleurs que dans `:root`.
- Vues : `app/Views/cmsadmin/layouts/` (`app`, `auth`), `partials/` (navbar, sidebar, page-header, status-badge, pagination, empty-state, flash, field, switch, state-badge, row-actions), `pages/<module>/`. Modèles de référence à copier (écrans réels) : `pages/geo/index.php` et `pages/agencies/index.php` (listes), `pages/catalog/attributes/form.php` et `pages/agencies/form.php` (formulaires, envoi de fichier). `pages/leads/index.php` et `pages/leads/show.php` (liste + détail avec panneau de suivi), `pages/agencies/profile.php` (formulaire limité à un périmètre) et `pages/dashboard/agency.php` (chiffres clés, graphique, listes) complètent ces modèles. `pages/dashboard/index.php` reste une maquette fictive (lot 1.12).
- Menu : `config/cmsadmin-menu.php` (entrées, rôles autorisés, compteurs). Masquer une entrée n'est pas un contrôle d'accès.
- Helpers disponibles : `e()`, `__()`, `site()`, `settings()`, `url()`, `absolute_url()`, `route()`, `cmsadmin_url()`, `cmsadmin_asset()` (versionné), `render_view()`, `cmsadmin_partial()`, `csrf_field()`, `format_price()`, `format_number()` (`app/Support/helpers.php`).
- Plugins chargés à la demande via `$plugins` (`'select2'`, `'chart'`). Select2 : attribut **`data-im-select`** (jamais `data-select2`, qui entre en conflit avec la bibliothèque).
- Listes : pagination et filtres **côté serveur** (pas de DataTables : volumes importants). Dates : `<input type="date">` natif (pas de datepicker jQuery).
- jQuery est toléré **uniquement** dans `cmsadmin` (dépendance du template), jamais côté public.
- Licences tierces : `public/cmsadmin/assets/THIRD-PARTY-LICENSES.md` (à tenir à jour).

---

## Méthode de travail

- Lire la section concernée du cahier des charges + `docs/PLAN.md` avant chaque lot ; mettre à jour le statut du lot à la fin.
- Travail par lots **directement sur `main`** (pas de branches de fonctionnalité) : vérifications (`php -l`, scénario par rôle, contrôle mobile 375 px, rendu sur http://localhost:8888) → commits atomiques en français.
- Toute évolution du schéma BDD passe par une migration numérotée dans `database/migrations/` (à partir de `0002_…`) + mise à jour de `schema.sql` et de `docs/database.md`.
- Données : requêtes préparées PDO ; ne **jamais** joindre `property_private_details` dans une requête du site public ; toujours filtrer par `country_id` du site courant ; dates en UTC.
- En cas de doute sur le périmètre, une décision métier ou un choix graphique structurant : **demander avant de coder**.

### Vérification visuelle (Chrome headless)
- Capture : `"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --disable-gpu --hide-scrollbars --user-data-dir=<dossier temporaire> --window-size=1440,1500 --virtual-time-budget=3000 --screenshot=<fichier.png> <url>`, encadré par `perl -e 'alarm 40; exec @ARGV'` (Chrome peut ne pas rendre la main).
- Mobile : Chrome headless impose une largeur de fenêtre minimale (~500 px) → pour tester 390 px, capturer une page HTML contenant des `<iframe width="390">`. Depuis le lot 1.1, `X-Frame-Options: SAMEORIGIN` bloque l’iframe depuis `file://` : servir cette page et relayer les URL par un petit proxy PHP lancé dans le scratchpad (`php -S 127.0.0.1:8767 proxy.php`, qui ne recopie pas les en-têtes). Tuer ensuite les processus Chrome restants (`pgrep -f <user-data-dir> | xargs kill -9`) : le tableau de bord (Chart.js) peut empêcher Chrome de rendre la main.
- Erreurs JS : `--enable-logging=stderr --v=0 --dump-dom <url>` puis filtrer `CONSOLE`.
- Faire les captures dans le scratchpad, jamais dans le dépôt.
- Écrans connectés : le proxy du dossier temporaire peut relayer le cookie de session d’un fichier `curl -c` (en-tête `Cookie`). Les tests par curl écrivent en base locale : **la base contient désormais le compte réel du client** — ne jamais la réinstaller sans demander ; supprimer seulement ses propres données de test (comptes `@test.local`, agences et annonces créées, fichiers `public/uploads/`).

### Git
- Dépôt **propre au projet** (racine `immobilier-abidjan-net/`), branche unique `main`, remote `origin` = `git@github.com:ekassi-wgy/immobilier-abidjan-net.git` (GitHub, SSH : clé `~/.ssh/id_ed25519` protégée par phrase de passe, à charger dans l'agent avec `ssh-add --apple-use-keychain`).
- Ne jamais committer : `.env`, `vendor/`, `public/uploads/` (sauf son `.htaccess`), `storage/`, captures d'écran.
- Push : `git push origin main` (si la clé SSH n’est pas chargée, l’utilisateur lance `! ssh-add --apple-use-keychain ~/.ssh/id_ed25519`).
