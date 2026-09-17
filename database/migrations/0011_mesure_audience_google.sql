-- =============================================================================
-- 0011 — Mesure d'audience Google (tag du site principal Abidjan.net)
--
-- `sites.analytics_id` : identifiant du tag Google du site (« G-XXXXXXX » pour Google Analytics 4,
-- « UA-… » pour l'ancien Universal Analytics), saisi dans Pays & sites. Vide = aucune mesure.
-- Le tag n'est servi que sur un domaine de production, et seulement APRÈS le consentement du
-- visiteur (bandeau cookies) : aucun script Google n'est chargé tant qu'il n'a pas accepté.
--
-- Côte d'Ivoire : identifiant du site principal Abidjan.net communiqué par le client (17/09/2026).
-- Un identifiant « UA- » relève d'Universal Analytics, que Google ne traite plus depuis 2023 ; celui-ci
-- est toutefois relié par Google à la propriété GA4 « G-R7BTH5QZYP » (cookie _ga_R7BTH5QZYP observé
-- au test), qui reçoit les mesures. Le tag est aussi relié à Google Ads : site.js refuse le stockage
-- publicitaire (mode consentement, ad_storage = denied), aucun cookie _gcl_* n'est déposé.
--
-- Pages « Politique cookies » et « Confidentialité » : la mesure d'audience et le recueil du
-- consentement y sont décrits. Remplacements ciblés, sans effet sur une page déjà retouchée dans
-- le back-office ni au second passage.
-- =============================================================================

SET @has_analytics := (SELECT COUNT(*) FROM information_schema.columns
                       WHERE table_schema = DATABASE() AND table_name = 'sites' AND column_name = 'analytics_id');
SET @sql := IF(@has_analytics = 0,
  'ALTER TABLE sites ADD COLUMN analytics_id VARCHAR(40) NULL COMMENT ''Tag Google (G-… ou UA-…), chargé après consentement, production uniquement'' AFTER social_links',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE sites SET analytics_id = 'UA-112758-1' WHERE code = 'ci' AND analytics_id IS NULL;

-- Politique cookies : texte d'origine (0009) remplacé en entier.
UPDATE pages SET
  content = '<p>immobilier.abidjan.net utilise des cookies nécessaires à son fonctionnement et, <strong>uniquement si vous l’acceptez</strong>, des cookies de mesure d’audience de Google Analytics. Le site ne dépose <strong>aucun traceur publicitaire</strong>.</p>

<h2>Cookies nécessaires</h2>
<p>Ils sont indispensables au site et ne requièrent pas de consentement.</p>
<table>
<thead><tr><th>Nom</th><th>Rôle</th><th>Durée</th></tr></thead>
<tbody>
<tr><td><code>ian_session</code></td><td>Maintient la session sur les pages comportant un formulaire et dans l’espace propriétaire, et protège les envois contre la falsification de requête.</td><td>Session</td></tr>
<tr><td><code>ian_fav</code></td><td>Mémorise les annonces que vous mettez en favori, pour les retrouver sans créer de compte.</td><td>1 an</td></tr>
<tr><td><code>ian_seen</code></td><td>Évite de compter plusieurs fois la même consultation d’annonce.</td><td>12 heures</td></tr>
</tbody>
</table>
<p>Vos favoris et votre choix concernant la mesure d’audience sont également conservés dans le stockage local de votre navigateur. Aucune de ces informations n’est transmise à un tiers.</p>

<h2>Mesure d’audience (avec votre accord)</h2>
<p>Si vous l’acceptez, le site charge Google Analytics, service de Google, pour mesurer sa fréquentation de façon statistique : pages consultées, durée de visite, type d’appareil et provenance des visiteurs. Ces informations nous aident à améliorer le site ; elles ne servent ni à la publicité, ni à vous identifier. Google peut les traiter hors de Côte d’Ivoire, notamment aux États-Unis.</p>
<table>
<thead><tr><th>Nom</th><th>Rôle</th><th>Durée</th></tr></thead>
<tbody>
<tr><td><code>_ga</code>, <code>_ga_*</code></td><td>Distinguent les visiteurs de façon anonyme pour compter les visites.</td><td>13 mois</td></tr>
<tr><td><code>_gid</code></td><td>Distingue les visiteurs sur une journée.</td><td>24 heures</td></tr>
</tbody>
</table>

<h2>Votre choix</h2>
<p>À votre première visite, un bandeau vous propose d’accepter ou de refuser la mesure d’audience. <strong>Tant que vous n’avez pas accepté, aucun script Google n’est chargé.</strong> Votre choix est conservé six mois, puis vous est demandé à nouveau.</p>
<p>Vous pouvez le modifier à tout moment grâce au lien « Gestion des cookies » en bas de chaque page. Si vous retirez votre accord, les cookies de mesure d’audience sont supprimés.</p>
<p>Vous pouvez aussi supprimer les cookies depuis les réglages de votre navigateur ; vos favoris seront alors perdus, vous serez déconnecté de votre espace propriétaire et certains formulaires pourront cesser de fonctionner.</p>',
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'politique-cookies'
  AND content LIKE '%ne recourt à aucune mesure d’audience tierce%';

-- Confidentialité : finalité, base légale, destinataires et durée de la mesure d'audience.
UPDATE pages SET
  content = REPLACE(REPLACE(REPLACE(REPLACE(content,
    '<li>mesurer l’audience des annonces de façon agrégée ;</li>',
    '<li>mesurer l’audience des annonces de façon agrégée et, avec votre accord, la fréquentation du site (Google Analytics) ;</li>'),
    'La sécurité du site repose sur l’intérêt légitime',
    'La mesure d’audience par Google Analytics repose sur votre consentement, recueilli par le bandeau cookies et révocable à tout moment. La sécurité du site repose sur l’intérêt légitime'),
    'Aucune donnée n’est vendue, cédée ou utilisée à des fins publicitaires.</p>',
    'Si vous acceptez la mesure d’audience, des données de navigation (pages consultées, appareil, provenance) sont transmises à Google, qui peut les traiter hors de Côte d’Ivoire. Aucune donnée n’est vendue, cédée ou utilisée à des fins publicitaires.</p>'),
    '<li>Annonces et statistiques d’audience : pendant l’exploitation du site.</li>',
    '<li>Annonces et statistiques d’audience : pendant l’exploitation du site.</li>\n<li>Cookies de mesure d’audience Google Analytics : treize mois au plus.</li>'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'politique-de-confidentialite'
  AND content NOT LIKE '%Google Analytics%';
