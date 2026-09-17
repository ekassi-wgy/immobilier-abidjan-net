# Recette fonctionnelle — immobilier.abidjan.net

Checklist de recette par rôle (lot 1.13). À rejouer **avant chaque mise en production** et après
toute modification touchant les droits, le workflow d'annonce ou les formulaires publics.

Les scénarios sont écrits pour être exécutés à la main sur http://localhost:8888 (ou sur la
pré-production). Chacun est vérifié une première fois au lot 1.13 ; la colonne « Attendu » est ce
que le code fait réellement aujourd'hui.

## Préparation

1. `MAIL_MAILER=log` dans `.env` (sinon de vrais emails partent vers les adresses de test).
2. Créer un compte par rôle avec `php bin/create-user.php` (ou en base pour les comptes d'agence),
   plus **deux agences** : les vérifications de cloisonnement en ont besoin.
3. Déposer au moins une annonce par statut : `published`, `pending`, `rejected`.
4. À la fin : supprimer les comptes, agences, annonces, demandes et fichiers créés, vider
   `storage/mail/`, remettre `MAIL_MAILER=smtp`.

> **Ne jamais réinstaller la base locale** : elle contient le compte Super Admin du client.

---

## 1. Visiteur (site public, sans compte)

| # | Scénario | Attendu |
|---|---|---|
| 1.1 | Ouvrir l'accueil | Hero, recherche rapide, sections non vides uniquement ; accord singulier/pluriel du compteur d'annonces |
| 1.2 | Rechercher depuis l'accueil (type + lieu) | Redirection **302** vers l'URL canonique `/acheter/{type}/{ville}/{commune}` |
| 1.3 | Page de résultats : filtres, tri, pagination | Les filtres se cumulent, les puces se retirent une à une, la pagination conserve les filtres |
| 1.4 | Vues grille / liste / carte | La carte affiche des grappes ; une annonce sans coordonnées n'y figure pas |
| 1.5 | Désactiver JavaScript | Panneau de filtres visible au fil du contenu, tri et vues en liens, aucun bouton inopérant |
| 1.6 | Ouvrir une annonce publiée | **200**, galerie, critères, situation juridique, carte |
| 1.7 | Ouvrir une annonce en attente, rejetée, archivée ou expirée | **404** |
| 1.8 | Modifier le slug dans l'URL d'une annonce | **301** vers l'URL canonique |
| 1.9 | Chercher les données confidentielles dans le code source d'une fiche | Ni propriétaire, ni notaire, ni référence de dossier, ni adresse exacte si `show_exact_location = 0` |
| 1.10 | Annonce avec une modification en attente | La **version en ligne** s'affiche ; ni le titre proposé, ni les photos de la révision |
| 1.11 | Mettre une annonce en favori puis ouvrir `/favoris` | L'annonce y figure, sans compte ; une annonce retirée de la vente disparaît d'elle-même |
| 1.12 | Annuaire `/agences`, filtres ville et « vérifiées » | Seules les agences **actives** ; le nombre d'annonces est juste |
| 1.13 | Profil d'agence | Présentation, zones, annonces en ligne ; **jamais** RCCM, NCC ni email de gestion |
| 1.14 | Pages éditoriales publiées / non publiées | Publiée **200** ; non publiée **404** et **absente du pied de page** |
| 1.15 | Bandeau cookies | S'affiche une fois, se referme, ne revient pas |

### Formulaires publics (contact d'annonce, contact d'agence, contact, partenaire, dépôt de bien)

| # | Scénario | Attendu |
|---|---|---|
| 1.16 | Envoi valide | **303** + message de confirmation, ligne créée dans `leads` (ou `partner_requests`), notification au back-office |
| 1.17 | Envoi sans consentement, ou sans email **ni** téléphone | **422**, valeurs ressaisies, message sur le champ |
| 1.18 | POST sans jeton CSRF | **419** |
| 1.19 | Champ piège `site_web` rempli | **303** comme un envoi valide, **rien enregistré** |
| 1.20 | 6 envois d'affilée depuis la même adresse IP | Le 6ᵉ répond **429** |

---

## 2. Compte agence — responsable (`agency_owner`)

| # | Scénario | Attendu |
|---|---|---|
| 2.1 | Tableau de bord | Chiffres de **son** agence uniquement |
| 2.2 | Ses annonces, ouvrir l'une d'elles | **200** |
| 2.3 | Ouvrir l'annonce d'une **autre** agence (URL forgée) | **404** |
| 2.4 | Agir (archiver, supprimer) sur l'annonce d'une autre agence | **404** |
| 2.5 | Déposer une annonce | Passe en **`pending`**, jamais publiée directement |
| 2.6 | Modifier une annonce **publiée** | Crée une **révision** ; la version en ligne reste inchangée |
| 2.7 | Valider ou rejeter une annonce | **403** (réservé à l'équipe) |
| 2.8 | Mettre une annonce en avant | **403** |
| 2.9 | Ses demandes de contact | Seules les siennes ; celle d'une autre agence → **404** |
| 2.10 | Modifier le profil de l'agence | **200**, journalisé, notification à l'équipe |
| 2.11 | `/cmsadmin/agences`, `/demandes-partenariat`, `/exports`, `/geo/villes` | **403** |
| 2.12 | `/cmsadmin/categories`, `/utilisateurs`, `/pays-sites`, `/parametres` | **403** |
| 2.13 | Télécharger un export CSV par son URL directe | **403** |

## 3. Compte agence — agent (`agency_agent`)

| # | Scénario | Attendu |
|---|---|---|
| 3.1 | Tableau de bord et annonces de l'agence | **200** |
| 3.2 | Ouvrir le profil de l'agence | **200**, en lecture seule |
| 3.3 | **Enregistrer** le profil de l'agence | **403** |
| 3.4 | `/cmsadmin/journal` | **403** — le journal contient les décisions de modération et les actions des autres agences |

---

## 4. Admin Pays (`country_admin`)

| # | Scénario | Attendu |
|---|---|---|
| 4.1 | Tableau de bord | Chiffres de **tout le pays** du site |
| 4.2 | Annonces, agences, demandes de partenariat, contacts, référentiel géographique | **200** |
| 4.3 | Valider une annonce en attente | **303**, l'annonce passe `published` et apparaît sur le site public |
| 4.4 | Rejeter **sans** motif | Refusé, message d'erreur, statut inchangé |
| 4.5 | Rejeter **avec** motif | L'annonce passe `rejected` et disparaît du site public |
| 4.6 | Rejeter une annonce déjà publiée | Refusé (transition hors workflow), statut inchangé ; la bonne action est « dépublier » |
| 4.7 | Exports CSV | **200** |
| 4.8 | `/cmsadmin/categories`, `/utilisateurs`, `/pays-sites`, `/parametres` | **403** |
| 4.9 | Se connecter sur le site d'**un autre pays** | Accès refusé |
| 4.10 | `/cmsadmin/journal` | **200**, mais **uniquement les actions de son pays** — aucune action d'un autre pays, aucune action système, pas de colonne « Pays » |

## 5. Super Admin

| # | Scénario | Attendu |
|---|---|---|
| 5.1 | Tous les écrans du menu | **200** |
| 5.2 | Enregistrer les paramètres | **302**, valeurs en base, journal alimenté, cache des sites vidé |
| 5.3 | Mode de commission « pourcentage » sans taux | **422** |
| 5.4 | Valeur hors bornes (ex. 999 tentatives de connexion) | **422** |
| 5.5 | Export CSV annonces et contacts | Fichier ouvert directement par Excel, accents corrects, énumérations en clair |
| 5.6 | Dans un export, une valeur commençant par `=`, `+`, `-` ou `@` | Préfixée d'une apostrophe (aucune formule exécutée) |
| 5.7 | `/cmsadmin/journal` | **200**, actions de tous les pays, colonne « Pays » affichée, actions système visibles |
| 5.8 | Modifier une balise SEO puis rouvrir le journal | L'action apparaît ; « n champs modifiés » déplie les valeurs avant/après |
| 5.9 | Filtres du journal (module, action, auteur, du/au, recherche) | Chaque filtre restreint la liste, « Réinitialiser » la rétablit |

---

## 6. Connexion et sécurité (tous rôles)

| # | Scénario | Attendu |
|---|---|---|
| 6.1 | 6 tentatives avec un mauvais mot de passe | Blocage : « Trop de tentatives. Réessayez dans 15 minutes… » |
| 6.2 | Compte créé par invitation | Lien valable 72 h, changement de mot de passe imposé |
| 6.3 | Changement de mot de passe | Les autres sessions et jetons « Rester connecté » sont révoqués |
| 6.4 | Compte désactivé ou agence suspendue | Accès perdu **à la requête suivante** |
| 6.5 | `/cmsadmin` en navigation privée | Redirection vers la connexion |

## 7. Mobile et navigateurs

| # | Scénario | Attendu |
|---|---|---|
| 7.1 | Accueil, résultats, fiche, annuaire, contact à **375 px** | Aucun débordement horizontal, cibles tactiles ≥ 44 px |
| 7.2 | Page de résultats à 375 px | Le panneau de filtres s'ouvre en tiroir et se referme (Échap, clic à côté) |
| 7.3 | Galerie d'une fiche à 375 px | La visionneuse s'ouvre, se parcourt au clavier et se referme |
| 7.4 | Safari iOS, Chrome Android, Firefox, Edge | Rendu et formulaires identiques |
| 7.5 | Connexion 3G simulée | LCP < 2,5 s sur l'accueil |

---

## Résultat de la première exécution (lot 1.13)

**72 vérifications automatisées sur les sections 1 à 6 : toutes au vert** après correction de trois
défauts trouvés pendant la recette :

- **Division par zéro** sur les tableaux de bord dès qu'un classement ne contenait que des zéros
  (page en erreur 500).
- **Erreur 500 sur une fiche annonce** dont la révision en attente portait une charge utile
  incomplète : `PropertyPresenter::revisionDiff()` supposait toutes les clés présentes.
- **Accord du pluriel** du compteur d'annonces du hero (« 1 annonces vérifiées »).

Deux comportements d'abord pris pour des anomalies se sont révélés corrects et sont désormais
consignés comme tels : un rejet sans motif est refusé par un message d'erreur (et non un 422), et
une annonce déjà publiée ne peut pas être « rejetée » — il faut la dépublier.

Les sections **7.4 et 7.5** (navigateurs réels, 3G) restent à faire à la main : elles ne sont pas
automatisables ici.
