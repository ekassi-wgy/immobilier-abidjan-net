-- =============================================================================
-- 0013 — Nom public dans les textes éditoriaux
--
-- Suite de la migration 0012 : « À propos », « Politique cookies » et « Politique de
-- confidentialité » désignaient le site par son domaine. Elles utilisent désormais le nom
-- commercial « Abidjan.net Immobilier ».
--
-- Les mentions légales et les CGU gardent le domaine : il y désigne le site au sens juridique
-- (« Le site immobilier.abidjan.net est édité par Weblogy Tech S.A »).
--
-- La description de la politique cookies est également corrigée : elle annonçait « uniquement des
-- cookies nécessaires » alors que la mesure d'audience (soumise au consentement) a été ajoutée
-- par la migration 0011.
--
-- Remplacements ciblés : sans effet sur une page déjà retouchée dans le back-office, ni au
-- second passage.
-- =============================================================================

UPDATE pages SET
  content = REPLACE(content,
    '<p><strong>immobilier.abidjan.net</strong> est la plateforme immobilière',
    '<p><strong>Abidjan.net Immobilier</strong> est la plateforme immobilière'),
  meta_description = REPLACE(meta_description,
    'immobilier.abidjan.net, la plateforme immobilière de Weblogy',
    'Abidjan.net Immobilier, la plateforme immobilière de Weblogy'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'a-propos';

UPDATE pages SET
  content = REPLACE(content,
    '<p>immobilier.abidjan.net utilise des cookies',
    '<p>Abidjan.net Immobilier utilise des cookies'),
  meta_description = 'Cookies utilisés par Abidjan.net Immobilier : cookies nécessaires au site, mesure d’audience soumise à votre accord, aucun traceur publicitaire.',
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'politique-cookies';

UPDATE pages SET
  content = REPLACE(content,
    'Weblogy, éditeur d’immobilier.abidjan.net, traite',
    'Weblogy, éditeur d’Abidjan.net Immobilier, traite'),
  meta_description = REPLACE(meta_description,
    'Données collectées par immobilier.abidjan.net',
    'Données collectées par Abidjan.net Immobilier'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'politique-de-confidentialite';
