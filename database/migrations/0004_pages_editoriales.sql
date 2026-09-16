-- =============================================================================
-- 0004 — Contenu des pages éditoriales (lot 1.11)
--
-- `seed.sql` crée six pages vides et non publiées. Cette migration remplit et publie les
-- deux pages éditoriales (À propos, Comment ça marche) : ce sont des textes de PROPOSITION,
-- rédigés pour que le site ne soit pas amputé au lancement. Ils décrivent uniquement ce que
-- la plateforme fait réellement, sans engagement commercial ni juridique, et seront
-- modifiables depuis le back-office au lot 2.2.
--
-- Les quatre pages légales (mentions légales, CGU, confidentialité, cookies) restent
-- volontairement NON PUBLIÉES : leurs textes relèvent du client (décision en attente).
-- Une page non publiée répond 404 et son lien n'apparaît pas dans le pied de page :
-- le site ne sert donc aucune page vide, et aucun lien mort.
-- =============================================================================

UPDATE pages SET
  title = 'À propos',
  content = '<p>immobilier.abidjan.net réunit les annonces d’agences immobilières partenaires en Côte d’Ivoire. Chaque annonce est contrôlée par notre équipe avant sa mise en ligne : photos, localisation, cohérence du prix et pièces justificatives déclarées.</p>
<h2>Ce que nous faisons</h2>
<ul>
<li>Nous sélectionnons les agences partenaires et vérifions leurs documents d’entreprise.</li>
<li>Nous relisons chaque annonce avant publication et refusons celles qui sont incomplètes.</li>
<li>Nous mettons en relation directement : votre message part à l’agence qui gère le bien.</li>
</ul>
<h2>Ce que nous ne faisons pas</h2>
<ul>
<li>Nous ne sommes ni agence, ni mandataire : nous ne vendons et ne louons aucun bien.</li>
<li>Nous n’encaissons aucun paiement entre un visiteur et une agence.</li>
<li>Nous ne garantissons pas la situation juridique d’un bien : demandez toujours les documents originaux.</li>
</ul>
<h2>Couverture</h2>
<p>La plateforme couvre aujourd’hui la Côte d’Ivoire. Elle est conçue dès l’origine pour accueillir d’autres pays d’Afrique de l’Ouest et Centrale, avec leur propre référentiel géographique et leur devise.</p>',
  meta_description = 'Qui nous sommes, comment les annonces sont vérifiées et ce que la plateforme fait — ou ne fait pas.',
  is_published = 1
WHERE code = 'about';

UPDATE pages SET
  title = 'Comment ça marche',
  content = '<h2>Vous cherchez un bien</h2>
<ol>
<li><strong>Cherchez.</strong> Filtrez par transaction, type de bien, commune, budget, surface et équipements. La vue carte situe les biens à l’échelle du quartier.</li>
<li><strong>Comparez.</strong> Mettez des annonces de côté avec le cœur : elles restent dans votre navigateur, sans créer de compte.</li>
<li><strong>Contactez.</strong> Écrivez à l’agence depuis la fiche, ou appelez-la directement. Votre message ne transite par aucun intermédiaire.</li>
</ol>
<h2>Vous avez un bien à vendre ou à louer</h2>
<ol>
<li><strong>Décrivez-le</strong> depuis la page « Déposer un bien » : type, secteur, prix souhaité et vos coordonnées.</li>
<li><strong>Une agence partenaire</strong> de votre secteur vous rappelle et convient d’un rendez-vous.</li>
<li><strong>Elle prépare l’annonce</strong> et la publie après validation par notre équipe.</li>
</ol>
<h2>Vous êtes une agence</h2>
<ol>
<li><strong>Déposez votre candidature</strong> depuis la page « Devenir agence partenaire ».</li>
<li><strong>Nous vérifions vos documents</strong> (registre du commerce, coordonnées) et vous rappelons.</li>
<li><strong>Votre compte est créé :</strong> vous publiez et suivez vos annonces, et recevez les demandes des visiteurs.</li>
</ol>
<h2>La vérification des annonces</h2>
<p>Une annonce déposée par une agence passe en attente de validation. Notre équipe la relit, puis la publie ou la refuse avec un motif. Une annonce publiée puis modifiée reste visible dans sa version en ligne tant que la modification n’est pas validée.</p>',
  meta_description = 'Chercher un bien, confier un bien à une agence partenaire, devenir partenaire : le fonctionnement pas à pas.',
  is_published = 1
WHERE code = 'how_it_works';
