-- =============================================================================
-- 0007 — Liens vers les pages de réseaux sociaux du site
--
-- `sites.social_links` : objet JSON {"facebook": "https://…", "instagram": "https://…", …}.
-- Réseaux reconnus (liste fermée, dans l'ordre d'affichage) : facebook, instagram, linkedin,
-- x, youtube, tiktok. Saisis dans Pays & sites ; un réseau sans lien n'affiche aucun bouton
-- (jamais de lien mort dans le pied de page).
--
-- Idempotente : peut être rejouée sans effet.
-- =============================================================================

SET @has_social := (SELECT COUNT(*) FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'sites' AND column_name = 'social_links');
SET @sql := IF(@has_social = 0,
  'ALTER TABLE sites ADD COLUMN social_links JSON NULL COMMENT ''{"facebook": "https://…", …} — réseaux affichés dans le pied de page'' AFTER longitude',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
