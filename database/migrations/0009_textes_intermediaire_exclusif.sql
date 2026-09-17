-- =============================================================================
-- 0009 — Textes publics : Weblogy intermédiaire exclusif
--
-- Réécriture des pages éditoriales et légales pour le modèle Partenaire → Weblogy → Prospect et
-- Particulier → Weblogy → annonce (migration 0008) : les anciens textes décrivaient une mise en
-- relation directe avec les agences (« nous ne sommes ni agence ni mandataire », « votre message
-- ne transite par aucun intermédiaire »), désormais inexacts.
--
-- Création de la page FAQ (code `faq`, publiée, liée dans le pied de page).
--
-- Ces textes sont des PROPOSITIONS à faire relire par un juriste, notamment :
--  - l'activité d'intermédiation (mandats reçus de particuliers) peut relever de la
--    réglementation de la profession d'agent immobilier : la mention correspondante des
--    mentions légales est « à compléter » ;
--  - les conditions de rémunération des propriétaires particuliers restent à définir.
--
-- Les pages existantes sont remplacées : aucune n'a encore été modifiée en production (site non
-- déployé). Rejouer la migration réécrit les textes.
-- =============================================================================

UPDATE pages SET
  title = 'À propos',
  content = '<p><strong>immobilier.abidjan.net</strong> est la plateforme immobilière de <strong>Weblogy</strong>, éditeur d’Abidjan.net. Nous présentons des biens à vendre et à louer en Côte d’Ivoire, confiés par des professionnels de l’immobilier et par des propriétaires particuliers, et nous sommes <strong>l’interlocuteur unique</strong> de toute personne qui s’y intéresse.</p>

<h2>Un seul interlocuteur</h2>
<p>Qu’un bien provienne d’une agence partenaire, d’un promoteur, d’un gestionnaire ou d’un particulier, c’est notre équipe qui vous répond, qualifie votre demande et organise la visite. Vous n’avez pas à multiplier les appels : une seule équipe suit votre projet, de la première question jusqu’à la mise en relation utile.</p>

<h2>Ce que nous faisons</h2>
<ul>
<li>Nous sélectionnons nos partenaires professionnels et vérifions leurs documents d’entreprise.</li>
<li>Nous étudions chaque bien confié par un particulier avant d’en rédiger l’annonce.</li>
<li>Nous contrôlons les annonces : photos, localisation, cohérence du prix, documents déclarés.</li>
<li>Nous recevons toutes les demandes des prospects, les qualifions, puis sollicitons le partenaire ou le propriétaire concerné.</li>
</ul>

<h2>Ce que nous ne faisons pas</h2>
<ul>
<li>Nous n’encaissons aucun paiement en ligne entre un prospect et un vendeur ou un bailleur.</li>
<li>Notre vérification ne remplace ni une expertise du bien, ni l’examen du titre de propriété par un notaire : demandez toujours les documents originaux avant de vous engager.</li>
</ul>

<h2>Couverture</h2>
<p>La plateforme couvre aujourd’hui la Côte d’Ivoire. Elle est conçue pour accueillir d’autres pays d’Afrique de l’Ouest et Centrale, avec leur propre référentiel géographique et leur devise.</p>',
  meta_description = 'immobilier.abidjan.net, la plateforme immobilière de Weblogy : un seul interlocuteur pour les biens de nos partenaires et des propriétaires qui nous les confient.',
  is_published = 1
WHERE code = 'about';

UPDATE pages SET
  title = 'Comment ça marche',
  content = '<h2>Vous cherchez un bien</h2>
<ol>
<li><strong>Cherchez.</strong> Filtrez par transaction, type de bien, commune, budget, surface et équipements. La vue carte situe les biens à l’échelle du quartier.</li>
<li><strong>Comparez.</strong> Mettez des annonces de côté avec le cœur : elles restent dans votre navigateur, sans créer de compte.</li>
<li><strong>Contactez-nous.</strong> Depuis la fiche, écrivez à notre équipe, appelez-nous ou échangez sur WhatsApp en citant la référence de l’annonce.</li>
<li><strong>Visitez.</strong> Nous qualifions votre demande et organisons la visite avec le professionnel ou le propriétaire du bien.</li>
</ol>

