# Widget « Biens à la une » sur Abidjan.net (lot 2.6)

La section « Biens à la une » de l'accueil, affichée à l'identique sur les pages d'Abidjan.net pour y
amener les visiteurs vers la rubrique immobilière. Ce document sert à deux publics : **l'équipe
technique d'Abidjan.net** (§ 1 et 2, à lui transmettre) et **nous** (§ 3 et suivants).

## 1. Intégration recommandée : inclusion côté serveur (PHP)

C'est la forme la plus légère pour Abidjan.net : la section arrive dans le HTML de la page, sans
script ni requête supplémentaire côté visiteur, sans décalage à l'affichage, et ses liens sont de vrais
liens lus par les moteurs de recherche.

1. Copier [`docs/widget/biens-a-la-une.php`](widget/biens-a-la-une.php) sur le serveur d'Abidjan.net
   (par exemple `includes/ian-biens-a-la-une.php`).
2. À l'endroit voulu de la page :

```php
<?php require_once __DIR__ . '/includes/ian-biens-a-la-une.php'; ?>
<?= ian_biens_a_la_une(3) ?>
```

Le nombre d'annonces va de 1 à 6 (3 par défaut). La fonction :

- garde le fragment **10 minutes** dans un fichier du dossier temporaire du serveur : immobilier.abidjan.net
  n'est appelé qu'une fois toutes les 10 minutes, quel que soit le trafic ;
- n'attend **jamais plus de 2 secondes** ; sans réponse, elle ressert la dernière version gardée, ou
  rien (chaîne vide) — la page d'Abidjan.net n'est jamais bloquée ni cassée ;
- fonctionne de PHP 5.4 à 8.x, avec cURL ou, à défaut, `allow_url_fopen`.

La section occupe toute la largeur du bloc qui la contient : c'est la page d'Abidjan.net qui fixe la
largeur et les marges. Elle s'adapte à cette largeur (et non à celle de l'écran) : trois colonnes à
partir de 900 px, défilement horizontal en dessous.

## 2. Variante : script

Pour une page où l'inclusion PHP n'est pas possible :

```html
<div data-ian-widget="a-la-une" data-limit="3">
  <a href="https://immobilier.abidjan.net/">Abidjan.net Immobilier : toutes les annonces</a>
</div>
<script src="https://immobilier.abidjan.net/widget/biens-a-la-une.js" async></script>
```

Le script (3 Ko) charge la section quand la zone approche de l'écran. Sans JavaScript, sans réponse ou
sans annonce à la une, le lien placé dans le `div` reste affiché. Si la page d'Abidjan.net a une
politique CSP, autoriser `https://immobilier.abidjan.net` en `script-src`, `connect-src`, `img-src`
et `font-src`.

## 3. Fonctionnement

| Élément | Fichier |
|---|---|
| Fragment HTML (`GET /widget/biens-a-la-une?limit=1…6`) | `app/Controllers/Front/WidgetController.php`, vue `app/Views/front/widget/featured.php` |
| Carte annonce | `app/Views/front/partials/property-card.php` avec `embed => true` |
| Feuille de style | `resources/scss/widget.scss` → `public/assets/css/widget.css` (`php bin/build-css.php`) |
| Script | `public/widget/biens-a-la-une.js` (cache 1 h : `public/widget/.htaccess`) |
| Inclusion PHP remise à Abidjan.net | `docs/widget/biens-a-la-une.php` |

- **Mêmes données, même carte que l'accueil** : `ListingRepository::featured()` (pays du site, annonces
  publiées et « à la une ») et `ListingPresenter::cards()`. Weblogy reste le seul interlocuteur affiché.
- **Isolation** : le fragment est une racine fantôme déclarative (`<template shadowrootmode="open">`),
  que le CSS d'Abidjan.net ne peut pas atteindre et dont le nôtre ne sort pas. Seules les deux
  `@font-face` sont déclarées hors de la racine (une police n'est prise en compte qu'au niveau du
  document), sous un nom propre (« IAN Plus Jakarta Sans ») qui ne peut pas entrer en conflit avec une
  police d'Abidjan.net.
