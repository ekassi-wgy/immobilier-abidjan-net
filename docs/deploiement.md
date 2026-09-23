# Mise en production — immobilier.abidjan.net (lot 3.1)

Procédure de première mise en ligne sur **Plesk**, puis de chaque déploiement suivant.

> **Ce document est une procédure, pas un compte rendu.** Le lot 3.1 ne peut pas être exécuté tant
> que le client n'a pas fourni les accès (§ 0). Tout ce qui pouvait être préparé sans ces accès l'a
> été : ce runbook et le script `bin/check-deploy.php`.

---

## 0. Ce qu'il faut obtenir du client avant de commencer

| Élément | Pourquoi | Statut |
|---|---|---|
| Accès Plesk (abonnement du domaine `abidjan.net`) | créer le sous-domaine, gérer PHP, SSL, CRON, sauvegardes | ⬜ |
| Droit de créer l'enregistrement DNS `immobilier.abidjan.net` | pointer le sous-domaine | ⬜ |
| Moteur de base de production : **MySQL 8 ou MariaDB (préciser la version)** | le schéma est écrit pour les deux mais n'a été testé que sur MySQL 8.0.40 | ⬜ |
| Clé SSH de déploiement à ajouter au dépôt GitHub | `git pull` depuis le serveur, sans mot de passe | ⬜ |
| Nouveau mot de passe d'application Gmail | celui du développement a circulé en clair, il doit être révoqué | ⬜ |

Les **mentions obligatoires** encore manquantes des mentions légales (autorisation d'intermédiation immobilière — l'identité de Weblogy Tech S.A est
renseignée par la migration `0010`) doivent aussi être complétées dans `/cmsadmin/pages` avant
l'ouverture au public.

---

## 1. Sous-domaine et PHP

1. Plesk → **Domaines** → *Ajouter un sous-domaine* : `immobilier` sous `abidjan.net`.
2. **Racine des documents : `httpdocs/public`** — c'est le point le plus important de toute la
   procédure. Si la racine pointe sur `httpdocs/`, l'intégralité du code source (`app/`, `config/`,
   `.env`) devient téléchargeable.
3. Plesk → **Paramètres PHP** du sous-domaine :
   - version **8.2 ou plus récente**, en mode *FPM géré par Apache* ou *FPM autonome* ;
   - extensions : `gd` (avec WebP), `intl`, `mbstring`, `pdo_mysql`, `fileinfo` ;
   - `memory_limit` ≥ 128M, `upload_max_filesize` ≥ 12M, `post_max_size` ≥ 60M (une annonce peut
     recevoir plusieurs photos en une fois), `max_execution_time` ≥ 60 ;
   - `display_errors = Off`.
4. Vérifier que la **ligne de commande** utilise elle aussi PHP 8.2+ (`php -v` en SSH). **Ce n'est
   presque jamais le cas** : sur le serveur du client, le `php` du PATH est le PHP système
   (constaté : 5.4.16), qui ne sait même pas lire `declare(strict_types=1)` — le script s'arrête sur
   `Unsupported declare 'strict_types'`. Lister les versions Plesk avec `ls /opt/plesk/php/` et
   employer **le chemin complet partout** : scripts `bin/`, tâches planifiées, Composer.

   ```bash
   ls /opt/plesk/php/                       # versions installées
   /opt/plesk/php/8.2/bin/php -v
   /opt/plesk/php/8.2/bin/php -m | grep -E '^(gd|intl|mbstring|pdo_mysql|fileinfo)$'
   /opt/plesk/php/8.2/bin/php -r 'var_dump(function_exists("imagewebp"), gd_info()["WebP Support"] ?? false);'
   ```

   Le dernier contrôle n'est pas une formalité : toutes les photos du site sont en WebP, et sans ce
   support GD, `bin/seed-demo.php` et tout envoi de photo échouent en cours de route.

## 2. Base de données

Les fichiers SQL arrivent avec le dépôt (dossier `database/`) : déployer le code (§ 3) **avant**
l'import, ou téléverser les fichiers à la main si l'import se fait depuis phpMyAdmin.

