> Source : WTECH - 2026 - Cahier des charges site immobilier.md.docx (converti automatiquement — les tableaux d origine sont aplatis).

# CAHIER DES CHARGES
Plateforme d'annonces immobilières multisite — Afrique (Côte d'Ivoire, puis extension panafricaine)

> ## Avenant n° 1 — Weblogy intermédiaire exclusif (17/09/2026)
>
> **Cet avenant prime sur les points contraires du cahier ci-dessous** (§ 1.3, § 2, § 5.1, § 5.3), signalés par « → avenant n° 1 ».
>
> 1. **Modèle** : *Partenaire → Weblogy → Prospect* et *Particulier → Weblogy → annonce*. Weblogy est l'interlocuteur exclusif des prospects : aucune annonce publique n'affiche l'identité ni les coordonnées du partenaire ou du propriétaire, seulement celles de Weblogy. Le rattachement au partenaire (ou au propriétaire) reste connu en interne.
> 2. **Demandes des prospects** : reçues par l'équipe Weblogy uniquement, qui les qualifie puis sollicite le partenaire ou le propriétaire. Le partenaire ne voit que le nombre de demandes reçues sur ses biens.
> 3. **Partenaires professionnels** (agences, promoteurs, gestionnaires, autres professionnels habilités) : dossier « Devenir partenaire » avec informations légales et pièces justificatives, validation par Weblogy, puis console (back-office existant, rôles agence) pour gérer leurs biens : création, brouillon, modification, photos, désactivation / réactivation. Publication soumise à la validation de Weblogy par défaut, publication directe activable dans Paramètres ; Weblogy garde en toutes circonstances le contrôle (modifier, désactiver, supprimer).
> 4. **Particuliers** : **compte obligatoire** (email confirmé) pour « Confier un bien » à Weblogy depuis un espace propriétaire public (`/mon-espace`, jamais le back-office). Le particulier ne publie pas : l'équipe étudie le dossier et crée l'annonce ; il suit l'avancement.
> 5. **Annuaire des agences** remplacé par une vitrine « Nos partenaires » (logo, nom, type, implantation) sans coordonnées, sans formulaire ni liste d'annonces.
> 6. **Header** : « Devenir partenaire » (professionnels) et « Confiez-nous votre bien » (particuliers) ; plus aucun « Déposer un bien ».

## 1. Présentation du projet

### 1.1 Contexte
La société agit comme intermédiaire (agence immobilière agrégatrice) entre :

- des agences immobilières partenaires qui possèdent un compte validé et soumettent leurs annonces,
- des biens de particuliers qu'elle publie directement en tant qu'administrateur,
- ses propres biens qu'elle publie directement en tant qu'administrateur,
- et les clients/visiteurs (acheteurs, locataires) qui consultent les annonces.

Ce n'est pas une plateforme à inscription libre. Il n'existe aucun espace membre grand public. Seuls deux profils internes créent du contenu : l'administrateur (vous) et les agences partenaires, dont le compte est créé manuellement après prise de contact.

La société perçoit une commission sur chaque transaction générée via la mise en relation.

