-- =============================================================================
-- 0012 — Nom public du site : « Abidjan.net Immobilier »
--
-- `sites.name` servait de nom affiché ET reprenait le nom de domaine : le bloc « Votre
-- interlocuteur » d'une fiche annonce, les titres de pages, le pied de page et les emails
-- affichaient « immobilier.abidjan.net ». Le nom commercial est « Abidjan.net Immobilier ».
--
-- Le domaine ne change pas : il reste immobilier.abidjan.net dans `site_domains`, dans les URL
-- et dans les mentions légales (« Le site immobilier.abidjan.net est édité par… »).
--
-- Idempotente : ne modifie que le nom d'origine, sans effet au second passage.
-- =============================================================================

UPDATE sites SET name = 'Abidjan.net Immobilier' WHERE name = 'immobilier.abidjan.net';