1. Plesk → **Bases de données** → créer `immobilier_abidjan_net` en `utf8mb4` /
   `utf8mb4_unicode_ci`, avec un utilisateur dédié (**jamais** l'utilisateur d'administration).
2. Importer **dans cet ordre** : `schema.sql`, puis `seed.sql`, puis les migrations par numéro
   croissant (`0002` → `0014`). Deux voies, au choix — voir § 2.1 et § 2.2.
3. **Vérifier** la table `site_domains` : `seed.sql` y déclare déjà `immobilier.abidjan.net` en
   `environment = 'production'`, `is_primary = 1` — il n'y a normalement rien à modifier.

   ```sql
   SELECT host, environment, is_primary FROM site_domains;
   ```

   **Tant qu'un hôte n'est pas déclaré, le site répond 404**, et un hôte déclaré hors production
   met tout le site en `noindex`.
4. Créer le compte du client :

```bash
php bin/create-user.php --role=super_admin --email=… --first-name=… --last-name=…
```

Le mot de passe provisoire n'est affiché qu'une fois et devra être changé à la première connexion.

### 2.1 Import en SSH (voie recommandée)

```bash
cd ~/httpdocs

# Évite de retaper le mot de passe à chaque fichier et fixe le jeu de caractères du client.
cat > ~/.my.cnf <<'EOF'
[client]
user=<utilisateur>
password=<mot_de_passe>
host=127.0.0.1
default-character-set=utf8mb4
EOF
chmod 600 ~/.my.cnf

mysql immobilier_abidjan_net < database/schema.sql
mysql immobilier_abidjan_net < database/seed.sql
for f in database/migrations/*.sql; do
  echo "→ $f"
  mysql immobilier_abidjan_net < "$f" || { echo "ÉCHEC sur $f"; break; }
done

rm ~/.my.cnf
```

`--default-character-set=utf8mb4` (ici via `~/.my.cnf`) n'est pas facultatif : un client MariaDB
dont le défaut est `latin1` transforme tous les textes légaux accentués en mojibake, et le rattrapage
après coup est pénible. Le glob `database/migrations/*.sql` trie correctement `0002` → `0014`.

### 2.2 Import par phpMyAdmin

Possible et sans piège de syntaxe : le parseur de phpMyAdmin (`BufferedQuery`) traite correctement
le `DELIMITER` et le `CREATE PROCEDURE` de la migration `0008` — vérifié sur phpMyAdmin **4.9.11 et
5.2.1**, la procédure est extraite entière. La connexion étant en `utf8mb4`, le risque de mojibake
du § 2.1 disparaît. Trois règles :

- **Sélectionner la base dans le panneau de gauche avant tout.** Aucun fichier ne contient de `USE`
  ni de `CREATE DATABASE` : sans sélection, phpMyAdmin répond « No database selected ».
- **Passer par l'onglet « Importer », jamais par la fenêtre SQL.** La fenêtre SQL est un
  `<textarea>` : la spécification HTML impose la normalisation des sauts de ligne en **CRLF** à
  l'envoi du formulaire, si bien que tout littéral SQL multiligne collé là gagne un `\r` par ligne.
  Constaté en production le 23/09/2026 : les sept pages de `pages.content` portaient 20 à 46
  retours chariot, soit exactement leur nombre de lignes. L'affichage n'en souffre pas (espace
  blanc HTML), mais une migration ultérieure qui réécrit un texte par `REPLACE` sur un motif
  multiligne ne le retrouve plus. L'onglet Importer téléverse le fichier tel quel et n'a pas ce
  défaut ; il évite aussi la limite de POST sur `schema.sql` (53 Ko) et `seed.sql` (34 Ko). Laisser
  « Jeu de caractères du fichier » sur `utf-8`.

  Réparation si le mal est fait — `pages.content` est la seule colonne de la base à contenir des
  sauts de ligne :

  ```sql
  UPDATE pages SET content = REPLACE(content, CHAR(13), '') WHERE code IS NOT NULL;
  ```

