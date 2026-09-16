-- =============================================================================
-- 0005 — Proposition de commission et textes légaux (lot 2.2)
--
-- Les deux décisions restées en attente depuis le lot 1.6 reçoivent une PROPOSITION,
-- modifiable à tout moment depuis le back-office. Rien n'est figé dans le code.
--
-- 1. COMMISSION — modèle d'apport d'affaires : la plateforme prélève une part des honoraires
--    que l'agence encaisse, et non un pourcentage du prix du bien.
--
--    Pourquoi cette assiette : en Côte d'Ivoire les honoraires d'agence tournent autour de 5 %
--    du prix pour une vente et d'un mois de loyer pour une location. Un taux unique appliqué
--    aux honoraires fonctionne donc pour les deux, alors qu'un pourcentage du prix serait
--    dérisoire en location (quelques milliers de francs) et disproportionné en vente.
--
--    Valeurs proposées : 25 % des honoraires, avec un plancher de 50 000 FCFA.
--    Concrètement : vente de 100 000 000 FCFA (honoraires 5 % = 5 000 000) → 1 250 000 FCFA ;
--    location à 500 000 FCFA/mois (honoraires 1 mois) → 125 000 FCFA.
--    Écran : /cmsadmin/parametres.
--
-- 2. TEXTES LÉGAUX — rédigés au nom de Weblogy, éditeur d'immobilier.abidjan.net comme
--    d'Abidjan.net. Ce sont des textes de proposition : ils décrivent fidèlement ce que le
--    site fait, mais **une relecture par un conseil juridique reste recommandée avant la
--    mise en production**, et quatre mentions obligatoires restent à compléter dans les
--    mentions légales (forme juridique, capital, RCCM, directeur de publication, hébergeur).
-- =============================================================================

-- 1. Commission ---------------------------------------------------------------------------
INSERT INTO settings (site_id, setting_key, value, description) VALUES
  (NULL, 'commission.base',           '"agency_fee"', 'agency_fee | transaction_amount — assiette de la commission'),
  (NULL, 'commission.minimum_amount', '50000',        'Commission minimum par opération')
ON DUPLICATE KEY UPDATE value = VALUES(value), description = VALUES(description);

UPDATE settings SET value = '"percent"' WHERE setting_key = 'commission.mode';
UPDATE settings SET value = '25'        WHERE setting_key = 'commission.rate_percent';

-- 2. Textes légaux ------------------------------------------------------------------------
UPDATE pages SET
  title = 'Mentions légales',
  content = '<h2>Éditeur du site</h2>
<p>Le site <strong>immobilier.abidjan.net</strong> est édité par <strong>Weblogy</strong>, également éditeur d’Abidjan.net.</p>
<ul>
<li><strong>Siège social :</strong> Cocody Ambassade, 10 Rue Washington Booker, Abidjan, Côte d’Ivoire</li>
<li><strong>Téléphone :</strong> +225 05 64 00 00 80</li>
<li><strong>Courriel :</strong> info@weblogy.com</li>
<li><strong>Site institutionnel :</strong> <a href="https://www.weblogy.com/" rel="noopener">www.weblogy.com</a></li>
<li><strong>Forme juridique et capital social :</strong> à compléter avant la mise en ligne</li>
<li><strong>Registre du commerce (RCCM) :</strong> à compléter avant la mise en ligne</li>
<li><strong>Directeur de la publication :</strong> à compléter avant la mise en ligne</li>
</ul>

<h2>Hébergement</h2>
<p>Le site est hébergé sur une infrastructure mutualisée administrée par Weblogy. Les coordonnées complètes de l’hébergeur sont à compléter avant la mise en ligne.</p>

<h2>Objet du site</h2>
<p>immobilier.abidjan.net est une plateforme d’annonces immobilières qui met en relation des visiteurs avec des agences immobilières partenaires. Weblogy n’est ni agence immobilière, ni mandataire, ni partie aux transactions conclues entre un visiteur et une agence.</p>

<h2>Propriété intellectuelle</h2>
<p>La structure du site, sa charte graphique, ses textes et ses développements sont la propriété de Weblogy. Les photographies et descriptifs des annonces restent la propriété des agences qui les publient, lesquelles garantissent détenir les droits nécessaires à leur diffusion.</p>
<p>Toute reproduction, extraction ou réutilisation systématique du contenu du site, notamment par aspiration automatisée, est interdite sans autorisation écrite préalable.</p>

<h2>Responsabilité</h2>
<p>Les annonces sont déposées par les agences partenaires sous leur responsabilité. Weblogy contrôle chaque annonce avant sa mise en ligne, mais ce contrôle ne constitue ni une expertise du bien, ni une vérification de la situation juridique du titre de propriété. Il appartient à chacun de se faire remettre les documents originaux et, le cas échéant, de consulter un notaire avant tout engagement.</p>