<h2>Vous êtes propriétaire</h2>
<ol>
<li><strong>Créez votre compte</strong> depuis la page « Confiez-nous votre bien » et confirmez votre adresse email.</li>
<li><strong>Décrivez votre bien</strong> : type, localisation, caractéristiques, prix ou conditions, photos et documents.</li>
<li><strong>Un conseiller étudie votre dossier</strong>, vous recontacte si nécessaire, puis rédige et publie l’annonce.</li>
<li><strong>Nous traitons les demandes</strong> des personnes intéressées et vous sollicitons pour les visites. Vous suivez l’avancement depuis votre espace.</li>
</ol>
<p>Vous ne publiez jamais vous-même : aucune annonce n’est mise en ligne sans le travail de notre équipe.</p>

<h2>Vous êtes un professionnel de l’immobilier</h2>
<ol>
<li><strong>Déposez votre dossier</strong> depuis la page « Devenir partenaire » : identité de votre structure, informations légales, pièces justificatives.</li>
<li><strong>Nous vérifions vos documents</strong> et vous recontactons.</li>
<li><strong>Votre console partenaire est ouverte</strong> : vous créez, enrichissez, désactivez et suivez vos biens vous-même.</li>
<li><strong>Nous présentons vos biens et centralisons les demandes</strong> : vos coordonnées n’apparaissent pas sur le site, nous vous sollicitons pour chaque prospect qualifié.</li>
</ol>

<h2>La vérification des annonces</h2>
<p>Une annonce ou une modification proposée par un partenaire est vérifiée par notre équipe avant d’être visible, sauf décision contraire de Weblogy pour un partenaire donné. Dans tous les cas, Weblogy peut à tout moment corriger, désactiver ou retirer une annonce.</p>',
  meta_description = 'Chercher un bien, confier son bien à Weblogy, devenir partenaire professionnel : le fonctionnement pas à pas.',
  is_published = 1
WHERE code = 'how_it_works';

UPDATE pages SET
  title = 'Conditions générales d’utilisation',
  content = '<p>Les présentes conditions régissent l’utilisation du site immobilier.abidjan.net, édité par Weblogy. Toute utilisation du site vaut acceptation de ces conditions.</p>

<h2>1. Rôle de Weblogy</h2>
<p>immobilier.abidjan.net présente des biens immobiliers à vendre ou à louer, confiés à Weblogy par des professionnels de l’immobilier partenaires et par des propriétaires particuliers. Weblogy est l’<strong>interlocuteur exclusif</strong> des personnes intéressées par ces biens : il reçoit leurs demandes, les qualifie et sollicite le partenaire ou le propriétaire concerné. Aucune annonce ne présente les coordonnées d’un partenaire ou d’un propriétaire comme contact commercial.</p>

<h2>2. Accès au site et comptes</h2>
<p>La consultation des annonces est libre et gratuite, sans création de compte. Trois types de comptes existent :</p>
<ul>
<li>les comptes de l’équipe de Weblogy ;</li>
<li>les comptes des partenaires professionnels, ouverts par Weblogy après étude et validation d’un dossier ;</li>
<li>les comptes propriétaires, créés par les particuliers qui souhaitent confier un bien à Weblogy, après confirmation de leur adresse email.</li>
</ul>
<p>Chaque titulaire est responsable de la confidentialité de ses identifiants. Weblogy peut suspendre ou fermer un compte en cas d’usage contraire aux présentes.</p>

<h2>3. Partenaires professionnels</h2>
<p>Peuvent devenir partenaires les agences immobilières, promoteurs, gestionnaires de biens et autres professionnels habilités. Le partenaire gère ses biens depuis sa console et garantit :</p>
<ul>
<li>disposer d’un mandat ou d’un droit valide sur chaque bien proposé ;</li>
<li>l’exactitude du prix, de la superficie, de la localisation et de la situation juridique déclarée ;</li>
<li>détenir les droits sur les photographies transmises ;</li>
<li>désactiver ou faire archiver sans délai tout bien qui n’est plus disponible.</li>
</ul>
<p>Les annonces et modifications d’un partenaire sont vérifiées par Weblogy avant publication, sauf décision contraire de Weblogy. Weblogy conserve en toutes circonstances le contrôle des annonces : il peut les corriger, les désactiver ou les retirer. Le partenaire s’interdit de faire figurer ses coordonnées dans les annonces et traite les prospects qui lui sont adressés par Weblogy dans le cadre du partenariat.</p>