- **Un fichier = une exécution**, et jamais un fichier découpé en plusieurs « Exécuter » — vrai
  pour l'import comme pour la fenêtre SQL. Chaque soumission ouvre une **nouvelle connexion
  MySQL**, donc remet les variables de session à `NULL`. Or `seed.sql` définit `@ci`, `@site` et
  `@abidjan` en tête et les réutilise 43 fois ensuite, et les migrations `0002`, `0006`, `0007`,
  `0008` et `0011` enchaînent `SET @has_… := (SELECT …)` puis `PREPARE stmt FROM @sql; EXECUTE
  stmt;` pour rester rejouables. Un fichier coupé en deux insère des lignes avec `site_id = NULL`,
  **silencieusement**. Chaque fichier est en revanche autonome (aucun ne dépend d'une variable
  posée par un autre) : les passer un par un est toujours sûr.

La migration `0008` crée puis supprime une procédure (`im_add_column`) : l'utilisateur de la base a
besoin du droit `CREATE ROUTINE`, accordé par défaut par Plesk sur sa propre base. Une erreur
`#1044` ou `#1370` sur ce seul fichier vient de là.

phpMyAdmin ne dispense pas du SSH pour la suite : `bin/create-user.php`, `bin/cache-clear.php`,
`bin/check-deploy.php` et `bin/reset-before-launch.php` sont des scripts PHP. Sans accès SSH, ils
peuvent être lancés en exécution ponctuelle depuis *Plesk → Tâches planifiées* — mais
`create-user.php` affiche le mot de passe provisoire sur la sortie standard, à rediriger vers un
fichier, à lire, puis à supprimer.

### 2.3 Pourquoi les migrations sont obligatoires sur une installation neuve

`schema.sql` porte bien tout le DDL à jour (42 tables ; `analytics_id`, `social_links`,
`property_submissions`… y figurent déjà). Mais `seed.sql` ne crée que **six pages vides et non
publiées** : les textes d'À propos, Comment ça marche, FAQ, mentions légales, CGU, confidentialité
et cookies, le mode de commission, les coordonnées de contact et le tag Google n'existent que dans
les migrations `0004` à `0014`. Sans elles, les pages légales répondent **404** et disparaissent du
pied de page.

Les migrations sont **rejouables** (`CREATE TABLE IF NOT EXISTS`, gardes sur `information_schema`,
`DROP CONSTRAINT` avant `ADD`) : les relancer ne casse rien.

## 3. Code

> **Jamais en `root`.** Tout ce qui suit — `git clone`, `composer`, les scripts `bin/`, la création
> des dossiers — se fait sous **l'utilisateur système de l'abonnement** (Plesk → *Accès hébergement
> Web*). En root, les fichiers et dossiers créés appartiennent à `root:root` : le site les affiche,
> mais PHP-FPM, qui tourne sous l'utilisateur de l'abonnement, ne peut plus écrire dedans. La panne
> se déclare bien plus tard — au premier envoi de photo d'un partenaire, quand l'application essaie
> de créer `public/uploads/{pays}/annonces/{id}/` — et n'a alors plus rien d'évident.

```bash
# Nom de l'utilisateur (propriétaire du dossier du site)
stat -c '%U:%G' /var/www/vhosts/<domaine>/httpdocs

# Basculer sous cet utilisateur depuis root
su -s /bin/bash - <utilisateur>
```

L'invite devient `-bash-4.2$` : c'est l'invite par défaut de bash, l'utilisateur n'ayant pas de
profil personnalisé. `whoami && pwd` confirme où l'on est.

```bash
cd ~/httpdocs
git clone git@github.com:ekassi-wgy/immobilier-abidjan-net.git .
composer install --no-dev --optimize-autoloader   # si composer tourne sur le PHP système :
                                                 # /opt/plesk/php/8.2/bin/php $(command -v composer) install --no-dev
cp .env.example .env    # puis éditer (voir § 4)
mkdir -p storage/logs storage/cache storage/mail storage/private public/uploads
chmod -R u+rwX storage public/uploads
```

Le déploiement Git de Plesk ne crée pas `storage/` : sans le `mkdir`, `check-deploy.php` signale
`storage/logs` et `storage/private` non inscriptibles.

Si des fichiers ont malgré tout été créés en root — `find ~/httpdocs -user root | head` les
révèle — remettre les propriétaires en place :

