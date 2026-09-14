# Plan de projet — immobilier.abidjan.net

Légende statut : ⬜ à faire · 🟨 en cours · ✅ terminé · ⏸ en attente de validation client

| Phase | Lot | Contenu | Livrables | Durée indicative | Statut |
|---|---|---|---|---|---|
| **0. Cadrage** | 0.1 | Analyse cahier des charges, captures laforet.com, logo, template admin | `CLAUDE.md`, `docs/cahier-des-charges.md`, `docs/PLAN.md` | 0,5 j | ✅ |
| | 0.2 | Schéma BDD complet (multisite, géo, catégories arborescentes, EAV, workflow, logs) | `database/schema.sql`, `database/seed.sql` (CI : Abidjan, communes, quartiers, catégories) | 1 j | ⬜ |
| | 0.3 | Design system : palette, typo, grille, composants `im-` ; maquette hero + carte annonce | `public/assets/scss/` tokens + page styleguide interne | 1 j | ⬜ |
| | 0.4 | Intégration & nettoyage StarAdmin 2 → `cmsadmin` (squelette statique rethémé) | `public/cmsadmin/assets/`, layouts PHP du back-office | 0,5 j | ⏸ |
| **1. MVP Côte d'Ivoire** | 1.1 | Socle : front controller, routeur, PDO, `.env`, autoload PSR-4, helpers (`e()`, CSRF, flash), i18n `lang/`, build SCSS (scssphp), gestion d'erreurs | Application qui démarre, page 404/500 | 1 j | ⬜ |
| | 1.2 | Multisite : middleware `SiteResolver` (HTTP_HOST → site → pays, devise, langue) | Tables `countries`, `sites` actives, config par site | 0,5 j | ⬜ |
| | 1.3 | Authentification `cmsadmin` : connexion unique (admin / agence), rôles & permissions, limitation tentatives, mot de passe oublié | Login rethémé, middlewares Auth/Role | 1 j | ⬜ |
| | 1.4 | Back-office Super Admin — référentiels : pays/sites, villes/communes/quartiers, catégories, attributs dynamiques, équipements | CRUD + tables DataTables + journal d'activité | 1,5 j | ⬜ |
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
| Validation de la police proposée | 0.3 | ⬜ |
| Photos d'Abidjan pour le hero (droits d'usage) | 1.8 | ⬜ |
| Taux de commission, durée de vie d'une annonce | 1.6 / 1.12 | ⬜ |
| Accès SMTP | 1.6 / 1.11 | ⬜ |
| Accès Plesk, DNS du sous-domaine, dépôt GitLab | 3.1 | ⬜ |
| Textes légaux (mentions, CGU, confidentialité) | 1.11 | ⬜ |
