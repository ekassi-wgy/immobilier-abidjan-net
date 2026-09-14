# CLAUDE.md — immobilier.abidjan.net

Plateforme d'annonces immobilières **agrégatrice et multisite** (Côte d'Ivoire d'abord, puis Sénégal, Cameroun, Togo, Bénin…).
Production : **https://immobilier.abidjan.net** (sous-domaine, hébergement Plesk).
Langue du projet : **français** (code en anglais, textes, commentaires métier et commits en français).

## Documents de référence — à relire avant toute fonctionnalité

| Document | Rôle |
|---|---|
| `docs/cahier-des-charges.md` | **Périmètre fonctionnel. Fait foi.** Ne rien ajouter hors périmètre sans demander. |
| `docs/PLAN.md` | Plan de projet par phases/lots + état d'avancement (à tenir à jour). |
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
- Dépendances Composer **minimales et justifiées** : `phpmailer/phpmailer`, `scssphp/scssphp` (compilation Sass en PHP — pas de Node sur le poste). Pas de framework.
- Front : HTML5 sémantique, **Bootstrap 5.3 comme socle technique uniquement** (grille, reboot, utilitaires, modal/offcanvas/collapse) compilé en SCSS avec les seuls modules utiles, JavaScript **vanilla** (Alpine.js toléré si justifié). Pas de jQuery côté public.
- Carte : **Leaflet + OpenStreetMap + markercluster**. Slider/galerie/lightbox : librairies légères **hébergées localement**.
- Images : **GD** (Imagick non disponible) — redimensionnement multi-tailles + WebP, stockage `public/uploads/{pays}/{annonce}/`.
- **Zéro CDN / zéro ressource externe** (polices, JS, CSS, icônes : tout est auto-hébergé), sauf tuiles OSM et services explicitement validés.

## Arborescence

Légende : ✅ existe · ⬜ à créer (lot indiqué dans `docs/PLAN.md`).

```
.htaccess             ✅ dev MAMP uniquement : bloque les dossiers internes, sert tout depuis public/
app/
  Support/helpers.php ✅ e(), url(), cmsadmin_url(), cmsadmin_asset(), render_view()… (provisoire, intégré au socle au lot 1.1)
  Views/cmsadmin/     ✅ layouts/ (app, auth) · partials/ (head, navbar, sidebar, footer, scripts, flash, page-header, status-badge, pagination, empty-state) · pages/ (dashboard, properties, auth, errors)
  Views/front/        ⬜ layouts/, partials/, pages/
  Controllers/        ⬜ Front (Home, Search, Property, Agency, Page, Lead…) + Cmsadmin/ (Dashboard, Properties, Agencies, Categories, Geo, Leads, Users, Sites, Seo, Settings…)
  Models/ Services/ Middlewares/  ⬜ (SiteResolver, Auth, Role, Csrf, RateLimit)
bin/
  preview/            ✅ PROVISOIRE : routeur de prévisualisation cmsadmin + données fictives (supprimé au lot 1.1)
  dev-server.php      ✅ routeur pour le serveur PHP intégré (alternative à MAMP)
config/
  cmsadmin-menu.php   ✅ menu du back-office par rôle
  config.php, database.php, countries.php  ⬜
public/               DOCUMENT ROOT en production
  index.php, .htaccess  ✅ (index.php PROVISOIRE : prévisualisation, localhost uniquement)
  assets/fonts/       ✅ Plus Jakarta Sans (woff2 variable + OFL)
  assets/scss|css|js|img/  ⬜ front public
  cmsadmin/assets/    ✅ back-office (StarAdmin 2 nettoyé + cmsadmin.css, cmsadmin.js, dashboard.js)
  uploads/            ⬜ (git-ignoré)
lang/                 ⬜ fr.php, en.php — aucune chaîne d'interface en dur dans les vues
database/             ⬜ migrations/, seeders/, schema.sql, seed.sql
storage/              ⬜ cache/, logs/ (git-ignorés)
docs/                 ✅ cahier-des-charges.md, PLAN.md, brand/
```