```bash
chown -R <utilisateur>:psacln /var/www/vhosts/<domaine>/httpdocs
```

`public/assets/css/app.css` est **commité** : il n'y a ni Node ni build à lancer sur le serveur.

## 4. Fichier `.env` de production

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://immobilier.abidjan.net
APP_BASE_PATH=
APP_LOCALE=fr
APP_PREVIEW=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=immobilier_abidjan_net
DB_USERNAME=…
DB_PASSWORD=…

CACHE_SITES_TTL=600

SESSION_SECURE=true
SESSION_LIFETIME=120
AUTH_IDLE_TIMEOUT=120
AUTH_REMEMBER_DAYS=30

MAIL_MAILER=smtp
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=abidjan.net@weblogy.com
SMTP_PASSWORD=…            # mot de passe d'application régénéré, JAMAIS celui du développement
SMTP_ENCRYPTION=tls
MAIL_FROM_ADDRESS=abidjan.net@weblogy.com
MAIL_FROM_NAME="immobilier.abidjan.net"
```

`chmod 600 .env`. Une variable définie au niveau de Plesk prime sur le fichier.

> `nano` n'est pas installé sur le serveur ; `vi` l'est. Pour éviter l'éditeur, `sed -i` fait
> l'affaire — et `read -s` garde le mot de passe SMTP hors de l'historique du shell :
>
> ```bash
> read -s -p "Mot de passe d'application Gmail : " MDP_SMTP; echo
> sed -i "s|^SMTP_PASSWORD=.*|SMTP_PASSWORD=${MDP_SMTP}|" .env
> unset MDP_SMTP
> chmod 600 .env          # sed -i recrée le fichier : les droits sont à reposer
> ```

> `MAIL_FROM_ADDRESS` doit être le compte Gmail authentifié ou un alias validé, sinon les envois
> sont refusés. Le quota d'envoi journalier de Gmail est limité : à surveiller, et à revoir avant
> les alertes email de la v2.

## 5. HTTPS

1. Plesk → **Certificats SSL/TLS** → *Let's Encrypt* sur le sous-domaine (cocher le renouvellement
   automatique).
2. Activer **Rediriger de HTTP vers HTTPS**.
3. L'application pose elle-même l'en-tête HSTS dès que la connexion est chiffrée.

## 6. Contrôle automatique

Depuis la racine du projet, sur le serveur :

```bash
php bin/check-deploy.php --host=immobilier.abidjan.net
```

Le script vérifie la version de PHP et les extensions, la configuration `.env`, les droits
d'écriture, la base (schéma, Super Admin, sites et domaines, pages éditoriales et légales) et
rappelle les CRON à créer. Il ne modifie rien et sort en **code 1** dès qu'un contrôle bloquant
échoue — il peut donc être enchaîné dans un script de déploiement.

Sur les migrations, il distingue deux choses :

- « **Schéma à jour : …** » contrôle que les tables et colonnes introduites par les migrations
  `0002`, `0006`, `0007`, `0008` et `0011` existent. Leur DDL étant répercuté dans `schema.sql`,
  ces contrôles passent toujours sur une installation neuve : ils détectent une base **ancienne**
  restée en arrière du schéma, pas des migrations oubliées.
- « **Migration 0005 (commission) appliquée** » et les contrôles « Page « … » **rédigée** »
  détectent, eux, des migrations réellement non appliquées : `seed.sql` ne pose ni le paramètre
  `commission.base`, ni le contenu des pages, ni la page `faq`. Ces contrôles sont **bloquants** ;
  une page rédigée mais simplement non publiée reste un avertissement (décision éditoriale).

Il n'existe pas de table de suivi des migrations : ce sont ces marqueurs qui en tiennent lieu.

> Lancé sur un poste de développement, il échoue volontairement (`APP_ENV=local`, pas de HTTPS,
> pas de cache) : c'est le comportement attendu, il ne sert qu'en production et en pré-production.

## 7. Tâches planifiées

Plesk → **Tâches planifiées**, une fois par jour, dans cet ordre :

| Quand | Commande | Rôle |
|---|---|---|
| 03:00 | `cd ~/httpdocs && /opt/plesk/php/8.2/bin/php bin/expire-listings.php` | expire les annonces arrivées à échéance et relance les agences avant la date |
| 03:15 | `cd ~/httpdocs && /opt/plesk/php/8.2/bin/php bin/cleanup-uploads.php` | supprime les photos envoyées mais jamais rattachées à une annonce |

Le **chemin complet est obligatoire** : Plesk propose `php`, qui est le PHP système (5.4), sur
lequel les scripts ne démarrent pas. La tâche doit par ailleurs s'exécuter sous l'utilisateur de
l'abonnement, jamais en root (§ 3).

Les deux acceptent `--dry-run` pour un essai sans écriture. Faire un premier passage à blanc.

## 8. Sauvegardes

Plesk → **Sauvegardes** : sauvegarde quotidienne, rétention 7 jours minimum, **base de données et
fichiers**. `public/uploads/` (photos des annonces) et `storage/private/` (pièces justificatives des
partenaires, photos et documents des biens confiés) ne sont dans aucun dépôt Git : sans sauvegarde, ils
sont définitivement perdus.

Vérifier une fois qu'une restauration fonctionne réellement — une sauvegarde jamais testée n'est pas
une sauvegarde.

## 9. Vérifications manuelles après déploiement

Ce que `check-deploy.php` ne peut pas voir depuis la ligne de commande :

```bash
# Compression et cache des ressources statiques (mod_deflate, absent en local)
curl -sI -H "Accept-Encoding: gzip" https://immobilier.abidjan.net/assets/css/app.css \
  | grep -iE "content-encoding|cache-control"