### 1.2 Objectifs
- Centraliser et structurer l'offre immobilière (vente, location, tous types de biens).
- Offrir une expérience de recherche avancée, rapide et fiable (filtres multicritères).
- Garantir la qualité des annonces via un workflow de validation.
- Construire une architecture multisite / multi-pays, évolutive dès la conception (CI d'abord, puis Sénégal, Cameroun, Togo, Bénin, etc.).
- Être SEO friendly dès le départ (URLs propres, structure sémantique, vitesse).
- Rester en PHP natif + PDO, sans framework lourd, pour un contrôle total du code et des performances, mais avec une architecture propre (MVC maison).

### 1.3 Ce que le site n'est PAS
- Pas d'inscription publique ni de compte client. → **avenant n° 1** : compte obligatoire pour un particulier qui confie un bien (espace propriétaire, sans aucun droit de publication).
- Pas de paiement en ligne dans une v1 (à prévoir en évolution : paiement de la commission, boost d'annonce, etc.).
- Pas une simple vitrine : c'est un moteur de recherche + back-office de gestion multi-acteurs.

## 2. Les acteurs (rôles) du système
Rôle
Description
Droits
Super Admin
Vous, gestionnaire de la plateforme
Accès total, gestion des pays/sites, validation des annonces, gestion des agences, statistiques, commissions, contenu, SEO
Admin Pays / Modérateur
Employé local gérant un pays précis (évolutif)
Gestion des annonces et agences de son pays uniquement
Agence partenaire
Compte créé manuellement par le Super Admin
Dépose/édite ses annonces (statut "en attente"), consulte ses statistiques, gère son profil d'agence
Visiteur / Client
Grand public
Recherche, consultation des annonces, contact via formulaire/WhatsApp/téléphone, favoris (session ou cookie, sans compte)

### 2.1 Workflow de validation d'une annonce
- L'agence partenaire crée/modifie une annonce → statut "En attente de validation".
- Le Super Admin ou l'Admin Pays reçoit une notification (email + back-office).
- Validation → statut "Publiée" (visible sur le site public) ou "Rejetée" (avec motif, renvoyée à l'agence pour correction).
- L'annonce peut être dépubliée, archivée (bien vendu/loué) ou expirée (durée de vie configurable, ex. 90 jours, relance automatique).
- Les annonces créées par le Super Admin lui-même sont publiées directement (pas de validation nécessaire), sauf si vous préférez un contrôle à deux niveaux même en interne.

## 3. Catégorisation et typologie des biens
Il faut une catégorisation hiérarchique et extensible, car "tout type de bien" est demandé.

### 3.1 Type de transaction
- Vente
- Location (longue durée)
- Location meublée / courte durée (type séjour)
- Bail commercial / cession de bail
- Terrain à vendre / à louer

### 3.2 Catégories de biens (table property_categories, arborescence parent/enfant)
- Résidentiel
- Appartement (studio, T1/T2/T3…, duplex, triplex, penthouse)
- Villa / Maison individuelle
- Maison jumelée / mitoyenne
- Immeuble résidentiel (bloc d'appartements)
- Chambre / Colocation
- Terrains
- Terrain nu (résidentiel)
- Terrain agricole
- Terrain industriel
- Terrain loti / non loti
- Commercial & Bureaux
- Bureau / Plateau de bureau
- Local commercial / Boutique
- Magasin / Entrepôt / Hangar
- Immeuble commercial
- Centre commercial (lot)
- Industriel
- Usine
- Entrepôt logistique
- Zone industrielle
- Hôtellerie & Tourisme
- Hôtel / Résidence hôtelière
- Maison d'hôtes
- Résidence de vacances
- Institutionnel / Spécial
- Établissement scolaire
- Établissement de santé (clinique)
- Immeuble mixte (résidentiel + commercial)

La table doit permettre d'ajouter facilement de nouvelles catégories sans toucher au code (gestion depuis le back-office).

### 3.3 Critères / caractéristiques par type de bien (système d'attributs dynamiques)
Pour éviter de coder en dur des champs différents par catégorie, on utilise un modèle EAV léger (Entity-Attribute-Value) ou des groupes de champs dynamiques :

Critères généraux (tous biens) :

- Titre, description, référence interne
- Type de transaction, catégorie, sous-catégorie
- Prix (+ prix négociable oui/non), charges, commission agence (%)
- Localisation : pays, ville, commune, quartier, adresse, coordonnées GPS (carte)
- Superficie (terrain), surface habitable (bâti)
- Statut du bien (disponible, réservé, vendu, loué)
- Date de mise en ligne / date de disponibilité
- Photos (galerie multiple), vidéo (lien YouTube/Vimeo), visite virtuelle 360° (lien externe), plan/document PDF
- Agence propriétaire de l'annonce, agent en charge (nom, téléphone, WhatsApp)

Critères spécifiques résidentiel :

- Nombre de chambres, salles de bain, pièces, étage, nombre d'étages de l'immeuble
- Meublé / non meublé, année de construction, état du bien (neuf, à rénover, bon état)
- Orientation, vue, balcon/terrasse, piscine, jardin, garage/parking (nb places)
- Climatisation, sécurité (gardiennage, caméra, digicode), groupe électrogène, forage/eau, standing (économique/moyen/haut standing)

Critères terrain :

- Superficie exacte, viabilisation (eau, électricité, voirie), titre foncier (ACD, TF, lettre d'attribution, coutumier — statut juridique très important en Afrique de l'Ouest), bornage, accès route

Critères commercial/bureau :

- Surface, nombre de niveaux, quai de chargement, capacité (places assises pour commerce), zone/emplacement stratégique, fibre optique, ascenseur

Critères juridiques (essentiels en Côte d'Ivoire) :

- Type de titre de propriété (Titre Foncier, ACD, ADU, Lettre d'attribution, Certificat de propriété, Coutumier)
- Statut litige (oui/non)
- Notaire / référence dossier (interne, non public)

### 3.4 Recherche & filtres
- Recherche par mot-clé, ville/commune/quartier
- Filtre : type de transaction, catégorie, fourchette de prix, superficie, nb chambres, standing, titre foncier, équipements (cases à cocher)
- Tri : prix croissant/décroissant, date, superficie
- Recherche sur carte (Google Maps / OpenStreetMap avec clustering de pins)
- Alertes email (optionnel v2) : le visiteur laisse son email pour recevoir les nouvelles annonces correspondant à ses critères (sans créer de compte, juste un enregistrement + lien de désabonnement)

## 4. Architecture multisite / multi-pays

### 4.1 Principe
Une seule base de code, une seule base de données (avec country_id sur les tables clés), et plusieurs façons de gérer les domaines :

Option recommandée : sous-domaines ou domaines dédiés par pays

- www.votre-marque.ci (Côte d'Ivoire — lancement)
- www.votre-marque.sn (Sénégal — futur)
- ou ci.votre-marque.com, sn.votre-marque.com

Chaque site :

- partage le même code et la même base de données,
- filtre automatiquement les annonces par pays,
- a sa propre configuration : devise (FCFA XOF pour la zone UEMOA, mais prévoir d'autres devises pour d'autres zones), langue par défaut, mentions légales, coordonnées de contact, villes/quartiers propres à son référentiel géographique,
- possède son propre design/thème si besoin (variante de charte graphique), via un système de "site_id" en base + fichiers de configuration.

### 4.2 Ce qu'il faut prévoir dès le départ (même si un seul pays au lancement)
- Table countries (id, nom, code ISO, devise, langue par défaut, actif)
- Table cities / districts (rattachées à country_id) — référentiel géographique hiérarchique : Pays → Ville → Commune → Quartier
- Table sites (id, country_id, domaine, thème, statut)
- Middleware de détection du site courant (via $_SERVER['HTTP_HOST']) qui charge la config du pays correspondant
- Toutes les tables d'annonces, agences, catégories doivent référencer country_id pour permettre le filtrage
- Gestion multilingue prête dès la structure (fichiers de traduction /lang/fr.php, /lang/en.php) même si seul le français est actif au lancement (utile pour l'Afrique anglophone plus tard : Ghana, Nigeria)
- Gestion multidevise (affichage, pas forcément conversion automatique au départ)

## 5. Fonctionnalités détaillées

### 5.1 Front-office (site public)
- Page d'accueil : recherche rapide, biens à la une, dernières annonces, catégories phares, chiffres clés, partenaires en vedette (vitrine sans lien ni coordonnées → avenant n° 1)
- Page listing/résultats avec filtres avancés + pagination + vue grille/liste + vue carte
- Page détail annonce : galerie photo (lightbox), description complète, tous les critères, carte de localisation, ~~informations agence/agent~~ coordonnées de Weblogy seul interlocuteur (→ avenant n° 1), formulaire de contact adressé à Weblogy, bouton WhatsApp Weblogy, biens similaires, partage réseaux sociaux
- ~~Page annuaire des agences partenaires (profil public de chaque agence avec ses annonces)~~ → avenant n° 1 : vitrine « Nos partenaires » sans coordonnées ni annonces
- Pages statiques : À propos, Comment ça marche, Devenir partenaire (formulaire de demande de compte), Contact, Blog/Actualités immobilières (bonus SEO), Mentions légales, CGU, Politique de confidentialité
- ~~Formulaire "Déposer un bien" (lead anonyme)~~ → avenant n° 1 : « Confiez-nous votre bien », compte propriétaire obligatoire, dossier structuré avec photos et documents, suivi de l'avancement ; FAQ
- Responsive complet (mobile first, la majorité du trafic africain est mobile)

### 5.2 Back-office Super Admin
- Tableau de bord (statistiques : annonces publiées, en attente, agences actives, vues, leads, top biens consultés)
- Gestion des annonces (CRUD complet, validation/rejet, mise en avant "premium/à la une")
- Gestion des catégories et critères dynamiques
- Gestion des agences partenaires (création de compte, activation/désactivation, informations légales : RCCM, contact, logo, zone d'action)
- Gestion des utilisateurs internes (admin pays, modérateurs) avec rôles et permissions
- Gestion des pays/sites (multisite)
- Gestion du référentiel géographique (villes, communes, quartiers)
- Gestion des leads/contacts (messages reçus via les formulaires) — réservée à l'équipe (→ avenant n° 1)
- Biens confiés par les particuliers : étude, décision, création de l'annonce (→ avenant n° 1)
- Dossiers de partenariat avec pièces justificatives (→ avenant n° 1)
- Gestion du contenu (pages statiques, blog, bannières publicitaires)
- Gestion SEO (méta-titres, méta-descriptions, sitemap, redirections)
- Journal d'activité (logs des actions : qui a validé/modifié/supprimé quoi)
- Export de données (CSV/Excel des annonces, des leads)
- Paramétrage de la commission (%, ou montant fixe, par transaction ou par annonce premium payante)

### 5.3 Back-office Agence partenaire
- Tableau de bord personnel (mes annonces, leur statut, statistiques de vues/contacts)
- Dépôt/édition d'annonce (formulaire dynamique selon la catégorie choisie)
- Gestion de la galerie photo (upload multiple, réorganisation, compression automatique)
- Historique des annonces (publiées, en attente, rejetées, archivées)
- Gestion du profil de l'agence (logo, description, coordonnées, zones de couverture)
- ~~Messagerie interne simple (recevoir les demandes de contact des visiteurs pour ses biens)~~ → avenant n° 1 : les demandes sont traitées par Weblogy, qui sollicite le partenaire ; la console affiche le nombre de demandes reçues
- Brouillons, désactivation / réactivation de ses propres biens (→ avenant n° 1)

## 6. Spécifications techniques

### 6.1 Stack technique
- Backend : PHP 8.2+ natif, architecture MVC maison (pas de framework type Laravel/Symfony, mais organisation propre : Router, Controllers, Models, Views, Services)
- Base de données : MySQL/MariaDB via PDO (requêtes préparées obligatoires, protection injections SQL)
- Frontend : HTML5, CSS3 (Sass ou Tailwind CSS pour la rapidité et la cohérence design), JavaScript vanilla ou Alpine.js pour l'interactivité légère (pas besoin de React pour un site de ce type — meilleur pour le SEO et la performance)
- Cartographie : Leaflet.js + OpenStreetMap (gratuit) ou Google Maps API
- Recherche : requêtes SQL optimisées avec index ; si volume important plus tard, envisager Meilisearch ou Elasticsearch en V2
- Upload/Images : optimisation automatique (redimensionnement, conversion WebP), stockage organisé par annonce/pays
- Emails : PHPMailer + SMTP (notifications de validation, formulaires de contact)
- Sécurité : CSRF token sur tous les formulaires, hashage des mots de passe (password_hash), protection XSS (échappement systématique en sortie), limitation de tentatives de connexion, HTTPS obligatoire (Let's Encrypt via Plesk)
- SEO : URLs propres (/annonces/villa-4-pieces-cocody-abidjan-ref123), sitemap.xml généré dynamiquement, balises Schema.org (RealEstateListing), Open Graph pour le partage social
- Performance : cache de pages (fichiers ou Redis si disponible), lazy loading images, minification CSS/JS, CDN pour les assets si besoin

### 6.2 Structure de projet suggérée
/app
/Controllers
/Models
/Views
/Services
/Middlewares
/config
config.php
database.php
countries.php
/public
index.php (front controller)
/assets (css, js, images)
/uploads
/lang
fr.php
en.php
/database
/migrations
/seeders
/vendor (composer : phpmailer, etc.)
.env
composer.json
6.3 Modèle de données — tables principales (aperçu)
- countries, sites, cities, districts
- agencies (id, country_id, nom, logo, rccm, statut, date_creation)
- agency_users (comptes de connexion agence, rôle, hash mot de passe)
- admin_users (super admin, admin pays)
- property_categories (arborescence)
- properties (annonce : titre, description, prix, category_id, agency_id, country_id, city_id, district_id, statut, référence, coordonnées GPS, dates)
- property_attributes / property_attribute_values (critères dynamiques EAV)
- property_images
- property_features (équipements, table pivot many-to-many : piscine, climatisation, etc.)
- leads (messages de contact reçus)
- partner_requests (demandes "devenir partenaire" avant création manuelle du compte)
- activity_logs
- pages (contenu statique/CMS léger)
- blog_posts (optionnel)
- settings (paramètres par site : devise, commission, contacts)

## 7. Design / UX
- S'inspirer du site de référence (Laforêt : https://www.laforet.com/) pour l'ergonomie des filtres et fiches annonces, sans copier leur charte graphique ni leur code.
- Identité visuelle propre : palette de couleurs, typographie, logo, iconographie adaptée au marché africain (photos représentatives, mise en avant du standing local).
- Attention particulière à la vitesse de chargement sur mobile et connexions 3G/4G (compression images agressive, poids de page optimisé).
- Fiches annonces très visuelles (galerie en premier plan), informations structurées et scannables.
- Confiance : badges "Agence vérifiée", nombre d'annonces publiées par l'agence, avis (optionnel v2).

## 8. Sécurité, RGPD/protection des données et légal
- Consentement cookies (bandeau conforme)
- Politique de confidentialité claire (données des leads, formulaires)
- Sauvegardes automatiques régulières de la base de données (quotidiennes) et des fichiers uploadés
- Certificat SSL sur chaque domaine/sous-domaine (Let's Encrypt, gratuit et automatisable via Plesk)
- Protection contre le spam sur les formulaires (reCAPTCHA ou honeypot)
- Séparation stricte des permissions : une agence ne voit et ne modifie que ses propres annonces

## 9. Phasage du projet (roadmap)
Mode de réalisation retenu : développement quasi autonome par Claude Code. Claude Code prend en charge lui-même l'écriture du code, la relecture, les tests fonctionnels (scénarios par rôle), les corrections de bugs et les vérifications de sécurité de base (CSRF, XSS, injections SQL, permissions), avec une intervention humaine réduite à quelques points de passage incompressibles (voir §9.1). L'objectif est une mise en ligne du MVP la plus rapide possible.

Phase
Contenu
Durée indicative
Phase 0
Cadrage : Claude Code lit le cahier des charges + la charte graphique, propose schéma de BDD, arborescence, plan de développement
1-2 jours
Phase 1 — MVP Côte d'Ivoire
Socle technique, multisite, back-office admin, module annonces (catégories/critères dynamiques), back-office agence, workflow de validation, front-office (recherche, filtres, fiches), formulaires de contact, tests fonctionnels par rôle réalisés par Claude Code au fil de l'eau
1-2 semaines
Phase 2
SEO (URLs, sitemap, meta), blog/CMS léger, durcissement sécurité, optimisation performance/mobile, jeu de tests de charge basique
3-5 jours
Phase 3
Préparation et exécution de la mise en ligne sur Plesk (BDD prod, SSL, CRON, sauvegardes), vérifications post-déploiement
2-3 jours
Phase 4
Extension multisite à un 2e pays (réplication de la configuration, validée en conditions réelles)
1-2 jours
Phase 5 (évolutions)
Paiement en ligne de la commission, alertes email, avis clients, application mobile, moteur de recherche avancé (Meilisearch)
à planifier

Estimation globale MVP Côte d'Ivoire en ligne : environ 2 à 3 semaines, contre 10-16 semaines dans un schéma classique — grâce à un développement continu, sans attente entre les lots, Claude Code enchaînant génération, tests et corrections de façon autonome.

### 9.1 Points de passage humains incompressibles
Même avec une intervention "quasi nulle", certains éléments ne peuvent techniquement pas être automatisés et resteront des points de blocage réels s'ils ne sont pas anticipés avant le lancement du développement, pour ne pas casser la continuité :

- Accès et identifiants : accès Plesk (hébergement, base de données), nom de domaine déjà réservé et pointé, accès SMTP pour l'envoi d'emails, accès GitLab.
- Éléments de marque : logo (fichiers finaux), couleurs exactes, police, éventuel wording de la baseline — à fournir en un seul lot au départ pour éviter tout arrêt en cours de route.
- Décisions métier figées à l'avance : taux de commission, statuts d'annonce retenus, durée de vie d'une annonce avant expiration, textes légaux (mentions légales, CGU, politique de confidentialité) ou à défaut un modèle type à valider a posteriori.
- Contenu réel de lancement : les toutes premières annonces et agences partenaires réelles (avec vraies photos) devront être saisies après la mise en ligne, ce n'est pas un travail de développement.
- Une validation finale avant mise en production réelle : même en mode autonome, il est fortement recommandé de garder une vérification humaine rapide juste avant le passage en production (Phase 3), a minima un simple parcours de test sur le site en pré-production, pour éviter qu'une erreur de configuration (ex. base de données, clé SMTP) ne parte en production sans contrôle.

Ces points ne rallongent pas le développement lui-même : ils doivent simplement être préparés en amont (idéalement pendant la Phase 0) pour que Claude Code puisse travailler sans interruption jusqu'à la mise en ligne.

## PROCÉDURE ÉTAPE PAR ÉTAPE — Réalisation avec Claude Code, VS Code, GitLab et mise en ligne via Plesk

## Étape 0 — Préparation des outils
- Installer VS Code (si pas déjà fait) : https://code.visualstudio.com
- Créer un compte GitLab (ou utiliser un compte existant), créer un groupe (ex. votre-agence) puis un projet/dépôt (ex. immo-platform), en Private.
- Installer Git en local et le configurer :

git config --global user.name "Votre Nom"
    git config --global user.email "vous@email.com"

- Installer Claude Code (extension VS Code ou CLI selon votre préférence) et vous connecter avec votre compte Anthropic.
- Installer un environnement PHP local : XAMPP, WAMP, MAMP, ou Laragon (recommandé sous Windows pour sa simplicité, gère PHP + MySQL + Apache/Nginx facilement).
- Vérifier l'accès à votre hébergement Plesk (identifiants, domaine principal, accès base de données MySQL, accès SSH si disponible).

## Étape 1 — Initialisation du dépôt
- Cloner le dépôt GitLab vide en local :

git clone https://gitlab.com/votre-agence/immo-platform.git
    cd immo-platform

- Ouvrir le dossier dans VS Code.
- Créer la structure de base (/app, /public, /config, /database, /lang, .gitignore, README.md).
- Créer un fichier .gitignore incluant : /vendor, .env, /public/uploads, *.log.
- Premier commit :

git add .
    git commit -m "Initialisation du projet"
    git push origin main
## Étape 2 — Cadrage avec Claude Code
- Dans VS Code, ouvrir Claude Code sur le dossier du projet.
- Fournir ce cahier des charges complet à Claude Code comme document de référence (le copier dans un fichier docs/cahier-des-charges.md à la racine du projet, pour que Claude Code puisse s'y référer à chaque session).
- Demander à Claude Code de proposer/valider avec vous :
- le schéma de base de données détaillé (script SQL de création),
- l'arborescence complète des fichiers,
- les conventions de code (nommage, style PSR-12).
- Faire générer le script SQL de création de la base (database/schema.sql) et un jeu de données de test (database/seed.sql).

## Étape 3 — Développement itératif (par petits lots, avec Git à chaque étape)
Travailler fonctionnalité par fonctionnalité, en demandant à Claude Code de :

- Socle technique : routeur front-controller, connexion PDO, gestion des variables d'environnement (.env), autoload des classes (Composer PSR-4).
- Système multisite : middleware de détection du pays via le domaine, chargement de la configuration correspondante.
- Back-office Super Admin : authentification sécurisée, gestion des catégories, gestion des villes/quartiers, gestion des agences.
- Module Annonces : CRUD complet, gestion des critères dynamiques, upload/galerie photo, workflow de validation.
- Back-office Agence partenaire : authentification, dépôt d'annonce, dashboard.
- Front-office : page d'accueil, recherche/filtres, fiche annonce, formulaires de contact.
- SEO & performance : URLs propres, sitemap, meta tags, cache.
- Sécurité : audit CSRF/XSS/injections avec Claude Code, tests de permissions par rôle.

Bonne pratique avec Git à chaque lot terminé :

git checkout -b feature/module-annonces
    # développement avec Claude Code
    git add .
    git commit -m "Ajout du module de gestion des annonces"
    git push origin feature/module-annonces
    # puis Merge Request sur GitLab vers main après relecture

Tester systématiquement en local (via Laragon/XAMPP) avant chaque merge.

## Étape 4 — Tests
- Demander à Claude Code d'aider à rédiger des scénarios de test manuels (checklist par rôle : visiteur, agence, admin).
- Tester sur mobile (responsive) et sur différents navigateurs.
- Tester la charge de base (nombreuses annonces factices) pour vérifier la performance des filtres/recherche.
- Corriger les bugs identifiés, commit + push à chaque correction.

## Étape 5 — Préparation de la mise en ligne (Plesk)
- Dans Plesk, créer le domaine (ex. votre-marque.ci) et son certificat SSL Let's Encrypt (activation en un clic dans Plesk).
- Créer la base de données MySQL dédiée depuis Plesk (Bases de données → Ajouter une base de données), noter les identifiants.
- Configurer PHP côté Plesk : version PHP (choisir 8.2+), extensions activées (PDO, GD ou Imagick pour les images, mbstring, openssl, curl).
- Vérifier/configurer l'accès SSH ou Git dans Plesk (Plesk propose une extension Git : possibilité de connecter directement le dépôt GitLab et de déployer automatiquement à chaque push — recommandé).

## Option A — Déploiement via l'extension Git de Plesk (recommandée)
- Dans Plesk → domaine → Git, ajouter le dépôt distant (URL GitLab + clé SSH de déploiement à ajouter dans GitLab en lecture seule).
- Définir le dossier de déploiement public (public/ comme racine du domaine, document root).
- Configurer un webhook ou un déploiement manuel à chaque push sur main.
- À chaque mise à jour validée en local : git push origin main → Plesk récupère automatiquement le code.

## Option B — Déploiement manuel via SFTP/SSH
- Se connecter en SFTP (identifiants Plesk) depuis VS Code (extension SFTP) ou FileZilla.
- Transférer les fichiers du projet (hors .git, vendor si non commité — sinon lancer composer install en SSH sur le serveur).
- Créer le fichier .env de production directement sur le serveur (ne jamais le committer) avec les vrais identifiants de base de données.

## Étape 6 — Mise en production
- Importer le schéma SQL (schema.sql) dans la base de données de production via phpMyAdmin (accessible depuis Plesk) ou en SSH.
- Créer le premier compte Super Admin (script d'installation ou insertion manuelle sécurisée).
- Vérifier le bon fonctionnement : HTTPS actif, formulaires fonctionnels (test d'envoi d'email SMTP), upload d'images opérationnel, permissions de dossiers correctes (public/uploads en écriture).
- Configurer les tâches planifiées (CRON) si besoin dans Plesk (ex. : expiration automatique des annonces, envoi de rapports).
- Soumettre le sitemap à Google Search Console, vérifier le site avec Google, configurer Google Analytics.

## Étape 7 — Après mise en ligne
- Créer les comptes des premières agences partenaires réelles.
- Publier vos premières annonces.
- Suivre les statistiques (Analytics + dashboard back-office).
- Planifier la Phase 4 : réplication de la configuration pour le 2ᵉ pays (nouveau domaine dans Plesk, nouvelle entrée dans la table countries/sites, mêmes code et base de données).

## 10. Recommandations finales
- Toujours travailler avec des branches Git séparées par fonctionnalité et ne fusionner sur main qu'après test.
- Garder ce cahier des charges dans le dépôt (docs/) et le mettre à jour au fil du projet : c'est le document de référence que vous pourrez refournir à Claude Code à chaque nouvelle session pour qu'il garde le contexte complet du projet.
- Prévoir un environnement de pré-production (sous-domaine staging.votre-marque.ci sur Plesk) pour tester avant chaque mise en production définitive.
- Mettre en place des sauvegardes automatiques (Plesk propose une sauvegarde programmée de la base et des fichiers — à activer dès le premier jour).