## Commandes

```bash
# `php` dans le PATH = Homebrew PHP 8.4 (CLI) ; MAMP sert le site en PHP 8.3.14 → rester compatible 8.2+
/Applications/MAMP/bin/php/composer install
php bin/build-css.php            # compile public/assets/scss → css (scssphp) — à créer au lot 1.1
php -l <fichier>                 # vérif syntaxe avant commit
```

**Environnement local : MAMP → http://localhost:8888/** (Apache 2.4, PHP 8.3.14, MySQL 8 sur le port 8889).
Le `DocumentRoot` MAMP pointe sur la racine du projet : le `.htaccess` racine bloque les dossiers internes (`app/`, `bin/`, `config/`, `docs/`, fichiers cachés…) et sert tout depuis `public/`, qui est le `DocumentRoot` en production. `public/.htaccess` envoie les URL non statiques vers `public/index.php`.

Prévisualisation du back-office **sans base de données** (données fictives `bin/preview/fixtures.php`), servie par `public/index.php` **uniquement sur localhost / *.local** :
- http://localhost:8888/cmsadmin · `/cmsadmin/annonces` · `/cmsadmin/annonces/nouvelle` · `/cmsadmin/annonces/IAN-24531/modifier?erreurs=1` · `/cmsadmin/connexion` · `/cmsadmin/erreur-500`
- Rôle simulé : `?role=super_admin|country_admin|agency` (mémorisé par cookie).
- `public/index.php` et `bin/preview/` sont provisoires : remplacés par le vrai routeur au lot 1.1.
- Alternative sans MAMP : `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8765 -t public bin/dev-server.php` (plusieurs workers obligatoires).
`.env` (jamais commité) : `APP_ENV`, `APP_URL`, `DB_*`, `SMTP_*`. Fournir `.env.example`.

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
Une sans-serif géométrique à forte personnalité, auto-hébergée : **Plus Jakarta Sans** (validée, variable woff2 dans `public/assets/fonts/`), 2–3 graisses. Titres serrés (`letter-spacing: -0.02em`), corps 16 px min, chiffres tabulaires pour prix et surfaces. Pas d'Inter, Roboto, Poppins ou Open Sans par défaut.

### Principes qui évitent l'effet template / IA
- Bootstrap = moteur invisible : **aucune** classe visuelle Bootstrap laissée telle quelle (`.btn-primary`, `.card`, `.navbar` par défaut, `.badge`, `.shadow`…). Composants maison préfixés **`im-`** (`.im-btn`, `.im-card-property`, `.im-hero`…).
- Photographie réelle et locale (Abidjan : Cocody, Plateau, Riviera, Assinie…) comme matière principale ; pas d'illustrations 3D, pas de stock générique.
- **Refusés** : dégradés violets/néon, glassmorphism généralisé, blobs, orbes lumineux, icônes « sparkles ✨ », emojis, cartes toutes identiques avec pastille d'icône + titre + 2 lignes, ombres lourdes, arrondis excessifs partout, textes marketing creux (« Découvrez l'excellence… »), animations gratuites.
- **Recherchés** : grille rigoureuse avec respirations généreuses, asymétries maîtrisées, contrastes d'échelle typographique, filets fins (1 px `$line`), rayons sobres (8–14 px), ombres très diffuses et rares, micro-interactions discrètes (150–250 ms, `ease-out`), respect de `prefers-reduced-motion`.
- Densité d'information à la Laforêt sur les cartes annonce (type, prix, ville/quartier, surface • pièces • chambres, CTA Message/WhatsApp/Appeler) mais présentation plus aérée et hiérarchisée.
- Icônes : **un seul jeu**, trait fin cohérent (SVG sprite local). Accessibilité AA (contrastes, focus visibles, `aria-*`, cibles tactiles ≥ 44 px).