<h2>4. Propriétaires particuliers</h2>
<p>Le particulier qui confie un bien certifie en être propriétaire ou être mandaté pour le proposer. Il ne publie pas lui-même d’annonce : Weblogy étudie le dossier, peut l’accepter ou le refuser, rédige et publie l’annonce. Les conditions de la commercialisation (durée, exclusivité éventuelle, rémunération) sont précisées au particulier par Weblogy avant la mise en ligne. Le particulier peut retirer son bien depuis son espace tant que l’annonce n’est pas en préparation.</p>

<h2>5. Demandes des prospects</h2>
<p>Les demandes envoyées depuis le site sont adressées à Weblogy. Pour organiser une visite ou traiter une demande, Weblogy peut transmettre au partenaire ou au propriétaire concerné les informations strictement nécessaires. Les formulaires ne doivent pas servir à des envois publicitaires ou automatisés ; Weblogy peut restreindre l’accès en cas d’usage abusif.</p>

<h2>6. Durée de vie des annonces</h2>
<p>Une annonce publiée expire au terme de la durée fixée par Weblogy ; le partenaire est prévenu avant l’échéance et peut la prolonger si le bien est toujours disponible.</p>

<h2>7. Rémunération</h2>
<p>La consultation du site est gratuite pour les visiteurs. Weblogy est rémunéré par ses partenaires professionnels selon le contrat de partenariat, et par les propriétaires particuliers selon les conditions qui leur sont communiquées. Aucun paiement n’est encaissé en ligne sur le site.</p>

<h2>8. Responsabilité</h2>
<p>Les informations des annonces sont déclarées par les partenaires et les propriétaires. La vérification opérée par Weblogy ne constitue ni une expertise du bien, ni un examen du titre de propriété : il appartient à chacun de se faire remettre les documents originaux et, le cas échéant, de consulter un notaire avant tout engagement.</p>

<h2>9. Disponibilité</h2>
<p>Weblogy s’efforce d’assurer la disponibilité du site sans pouvoir la garantir. L’accès peut être suspendu pour maintenance ou en cas d’incident technique.</p>

<h2>10. Modification des conditions</h2>
<p>Weblogy peut modifier les présentes conditions. La version applicable est celle publiée sur cette page à la date de l’utilisation du site.</p>

<h2>11. Droit applicable</h2>
<p>Les présentes conditions sont soumises au droit ivoirien. À défaut de règlement amiable, tout litige relève des juridictions compétentes d’Abidjan.</p>',
  meta_description = 'Conditions d’utilisation d’immobilier.abidjan.net : rôle d’intermédiaire exclusif de Weblogy, partenaires, propriétaires, demandes, rémunération.',
  is_published = 1
WHERE code = 'terms';

UPDATE pages SET
  title = 'Politique de confidentialité',
  content = '<p>Weblogy, éditeur d’immobilier.abidjan.net, traite des données personnelles dans le cadre de l’exploitation du site. Cette page explique lesquelles, pourquoi, et pendant combien de temps.</p>

<h2>Responsable du traitement</h2>
<p>Weblogy — Rue Washington Booker, Cocody-Ambassades, 01 BP 12324 01 Abidjan, Côte d’Ivoire — <a href="mailto:info@weblogy.com">info@weblogy.com</a> — +225 05 64 00 00 80.</p>

<h2>Données collectées</h2>
<ul>
<li><strong>Demandes des prospects :</strong> nom, adresse électronique et/ou téléphone, message, objet de la demande et annonce concernée.</li>
<li><strong>Comptes propriétaires :</strong> identité, adresse électronique, téléphone, mot de passe (conservé sous forme chiffrée irréversible), date de confirmation de l’adresse.</li>
<li><strong>Biens confiés :</strong> description et caractéristiques du bien, localisation (dont l’adresse, jamais publiée), prix ou conditions, photos et documents transmis.</li>
<li><strong>Dossiers de partenariat :</strong> identité légale de la structure, coordonnées, informations du responsable et pièces justificatives (registre du commerce, pièce d’identité, déclaration fiscale, carte professionnelle).</li>
<li><strong>Données techniques :</strong> adresse IP, navigateur et page d’origine, enregistrés avec chaque envoi pour prévenir les abus.</li>
<li><strong>Comptes du back-office :</strong> identité, coordonnées professionnelles, historique de connexion et journal des actions effectuées.</li>
</ul>
<p>Le site ne demande jamais de données bancaires : aucun paiement n’y est encaissé.</p>

