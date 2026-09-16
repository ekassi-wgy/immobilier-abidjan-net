-- =============================================================================
-- immobilier.abidjan.net — Données de référence (lancement Côte d'Ivoire)
-- À importer après schema.sql, sur une base vide.
-- Aucun compte utilisateur ici : le premier Super Admin est créé par le script
-- d'installation (lot 1.3), jamais avec un mot de passe versionné.
-- Référentiel géographique et catalogue = base de départ, à compléter depuis le back-office.
-- =============================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- -----------------------------------------------------------------------------
-- Pays (seule la Côte d'Ivoire est active au lancement)
-- -----------------------------------------------------------------------------
INSERT INTO countries (iso2, name, name_translations, currency_code, currency_symbol, currency_decimals, phone_prefix, default_locale, timezone, is_active, sort_order) VALUES
  ('CI', 'Côte d''Ivoire', '{"en": "Ivory Coast"}', 'XOF', 'FCFA', 0, '+225', 'fr', 'Africa/Abidjan',    1, 1),
  ('SN', 'Sénégal',        '{"en": "Senegal"}',     'XOF', 'FCFA', 0, '+221', 'fr', 'Africa/Dakar',      0, 2),
  ('CM', 'Cameroun',       '{"en": "Cameroon"}',    'XAF', 'FCFA', 0, '+237', 'fr', 'Africa/Douala',     0, 3),
  ('TG', 'Togo',           '{"en": "Togo"}',        'XOF', 'FCFA', 0, '+228', 'fr', 'Africa/Lome',       0, 4),
  ('BJ', 'Bénin',          '{"en": "Benin"}',       'XOF', 'FCFA', 0, '+229', 'fr', 'Africa/Porto-Novo', 0, 5);

SET @ci = (SELECT id FROM countries WHERE iso2 = 'CI');

-- -----------------------------------------------------------------------------
-- Site & domaines
-- -----------------------------------------------------------------------------
INSERT INTO sites (country_id, code, name, theme, default_locale, supported_locales, status)
VALUES (@ci, 'ci', 'immobilier.abidjan.net', 'default', 'fr', '["fr"]', 'active');

SET @site = (SELECT id FROM sites WHERE code = 'ci');

INSERT INTO site_domains (site_id, host, environment, is_primary) VALUES
  (@site, 'immobilier.abidjan.net',         'production', 1),
  (@site, 'staging.immobilier.abidjan.net', 'staging',    0),
  (@site, 'immobilier.abidjan.local',       'local',      0),
  (@site, 'localhost',                      'local',      0),
  (@site, '127.0.0.1',                      'local',      0);

-- -----------------------------------------------------------------------------
-- Paramètres (NULL = décision métier à prendre, cf. cahier des charges §9.1)
-- -----------------------------------------------------------------------------
INSERT INTO settings (site_id, setting_key, value, description) VALUES
  (NULL, 'listing.lifetime_days',              '90',     'Durée de vie d’une annonce publiée avant expiration'),
  (NULL, 'listing.expiry_reminder_days',       '7',      'Relance de l’agence N jours avant expiration'),
  (NULL, 'listing.reference_prefix',           '"IAN"',  'Préfixe des références publiques'),
  (NULL, 'listing.max_photos',                 '30',     'Nombre maximal de photos par annonce'),
  (NULL, 'listing.max_photo_size_mb',          '10',     'Poids maximal d’une photo à l’envoi'),
  (NULL, 'workflow.auto_publish_super_admin',  'true',   'Annonces du Super Admin publiées sans validation'),
  (NULL, 'workflow.auto_publish_country_admin','false',  'Annonces des Admins Pays publiées sans validation'),
  (NULL, 'commission.mode',                    'null',   'percent | fixed | premium_listing — à définir'),
  (NULL, 'commission.rate_percent',            'null',   'Taux de commission par transaction — à définir'),
  (NULL, 'commission.fixed_amount',            'null',   'Montant fixe — à définir'),
  (NULL, 'security.login_max_attempts',        '5',      'Tentatives de connexion avant blocage temporaire'),
  (NULL, 'security.login_lockout_minutes',     '15',     'Durée du blocage'),
  (NULL, 'home.featured_limit',                '6',      'Nombre de biens à la une sur l’accueil'),
  (@site,'contact.email',                      'null',   'Email de contact du site — à fournir'),
  (@site,'contact.phone',                      'null',   'Téléphone de contact — à fournir'),
  (@site,'contact.whatsapp',                   'null',   'WhatsApp de contact — à fournir');

-- -----------------------------------------------------------------------------
-- Villes (coordonnées approximatives : centrage de carte)
-- -----------------------------------------------------------------------------
INSERT INTO cities (country_id, name, slug, latitude, longitude, sort_order) VALUES
  (@ci, 'Abidjan',       'abidjan',       5.3599500, -4.0082600, 1),
  (@ci, 'Grand-Bassam',  'grand-bassam',  5.2118000, -3.7388000, 2),
  (@ci, 'Assinie-Mafia', 'assinie-mafia', 5.1280000, -3.2830000, 3),
  (@ci, 'Jacqueville',   'jacqueville',   5.2050000, -4.4190000, 4),
  (@ci, 'Yamoussoukro',  'yamoussoukro',  6.8276000, -5.2893000, 5),
  (@ci, 'Bouaké',        'bouake',        7.6900000, -5.0300000, 6),
  (@ci, 'San-Pédro',     'san-pedro',     4.7485000, -6.6363000, 7),
  (@ci, 'Korhogo',       'korhogo',       9.4580000, -5.6290000, 8),
  (@ci, 'Daloa',         'daloa',         6.8770000, -6.4500000, 9),
  (@ci, 'Man',           'man',           7.4125000, -7.5538000, 10);

SET @abidjan = (SELECT id FROM cities WHERE country_id = @ci AND slug = 'abidjan');

-- Les 13 communes du District autonome d'Abidjan
INSERT INTO communes (city_id, name, slug, latitude, longitude, sort_order) VALUES
  (@abidjan, 'Cocody',      'cocody',      5.3600000, -3.9700000, 1),
  (@abidjan, 'Plateau',     'plateau',     5.3237000, -4.0197000, 2),
  (@abidjan, 'Marcory',     'marcory',     5.3030000, -3.9840000, 3),
  (@abidjan, 'Yopougon',    'yopougon',    5.3460000, -4.0800000, 4),
  (@abidjan, 'Treichville', 'treichville', 5.2930000, -4.0080000, 5),
  (@abidjan, 'Koumassi',    'koumassi',    5.2950000, -3.9520000, 6),
  (@abidjan, 'Port-Bouët',  'port-bouet',  5.2560000, -3.9260000, 7),
  (@abidjan, 'Bingerville', 'bingerville', 5.3550000, -3.8850000, 8),
  (@abidjan, 'Abobo',       'abobo',       5.4160000, -4.0200000, 9),
  (@abidjan, 'Adjamé',      'adjame',      5.3570000, -4.0250000, 10),
  (@abidjan, 'Attécoubé',   'attecoube',   5.3350000, -4.0450000, 11),
  (@abidjan, 'Anyama',      'anyama',      5.4940000, -4.0520000, 12),
  (@abidjan, 'Songon',      'songon',      5.3200000, -4.2600000, 13);

-- Hors Abidjan : une commune homonyme par ville
INSERT INTO communes (city_id, name, slug, latitude, longitude, sort_order)
SELECT id, name, slug, latitude, longitude, 1 FROM cities WHERE country_id = @ci AND slug <> 'abidjan';

-- Quartiers principaux (liste de départ, à compléter depuis le back-office)
INSERT INTO districts (commune_id, name, slug, sort_order)
SELECT c.id, d.name, d.slug, d.sort_order
FROM communes c
JOIN cities ci ON ci.id = c.city_id AND ci.country_id = @ci
JOIN (
            SELECT 'abidjan' city, 'cocody' commune, 'Riviera Golf' name, 'riviera-golf' slug, 1 sort_order
  UNION ALL SELECT 'abidjan', 'cocody', 'Riviera 1', 'riviera-1', 2
  UNION ALL SELECT 'abidjan', 'cocody', 'Riviera 2', 'riviera-2', 3
  UNION ALL SELECT 'abidjan', 'cocody', 'Riviera 3', 'riviera-3', 4
  UNION ALL SELECT 'abidjan', 'cocody', 'Riviera 4', 'riviera-4', 5
  UNION ALL SELECT 'abidjan', 'cocody', 'Riviera Palmeraie', 'riviera-palmeraie', 6
  UNION ALL SELECT 'abidjan', 'cocody', 'Riviera Bonoumin', 'riviera-bonoumin', 7
  UNION ALL SELECT 'abidjan', 'cocody', 'Riviera Faya', 'riviera-faya', 8
  UNION ALL SELECT 'abidjan', 'cocody', 'Angré', 'angre', 9
  UNION ALL SELECT 'abidjan', 'cocody', 'Deux-Plateaux', 'deux-plateaux', 10
  UNION ALL SELECT 'abidjan', 'cocody', 'Deux-Plateaux Vallon', 'deux-plateaux-vallon', 11
  UNION ALL SELECT 'abidjan', 'cocody', 'Ambassades', 'ambassades', 12
  UNION ALL SELECT 'abidjan', 'cocody', 'Danga', 'danga', 13
  UNION ALL SELECT 'abidjan', 'cocody', 'Blockhaus', 'blockhaus', 14
  UNION ALL SELECT 'abidjan', 'cocody', 'Cocody Centre', 'cocody-centre', 15
  UNION ALL SELECT 'abidjan', 'cocody', 'Saint-Jean', 'saint-jean', 16
  UNION ALL SELECT 'abidjan', 'cocody', 'M''Pouto', 'mpouto', 17
  UNION ALL SELECT 'abidjan', 'cocody', 'M''Badon', 'mbadon', 18
  UNION ALL SELECT 'abidjan', 'cocody', 'Attoban', 'attoban', 19
  UNION ALL SELECT 'abidjan', 'cocody', 'Akouédo', 'akouedo', 20
  UNION ALL SELECT 'abidjan', 'plateau', 'Centre des affaires', 'centre-des-affaires', 1
  UNION ALL SELECT 'abidjan', 'plateau', 'Cité administrative', 'cite-administrative', 2
  UNION ALL SELECT 'abidjan', 'plateau', 'Indénié', 'indenie', 3
  UNION ALL SELECT 'abidjan', 'marcory', 'Zone 4', 'zone-4', 1
  UNION ALL SELECT 'abidjan', 'marcory', 'Biétry', 'bietry', 2
  UNION ALL SELECT 'abidjan', 'marcory', 'Marcory Résidentiel', 'marcory-residentiel', 3
  UNION ALL SELECT 'abidjan', 'marcory', 'Anoumabo', 'anoumabo', 4
  UNION ALL SELECT 'abidjan', 'marcory', 'Remblais', 'remblais', 5
  UNION ALL SELECT 'abidjan', 'marcory', 'Champroux', 'champroux', 6
  UNION ALL SELECT 'abidjan', 'yopougon', 'Niangon', 'niangon', 1
  UNION ALL SELECT 'abidjan', 'yopougon', 'Selmer', 'selmer', 2
  UNION ALL SELECT 'abidjan', 'yopougon', 'Maroc', 'maroc', 3
  UNION ALL SELECT 'abidjan', 'yopougon', 'Toits Rouges', 'toits-rouges', 4
  UNION ALL SELECT 'abidjan', 'yopougon', 'Sideci', 'sideci', 5
  UNION ALL SELECT 'abidjan', 'yopougon', 'Andokoi', 'andokoi', 6
  UNION ALL SELECT 'abidjan', 'yopougon', 'Siporex', 'siporex', 7
  UNION ALL SELECT 'abidjan', 'yopougon', 'Wassakara', 'wassakara', 8
  UNION ALL SELECT 'abidjan', 'yopougon', 'Ananeraie', 'ananeraie', 9
  UNION ALL SELECT 'abidjan', 'treichville', 'Arras', 'arras', 1
  UNION ALL SELECT 'abidjan', 'treichville', 'Belleville', 'belleville', 2
  UNION ALL SELECT 'abidjan', 'treichville', 'Biafra', 'biafra', 3
  UNION ALL SELECT 'abidjan', 'treichville', 'Zone portuaire', 'zone-portuaire', 4
  UNION ALL SELECT 'abidjan', 'koumassi', 'Remblais', 'remblais', 1
  UNION ALL SELECT 'abidjan', 'koumassi', 'Sicogi', 'sicogi', 2
  UNION ALL SELECT 'abidjan', 'koumassi', 'Grand Campement', 'grand-campement', 3
  UNION ALL SELECT 'abidjan', 'koumassi', 'Zone industrielle', 'zone-industrielle', 4
  UNION ALL SELECT 'abidjan', 'port-bouet', 'Vridi', 'vridi', 1
  UNION ALL SELECT 'abidjan', 'port-bouet', 'Gonzagueville', 'gonzagueville', 2
  UNION ALL SELECT 'abidjan', 'port-bouet', 'Adjouffou', 'adjouffou', 3
  UNION ALL SELECT 'abidjan', 'port-bouet', 'Aéroport', 'aeroport', 4
  UNION ALL SELECT 'abidjan', 'bingerville', 'Bingerville Centre', 'bingerville-centre', 1
  UNION ALL SELECT 'abidjan', 'bingerville', 'Akandjé', 'akandje', 2
  UNION ALL SELECT 'abidjan', 'bingerville', 'Abatta', 'abatta', 3
  UNION ALL SELECT 'abidjan', 'abobo', 'Abobo Baoulé', 'abobo-baoule', 1
  UNION ALL SELECT 'abidjan', 'abobo', 'Avocatier', 'avocatier', 2
  UNION ALL SELECT 'abidjan', 'abobo', 'PK 18', 'pk-18', 3
  UNION ALL SELECT 'abidjan', 'abobo', 'Anador', 'anador', 4
  UNION ALL SELECT 'abidjan', 'abobo', 'Dokui', 'dokui', 5
  UNION ALL SELECT 'abidjan', 'adjame', 'Williamsville', 'williamsville', 1
  UNION ALL SELECT 'abidjan', 'adjame', '220 Logements', '220-logements', 2
  UNION ALL SELECT 'abidjan', 'adjame', 'Liberté', 'liberte', 3
  UNION ALL SELECT 'abidjan', 'adjame', 'Paillet', 'paillet', 4
  UNION ALL SELECT 'abidjan', 'attecoube', 'Santé', 'sante', 1
  UNION ALL SELECT 'abidjan', 'attecoube', 'Locodjoro', 'locodjoro', 2
  UNION ALL SELECT 'abidjan', 'attecoube', 'Agban', 'agban', 3
  UNION ALL SELECT 'abidjan', 'anyama', 'Anyama Centre', 'anyama-centre', 1
  UNION ALL SELECT 'abidjan', 'anyama', 'Ebimpé', 'ebimpe', 2
  UNION ALL SELECT 'abidjan', 'songon', 'Songon-Agban', 'songon-agban', 1
  UNION ALL SELECT 'abidjan', 'songon', 'Songon-Kassemblé', 'songon-kassemble', 2
  UNION ALL SELECT 'grand-bassam', 'grand-bassam', 'Quartier France', 'quartier-france', 1
  UNION ALL SELECT 'grand-bassam', 'grand-bassam', 'Azuretti', 'azuretti', 2
  UNION ALL SELECT 'grand-bassam', 'grand-bassam', 'Mockeyville', 'mockeyville', 3
) d ON d.city = ci.slug AND d.commune = c.slug;

-- -----------------------------------------------------------------------------
-- Types de transaction (§3.1)
-- -----------------------------------------------------------------------------
INSERT INTO transaction_types (code, slug, name, name_translations, default_price_period, sort_order) VALUES
  ('sale',             'acheter',          'Vente',                               '{"en": "Sale"}',                 'total', 1),
  ('rent',             'louer',            'Location',                            '{"en": "Rent"}',                 'month', 2),
  ('furnished_rent',   'location-meublee', 'Location meublée / courte durée',     '{"en": "Furnished / short stay"}','month', 3),
  ('commercial_lease', 'bail-commercial',  'Bail commercial',                     '{"en": "Commercial lease"}',     'month', 4),
  ('lease_transfer',   'cession-de-bail',  'Cession de bail',                     '{"en": "Lease transfer"}',       'total', 5);

-- -----------------------------------------------------------------------------
-- Catégories (§3.2) — racines puis sous-catégories
-- -----------------------------------------------------------------------------
INSERT INTO property_categories (parent_id, code, slug, name, name_plural, name_translations, icon, sort_order) VALUES
  (NULL, 'residential',   'residentiel',          'Résidentiel',             'Résidentiel',             '{"en": "Residential"}',          'house',     1),
  (NULL, 'land',          'terrains',             'Terrains',                'Terrains',                '{"en": "Land"}',                 'land',      2),
  (NULL, 'commercial',    'commercial-bureaux',   'Commercial & Bureaux',    'Commercial & Bureaux',    '{"en": "Commercial & offices"}', 'store',     3),
  (NULL, 'industrial',    'industriel',           'Industriel',              'Industriel',              '{"en": "Industrial"}',           'factory',   4),
  (NULL, 'hospitality',   'hotellerie-tourisme',  'Hôtellerie & Tourisme',   'Hôtellerie & Tourisme',   '{"en": "Hospitality"}',          'bed',       5),
  (NULL, 'institutional', 'institutionnel',       'Institutionnel / Spécial','Institutionnel / Spécial','{"en": "Institutional"}',        'buildings', 6);

INSERT INTO property_categories (parent_id, code, slug, name, name_plural, name_translations, sort_order)
SELECT p.id, s.code, s.slug, s.name, s.name_plural, s.translations, s.sort_order
FROM property_categories p
JOIN (
            SELECT 'residential' parent, 'apartment' code, 'appartement' slug, 'Appartement' name, 'Appartements' name_plural, '{"en": "Apartment"}' translations, 1 sort_order
  UNION ALL SELECT 'residential', 'villa', 'villa', 'Villa / Maison individuelle', 'Villas & maisons', '{"en": "Villa / House"}', 2
  UNION ALL SELECT 'residential', 'semi_detached', 'maison-jumelee', 'Maison jumelée / mitoyenne', 'Maisons jumelées', '{"en": "Semi-detached house"}', 3
  UNION ALL SELECT 'residential', 'residential_building', 'immeuble-residentiel', 'Immeuble résidentiel', 'Immeubles résidentiels', '{"en": "Apartment building"}', 4
  UNION ALL SELECT 'residential', 'room', 'chambre-colocation', 'Chambre / Colocation', 'Chambres & colocations', '{"en": "Room / Flatshare"}', 5
  UNION ALL SELECT 'land', 'residential_land', 'terrain-nu', 'Terrain nu (résidentiel)', 'Terrains nus', '{"en": "Residential land"}', 1
  UNION ALL SELECT 'land', 'agricultural_land', 'terrain-agricole', 'Terrain agricole', 'Terrains agricoles', '{"en": "Agricultural land"}', 2
  UNION ALL SELECT 'land', 'industrial_land', 'terrain-industriel', 'Terrain industriel', 'Terrains industriels', '{"en": "Industrial land"}', 3
  UNION ALL SELECT 'commercial', 'office', 'bureau', 'Bureau / Plateau de bureaux', 'Bureaux', '{"en": "Office"}', 1
  UNION ALL SELECT 'commercial', 'retail', 'local-commercial', 'Local commercial / Boutique', 'Locaux commerciaux', '{"en": "Retail space"}', 2
  UNION ALL SELECT 'commercial', 'warehouse', 'magasin-entrepot', 'Magasin / Entrepôt / Hangar', 'Magasins & entrepôts', '{"en": "Warehouse"}', 3
  UNION ALL SELECT 'commercial', 'commercial_building', 'immeuble-commercial', 'Immeuble commercial', 'Immeubles commerciaux', '{"en": "Commercial building"}', 4
  UNION ALL SELECT 'commercial', 'mall_unit', 'centre-commercial', 'Centre commercial (lot)', 'Lots en centre commercial', '{"en": "Shopping mall unit"}', 5
  UNION ALL SELECT 'industrial', 'factory', 'usine', 'Usine', 'Usines', '{"en": "Factory"}', 1
  UNION ALL SELECT 'industrial', 'logistics_warehouse', 'entrepot-logistique', 'Entrepôt logistique', 'Entrepôts logistiques', '{"en": "Logistics warehouse"}', 2
  UNION ALL SELECT 'industrial', 'industrial_zone', 'zone-industrielle', 'Zone industrielle', 'Zones industrielles', '{"en": "Industrial estate"}', 3
  UNION ALL SELECT 'hospitality', 'hotel', 'hotel', 'Hôtel / Résidence hôtelière', 'Hôtels', '{"en": "Hotel"}', 1
  UNION ALL SELECT 'hospitality', 'guest_house', 'maison-hotes', 'Maison d''hôtes', 'Maisons d''hôtes', '{"en": "Guest house"}', 2
  UNION ALL SELECT 'hospitality', 'holiday_home', 'residence-vacances', 'Résidence de vacances', 'Résidences de vacances', '{"en": "Holiday home"}', 3
  UNION ALL SELECT 'institutional', 'school', 'etablissement-scolaire', 'Établissement scolaire', 'Établissements scolaires', '{"en": "School"}', 1
  UNION ALL SELECT 'institutional', 'healthcare', 'etablissement-sante', 'Établissement de santé (clinique)', 'Établissements de santé', '{"en": "Healthcare facility"}', 2
  UNION ALL SELECT 'institutional', 'mixed_use_building', 'immeuble-mixte', 'Immeuble mixte (résidentiel + commercial)', 'Immeubles mixtes', '{"en": "Mixed-use building"}', 3
) s ON s.parent = p.code;

-- Transactions autorisées (niveau racine ; une sous-catégorie peut restreindre)
INSERT INTO category_transaction_types (category_id, transaction_type_id)
SELECT c.id, t.id
FROM property_categories c
JOIN transaction_types t
JOIN (
            SELECT 'residential' cat, 'sale' tx
  UNION ALL SELECT 'residential', 'rent'
  UNION ALL SELECT 'residential', 'furnished_rent'
  UNION ALL SELECT 'land', 'sale'
  UNION ALL SELECT 'land', 'rent'
  UNION ALL SELECT 'commercial', 'sale'
  UNION ALL SELECT 'commercial', 'rent'
  UNION ALL SELECT 'commercial', 'commercial_lease'
  UNION ALL SELECT 'commercial', 'lease_transfer'
  UNION ALL SELECT 'industrial', 'sale'
  UNION ALL SELECT 'industrial', 'rent'
  UNION ALL SELECT 'industrial', 'commercial_lease'
  UNION ALL SELECT 'hospitality', 'sale'
  UNION ALL SELECT 'hospitality', 'rent'
  UNION ALL SELECT 'hospitality', 'furnished_rent'
  UNION ALL SELECT 'institutional', 'sale'
  UNION ALL SELECT 'institutional', 'rent'
  UNION ALL SELECT 'institutional', 'commercial_lease'
  UNION ALL SELECT 'room', 'rent'
  UNION ALL SELECT 'room', 'furnished_rent'
  UNION ALL SELECT 'residential_building', 'sale'
  UNION ALL SELECT 'residential_building', 'rent'
) m ON m.cat = c.code AND m.tx = t.code;

-- -----------------------------------------------------------------------------
-- Critères dynamiques (§3.3)
-- -----------------------------------------------------------------------------
INSERT INTO attribute_groups (code, name, name_translations, sort_order) VALUES
  ('general',     'Caractéristiques générales', '{"en": "General"}',          1),
  ('residential', 'Logement',                   '{"en": "Living space"}',     2),
  ('land',        'Terrain',                    '{"en": "Land"}',             3),
  ('commercial',  'Local professionnel',        '{"en": "Business premises"}',4),
  ('legal',       'Situation juridique',        '{"en": "Legal status"}',     5);

INSERT INTO property_attributes (group_id, code, name, name_translations, input_type, unit, storage, column_name, min_value, max_value, help_text, is_filterable, is_public, sort_order)
SELECT g.id, a.code, a.name, a.translations, a.input_type, a.unit, a.storage, a.column_name, a.min_value, a.max_value, a.help_text, a.is_filterable, a.is_public, a.sort_order
FROM attribute_groups g
JOIN (
            SELECT 'general' grp, 'living_area' code, 'Surface habitable' name, '{"en": "Living area"}' translations, 'decimal' input_type, 'm²' unit, 'column' storage, 'living_area' column_name, 1 min_value, 1000000 max_value, 'Surface bâtie utile' help_text, 1 is_filterable, 1 is_public, 1 sort_order
  UNION ALL SELECT 'general', 'land_area', 'Superficie du terrain', '{"en": "Land area"}', 'decimal', 'm²', 'column', 'land_area', 1, 100000000, NULL, 1, 1, 2
  UNION ALL SELECT 'general', 'standing', 'Standing', '{"en": "Standard"}', 'select', NULL, 'eav', NULL, NULL, NULL, NULL, 1, 1, 3
  UNION ALL SELECT 'general', 'condition', 'État du bien', '{"en": "Condition"}', 'select', NULL, 'eav', NULL, NULL, NULL, NULL, 0, 1, 4
  UNION ALL SELECT 'general', 'construction_year', 'Année de construction', '{"en": "Year built"}', 'year', NULL, 'eav', NULL, 1900, 2100, NULL, 0, 1, 5
  UNION ALL SELECT 'general', 'parking_spaces', 'Places de parking', '{"en": "Parking spaces"}', 'integer', 'places', 'eav', NULL, 0, 5000, NULL, 0, 1, 6
  UNION ALL SELECT 'residential', 'rooms', 'Nombre de pièces', '{"en": "Rooms"}', 'integer', NULL, 'column', 'rooms', 1, 500, NULL, 1, 1, 1
  UNION ALL SELECT 'residential', 'bedrooms', 'Chambres', '{"en": "Bedrooms"}', 'integer', NULL, 'column', 'bedrooms', 0, 500, NULL, 1, 1, 2
  UNION ALL SELECT 'residential', 'bathrooms', 'Salles de bain', '{"en": "Bathrooms"}', 'integer', NULL, 'column', 'bathrooms', 0, 500, NULL, 0, 1, 3
  UNION ALL SELECT 'residential', 'apartment_layout', 'Type d''appartement', '{"en": "Apartment type"}', 'select', NULL, 'eav', NULL, NULL, NULL, NULL, 1, 1, 4
  UNION ALL SELECT 'residential', 'floor', 'Étage', '{"en": "Floor"}', 'integer', NULL, 'eav', NULL, 0, 200, '0 = rez-de-chaussée', 0, 1, 5
  UNION ALL SELECT 'residential', 'building_floors', 'Nombre d''étages de l''immeuble', '{"en": "Building floors"}', 'integer', NULL, 'eav', NULL, 0, 200, NULL, 0, 1, 6
  UNION ALL SELECT 'residential', 'furnished', 'Meublé', '{"en": "Furnished"}', 'boolean', NULL, 'eav', NULL, NULL, NULL, NULL, 1, 1, 7
  UNION ALL SELECT 'residential', 'orientation', 'Orientation', '{"en": "Orientation"}', 'select', NULL, 'eav', NULL, NULL, NULL, NULL, 0, 1, 8
  UNION ALL SELECT 'residential', 'view', 'Vue', '{"en": "View"}', 'multiselect', NULL, 'eav', NULL, NULL, NULL, NULL, 0, 1, 9
  UNION ALL SELECT 'land', 'land_subdivision', 'Lotissement', '{"en": "Subdivision"}', 'select', NULL, 'eav', NULL, NULL, NULL, 'Terrain loti ou non loti', 1, 1, 1
  UNION ALL SELECT 'land', 'servicing', 'Viabilisation', '{"en": "Utilities"}', 'multiselect', NULL, 'eav', NULL, NULL, NULL, NULL, 1, 1, 2
  UNION ALL SELECT 'land', 'boundary_marked', 'Bornage effectué', '{"en": "Boundary marked"}', 'boolean', NULL, 'eav', NULL, NULL, NULL, NULL, 0, 1, 3
  UNION ALL SELECT 'land', 'road_access', 'Accès route', '{"en": "Road access"}', 'select', NULL, 'eav', NULL, NULL, NULL, NULL, 0, 1, 4
  UNION ALL SELECT 'commercial', 'levels_count', 'Nombre de niveaux', '{"en": "Levels"}', 'integer', NULL, 'eav', NULL, 1, 200, NULL, 0, 1, 1
  UNION ALL SELECT 'commercial', 'loading_dock', 'Quai de chargement', '{"en": "Loading dock"}', 'boolean', NULL, 'eav', NULL, NULL, NULL, NULL, 0, 1, 2
  UNION ALL SELECT 'commercial', 'seating_capacity', 'Capacité d''accueil', '{"en": "Capacity"}', 'integer', 'places', 'eav', NULL, 1, 100000, NULL, 0, 1, 3
  UNION ALL SELECT 'commercial', 'strategic_location', 'Emplacement stratégique', '{"en": "Prime location"}', 'boolean', NULL, 'eav', NULL, NULL, NULL, NULL, 0, 1, 4
  UNION ALL SELECT 'legal', 'title_type', 'Titre de propriété', '{"en": "Title deed"}', 'select', NULL, 'eav', NULL, NULL, NULL, NULL, 1, 1, 1
  UNION ALL SELECT 'legal', 'litigation', 'Litige en cours', '{"en": "Pending litigation"}', 'boolean', NULL, 'eav', NULL, NULL, NULL, NULL, 0, 1, 2
) a ON a.grp = g.code;

INSERT INTO property_attribute_options (attribute_id, code, label, label_translations, sort_order)
SELECT pa.id, o.code, o.label, o.translations, o.sort_order
FROM property_attributes pa
JOIN (
            SELECT 'standing' attr, 'economic' code, 'Économique' label, '{"en": "Budget"}' translations, 1 sort_order
  UNION ALL SELECT 'standing', 'mid', 'Moyen standing', '{"en": "Mid-range"}', 2
  UNION ALL SELECT 'standing', 'high', 'Haut standing', '{"en": "High-end"}', 3
  UNION ALL SELECT 'condition', 'new', 'Neuf', '{"en": "New"}', 1
  UNION ALL SELECT 'condition', 'good', 'Bon état', '{"en": "Good condition"}', 2
  UNION ALL SELECT 'condition', 'refresh', 'À rafraîchir', '{"en": "Needs refreshing"}', 3
  UNION ALL SELECT 'condition', 'renovate', 'À rénover', '{"en": "Needs renovation"}', 4
  UNION ALL SELECT 'apartment_layout', 'studio', 'Studio', '{"en": "Studio"}', 1
  UNION ALL SELECT 'apartment_layout', 'simplex', 'Simplex', '{"en": "Single-level"}', 2
  UNION ALL SELECT 'apartment_layout', 'duplex', 'Duplex', '{"en": "Duplex"}', 3
  UNION ALL SELECT 'apartment_layout', 'triplex', 'Triplex', '{"en": "Triplex"}', 4
  UNION ALL SELECT 'apartment_layout', 'penthouse', 'Penthouse', '{"en": "Penthouse"}', 5
  UNION ALL SELECT 'orientation', 'north', 'Nord', '{"en": "North"}', 1
  UNION ALL SELECT 'orientation', 'south', 'Sud', '{"en": "South"}', 2
  UNION ALL SELECT 'orientation', 'east', 'Est', '{"en": "East"}', 3
  UNION ALL SELECT 'orientation', 'west', 'Ouest', '{"en": "West"}', 4
  UNION ALL SELECT 'view', 'sea', 'Mer', '{"en": "Sea"}', 1
  UNION ALL SELECT 'view', 'lagoon', 'Lagune', '{"en": "Lagoon"}', 2
  UNION ALL SELECT 'view', 'garden', 'Jardin', '{"en": "Garden"}', 3
  UNION ALL SELECT 'view', 'city', 'Ville', '{"en": "City"}', 4
  UNION ALL SELECT 'view', 'open', 'Dégagée', '{"en": "Open view"}', 5
  UNION ALL SELECT 'land_subdivision', 'subdivided', 'Loti', '{"en": "Subdivided"}', 1
  UNION ALL SELECT 'land_subdivision', 'not_subdivided', 'Non loti', '{"en": "Not subdivided"}', 2
  UNION ALL SELECT 'servicing', 'water', 'Eau', '{"en": "Water"}', 1
  UNION ALL SELECT 'servicing', 'electricity', 'Électricité', '{"en": "Electricity"}', 2
  UNION ALL SELECT 'servicing', 'road', 'Voirie', '{"en": "Road network"}', 3
  UNION ALL SELECT 'road_access', 'paved', 'Route bitumée', '{"en": "Paved road"}', 1
  UNION ALL SELECT 'road_access', 'laterite', 'Voie en latérite', '{"en": "Dirt road"}', 2
  UNION ALL SELECT 'road_access', 'track', 'Piste', '{"en": "Track"}', 3
  UNION ALL SELECT 'road_access', 'none', 'Pas d''accès carrossable', '{"en": "No vehicle access"}', 4
  UNION ALL SELECT 'title_type', 'tf', 'Titre foncier (TF)', '{"en": "Land title (TF)"}', 1
  UNION ALL SELECT 'title_type', 'acd', 'Arrêté de concession définitive (ACD)', '{"en": "Final concession order (ACD)"}', 2
  UNION ALL SELECT 'title_type', 'adu', 'Attestation de droit d''usage (ADU)', '{"en": "Right of use certificate (ADU)"}', 3
  UNION ALL SELECT 'title_type', 'allocation_letter', 'Lettre d''attribution', '{"en": "Allocation letter"}', 4
  UNION ALL SELECT 'title_type', 'ownership_certificate', 'Certificat de propriété', '{"en": "Ownership certificate"}', 5
  UNION ALL SELECT 'title_type', 'customary', 'Coutumier', '{"en": "Customary"}', 6
) o ON o.attr = pa.code;

-- Critères par catégorie racine (hérités par les sous-catégories)
INSERT INTO category_attributes (category_id, attribute_id, sort_order)
SELECT c.id, pa.id, (g.sort_order * 100) + pa.sort_order
FROM property_categories c
JOIN (
            SELECT 'residential' cat, 'living_area' attr UNION ALL SELECT 'residential', 'land_area'
  UNION ALL SELECT 'residential', 'rooms' UNION ALL SELECT 'residential', 'bedrooms' UNION ALL SELECT 'residential', 'bathrooms'
  UNION ALL SELECT 'residential', 'furnished' UNION ALL SELECT 'residential', 'parking_spaces' UNION ALL SELECT 'residential', 'orientation'
  UNION ALL SELECT 'residential', 'view' UNION ALL SELECT 'residential', 'standing' UNION ALL SELECT 'residential', 'condition'
  UNION ALL SELECT 'residential', 'construction_year' UNION ALL SELECT 'residential', 'title_type' UNION ALL SELECT 'residential', 'litigation'
  UNION ALL SELECT 'apartment', 'apartment_layout' UNION ALL SELECT 'apartment', 'floor' UNION ALL SELECT 'apartment', 'building_floors'
  UNION ALL SELECT 'residential_building', 'levels_count'
  UNION ALL SELECT 'land', 'land_area' UNION ALL SELECT 'land', 'land_subdivision' UNION ALL SELECT 'land', 'servicing'
  UNION ALL SELECT 'land', 'boundary_marked' UNION ALL SELECT 'land', 'road_access' UNION ALL SELECT 'land', 'title_type' UNION ALL SELECT 'land', 'litigation'
  UNION ALL SELECT 'commercial', 'living_area' UNION ALL SELECT 'commercial', 'land_area' UNION ALL SELECT 'commercial', 'levels_count'
  UNION ALL SELECT 'commercial', 'parking_spaces' UNION ALL SELECT 'commercial', 'loading_dock' UNION ALL SELECT 'commercial', 'seating_capacity'
  UNION ALL SELECT 'commercial', 'strategic_location' UNION ALL SELECT 'commercial', 'standing' UNION ALL SELECT 'commercial', 'condition'
  UNION ALL SELECT 'commercial', 'construction_year' UNION ALL SELECT 'commercial', 'title_type' UNION ALL SELECT 'commercial', 'litigation'
  UNION ALL SELECT 'industrial', 'living_area' UNION ALL SELECT 'industrial', 'land_area' UNION ALL SELECT 'industrial', 'levels_count'
  UNION ALL SELECT 'industrial', 'loading_dock' UNION ALL SELECT 'industrial', 'parking_spaces' UNION ALL SELECT 'industrial', 'servicing'
  UNION ALL SELECT 'industrial', 'road_access' UNION ALL SELECT 'industrial', 'condition' UNION ALL SELECT 'industrial', 'title_type'
  UNION ALL SELECT 'industrial', 'litigation'
  UNION ALL SELECT 'hospitality', 'living_area' UNION ALL SELECT 'hospitality', 'land_area' UNION ALL SELECT 'hospitality', 'rooms'
  UNION ALL SELECT 'hospitality', 'bedrooms' UNION ALL SELECT 'hospitality', 'bathrooms' UNION ALL SELECT 'hospitality', 'levels_count'
  UNION ALL SELECT 'hospitality', 'parking_spaces' UNION ALL SELECT 'hospitality', 'seating_capacity' UNION ALL SELECT 'hospitality', 'view'
  UNION ALL SELECT 'hospitality', 'standing' UNION ALL SELECT 'hospitality', 'condition' UNION ALL SELECT 'hospitality', 'construction_year'
  UNION ALL SELECT 'hospitality', 'title_type' UNION ALL SELECT 'hospitality', 'litigation'
  UNION ALL SELECT 'institutional', 'living_area' UNION ALL SELECT 'institutional', 'land_area' UNION ALL SELECT 'institutional', 'rooms'
  UNION ALL SELECT 'institutional', 'levels_count' UNION ALL SELECT 'institutional', 'parking_spaces' UNION ALL SELECT 'institutional', 'seating_capacity'
  UNION ALL SELECT 'institutional', 'condition' UNION ALL SELECT 'institutional', 'construction_year' UNION ALL SELECT 'institutional', 'title_type'
  UNION ALL SELECT 'institutional', 'litigation'
) m ON m.cat = c.code
JOIN property_attributes pa ON pa.code = m.attr
JOIN attribute_groups g ON g.id = pa.group_id;

-- Critères obligatoires
UPDATE category_attributes ca
JOIN property_categories c ON c.id = ca.category_id
JOIN property_attributes pa ON pa.id = ca.attribute_id
SET ca.is_required = 1
WHERE (c.code = 'land' AND pa.code = 'land_area')
   OR (c.code IN ('residential','commercial','hospitality') AND pa.code = 'living_area');

-- -----------------------------------------------------------------------------
-- Équipements (cases à cocher)
-- -----------------------------------------------------------------------------
INSERT INTO features (code, name, name_translations, feature_group, icon, sort_order) VALUES
  ('pool',             'Piscine',                  '{"en": "Swimming pool"}',    'outdoor',      'pool',        1),
  ('garden',           'Jardin',                   '{"en": "Garden"}',           'outdoor',      'garden',      2),
  ('terrace',          'Balcon / terrasse',        '{"en": "Balcony / terrace"}','outdoor',      'terrace',     3),
  ('garage',           'Garage',                   '{"en": "Garage"}',           'outdoor',      'garage',      4),
  ('air_conditioning', 'Climatisation',            '{"en": "Air conditioning"}', 'comfort',      'snowflake',   5),
  ('equipped_kitchen', 'Cuisine équipée',          '{"en": "Fitted kitchen"}',   'comfort',      'kitchen',     6),
  ('elevator',         'Ascenseur',                '{"en": "Lift"}',             'comfort',      'elevator',    7),
  ('security_guard',   'Gardiennage',              '{"en": "Security guard"}',   'security',     'shield',      8),
  ('cctv',             'Vidéosurveillance',        '{"en": "CCTV"}',             'security',     'cctv',        9),
  ('access_control',   'Digicode / interphone',    '{"en": "Door entry system"}','security',     'keypad',      10),
  ('generator',        'Groupe électrogène',       '{"en": "Generator"}',        'utilities',    'generator',   11),
  ('borehole',         'Forage',                   '{"en": "Borehole"}',         'utilities',    'water',       12),
  ('water_tank',       'Réserve d''eau',           '{"en": "Water tank"}',       'utilities',    'water-tank',  13),
  ('fiber_optic',      'Fibre optique',            '{"en": "Fibre internet"}',   'connectivity', 'wifi',        14);

-- -----------------------------------------------------------------------------
-- Pages système (contenu à rédiger, non publiées)
-- -----------------------------------------------------------------------------
INSERT INTO pages (site_id, code, slug, locale, title, is_published) VALUES
  (@site, 'about',         'a-propos',                    'fr', 'À propos',                     0),
  (@site, 'how_it_works',  'comment-ca-marche',           'fr', 'Comment ça marche',            0),
  (@site, 'legal_notice',  'mentions-legales',            'fr', 'Mentions légales',             0),
  (@site, 'terms',         'conditions-generales',        'fr', 'Conditions générales d’utilisation', 0),
  (@site, 'privacy',       'politique-de-confidentialite','fr', 'Politique de confidentialité', 0),
  (@site, 'cookies',       'politique-cookies',           'fr', 'Politique de cookies',         0);

COMMIT;
