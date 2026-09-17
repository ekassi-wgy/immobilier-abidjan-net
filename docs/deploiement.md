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
4. Vérifier que la **ligne de commande** utilise elle aussi PHP 8.2+ (`php -v` en SSH) : les CRON en
   dépendent. Si l'hébergeur garde un PHP ancien par défaut, utiliser le chemin complet
   (`/opt/plesk/php/8.2/bin/php`) dans les tâches planifiées.

## 2. Base de données

1. Plesk → **Bases de données** → créer `immobilier_abidjan_net` en `utf8mb4` /
   `utf8mb4_unicode_ci`, avec un utilisateur dédié (**jamais** l'utilisateur d'administration).
2. Importer, dans cet ordre :

```bash
mysql -u <user> -p <base> < database/schema.sql
mysql -u <user> -p <base> < database/seed.sql
for f in database/migrations/*.sql; do mysql -u <user> -p <base> < "$f"; done
```

3. Adapter la table `site_domains` : `immobilier.abidjan.net` doit y figurer en `environment =
   'production'` et `is_primary = 1`. **Tant qu'un hôte n'est pas déclaré, le site répond 404**, et
   un hôte déclaré hors production met tout le site en `noindex`.
4. Créer le compte du client :

```bash
php bin/create-user.php --role=super_admin --email=… --first-name=… --last-name=…
```

Le mot de passe provisoire n'est affiché qu'une fois et devra être changé à la première connexion.

## 3. Code

```bash
cd ~/httpdocs
git clone git@github.com:ekassi-wgy/immobilier-abidjan-net.git .
composer install --no-dev --optimize-autoloader
cp .env.example .env    # puis éditer (voir § 4)
mkdir -p storage/logs storage/cache storage/mail storage/private public/uploads
chmod -R u+rwX storage public/uploads
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

SESSION_SECURE=auto
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
d'écriture, la base (schéma, migrations, Super Admin, sites et domaines, pages légales publiées) et
rappelle les CRON à créer. Il ne modifie rien et sort en **code 1** dès qu'un contrôle bloquant
échoue — il peut donc être enchaîné dans un script de déploiement.

> Lancé sur un poste de développement, il échoue volontairement (`APP_ENV=local`, pas de HTTPS,
> pas de cache) : c'est le comportement attendu, il ne sert qu'en production et en pré-production.

## 7. Tâches planifiées

Plesk → **Tâches planifiées**, une fois par jour, dans cet ordre :

| Quand | Commande | Rôle |
|---|---|---|
| 03:00 | `cd ~/httpdocs && php bin/expire-listings.php` | expire les annonces arrivées à échéance et relance les agences avant la date |
| 03:15 | `cd ~/httpdocs && php bin/cleanup-uploads.php` | supprime les photos envoyées mais jamais rattachées à une annonce |

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

---

## Rappel des pièges

- **Racine des documents sur `public/`**, jamais sur la racine du dépôt.
- `.env`, `vendor/`, `storage/` et `public/uploads/` ne sont pas dans Git : ils vivent sur le
  serveur et doivent être sauvegardés.
- Le cache des sites (`CACHE_SITES_TTL=600`) rend les modifications faites en base invisibles
  pendant 10 minutes : `php bin/cache-clear.php`.
- `MAIL_MAILER=log` n'envoie rien. C'est utile pour tester, mais en production les invitations et
  les notifications ne partiraient pas.
- Une page légale non publiée répond **404** et son lien disparaît du pied de page.
