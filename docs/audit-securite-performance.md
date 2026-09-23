# Audit sécurité & performance — lot 2.3

Audit réalisé le **16 septembre 2026** sur la version locale (MAMP, PHP 8.3.14, MySQL 8), avant la
préparation de la mise en production (lot 3.1).

Périmètre : le site public, le back-office `/cmsadmin` et les traitements en ligne de commande.
Trois volets : **sécurité applicative**, **performance sous charge**, **test de charge à 10 000
annonces**. Les correctifs apportés pendant l'audit sont marqués ✅ ; les points laissés ouverts sont
regroupés en fin de document.

---

## 1. Sécurité

### 1.1 CSRF

Le middleware `VerifyCsrfToken` est **global** : toute requête POST, PUT, PATCH ou DELETE sans jeton
valide est refusée en 419, y compris sur les formulaires publics qui ne demandent aucune connexion.

Vérifié : POST sans jeton sur `/contact`, `/devenir-partenaire` et `/deposer-un-bien` → **419** dans
les trois cas. Le jeton ne circule jamais en paramètre d'URL (champ caché `_csrf` ou en-tête
`X-CSRF-Token`), il ne peut donc pas fuiter par le `Referer` ni par les journaux du serveur.

### 1.2 XSS

Revue de toutes les vues : chaque valeur issue de la base ou d'un formulaire passe par `e()`
(`htmlspecialchars`, `ENT_QUOTES`, UTF-8). Les seules sorties en HTML brut sont :

- le contenu des pages éditoriales et des actualités (`pages.content`, `blog_posts.content`), saisi
  par l'équipe interne depuis le back-office — un rôle déjà autorisé à modifier le site ;
- des fragments pré-rendus par l'application elle-même (partials, pagination, badges).

Aucune donnée fournie par un visiteur ou par une agence partenaire n'est affichée sans échappement.
Les attributs `data-*` alimentés en JavaScript (carte, favoris, galerie) passent par `json_encode`
puis `e()`.

### 1.3 Injection SQL

Toutes les requêtes sont préparées, avec `EMULATE_PREPARES=false` : aucune valeur n'est concaténée.
Les seuls éléments interpolés dans une requête sont des **noms de colonnes et de tables construits
par le code**, systématiquement issus d'une liste blanche :

- `PropertyRepository` : `assertTable()` et la liste des colonnes indexées (`storage = 'column'`) ;
- `ListingRepository::conditions()` : colonnes de filtre choisies dans un tableau fixe ;
- `SearchFilters` : seuls les paramètres connus (`PATH_PARAMS` et la liste des filtres) sont lus.

Vérifié qu'aucun `$request->all()` n'arrive tel quel dans un dépôt : les contrôleurs passent par
`Validator` et reconstruisent un tableau de données explicite.

### 1.4 Envoi de fichiers

| Protection | Mise en œuvre |
|---|---|
| Exécution interdite | `public/uploads/.htaccess` refuse `.php`, `.phtml`, `.svg`, `.js`, `.html` — vérifié : `/uploads/test.php` → **403** |
| Type réel | `finfo` sur le fichier reçu, l'extension et le `Content-Type` du navigateur sont ignorés |
| Types acceptés | `image/jpeg`, `image/png`, `image/webp` uniquement |
| Bombe de décompression | plafond `MAX_PIXELS` avant tout traitement GD |
| Ré-encodage | toute image est redécodée puis réécrite en WebP (métadonnées EXIF supprimées, orientation appliquée) |
| Nom de fichier | aléatoire, jamais le nom d'origine |
| Taille | 8 Mo pour les images de contenu, plafond par champ |

✅ **Correctif** : le message d'erreur d'envoi d'une image de contenu était construit avec un double
préfixe (`__('upload.' . $error)` alors que `ImageUploader::check()` renvoie déjà `upload.too_large`),
ce qui affichait la clé de traduction au lieu du message. Corrigé dans `ContentController`.

### 1.5 Contrôle d'accès

Contrôlé en détail au lot 1.13 (recette par rôle, `docs/tests.md`, 72 vérifications automatisées).
Rappel des garanties confirmées :

- une agence qui agit sur l'annonce, le contact ou le profil d'une autre agence → **404** ;
- un Admin Pays hors de son pays → refus ; 11 écrans sont fermés à tout autre rôle que Super Admin ;
- le menu n'est jamais un contrôle d'accès : chaque action revérifie rôle, `country_id` et
  `agency_id` côté serveur.

### 1.6 Redirections ouvertes

