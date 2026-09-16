-- =============================================================================
-- 0003 — Icônes des familles de catégories (lot 1.8)
--
-- Les icônes du site public sont servies par le sprite Phosphor local
-- (public/assets/img/icons.svg, généré par bin/build-icons.php) : property_categories.icon
-- doit porter un nom présent dans ce sprite, sinon l'emplacement reste vide.
--
-- Quatre familles issues de seed.sql pointaient sur des noms absents du sprite :
--   home → house · shop → store · hotel → bed · building → buildings
-- =============================================================================

UPDATE property_categories SET icon = 'house'     WHERE code = 'residential'   AND icon = 'home';
UPDATE property_categories SET icon = 'store'     WHERE code = 'commercial'    AND icon = 'shop';
UPDATE property_categories SET icon = 'bed'       WHERE code = 'hospitality'   AND icon = 'hotel';
UPDATE property_categories SET icon = 'buildings' WHERE code = 'institutional' AND icon = 'building';
