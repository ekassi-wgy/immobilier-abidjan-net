-- =============================================================================
-- 0014 — Nom commercial dans les mentions légales et les CGU
--
-- Suite des migrations 0012 et 0013 : ces deux pages désignent désormais le site par son nom
-- commercial, « Abidjan.net Immobilier ».
--
-- La phrase d'identification de chaque page conserve le domaine entre parenthèses
-- (« Abidjan.net Immobilier (immobilier.abidjan.net) ») : les mentions légales et les CGU doivent
-- permettre d'identifier sans ambiguïté le site auquel elles s'appliquent.
--
-- Remplacements ciblés : sans effet sur une page déjà retouchée dans le back-office, ni au
-- second passage.
-- =============================================================================

UPDATE pages SET
  content = REPLACE(REPLACE(content,
    '<p>Le site <strong>immobilier.abidjan.net</strong> est édité par',
    '<p>Le site <strong>Abidjan.net Immobilier</strong> (immobilier.abidjan.net) est édité par'),
    '<p>immobilier.abidjan.net présente des biens immobiliers',
    '<p>Abidjan.net Immobilier présente des biens immobiliers'),
  meta_description = REPLACE(meta_description,
    'responsabilité du site immobilier.abidjan.net, édité par Weblogy.',
    'responsabilité du site Abidjan.net Immobilier, édité par Weblogy Tech S.A.'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'mentions-legales';

UPDATE pages SET
  content = REPLACE(REPLACE(content,
    'l’utilisation du site immobilier.abidjan.net, édité par Weblogy.',
    'l’utilisation du site Abidjan.net Immobilier (immobilier.abidjan.net), édité par Weblogy.'),
    '<p>immobilier.abidjan.net présente des biens immobiliers',
    '<p>Abidjan.net Immobilier présente des biens immobiliers'),
  meta_description = REPLACE(meta_description,
    'Conditions d’utilisation d’immobilier.abidjan.net',
    'Conditions d’utilisation d’Abidjan.net Immobilier'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'conditions-generales';
