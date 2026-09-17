-- =============================================================================
-- 0006 — Coordonnées GPS du site et coordonnées de contact de Weblogy
--
-- 1. `sites.latitude` / `sites.longitude` : position de l'éditeur, affichée sur la carte de la
--    page Contact. Modifiables dans Pays & sites. NULL = pas de carte.
--
-- 2. Coordonnées de contact du site immobilier.abidjan.net, communiquées par le client
--    (agence Abidjan.net de Cocody). Elles ne remplacent QUE des valeurs vides : une coordonnée
--    déjà saisie dans le back-office n'est jamais écrasée.
--
-- Idempotente : peut être rejouée sans effet.
-- =============================================================================

SET @has_lat := (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'sites' AND column_name = 'latitude');
SET @sql := IF(@has_lat = 0,
  'ALTER TABLE sites
     ADD COLUMN latitude  DECIMAL(10,7) NULL COMMENT ''Position de l''''éditeur (carte de la page Contact)'' AFTER address,
     ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE sites SET
  contact_email    = COALESCE(NULLIF(contact_email, ''), 'info@weblogy.com'),
  contact_phone    = COALESCE(NULLIF(contact_phone, ''), '+225 05 64 00 00 80'),
  contact_whatsapp = COALESCE(NULLIF(contact_whatsapp, ''), '+225 05 64 00 00 80'),
  address          = COALESCE(NULLIF(address, ''), 'Rue Washington Booker, Cocody-Ambassades, 01 BP 12324 01 Abidjan, Côte d’Ivoire'),
  latitude         = COALESCE(latitude, 5.3322343),
  longitude        = COALESCE(longitude, -4.0002405)
WHERE code = 'ci';