# attendu : Content-Encoding: gzip  ET  Cache-Control: public, max-age=31536000, immutable

# Les fichiers internes ne doivent jamais être servis (403 ou 404, jamais 200)
for u in /.env /composer.json /app/bootstrap.php /config/database.php /.git/config; do
  echo "$u $(curl -s -o /dev/null -w '%{http_code}' https://immobilier.abidjan.net$u)"
done

# Aucun script ne s'exécute dans les fichiers envoyés
curl -s -o /dev/null -w '%{http_code}\n' https://immobilier.abidjan.net/uploads/test.php   # 403

# Référencement
curl -s https://immobilier.abidjan.net/robots.txt          # doit autoriser et pointer le sitemap
curl -s https://immobilier.abidjan.net/sitemap.xml | head  # doit lister les annonces

# Une page publique sans formulaire ne pose pas de cookie de session
curl -sI https://immobilier.abidjan.net/ | grep -i set-cookie   # aucune ligne attendue

# Email
php bin/mail-test.php --to=…
```

Puis, dans un navigateur : connexion au back-office, création d'une annonce de bout en bout
(photos comprises), envoi d'une demande de contact depuis le site public, et rendu sur mobile réel.
La checklist complète par rôle est dans [`docs/tests.md`](tests.md).

## 10. Déploiements suivants

```bash
cd ~/httpdocs
git pull origin main
composer install --no-dev --optimize-autoloader   # seulement si composer.lock a changé
php bin/cache-clear.php                            # obligatoire : sites, pages et redirections sont en cache
php bin/check-deploy.php --host=immobilier.abidjan.net
```

`php bin/cache-clear.php` est également à lancer après toute modification faite **directement en
base** (sites, domaines, paramètres, pages, redirections) sans passer par le back-office.

Si le déploiement comporte une migration, l'appliquer **avant** le `git pull` du code qui en dépend,
ou accepter une courte fenêtre d'erreurs.

## 11. Pré-production

Le domaine `staging.immobilier.abidjan.net` est déjà déclaré dans `site_domains` en `environment =
'staging'`. Un domaine hors production met automatiquement tout le site en `noindex` et fait
répondre à `robots.txt` un `Disallow: /` — la pré-production ne peut donc pas être indexée ni
concurrencer le site réel.

Une pré-production se déploie exactement comme la production, avec sa **propre base** et
`APP_ENV=staging`. Ne jamais la faire pointer sur la base de production.

## 12. Aperçu avant ouverture, puis ouverture

### Aperçu avec des données de démonstration

Pour montrer le site en ligne avant son ouverture (partenaires, commanditaires, recette) :

```bash
php bin/seed-demo.php --yes     # --yes obligatoire quand APP_ENV=production
```

Le script crée 6 partenaires fictifs, 22 annonces avec photos (toutes les transactions, plusieurs
villes), des demandes de contact, deux dossiers de partenariat, 30 jours de statistiques et
3 actualités. Aucun email n'est envoyé, aucun compte n'est créé (adresses en `@demo.invalid`).

Tant que ces données existent, le site est en **mode démonstration** : pastille « Aperçu : annonces
et partenaires fictifs » en bas de chaque page, toutes les pages en `noindex`, `robots.txt` en
`Disallow: /`, **aucune mesure d'audience**, et un bandeau d'alerte sur le tableau de bord du
back-office. `bin/check-deploy.php` le signale aussi.

### Jour de l'ouverture (en SSH)

```bash
cd ~/httpdocs