- **Style identique** : `widget.scss` importe les mêmes sources que le site (`_tokens.scss`,
  `_ui.scss`, `_property-card.scss`) — une retouche de la carte passe dans le widget à la
  compilation suivante. Deux adaptations : les `rem` sont convertis en `px` à la compilation (un `rem`
  dépendrait de la taille de police de la page d'Abidjan.net), et la grille suit la largeur du bloc
  (requêtes de conteneur). La remise à zéro est en `:where()` : elle ne l'emporte jamais sur un composant.
- **Adaptations de la carte** (`embed`) : adresses complètes, marquées
  `utm_source=abidjan.net&utm_medium=widget&utm_campaign=biens-a-la-une` pour Google Analytics ; icônes
  recopiées dans le HTML (`icon_inline()` : un navigateur refuse un sprite SVG d'une autre origine) ;
  **pas de bouton favori** (les favoris vivent dans le navigateur, sur notre domaine).
- **Cache** : fragment gardé 5 minutes sur notre serveur (`storage/cache`, vidé par
  `bin/cache-clear.php`), `Cache-Control: public, max-age=600, stale-while-revalidate=600` pour les
  navigateurs et l'inclusion PHP. Une mise à la une ou un retrait apparaît donc sur Abidjan.net en
  15 minutes au plus. Aucun cookie, aucune session.
- **CORS** : seul le script en a besoin. `WIDGET_ORIGINS` dans `.env` (par défaut
  `https://www.abidjan.net,https://abidjan.net`) ; une autre origine ne reçoit pas
  d'`Access-Control-Allow-Origin`. Les polices sont servies avec `Access-Control-Allow-Origin: *`
  (`public/.htaccess`, police libre OFL).
- **Mode démonstration** : le fragment est **vide** tant que `demo.active` est posé — Abidjan.net ne
  montre pas d'annonce fictive au public. Même chose s'il n'y a aucun bien à la une.
  **Exception pour la validation** : `WIDGET_ALLOW_DEMO=true` dans `.env` sert les biens de démonstration
  (voir § 4 bis). Le réglage devient sans effet dès la purge de la démo (`bin/reset-before-launch.php`).
- Le fragment est servi en `X-Robots-Tag: noindex` : ce n'est pas une page.

## 4. Avant la mise en service sur Abidjan.net

1. Le site doit être **ouvert** (`bin/reset-before-launch.php --confirm`) : en mode démonstration le
   fragment est vide.
2. La **protection par mot de passe** de l'aperçu (réponse `401`) doit être levée, sinon ni le serveur
   ni les visiteurs d'Abidjan.net ne peuvent charger le fragment.
3. Si Abidjan.net sert ses pages sous une autre adresse que `https://www.abidjan.net` ou
   `https://abidjan.net`, compléter `WIDGET_ORIGINS` dans `.env` (variante script uniquement).
4. Vérifier :

```bash
curl -s "https://immobilier.abidjan.net/widget/biens-a-la-une?limit=3" | grep -c '<article'   # 3
curl -sI -H "Origin: https://www.abidjan.net" https://immobilier.abidjan.net/widget/biens-a-la-une \
  | grep -i access-control-allow-origin
curl -sI https://immobilier.abidjan.net/widget/biens-a-la-une.js | grep -i cache-control        # max-age=3600
```

## 4 bis. Validation du rendu avant l'ouverture

Pour faire valider la section sur Abidjan.net alors que le site est encore en démonstration :

1. Sur le serveur immobilier, ajouter `WIDGET_ALLOW_DEMO=true` au `.env`, puis
   `/opt/plesk/php/8.2/bin/php bin/cache-clear.php`.
2. Lever la protection par mot de passe (`401`) le temps de la validation.
3. Afficher la section **sur une page de test d'Abidjan.net** (`index-test.php`), pas sur l'accueil
   public : ce sont des annonces fictives. Ajouter `<meta name="robots" content="noindex">` à cette page.
4. Après validation : retirer `WIDGET_ALLOW_DEMO` du `.env`, vider le cache, remettre le `401`.
   Côté Abidjan.net, la copie gardée expire en 10 minutes ; pour l'effacer tout de suite :
   `rm -f /tmp/ian-biens-a-la-une-*` sur leur serveur.

## 5. Tester en local

Le fragment est vide tant que la base locale est en mode démonstration : ajouter temporairement
`WIDGET_ALLOW_DEMO=true` au `.env`, puis le retirer et lancer `php bin/cache-clear.php`. Une page
servie sur une autre origine (`php -S 127.0.0.1:8767` dans le scratchpad, avec
`WIDGET_ORIGINS=http://127.0.0.1:8767` dans `.env` le temps du test) reproduit Abidjan.net ; lui
ajouter une feuille hostile (`html{font-size:62.5%}`, `a{color:green}`…) vérifie l'isolation.