<h2>Finalités</h2>
<ul>
<li>répondre aux demandes des prospects et organiser les visites ;</li>
<li>gérer les comptes propriétaires, étudier les biens confiés et les commercialiser ;</li>
<li>instruire les dossiers de partenariat et gérer les partenaires ;</li>
<li>mesurer l’audience des annonces de façon agrégée ;</li>
<li>assurer la sécurité du site et prévenir les usages abusifs.</li>
</ul>

<h2>Base légale</h2>
<p>Les demandes de contact et les dossiers envoyés reposent sur votre consentement, recueilli avant l’envoi. La gestion d’un compte propriétaire ou partenaire repose sur l’exécution de la relation que vous engagez avec Weblogy. La sécurité du site repose sur l’intérêt légitime de Weblogy à exploiter la plateforme.</p>

<h2>Destinataires</h2>
<p>Vos données sont traitées par l’équipe de Weblogy. Weblogy étant l’intermédiaire exclusif des biens présentés, les coordonnées d’un prospect ne sont communiquées au partenaire ou au propriétaire concerné que dans la mesure nécessaire à l’organisation d’une visite ou au traitement de sa demande. Les coordonnées et documents des propriétaires et des partenaires ne sont jamais publiés sur le site. Aucune donnée n’est vendue, cédée ou utilisée à des fins publicitaires.</p>

<h2>Durée de conservation</h2>
<ul>
<li>Demandes des prospects : trois ans à compter du dernier échange.</li>
<li>Comptes propriétaires et biens confiés : pendant la relation, puis trois ans après le dernier bien commercialisé ou la dernière connexion.</li>
<li>Dossiers de partenariat et pièces justificatives : pendant le partenariat puis trois ans ; un an pour un dossier non retenu.</li>
<li>Journal des connexions et des actions du back-office : douze mois.</li>
<li>Annonces et statistiques d’audience : pendant l’exploitation du site.</li>
</ul>

<h2>Vos droits</h2>
<p>Vous pouvez demander l’accès à vos données, leur rectification, leur effacement, ou vous opposer à leur traitement, en écrivant à <a href="mailto:info@weblogy.com">info@weblogy.com</a>. Une réponse vous est apportée dans un délai d’un mois. Conformément à la loi ivoirienne relative à la protection des données à caractère personnel, vous pouvez également saisir l’Autorité de Régulation des Télécommunications de Côte d’Ivoire (ARTCI).</p>

<h2>Sécurité</h2>
<p>Les comptes sont protégés par des mots de passe chiffrés et une limitation des tentatives de connexion. Les échanges avec le site sont chiffrés (HTTPS). Les photos et documents transmis avec un bien confié ou un dossier de partenariat sont conservés hors de l’espace public du site et ne sont accessibles qu’à l’équipe habilitée. Les informations confidentielles associées à une annonce — coordonnées du propriétaire, notaire, référence de dossier — ne sont jamais publiées.</p>',
  meta_description = 'Données collectées par immobilier.abidjan.net (prospects, propriétaires, partenaires), finalités, destinataires, conservation et droits.',
  is_published = 1
WHERE code = 'privacy';

UPDATE pages SET
  title = 'Politique de cookies',
  content = '<p>immobilier.abidjan.net n’utilise que des cookies nécessaires à son fonctionnement. Le site ne dépose <strong>aucun traceur publicitaire</strong> et ne recourt à aucune mesure d’audience tierce.</p>

<h2>Cookies déposés</h2>
<table>
<thead><tr><th>Nom</th><th>Rôle</th><th>Durée</th></tr></thead>
<tbody>
<tr><td><code>ian_session</code></td><td>Maintient la session sur les pages comportant un formulaire et dans l’espace propriétaire, et protège les envois contre la falsification de requête.</td><td>Session</td></tr>
<tr><td><code>ian_fav</code></td><td>Mémorise les annonces que vous mettez en favori, pour les retrouver sans créer de compte.</td><td>1 an</td></tr>
<tr><td><code>ian_seen</code></td><td>Évite de compter plusieurs fois la même consultation d’annonce.</td><td>12 heures</td></tr>
</tbody>
</table>
<p>Vos favoris sont également conservés dans le stockage local de votre navigateur. Aucune de ces informations n’est transmise à un tiers.</p>