`backPath()` (retour après enregistrement) n'accepte qu'un chemin interne : toute valeur absolue ou
commençant par `//` est écartée. Les redirections gérées en base (`redirects`) sont réservées au
Super Admin et ne peuvent ni boucler ni s'enchaîner (contrôles à l'enregistrement).

### 1.7 En-têtes et session

Vérifiés sur une réponse réelle : `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy`, `Permissions-Policy`, HSTS en HTTPS. Cookie de session `HttpOnly`, `SameSite=Lax`,
`Secure` dès que la connexion est chiffrée, identifiant régénéré à la connexion.

`X-Robots-Tag: noindex, nofollow` est posé sur `/cmsadmin` **et** sur tout le site tant que l'hôte
n'est pas le domaine de production — un domaine de recette ne peut pas être indexé par erreur.

### 1.8 Exposition des fichiers internes

Toutes les URL suivantes répondent **403** : `/.env`, `/composer.json`, `/composer.lock`,
`/app/bootstrap.php`, `/config/database.php`, `/storage/logs/`, `/vendor/autoload.php`,
`/database/schema.sql`, `/docs/PLAN.md`, `/.git/config`, `/bin/create-user.php`. Le listage de
répertoire est désactivé (`Options -Indexes`).

> En production la racine web est `public/` : ces dossiers sont de toute façon hors de l'arborescence
> servie. Le `.htaccess` racine n'est qu'un filet de sécurité pour l'environnement local.

### 1.9 Confidentialité des données sensibles

Règle non négociable : `property_private_details` (notaire, référence de dossier) **ne doit jamais
être lu par une requête publique**. Vérifié par revue exhaustive : la table n'est lue que par
`PropertyRepository` (back-office) ; dans `ListingRepository`, `ListingPresenter` et
`PropertyController`, les seules occurrences du nom sont des commentaires rappelant la règle.

L'export CSV des annonces ne joint pas cette table et n'exporte l'adresse que si
`show_exact_location = 1`. Il est réservé au rôle `staff` et limité au pays du site.

---

## 2. Test de charge

### 2.1 Jeu de données

10 000 annonces générées dans le pays du site (9 500 publiées, 500 en attente), rattachées à une
agence, avec 30 000 équipements et 10 000 valeurs de critères EAV. Base portée à **17,5 Mo**.

> Les données de charge ont été supprimées à la fin de l'audit : la base locale ne contient plus que
> les référentiels, le compte Super Admin du client et les contenus de démonstration.

### 2.2 Temps de réponse du site public

Mesures serveur (`curl`, hors réseau et hors rendu navigateur), après chauffe, sur les 10 000
annonces :

| Page | Temps | Poids HTML |
|---|---|---|
| Accueil | 44 ms | 48 Ko |
| Résultats `/acheter` | 26 ms | 89 Ko |
| Résultats filtrés (prix + chambres + tri) | 18 ms | 90 Ko |
| Résultats par URL propre `/acheter/appartement/abidjan` | 10 ms | 88 Ko |
| Résultats en vue carte | 52 ms | 217 Ko |
| Fiche annonce | 8 ms | 28 Ko |
| Annuaire des agences | 10 ms | 11 Ko |
| Fiche agence (pire cas : 10 000 annonces sur une seule agence) | 60 ms | 34 Ko |
| Actualités | 6 ms | 9 Ko |
| `sitemap.xml` (9 693 URL) | 123 ms | 1,8 Mo |

Le budget mobile (LCP < 2,5 s en 4G) est tenu côté serveur avec une marge très large : le temps de
rendu est dominé par le réseau et les images, pas par PHP.

### 2.3 Temps de réponse du back-office

| Écran | Temps |
|---|---|
| Tableau de bord équipe | 69 ms |
| Liste des annonces (10 000 lignes, page 1) | 82 ms |
| Liste des annonces, page 200 | 83 ms |
| Liste filtrée par statut | 24 ms |
| Recherche plein texte sur la référence | 82 ms |
| Agences, contacts, référencement, paramètres | 12 ms |

La pagination profonde ne dégrade pas : le coût vient du tri, pas du décalage.

### 2.4 Export CSV au plafond

L'export est plafonné à 10 000 lignes et construit en mémoire. Mesuré à ce plafond exact :

- requête : 102 ms ;
- fichier produit : 2,8 Mo, 10 001 lignes ;
- **pic mémoire : 28 Mo**, pour un `memory_limit` de 128 Mo en production.

