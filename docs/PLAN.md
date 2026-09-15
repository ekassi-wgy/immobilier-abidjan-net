# Plan de projet — immobilier.abidjan.net

Légende statut : ⬜ à faire · 🟨 en cours · ✅ terminé · ⏸ en attente de validation client

| Phase | Lot | Contenu | Livrables | Durée indicative | Statut |
|---|---|---|---|---|---|
| **0. Cadrage** | 0.1 | Analyse cahier des charges, captures laforet.com, logo, template admin | `CLAUDE.md`, `docs/cahier-des-charges.md`, `docs/PLAN.md` | 0,5 j | ✅ |
| | 0.2 | Schéma BDD complet (multisite, géo, catégories arborescentes, EAV, workflow, logs) | `database/schema.sql` (37 tables, testé MySQL 8.0.40), `database/seed.sql` (CI : 13 communes d'Abidjan, 72 quartiers, 28 catégories, 25 critères, 14 équipements), `docs/database.md` | 1 j | ✅ |
| | 0.3 | Design system : palette, typo, grille, composants `im-` ; maquette hero + carte annonce | `resources/scss/` (tokens, Bootstrap 5.3 en socle, composants), `bin/build-css.php`, sprite Phosphor, /styleguide, maquette d'accueil sur http://localhost:8888/ | 1 j | ✅ |
| | 0.4 | Intégration & nettoyage StarAdmin 2 → `cmsadmin` (squelette rethémé, vues PHP, menu par rôle, prévisualisation sans BDD) | `public/cmsadmin/assets/` (56 Mo → 2,1 Mo, zéro ressource externe), `app/Views/cmsadmin/`, `bin/preview/` (MAMP : http://localhost:8888/cmsadmin) | 0,5 j | ✅ |
| **1. MVP Côte d'Ivoire** | 1.1 | Socle : front controller, routeur, PDO, `.env`, autoload PSR-4, helpers (`e()`, CSRF, flash), i18n `lang/`, build SCSS (scssphp), gestion d'erreurs | `app/Core/` (routeur, PDO, session, CSRF, vues, i18n, erreurs + journal), `routes/`, `lang/fr.php`·`en.php`, `.env.example`, pages 404/419/500/503 front et cmsadmin ; maquettes conservées derrière `APP_ENV=local` (option A) | 1 j | ✅ |
| | 1.2 | Multisite : middleware `SiteResolver` (HTTP_HOST → site → pays, devise, langue) | `SiteResolver` global (hôte → site → pays, langue ; 404 hôte inconnu, 503 maintenance, 301 alias, noindex hors production), `Models/Site`·`Country`, `Services/SiteRepository` (cache fichier) et `Settings`, helpers `site()`, `settings()`, `absolute_url()`, devise du pays dans `format_price()`, `bin/cache-clear.php` | 0,5 j | ✅ |
| | 1.3 | Authentification `cmsadmin` : connexion unique (admin / agence), rôles & permissions, limitation tentatives, mot de passe oublié | Connexion, déconnexion, « Rester connecté » (jetons sélecteur/validateur), blocage des tentatives (email + IP), mot de passe oublié (lien unique par email, pilote log en local), changement forcé du mot de passe provisoire, Mon compte ; middlewares `Authenticate`, `RedirectIfAuthenticated`, `RequireRole` ; restriction au pays du site ; `activity_logs` ; PHPMailer ; `bin/create-user.php` | 1 j | ✅ |
| | 1.4 | Back-office Super Admin — référentiels : pays/sites, villes/communes/quartiers, catégories, attributs dynamiques, équipements | Pays & sites (pays, sites, domaines, garde-fous du site courant), référentiel géographique villes / communes / quartiers (Super Admin + Admin Pays limité à son pays), catégories arborescentes avec transactions et critères hérités, critères dynamiques avec options, équipements ; listes filtrées et paginées côté serveur, suppression limitée aux éléments inutilisés, journal d'activité ; `Support/Validator`, partials de formulaire réutilisables | 1,5 j | ✅ |
| | 1.5 | Gestion des agences partenaires & utilisateurs internes (RCCM, logo, zones, activation) + demandes « Devenir partenaire » | CRUD agences, comptes agence, admins pays | 1 j | ⬜ |
| | 1.6 | Module annonces : formulaire dynamique selon catégorie, galerie (upload multiple, tri, WebP), vidéo/360°/PDF, carte GPS, workflow de validation, mise en avant, expiration | CRUD annonces, notifications email + back-office | 2 j | ⬜ |
| | 1.7 | Back-office Agence : dashboard perso, mes annonces par statut, profil agence, leads reçus | Espace agence cloisonné (tests de permissions) | 1 j | ⬜ |
| | 1.8 | Front — accueil : header, **hero slide** + recherche, biens à la une, dernières annonces, catégories phares, chiffres clés, agences en vedette, footer SEO | Page d'accueil responsive | 1,5 j | ⬜ |
| | 1.9 | Front — résultats : filtres multicritères, tri, pagination, vue grille/liste/**carte** (clusters), favoris cookie | `/acheter/…`, `/louer/…` | 1,5 j | ⬜ |
| | 1.10 | Front — fiche annonce : galerie mosaïque + lightbox, critères structurés, juridique, carte, bloc agence « vérifiée », formulaire contact, WhatsApp, appel, biens similaires, partage | `/annonces/{slug}-ref{id}` | 1,5 j | ⬜ |
| | 1.11 | Front — annuaire & profil agences, pages statiques, Contact, Devenir partenaire, Déposer un bien (lead), bandeau cookies | Pages + leads en base + emails PHPMailer | 1 j | ⬜ |
| | 1.12 | Back-office — dashboard stats (annonces, vues, leads, top biens), leads, exports CSV, paramètres commission | Dashboard Chart.js, exports | 1 j | ⬜ |
| | 1.13 | Recette par rôle (visiteur, agence, modérateur, super admin), mobile 375 px, navigateurs | Checklist de tests `docs/tests.md`, correctifs | 1 j | ⬜ |
| **2. SEO, contenu, durcissement** | 2.1 | SEO : méta par page, sitemap.xml, Schema.org, Open Graph, redirections 301, canoniques | Module SEO back-office | 1 j | ⬜ |
| | 2.2 | Blog/actualités + CMS léger pages + bannières | CRUD blog/pages | 1 j | ⬜ |
| | 2.3 | Audit sécurité (CSRF, XSS, SQLi, uploads, permissions), performance (cache pages, minification, images), test de charge (≈ 10 000 annonces factices) | Rapport d'audit + correctifs | 1–2 j | ⬜ |
| **3. Mise en ligne Plesk** | 3.1 | Pré-production `staging`, BDD prod, SSL, PHP 8.2+, CRON (expiration, relances), sauvegardes quotidiennes, déploiement Git | Site sur **immobilier.abidjan.net** | 1–2 j | ⬜ |
| | 3.2 | ⚑ Validation humaine en pré-prod, Search Console, Analytics, vérifications post-déploiement | PV de mise en ligne | 0,5 j | ⬜ |
| **4. Extension pays** | 4.1 | Réplication multisite sur un 2ᵉ pays (nouveau domaine, référentiel géo, devise) | 2ᵉ site en ligne | 1–2 j | ⬜ |
| **5. Évolutions (v2)** | 5.x | Alertes email, paiement commission/boost, avis, Meilisearch, application mobile | À planifier | — | ⬜ |

**Estimation MVP en ligne : ≈ 3 semaines** de développement continu (phases 0 → 3).

## Pré-requis client (à fournir tôt, cf. §9.1 du cahier des charges)

| Élément | Nécessaire pour | Statut |
|---|---|---|
| Logo fichiers finaux | 0.3 / 1.8 | ✅ reçu (`docs/brand/`) |
| Validation de la police proposée | 0.3 | ✅ Plus Jakarta Sans |
| Photos d'Abidjan pour le hero (droits d'usage) — photos libres provisoires en place | 1.8 | ⬜ |
| Taux de commission, durée de vie d'une annonce | 1.6 / 1.12 | ⬜ |
| Accès SMTP | 1.6 / 1.11 | ✅ Gmail (compte `abidjan.net@weblogy.com`), configuré dans `.env` |
| Dépôt Git distant | 3.1 | ✅ GitHub `ekassi-wgy/immobilier-abidjan-net` |
| Accès Plesk, DNS du sous-domaine, moteur BDD de production (MySQL 8 ou MariaDB) | 3.1 | ⬜ |
| Textes légaux (mentions, CGU, confidentialité) | 1.11 | ⬜ |
| Modification d'une annonce publiée : retrait du site pendant revalidation, ou révision en parallèle | 1.6 | ⬜ |
| Relecture du référentiel des quartiers | 1.4 | ⬜ |