<h2>Refuser ou supprimer les cookies</h2>
<p>Ces cookies étant strictement nécessaires, aucun consentement n’est requis et le bandeau affiché à votre première visite est purement informatif. Vous pouvez néanmoins les supprimer à tout moment depuis les réglages de votre navigateur ; vos favoris seront alors perdus, vous serez déconnecté de votre espace propriétaire et certains formulaires pourront cesser de fonctionner.</p>

<h2>Évolution</h2>
<p>Si une mesure d’audience venait à être ajoutée, cette page serait mise à jour et un véritable recueil de consentement serait mis en place avant tout dépôt de traceur.</p>',
  meta_description = 'Cookies utilisés par immobilier.abidjan.net : uniquement des cookies nécessaires, aucun traceur publicitaire.',
  is_published = 1
WHERE code = 'cookies';

UPDATE pages SET
  title = 'Mentions légales',
  content = '<h2>Éditeur du site</h2>
<p>Le site <strong>immobilier.abidjan.net</strong> est édité par <strong>Weblogy</strong>, également éditeur d’Abidjan.net.</p>
<ul>
<li><strong>Siège social :</strong> Rue Washington Booker, Cocody-Ambassades, 01 BP 12324 01 Abidjan, Côte d’Ivoire</li>
<li><strong>Téléphone :</strong> +225 05 64 00 00 80</li>
<li><strong>Courriel :</strong> info@weblogy.com</li>
<li><strong>Site institutionnel :</strong> <a href="https://www.weblogy.com/" rel="noopener">www.weblogy.com</a></li>
<li><strong>Forme juridique et capital social :</strong> à compléter avant la mise en ligne</li>
<li><strong>Registre du commerce (RCCM) :</strong> à compléter avant la mise en ligne</li>
<li><strong>Directeur de la publication :</strong> à compléter avant la mise en ligne</li>
<li><strong>Activité d’intermédiation immobilière (carte ou autorisation professionnelle) :</strong> à compléter avant la mise en ligne</li>
</ul>

<h2>Hébergement</h2>
<p>Le site est hébergé sur une infrastructure mutualisée administrée par Weblogy. Les coordonnées complètes de l’hébergeur sont à compléter avant la mise en ligne.</p>

<h2>Objet du site</h2>
<p>immobilier.abidjan.net présente des biens immobiliers à vendre ou à louer, confiés à Weblogy par des professionnels de l’immobilier partenaires et par des propriétaires particuliers. Weblogy agit en intermédiaire exclusif : il est l’interlocuteur des personnes intéressées, reçoit et qualifie leurs demandes, puis sollicite le partenaire ou le propriétaire concerné. Aucun paiement n’est encaissé en ligne sur le site.</p>

<h2>Propriété intellectuelle</h2>
<p>La structure du site, sa charte graphique, ses textes et ses développements sont la propriété de Weblogy. Les photographies et documents transmis par les partenaires et les propriétaires restent la propriété de leurs auteurs ; ceux qui les transmettent garantissent détenir les droits nécessaires et autorisent Weblogy à les diffuser pour la présentation des biens.</p>
<p>Toute reproduction, extraction ou réutilisation systématique du contenu du site, notamment par aspiration automatisée, est interdite sans autorisation écrite préalable.</p>

<h2>Responsabilité</h2>
<p>Les informations des annonces sont déclarées par les partenaires et les propriétaires sous leur responsabilité. Weblogy contrôle les annonces, mais ce contrôle ne constitue ni une expertise du bien, ni une vérification de la situation juridique du titre de propriété. Il appartient à chacun de se faire remettre les documents originaux et, le cas échéant, de consulter un notaire avant tout engagement.</p>

<h2>Signalement</h2>
<p>Toute annonce manifestement erronée, frauduleuse ou contraire à la loi peut être signalée à <a href="mailto:info@weblogy.com">info@weblogy.com</a>. Weblogy se réserve le droit de retirer sans préavis toute annonce ou tout compte qui contreviendrait aux présentes.</p>',
  meta_description = 'Éditeur, activité d’intermédiation, hébergement, propriété intellectuelle et responsabilité du site immobilier.abidjan.net, édité par Weblogy.',
  is_published = 1