Le plafond `MAX_ROWS` est donc correctement dimensionné. Au-delà (multi-pays, plusieurs années
d'historique), il faudra passer à un envoi en flux plutôt qu'à une construction en mémoire.

---

## 3. Correctifs de performance apportés

### ✅ 3.1 Sitemap : une requête par URL

`SitemapController` interrogeait `seo_meta` **une fois par URL** pour savoir si la page était en
`noindex`, soit près de 9 700 requêtes sur un site chargé.

`SeoRepository::noindexPaths()` charge désormais la liste en **une seule requête** et le contrôleur
filtre en mémoire.

> `sitemap.xml` : **965 ms → 170 ms** au moment du correctif (123 ms après les optimisations suivantes).

### ✅ 3.2 Accueil : tri de toutes les annonces du pays

`ListingRepository::latest()` sélectionnait les 8 dernières annonces avec les six jointures de la
carte dans le même `SELECT`. L'optimiseur MySQL choisissait alors de commencer par
`transaction_types` (5 lignes) et **triait les 9 500 annonces publiées** (`Using temporary; Using
filesort`) avant d'en garder 8.

La requête est désormais en deux temps : les identifiants sont lus sur `properties` seule — l'ordre
chronologique se lit alors directement dans `idx_properties_published` (`Backward index scan`, sans
tri) — puis les cartes sont chargées par identifiants.

> `latest()` : **88 ms → 2 ms**. Accueil complet : **88 ms → 44 ms**.

### ✅ 3.3 Compression et cache navigateur des ressources statiques

`public/.htaccess` ne portait ni compression ni en-tête de cache : `app.css` partait en **172 Ko non
compressés à chaque visite**, de même que le JavaScript et le sprite d'icônes.

Ajouté :

- **gzip** sur le HTML, le CSS, le JS, le XML, le JSON et le SVG (les WebP, woff2 et PDF sont déjà
  compressés et sont laissés de côté) ;
- **`Cache-Control: public, max-age=31536000, immutable`** sur les ressources statiques. C'est sans
  risque ici : les URL de CSS/JS portent déjà `?v={date de modification}` (helpers `asset()` et
  `cmsadmin_asset()`) et les fichiers envoyés ont un nom aléatoire jamais réécrit — le contenu d'une
  URL ne change jamais.

Les pages HTML, elles, ne sont **pas** mises en cache par le navigateur : elles dépendent du site
résolu et de la session.

> `mod_deflate` n'est pas chargé dans MAMP : la directive a été écrite sous `<IfModule>` et reste
> sans effet en local. **À vérifier sur Plesk lors de la mise en production** (lot 3.1). L'en-tête de
> cache, lui, a été vérifié en local sur le CSS, le JS, le sprite et les polices.

### 3.4 Index vérifiés

`EXPLAIN` sur la recherche filtrée confirme l'usage de `idx_properties_search`
(`country_id, status, transaction_type_id, category_id, price`) : `rows: 1500`, `Using index
condition; Using where`. Les index de localisation, de surface et de chambres sont sollicités par les
filtres correspondants. Aucun index manquant identifié à ce volume.

---

## 4. Points ouverts et recommandations

| # | Point | Recommandation | Échéance |
|---|---|---|---|
| 1 | `mod_deflate` non vérifiable en local | Confirmer la compression active sur Plesk (`curl -H "Accept-Encoding: gzip" -I`) | Lot 3.1 |
| 2 | Logos PNG : 54 Ko chargés sur chaque page (42 Ko + 12 Ko, versions sombre et claire) | WebP servis via `<picture>` avec repli PNG (`logo_picture()`, fichiers générés par `php bin/build-logos.php`, sans perte pour le logo blanc, qualité 92 pour les autres) : **54 Ko → 27 Ko**, rendu identique à l’œil. Les PNG d’origine restent (repli, JSON-LD, emails) | **Fait** |
| 3 | Liste des annonces du back-office : 82 ms, tri non indexable (`FIELD(status,'pending') DESC, COALESCE(updated_at, created_at) DESC`) | Acceptable à ce volume. À revoir au-delà de ~50 000 annonces par pays : colonne générée indexée ou simplification du tri | Si le volume l'impose |
| 4 | Export CSV construit en mémoire | Suffisant jusqu'au plafond de 10 000 lignes (28 Mo mesurés). Passer en flux si le plafond est relevé | Si le plafond change |
| 5 | Pas de cache HTTP sur le HTML | Un micro-cache (quelques secondes) devant PHP serait utile en cas de pic de trafic. Dépend de l'infrastructure retenue | À arbitrer avec l'hébergeur |
| 6 | Mot de passe d'application Gmail communiqué en clair pendant le développement | Le régénérer et remplacer `SMTP_PASSWORD` | **Lot 3.1, obligatoire** |
| 7 | Cookies d'authentification derrière le proxy nginx de Plesk : `SESSION_SECURE=auto` dépend de la détection du HTTPS par Apache | `SESSION_SECURE=true` en production (runbook mis à jour) ; le cookie « Rester connecté » suit désormais ce réglage ; `bin/check-deploy.php` alerte sinon | **Lot 3.1** |
| 8 | Aucune `Content-Security-Policy` sur les pages HTML | Défense en profondeur : la poser d'abord en `Report-Only` après la mise en ligne (scripts tous auto-hébergés, seules exceptions : tuiles OpenStreetMap), puis l'appliquer | Après mise en ligne |
| 9 | Contenu des pages et actualités saisi en HTML brut par le Super Admin | Acceptable (compte de confiance, rôle unique). Si la rédaction est un jour confiée à d'autres rôles, filtrer le HTML (liste blanche de balises) | Si les rôles changent |

---

## 5. Conclusion

Aucune vulnérabilité exploitable n'a été trouvée sur le périmètre audité. Les trois correctifs de
performance apportés (sitemap, accueil, compression/cache) sont appliqués et vérifiés. À 10 000
annonces, toutes les pages publiques répondent **en moins de 125 ms** côté serveur et le back-office
**en moins de 85 ms**.

Les points 1 et 6 du tableau ci-dessus sont des **prérequis de mise en production** et sont repris
dans la checklist du lot 3.1.

---

## 6. Audit de pré-production (17/09/2026)

Audit complet avant mise en ligne, sur la version `main` du jour : fonctionnement, sécurité, référencement,
rapidité d'affichage. Données de test créées pour l'occasion (partenaire, annonces avec photos, particulier,
prospect, article), puis **supprimées** ; emails en pilote `log` pendant les tests.

### 6.1 Contrôles statiques

| Contrôle | Résultat |
|---|---|
| Syntaxe PHP, 248 fichiers, PHP 8.3 (MAMP) et 8.4 | ✅ aucune erreur ; aucune fonction propre à PHP 8.3+ (compatibilité 8.2 conservée) |
| 151 routes → contrôleur et méthode existants | ✅ |
| 358 appels de vues → fichier existant | ✅ |
| Traductions : 1 901 clés, parité `fr`/`en` | ✅ 0 clé manquante |
| Restes de débogage (`var_dump`, `dd`, `print_r`) | ✅ aucun |
| Bibliothèques tierces | ✅ PHPMailer 6.12, jQuery 3.7.1, Bootstrap 5.3, Chart.js 4.4, Select2 4.0.13, Leaflet 1.9.4 — versions sans faille connue exploitable ici |

### 6.2 Parcours fonctionnels rejoués

| Parcours | Résultat |
|---|---|
| Dossier partenaire public avec pièces (PDF, photo) ; fichier PHP déguisé en `.jpg` | ✅ enregistré ; faux fichier **refusé** (type réel vérifié) |
| Pièces justificatives : équipe / anonyme / partenaire | ✅ téléchargement `attachment` + `nosniff` + CSP `sandbox` / 302 connexion / 403 |
| Agence créée depuis le dossier → invitation → mot de passe → connexion partenaire | ✅ |
| Cloisonnement du partenaire (13 écrans réservés à l'équipe) | ✅ 403 partout |
| Annonce partenaire : photos en arrière-plan, envoi en validation, validation par l'équipe | ✅ ; action de validation par le partenaire → 403 ; POST sans jeton → 419 |
| Fiche publique : contact interne, notaire, référence interne, adresse privée, nom de l'agence | ✅ **aucune fuite** ; `<script>` saisi dans la description affiché comme texte |
| Recherche : mot-clé, référence, filtres invalides, injection HTML dans `q` | ✅ résultats justes, paramètres invalides ignorés, sortie échappée |
| Demande de prospect : envoi, robot (pot de miel), formulaire invalide | ✅ équipe seule notifiée (jamais le partenaire), robot ignoré, 422 |
| Particulier : inscription → confirmation email → bien confié → conversion en brouillon → publication | ✅ dossier « publié », email au propriétaire ; aucune donnée du propriétaire en public |
| Modification d'une annonce en ligne par le partenaire | ✅ révision en attente, version en ligne inchangée |
| Désactivation / réactivation partenaire ; réactivation après dépublication par l'équipe | ✅ ; la seconde est **refusée** |
| Contact général, mot de passe oublié (adresse connue ou non) | ✅ réponse identique dans les deux cas |
| CRON `expire-listings` et `cleanup-uploads` (simulation) | ✅ |
| 52 écrans du back-office (Super Admin) | ✅ aucun code 500, 3 à 26 ms |
| Fichiers internes (`.env`, `app/`, `storage/`, `vendor/`, `docs/`…) et script PHP déposé dans `uploads/` | ✅ 403, non exécuté |

### 6.3 Défauts trouvés et corrigés

| # | Domaine | Défaut | Correctif |
|---|---|---|---|
| 1 | Sécurité | Inscription particulier : le message « adresse déjà utilisée » était renvoyé **sans limite**, un robot pouvait tester une liste d'emails | Quota propre à cette vérification (20 par heure et par IP), vérifié : 429 au 21ᵉ essai |
| 2 | Sécurité | Cookie « Rester connecté » (30 jours) : attribut `Secure` dépendant de la seule détection du HTTPS | Suit aussi `SESSION_SECURE` ; runbook passé à `SESSION_SECURE=true` ; contrôle ajouté à `check-deploy.php` |
| 3 | Rapidité | Accueil : les 4 photos du diaporama étaient toutes téléchargées au chargement (`loading="lazy"` sans effet sur des images empilées dans la fenêtre) | Seule la diapositive suivante est chargée, juste avant son tour : **957 → 491 Ko** sur ordinateur, **741 → 428 Ko** sur mobile |
| 4 | SEO | Sitemap : Contact, Confiez-nous votre bien, Devenir partenaire et **les actualités** (liste et articles) absents alors qu'indexables | Ajoutés (actualités seulement si au moins un article est publié) |
| 5 | Affichage | Fiche annonce avec 2 ou 3 photos : mosaïque prévue pour 5, grand vide blanc | Mosaïque recomposée selon le nombre de photos |
| 6 | Affichage | Bloc contact de la fiche : le numéro de téléphone débordait de son bouton | Boutons empilés quand la place manque |
| 7 | Ergonomie | Formulaire d'annonce : « Envoyer en validation » affiché à un Super Admin dont l'annonce est publiée directement | « Publier l'annonce » selon les règles réelles de publication du compte |
| 8 | Robustesse | Mise en page publique : une page sans description provoquait une erreur | Valeur par défaut, balise omise si vide |
| 9 | Accessibilité | Intitulé des filtres actifs : guillemets doublés (« « Riviera » ») lus par les lecteurs d'écran | Libellé corrigé (fr, en) |

### 6.4 Référencement (vérifié)

- Un seul `h1` par page, `title` et `description` présents partout, canonique et Open Graph sur les pages publiques.
- Fiche annonce : JSON-LD `RealEstateListing` + `Offer` + `RealEstateAgent` (Weblogy), image de partage, slug obsolète → 301.
- Pages vides ou filtrées → `noindex` ; sitemap : 100 % des URL listées répondent 200 sans `noindex`.
- `robots.txt` : tout interdit hors production (normal en local), règles et sitemap en production.
- Recommandation (non bloquante) : titres de fiches longs (~85 caractères avec le nom du site) — Google tronque vers 60 ; le début, le plus utile, reste visible.

### 6.5 Rapidité d'affichage (mesurée dans Chrome)

| Page | Serveur | HTML (gzip) | Transfert initial |
|---|---|---|---|
| Accueil ordinateur / mobile | 26 ms | 6,6 Ko | 491 Ko / 428 Ko (dont photo du diaporama 76 / 46 Ko) |
| Résultats de recherche | 12 ms | 6,4 Ko | ≈ 145 Ko compressés |
| Fiche annonce | 7 ms | 5,7 Ko | ≈ 195 Ko compressés (dont Leaflet 42 Ko) |

CSS 192 Ko → **30 Ko compressé** : la compression `mod_deflate` sur Plesk (point 1 du § 4) est donc déterminante.
Les photos du diaporama restent des **photos provisoires** ; les bannières définitives passeront par
`ImageUploader` (WebP).

### 6.6 Verdict

**Le site est prêt pour la mise en production** sur le plan fonctionnel, sécurité, référencement et performance.

Mise à jour du **23/09/2026**, le site étant déployé : accès Plesk et DNS obtenus, `SESSION_SECURE=true`
posé, et la **compression est vérifiée en ligne** — `curl -H "Accept-Encoding: gzip" -I` sur `app.css`
renvoie `Content-Encoding: gzip` **et** `Cache-Control: public, max-age=31536000, immutable`. Ce point,
non testable en local (MAMP ne charge pas `mod_deflate`), est donc clos : les 192 Ko de CSS partent bien
en 30 Ko. Restent hors code : nouveau mot de passe SMTP, autorisation d'intermédiation dans les mentions
légales, relecture juridique.