<h2>Signalement</h2>
<p>Toute annonce manifestement erronée, frauduleuse ou contraire à la loi peut être signalée à <a href="mailto:info@weblogy.com">info@weblogy.com</a>. Weblogy se réserve le droit de retirer sans préavis toute annonce ou tout compte qui contreviendrait aux présentes.</p>',
  meta_description = 'Éditeur, hébergement, propriété intellectuelle et responsabilité du site immobilier.abidjan.net, édité par Weblogy.',
  is_published = 1
WHERE code = 'legal_notice';

UPDATE pages SET
  title = 'Conditions générales d’utilisation',
  content = '<p>Les présentes conditions régissent l’utilisation du site immobilier.abidjan.net, édité par Weblogy. Toute utilisation du site vaut acceptation de ces conditions.</p>

<h2>1. Ce que fait le site</h2>
<p>immobilier.abidjan.net publie des annonces immobilières déposées par des agences partenaires et met les visiteurs en relation avec elles. Weblogy n’intervient ni dans la négociation, ni dans la conclusion, ni dans le règlement des transactions.</p>

<h2>2. Accès et comptes</h2>
<p>La consultation des annonces est libre et gratuite, sans création de compte. Les seuls comptes existants sont ceux des agences partenaires et de l’équipe de Weblogy ; ils sont créés manuellement, après vérification des documents de l’agence. Chaque titulaire est responsable de la confidentialité de ses identifiants.</p>

<h2>3. Engagements des agences partenaires</h2>
<p>L’agence qui publie une annonce garantit :</p>
<ul>
<li>disposer d’un mandat valide sur le bien proposé ;</li>
<li>l’exactitude du prix, de la superficie, de la localisation et de la situation juridique déclarée ;</li>
<li>détenir les droits sur les photographies publiées ;</li>
<li>retirer ou faire archiver sans délai toute annonce dont le bien n’est plus disponible.</li>
</ul>
<p>Weblogy valide chaque annonce avant sa mise en ligne et peut la refuser, en motivant sa décision. Une annonce publiée puis modifiée reste visible dans sa version validée tant que la modification n’a pas été acceptée.</p>

<h2>4. Durée de vie des annonces</h2>
<p>Une annonce publiée expire automatiquement au terme de la durée fixée par Weblogy, l’agence étant prévenue avant l’échéance. Elle peut être prolongée sans nouvelle validation.</p>

<h2>5. Rémunération de la plateforme</h2>
<p>La consultation du site est gratuite pour les visiteurs. Weblogy est rémunéré par les agences partenaires, sous la forme d’une commission d’apport d’affaires sur les opérations issues d’une mise en relation réalisée par le site. Le taux applicable figure au contrat de partenariat signé avec chaque agence. Aucun paiement n’est encaissé en ligne sur le site.</p>

<h2>6. Demandes de contact</h2>
<p>Les messages adressés depuis le site sont transmis à l’agence concernée, ou à l’équipe de Weblogy pour les demandes générales. Les formulaires ne doivent pas servir à des envois publicitaires ou automatisés ; Weblogy peut restreindre l’accès en cas d’usage abusif.</p>

<h2>7. Disponibilité</h2>
<p>Weblogy s’efforce d’assurer la disponibilité du site sans pouvoir la garantir. L’accès peut être suspendu pour maintenance ou en cas d’incident technique.</p>

<h2>8. Modification des conditions</h2>
<p>Weblogy peut modifier les présentes conditions. La version applicable est celle publiée sur cette page à la date de l’utilisation du site.</p>

<h2>9. Droit applicable</h2>
<p>Les présentes conditions sont soumises au droit ivoirien. À défaut de règlement amiable, tout litige relève des juridictions compétentes d’Abidjan.</p>',
  meta_description = 'Conditions d’utilisation d’immobilier.abidjan.net : rôle de la plateforme, engagements des agences, validation des annonces, rémunération.',
  is_published = 1
WHERE code = 'terms';

UPDATE pages SET
  title = 'Politique de confidentialité',
  content = '<p>Weblogy, éditeur d’immobilier.abidjan.net, traite des données personnelles dans le cadre de l’exploitation du site. Cette page explique lesquelles, pourquoi, et pendant combien de temps.</p>

<h2>Responsable du traitement</h2>
<p>Weblogy — Cocody Ambassade, 10 Rue Washington Booker, Abidjan, Côte d’Ivoire — <a href="mailto:info@weblogy.com">info@weblogy.com</a> — +225 05 64 00 00 80.</p>