WHERE code = 'legal_notice';

INSERT INTO pages (site_id, code, slug, locale, title, content, meta_description, is_published)
SELECT s.id, 'faq', 'faq', 'fr', 'Questions fréquentes', '<h2>Rechercher un bien</h2>
<h3>Qui contacter au sujet d’une annonce ?</h3>
<p>Toujours notre équipe : par le formulaire de la fiche, par téléphone ou par WhatsApp. Citez la référence de l’annonce (par exemple IAN-10245) : nous retrouvons le bien immédiatement et organisons la visite avec son propriétaire ou le professionnel qui le gère.</p>
<h3>Pourquoi les coordonnées de l’agence ne sont-elles pas affichées ?</h3>
<p>Parce que nous sommes l’intermédiaire de toutes les annonces présentées. Un seul interlocuteur vous évite de multiplier les appels, et nous permet de vérifier chaque demande avant de solliciter le partenaire concerné.</p>
<h3>Faut-il créer un compte pour chercher un bien ?</h3>
<p>Non. La recherche, les fiches et les favoris sont accessibles sans compte. Les favoris sont mémorisés par votre navigateur.</p>
<h3>Les annonces sont-elles vérifiées ?</h3>
<p>Oui : photos, localisation, cohérence du prix et documents déclarés sont contrôlés par notre équipe. Cette vérification ne remplace ni une expertise, ni l’examen du titre de propriété par un notaire.</p>

<h2>Confier un bien (particuliers)</h2>
<h3>Puis-je publier moi-même mon annonce ?</h3>
<p>Non. Vous nous confiez votre bien depuis votre espace propriétaire ; un conseiller étudie le dossier, rédige l’annonce et la met en ligne. Nous nous occupons ensuite des demandes des personnes intéressées.</p>
<h3>Pourquoi dois-je créer un compte ?</h3>
<p>Pour que nous puissions vous identifier, vous recontacter et que vous suiviez l’avancement de votre dossier. Votre adresse email doit être confirmée avant l’envoi d’un bien.</p>
<h3>Quels documents fournir ?</h3>
<p>Des photos récentes du bien et, si vous les avez, le titre de propriété ou tout document utile (plan, attestation). Ils restent privés : seule notre équipe les consulte.</p>
<h3>Mon adresse exacte sera-t-elle publiée ?</h3>
<p>Non. Elle sert à notre équipe pour organiser les visites. Le site n’affiche que la commune ou le quartier.</p>
<h3>Puis-je retirer mon bien ?</h3>
<p>Oui, depuis votre espace, tant que l’annonce n’est pas en préparation. Ensuite, contactez notre équipe.</p>

<h2>Devenir partenaire (professionnels)</h2>
<h3>Qui peut devenir partenaire ?</h3>
<p>Les agences immobilières, promoteurs, gestionnaires de biens et autres professionnels habilités à proposer des biens immobiliers.</p>
<h3>Quelles pièces sont demandées ?</h3>
<p>L’extrait du registre du commerce et la pièce d’identité du responsable sont obligatoires ; la déclaration fiscale d’existence et la carte ou l’agrément professionnel sont recommandés.</p>
<h3>Comment les prospects me contactent-ils ?</h3>
<p>Ils s’adressent à Weblogy. Nous qualifions chaque demande, puis nous vous sollicitons pour la visite ou le traitement du dossier. Votre console affiche le nombre de demandes reçues sur vos biens.</p>
<h3>Mes annonces sont-elles publiées immédiatement ?</h3>
<p>Par défaut, chaque annonce et chaque modification sont vérifiées par notre équipe avant d’être visibles. Weblogy conserve dans tous les cas la possibilité de corriger, désactiver ou retirer une annonce.</p>

<h2>Compte et données</h2>
<h3>J’ai oublié mon mot de passe.</h3>
<p>Utilisez le lien « Mot de passe oublié » de la page de connexion : un lien valable une heure vous est envoyé par email.</p>
<h3>Comment exercer mes droits sur mes données ?</h3>
<p>Écrivez à <a href="mailto:info@weblogy.com">info@weblogy.com</a>. Le détail figure dans notre politique de confidentialité.</p>',
       'Questions fréquentes : contacter Weblogy au sujet d’un bien, confier son bien, devenir partenaire, compte et données personnelles.', 1