### Hero de l'accueil (slide)
Plein écran sobre (≈ 88vh desktop, 70vh mobile) : **slides photo en fondu lent** (crossfade + léger zoom Ken Burns ≤ 1,06), voile navy très subtil pour la lisibilité, titre éditorial court + compteur d'annonces réelles, **module de recherche flottant** (onglets Acheter / Louer / Location meublée · type de bien · ville/commune · budget → Rechercher). Indicateurs de slide minimalistes (fines barres de progression + n°/total), légende discrète du lieu photographié. Pause au survol/focus, image 1 servie en priorité (LCP), les suivantes en lazy. Aucune flèche ni point Bootstrap par défaut.

### Back-office `cmsadmin`
Même palette et même typographie que le front, UI calme et dense, lisible. StarAdmin 2 fournit la **structure** (layout, sidebar, tables, formulaires) ; l'**habillage** est réécrit via une feuille d'override. Menu latéral construit selon le rôle connecté.

- **Ne jamais modifier** `public/cmsadmin/assets/css/style.css` (template compilé/minifié) : tout passe par `css/cmsadmin.css`, qui ne contient des couleurs que dans `:root`.
- Vues : `app/Views/cmsadmin/layouts/` (`app`, `auth`), `partials/` (navbar, sidebar, page-header, status-badge, pagination, empty-state, flash), `pages/<module>/`. Modèles de référence à copier : `pages/properties/index.php` (liste), `pages/properties/form.php` (formulaire), `pages/dashboard/index.php`.
- Menu : `config/cmsadmin-menu.php` (entrées, rôles autorisés, compteurs). Masquer une entrée n'est pas un contrôle d'accès.
- Helpers disponibles : `e()`, `url()`, `cmsadmin_url()`, `cmsadmin_asset()` (versionné), `render_view()`, `cmsadmin_partial()`, `format_price()`, `format_number()` (`app/Support/helpers.php`).
- Plugins chargés à la demande via `$plugins` (`'select2'`, `'chart'`). Select2 : attribut **`data-im-select`** (jamais `data-select2`, qui entre en conflit avec la bibliothèque).
- Listes : pagination et filtres **côté serveur** (pas de DataTables : volumes importants). Dates : `<input type="date">` natif (pas de datepicker jQuery).
- jQuery est toléré **uniquement** dans `cmsadmin` (dépendance du template), jamais côté public.
- Licences tierces : `public/cmsadmin/assets/THIRD-PARTY-LICENSES.md` (à tenir à jour).

---

## Méthode de travail

- Lire la section concernée du cahier des charges + `docs/PLAN.md` avant chaque lot ; mettre à jour le statut du lot à la fin.
- Travail par lots **directement sur `main`** (pas de branches de fonctionnalité) : vérifications (`php -l`, scénario par rôle, contrôle mobile 375 px, rendu sur http://localhost:8888) → commits atomiques en français.
- Toute évolution du schéma BDD passe par une migration numérotée dans `database/migrations/` + mise à jour de `schema.sql`.
- En cas de doute sur le périmètre, une décision métier ou un choix graphique structurant : **demander avant de coder**.

### Vérification visuelle (Chrome headless)
- Capture : `"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --disable-gpu --hide-scrollbars --user-data-dir=<dossier temporaire> --window-size=1440,1500 --virtual-time-budget=3000 --screenshot=<fichier.png> <url>`, encadré par `perl -e 'alarm 40; exec @ARGV'` (Chrome peut ne pas rendre la main).
- Mobile : Chrome headless impose une largeur de fenêtre minimale (~500 px) → pour tester 390 px, capturer une page HTML locale contenant des `<iframe width="390">` pointant vers les URL.
- Erreurs JS : `--enable-logging=stderr --v=0 --dump-dom <url>` puis filtrer `CONSOLE`.
- Faire les captures dans le scratchpad, jamais dans le dépôt.

### Git
- Dépôt **propre au projet** (racine `immobilier-abidjan-net/`), branche unique `main`, pas encore de remote GitLab (à ajouter au lot 3.1).
- Ne jamais committer : `.env`, `vendor/`, `public/uploads/`, `storage/`, captures d'écran.