<h2>Données collectées</h2>
<ul>
<li><strong>Formulaires de contact :</strong> nom, adresse électronique et/ou téléphone, message, et selon le formulaire l’objet de la demande ou les caractéristiques du bien proposé. Ces champs sont ceux que vous saisissez.</li>
<li><strong>Demandes de partenariat :</strong> nom de l’agence, interlocuteur, coordonnées, registre du commerce, ville d’implantation.</li>
<li><strong>Données techniques :</strong> adresse IP, navigateur et page d’origine, enregistrés avec chaque demande pour prévenir les envois abusifs.</li>
<li><strong>Comptes du back-office :</strong> identité, coordonnées professionnelles, historique de connexion et journal des actions effectuées.</li>
</ul>
<p>Le site ne demande jamais de données bancaires : aucun paiement n’y est encaissé.</p>

<h2>Finalités</h2>
<ul>
<li>transmettre votre demande à l’agence concernée ou à notre équipe, et y répondre ;</li>
<li>instruire une candidature de partenariat ;</li>
<li>mesurer l’audience des annonces de façon agrégée, au profit des agences ;</li>
<li>assurer la sécurité du site et prévenir les usages abusifs.</li>
</ul>

<h2>Base légale</h2>
<p>Les demandes de contact reposent sur votre consentement, recueilli par une case à cocher avant l’envoi. La gestion des comptes et la sécurité du site reposent sur l’intérêt légitime de Weblogy à exploiter la plateforme.</p>

<h2>Destinataires</h2>
<p>Vos coordonnées sont transmises à l’agence responsable du bien concerné, afin qu’elle puisse vous répondre — c’est l’objet même de votre demande. Elles sont également accessibles à l’équipe de Weblogy chargée du suivi. Elles ne sont ni vendues, ni cédées, ni utilisées à des fins publicitaires.</p>

<h2>Durée de conservation</h2>
<ul>
<li>Demandes de contact : trois ans à compter du dernier échange.</li>
<li>Demandes de partenariat : trois ans, ou la durée du partenariat s’il est conclu.</li>
<li>Journal des connexions et des actions du back-office : douze mois.</li>
<li>Annonces et statistiques d’audience : pendant l’exploitation du site.</li>
</ul>

<h2>Vos droits</h2>
<p>Vous pouvez demander l’accès à vos données, leur rectification, leur effacement, ou vous opposer à leur traitement, en écrivant à <a href="mailto:info@weblogy.com">info@weblogy.com</a>. Une réponse vous est apportée dans un délai d’un mois. Conformément à la loi ivoirienne relative à la protection des données à caractère personnel, vous pouvez également saisir l’Autorité de Régulation des Télécommunications de Côte d’Ivoire (ARTCI).</p>

<h2>Sécurité</h2>
<p>Les accès au back-office sont protégés par mot de passe chiffré et limitation des tentatives de connexion. Les échanges avec le site sont chiffrés (HTTPS). Les informations confidentielles associées à une annonce — coordonnées du propriétaire, notaire, référence de dossier — ne sont jamais publiées et restent réservées à l’équipe.</p>',
  meta_description = 'Données collectées par immobilier.abidjan.net, finalités, destinataires, durées de conservation et exercice de vos droits.',
  is_published = 1
WHERE code = 'privacy';

UPDATE pages SET
  title = 'Politique de cookies',
  content = '<p>immobilier.abidjan.net n’utilise que des cookies nécessaires à son fonctionnement. Le site ne dépose <strong>aucun traceur publicitaire</strong> et ne recourt à aucune mesure d’audience tierce.</p>

<h2>Cookies déposés</h2>
<table>
<thead><tr><th>Nom</th><th>Rôle</th><th>Durée</th></tr></thead>
<tbody>
<tr><td><code>ian_session</code></td><td>Maintient la session sur les pages comportant un formulaire, et protège les envois contre la falsification de requête.</td><td>Session</td></tr>
<tr><td><code>ian_fav</code></td><td>Mémorise les annonces que vous mettez en favori, pour les retrouver sans créer de compte.</td><td>1 an</td></tr>
<tr><td><code>ian_seen</code></td><td>Évite de compter plusieurs fois la même consultation d’annonce.</td><td>12 heures</td></tr>
</tbody>
</table>
<p>Vos favoris sont également conservés dans le stockage local de votre navigateur. Aucune de ces informations n’est transmise à un tiers.</p>

<h2>Refuser ou supprimer les cookies</h2>
<p>Ces cookies étant strictement nécessaires, aucun consentement n’est requis et le bandeau affiché à votre première visite est purement informatif. Vous pouvez néanmoins les supprimer à tout moment depuis les réglages de votre navigateur ; vos favoris seront alors perdus et certains formulaires pourront cesser de fonctionner.</p>

<h2>Évolution</h2>
<p>Si une mesure d’audience venait à être ajoutée, cette page serait mise à jour et un véritable recueil de consentement serait mis en place avant tout dépôt de traceur.</p>',
  meta_description = 'Cookies utilisés par immobilier.abidjan.net : uniquement des cookies nécessaires, aucun traceur publicitaire.',
  is_published = 1
WHERE code = 'cookies';