FROM sites s
WHERE NOT EXISTS (SELECT 1 FROM pages p WHERE p.site_id = s.id AND p.code = 'faq');

UPDATE pages SET
  title = 'Questions fréquentes',
  content = '<h2>Rechercher un bien</h2>
<h3>Qui contacter au sujet d’une annonce ?</h3>
<p>Toujours notre équipe : par le formulaire de la fiche, par téléphone ou par WhatsApp. Citez la référence de l’annonce (par exemple IAN-10245) : nous retrouvons le bien immédiatement et organisons la visite avec son propriétaire ou le professionnel qui le gère.</p>
<h3>Pourquoi les coordonnées de l’agence ne sont-elles pas affichées ?</h3>
<p>Parce que nous sommes l’intermédiaire de toutes les annonces présentées. Un seul interlocuteur vous évite de multiplier les appels, et nous permet de vérifier chaque demande avant de solliciter le partenaire concerné.</p>
<h3>Faut-il créer un compte pour chercher un bien ?</h3>
<p>Non. La recherche, les fiches et les favoris sont accessibles sans compte. Les favoris sont mémorisés par votre navigateur.</p>
<h3>Les annonces sont-elles vérifiées ?</h3>
<p>Oui : photos, localisation, cohérence du prix et documents déclarés sont contrôlés par notre équipe. Cette vérification ne remplace ni une expertise, ni l’examen du titre de propriété par un notaire.</p>

<h2>Confier un bien (particuliers)</h2>
<h3>Puis-je publier moi-même mon annonce ?</h3>
<p>Non. Vous nous confiez votre bien depuis votre espace propriétaire ; un conseiller étudie le dossier, rédige l’annonce et la met en ligne. Nous nous occupons ensuite des demandes des personnes intéressées.</p>
<h3>Pourquoi dois-je créer un compte ?</h3>
<p>Pour que nous puissions vous identifier, vous recontacter et que vous suiviez l’avancement de votre dossier. Votre adresse email doit être confirmée avant l’envoi d’un bien.</p>
<h3>Quels documents fournir ?</h3>
<p>Des photos récentes du bien et, si vous les avez, le titre de propriété ou tout document utile (plan, attestation). Ils restent privés : seule notre équipe les consulte.</p>
<h3>Mon adresse exacte sera-t-elle publiée ?</h3>
<p>Non. Elle sert à notre équipe pour organiser les visites. Le site n’affiche que la commune ou le quartier.</p>
<h3>Puis-je retirer mon bien ?</h3>
<p>Oui, depuis votre espace, tant que l’annonce n’est pas en préparation. Ensuite, contactez notre équipe.</p>

<h2>Devenir partenaire (professionnels)</h2>
<h3>Qui peut devenir partenaire ?</h3>
<p>Les agences immobilières, promoteurs, gestionnaires de biens et autres professionnels habilités à proposer des biens immobiliers.</p>
<h3>Quelles pièces sont demandées ?</h3>
<p>L’extrait du registre du commerce et la pièce d’identité du responsable sont obligatoires ; la déclaration fiscale d’existence et la carte ou l’agrément professionnel sont recommandés.</p>
<h3>Comment les prospects me contactent-ils ?</h3>
<p>Ils s’adressent à Weblogy. Nous qualifions chaque demande, puis nous vous sollicitons pour la visite ou le traitement du dossier. Votre console affiche le nombre de demandes reçues sur vos biens.</p>
<h3>Mes annonces sont-elles publiées immédiatement ?</h3>
<p>Par défaut, chaque annonce et chaque modification sont vérifiées par notre équipe avant d’être visibles. Weblogy conserve dans tous les cas la possibilité de corriger, désactiver ou retirer une annonce.</p>

<h2>Compte et données</h2>
<h3>J’ai oublié mon mot de passe.</h3>
<p>Utilisez le lien « Mot de passe oublié » de la page de connexion : un lien valable une heure vous est envoyé par email.</p>
<h3>Comment exercer mes droits sur mes données ?</h3>
<p>Écrivez à <a href="mailto:info@weblogy.com">info@weblogy.com</a>. Le détail figure dans notre politique de confidentialité.</p>',
  meta_description = 'Questions fréquentes : contacter Weblogy au sujet d’un bien, confier son bien, devenir partenaire, compte et données personnelles.',
  is_published = 1
WHERE code = 'faq';
