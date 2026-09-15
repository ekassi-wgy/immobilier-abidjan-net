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
- Images : **GD** (Imagick non disponible) — redimensionnement multi-tailles + WebP, stockage `public/uploads/{pays}/{annonce}/`.
- **Zéro CDN / zéro ressource externe** (polices, JS, CSS, icônes : tout est auto-hébergé), sauf tuiles OSM et services explicitement validés.

## Arborescence

Légende : ✅ existe · ⬜ à créer (lot indiqué dans `docs/PLAN.md`).

```
.htaccess             ✅ dev MAMP uniquement : bloque les dossiers internes, sert tout depuis public/
.env.example          ✅ modèle de configuration (copier en .env, jamais commité)
app/
  bootstrap.php       ✅ amorçage commun (autoload, .env, erreurs, UTC) → retourne App\Core\App
  Core/               ✅ App (noyau + services), Router, Request, Response, Config, Env, Database (PDO), Cache (fichiers), Session, Csrf, View, Translator, Logger, ErrorHandler, Middleware, Exceptions/HttpException
  Support/helpers.php ✅ e(), __(), config(), env(), site(), settings(), url(), absolute_url(), route(), asset(), icon(), csrf_field(), csrf_token(), render_view(), cmsadmin_*(), format_*() (autoload Composer « files »)
  Views/cmsadmin/     ✅ layouts/ (app, auth) · partials/ (head, navbar, sidebar, footer, scripts, flash, page-header, status-badge, pagination, empty-state) · pages/ (dashboard, properties, auth, account, errors) · partials auth-aside, password-field
  Views/front/        ✅ layouts/app · partials/ (header, footer, hero, search, property-card) · pages/ (home-mockup, styleguide, errors/error)
  Views/errors/debug.php ✅ détail d’exception (app.debug uniquement, jamais en production)
  Views/emails/       ✅ layout (HTML en ligne) · password-reset (+ .text)
  Controllers/        ✅ Controller (base) · Front/HomeController (503 « en préparation » jusqu’au lot 1.8) · Cmsadmin/ (Controller de base, Auth, Password, Account) · Preview/ (PROVISOIRE, maquettes)
                      ⬜ Front (Search, Property, Agency, Page, Lead…) + Cmsadmin/ (Dashboard, Properties, Agencies, Categories, Geo, Leads, Users, Sites, Seo, Settings…)
  Middlewares/        ✅ SiteResolver, VerifyCsrfToken (globaux) · Authenticate, RedirectIfAuthenticated, RequireRole
  Models/             ✅ Site, Country, User · ⬜ modèles métier
  Services/           ✅ SiteRepository, Settings · Auth, UserRepository, PasswordHasher, PasswordReset, LoginThrottle, RateLimiter, ActivityLogger, Mailer, IpAddress · ⬜
bin/
  preview/            ✅ PROVISOIRE : données fictives (fixtures.php, front-fixtures.php) des contrôleurs Preview/, retirées module par module
  build-css.php       ✅ compile resources/scss → public/assets/css/app.css
  build-icons.php     ✅ génère le sprite d’icônes
  create-user.php     ✅ crée un Super Admin / Admin Pays (mot de passe provisoire affiché une fois)
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
  uploads/            ⬜ (git-ignoré)
resources/scss/       ✅ app.scss · abstracts/_tokens.scss (SOURCE UNIQUE des couleurs, typo, espacements) · abstracts/_mixins.scss · vendor/_bootstrap.scss · base/ · layout/ · components/ · pages/
lang/                 ✅ fr.php (référence et repli), en.php — aucune chaîne d'interface en dur dans les nouvelles vues
database/             ✅ schema.sql (référence v1, 37 tables) · seed.sql (référentiels CI) · migrations/ (évolutions 0002+) — voir docs/database.md
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
php -l <fichier>                 # vérif syntaxe avant commit
```

**Environnement local : MAMP → http://localhost:8888/** (Apache 2.4, PHP 8.3.14, MySQL 8 sur le port 8889).
Le `DocumentRoot` MAMP pointe sur la racine du projet : le `.htaccess` racine bloque les dossiers internes (`app/`, `bin/`, `config/`, `docs/`, fichiers cachés…) et sert tout depuis `public/`, qui est le `DocumentRoot` en production. `public/.htaccess` envoie les URL non statiques vers `public/index.php`.

Prévisualisation avec **données fictives** (contrôleurs `app/Controllers/Preview/` + `bin/preview/`) ; seul le site courant (nom, pays, devise) vient de la base locale, nécessaire depuis le lot 1.2, routes déclarées **uniquement si `APP_ENV=local` et `APP_PREVIEW=true`** (ailleurs : accueil en 503, maquettes en 404) :
- Site public : http://localhost:8888/ (maquette d’accueil : hero + recherche + biens à la une) · http://localhost:8888/styleguide (**charte graphique de référence**)
- Back-office (**connexion réelle obligatoire** depuis le lot 1.3 : créer d’abord un compte avec `bin/create-user.php`) : http://localhost:8888/cmsadmin · `/cmsadmin/annonces` · `/cmsadmin/annonces/nouvelle` · `/cmsadmin/annonces/IAN-24531/modifier?erreurs=1` · `/cmsadmin/erreur-500`
- Rôle affiché = celui du compte connecté ; un Super Admin peut prévisualiser un autre rôle avec `?role=country_admin|agency|super_admin` (cookie, écrans fictifs uniquement — sans effet sur les droits réels).
- Emails en local : `MAIL_MAILER=log` → fichiers `storage/mail/*.eml` (lien de réinitialisation inclus).
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
- Vues : `app/Views/cmsadmin/layouts/` (`app`, `auth`), `partials/` (navbar, sidebar, page-header, status-badge, pagination, empty-state, flash), `pages/<module>/`. Modèles de référence à copier : `pages/properties/index.php` (liste), `pages/properties/form.php` (formulaire), `pages/dashboard/index.php`.
- Menu : `config/cmsadmin-menu.php` (entrées, rôles autorisés, compteurs). Masquer une entrée n'est pas un contrôle d'accès.
- Helpers disponibles : `e()`, `__()`, `url()`, `route()`, `cmsadmin_url()`, `cmsadmin_asset()` (versionné), `render_view()`, `cmsadmin_partial()`, `csrf_field()`, `format_price()`, `format_number()` (`app/Support/helpers.php`).
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

### Git
- Dépôt **propre au projet** (racine `immobilier-abidjan-net/`), branche unique `main`, remote `origin` = `git@github.com:ekassi-wgy/immobilier-abidjan-net.git` (GitHub, SSH : clé `~/.ssh/id_ed25519` protégée par phrase de passe, à charger dans l'agent avec `ssh-add --apple-use-keychain`).
- Ne jamais committer : `.env`, `vendor/`, `public/uploads/`, `storage/`, captures d'écran.