# 1. Sauvegarde
mysqldump -u <utilisateur> -p <base> > sauvegarde-avant-ouverture.sql
tar czf fichiers-avant-ouverture.tar.gz public/uploads storage/private

# 2. Simulation : affiche ce qui sera supprimé, ne modifie rien
php bin/reset-before-launch.php            # données de démonstration seulement
php bin/reset-before-launch.php --all      # ou remise à zéro complète

# 3. Exécution
php bin/reset-before-launch.php --confirm          # ou : --all --confirm
php bin/check-deploy.php --host=immobilier.abidjan.net
```

| Mode | Supprime | Conserve |
|---|---|---|
| *(défaut)* | exactement ce que `seed-demo.php` a créé (registre `demo.registry`) | tout le reste, y compris les partenaires et annonces réels déjà saisis |
| `--all` | en plus : **toutes** les annonces et leurs photos, contacts, statistiques, partenaires et leurs comptes, dossiers et pièces, comptes particuliers et biens confiés, notifications, journal d'activité, tentatives de connexion ; numérotation remise à zéro (première annonce `IAN-10001`) | pays, sites, domaines, paramètres, référentiels, pages, bannières, actualités réelles, référencement, comptes Super Admin et Admin Pays |

Le mode par défaut refuse de s'exécuter si une annonce ou un compte réel a été rattaché à un
partenaire fictif (plutôt que de le supprimer) : le réaffecter d'abord, ou utiliser `--all`.

Après la purge, le mode démonstration est levé : indexation, `robots.txt` de production et mesure
d'audience (après consentement) reprennent automatiquement.

---

## Rappel des pièges

- **Racine des documents sur `public/`**, jamais sur la racine du dépôt.
- **Jamais en `root`** : sous l'utilisateur système de l'abonnement, sinon PHP-FPM ne peut plus
  écrire dans `public/uploads/` et le premier envoi de photo échoue, longtemps après (§ 3).
- **Le `php` du PATH est le PHP système** (5.4 chez le client) : chemin complet
  `/opt/plesk/php/8.2/bin/php` pour les scripts `bin/` comme pour les CRON (§ 1 et § 7).
- **Import SQL par phpMyAdmin : l'onglet « Importer », jamais la fenêtre SQL** (celle-ci normalise
  les sauts de ligne en CRLF et truffe les textes de `\r`), et **un fichier = une exécution** —
  chaque soumission ouvre une nouvelle connexion et remet les variables de session à `NULL`, si
  bien qu'un fichier coupé en deux insère des lignes avec `site_id = NULL`, sans erreur visible
  (§ 2.2).
- Les migrations `0002` à `0014` sont **obligatoires sur une installation neuve** : sans elles, les
  pages légales restent vides et répondent 404 (§ 2.3).
- `.env`, `vendor/`, `storage/` et `public/uploads/` ne sont pas dans Git : ils vivent sur le
  serveur et doivent être sauvegardés.
- Le cache des sites (`CACHE_SITES_TTL=600`) rend les modifications faites en base invisibles
  pendant 10 minutes : `php bin/cache-clear.php`.
- `MAIL_MAILER=log` n'envoie rien. C'est utile pour tester, mais en production les invitations et
  les notifications ne partiraient pas.
- Une page légale non publiée répond **404** et son lien disparaît du pied de page.
