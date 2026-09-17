-- =============================================================================
-- immobilier.abidjan.net — Schéma de base de données (référence v1)
-- Cible : MySQL 8.0+ / MariaDB 10.6+ · InnoDB · utf8mb4_unicode_ci
-- Dates stockées en UTC (DATETIME). Adresses IP en VARBINARY(16) (INET6_ATON).
-- Documentation : docs/database.md
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. MULTISITE & RÉFÉRENTIEL GÉOGRAPHIQUE
-- -----------------------------------------------------------------------------

CREATE TABLE countries (
  id                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  iso2               CHAR(2)          NOT NULL,
  name               VARCHAR(100)     NOT NULL,
  name_translations  JSON             NULL,
  currency_code      CHAR(3)          NOT NULL COMMENT 'ISO 4217 (XOF, XAF, GHS…)',
  currency_symbol    VARCHAR(10)      NOT NULL,
  currency_decimals  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  phone_prefix       VARCHAR(6)       NOT NULL,
  default_locale     VARCHAR(10)      NOT NULL DEFAULT 'fr',
  timezone           VARCHAR(40)      NOT NULL,
  is_active          TINYINT(1)       NOT NULL DEFAULT 0,
  sort_order         SMALLINT         NOT NULL DEFAULT 0,
  created_at         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_countries_iso2 (iso2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sites (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  country_id         INT UNSIGNED  NOT NULL,
  code               VARCHAR(30)   NOT NULL COMMENT 'Identifiant technique (ci, sn…)',
  name               VARCHAR(100)  NOT NULL,
  theme              VARCHAR(50)   NOT NULL DEFAULT 'default',
  default_locale     VARCHAR(10)   NOT NULL DEFAULT 'fr',
  supported_locales  JSON          NULL COMMENT '["fr","en"]',
  contact_email      VARCHAR(190)  NULL,
  contact_phone      VARCHAR(30)   NULL,
  contact_whatsapp   VARCHAR(30)   NULL,
  address            VARCHAR(255)  NULL,
  latitude           DECIMAL(10,7) NULL COMMENT 'Position de l''éditeur (carte de la page Contact)',
  longitude          DECIMAL(10,7) NULL,
  social_links       JSON          NULL COMMENT '{"facebook": "https://…", …} — réseaux affichés dans le pied de page',
  status             ENUM('active','maintenance','disabled') NOT NULL DEFAULT 'disabled',
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sites_code (code),
  KEY idx_sites_country (country_id),
  CONSTRAINT fk_sites_country FOREIGN KEY (country_id) REFERENCES countries (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Noms d'hôte résolus par le middleware SiteResolver (HTTP_HOST sans le port)
CREATE TABLE site_domains (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  site_id     INT UNSIGNED  NOT NULL,
  host        VARCHAR(190)  NOT NULL,
  environment ENUM('production','staging','local') NOT NULL DEFAULT 'production',
  is_primary  TINYINT(1)    NOT NULL DEFAULT 0,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_domains_host (host),
  KEY idx_site_domains_site (site_id),
  CONSTRAINT fk_site_domains_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cities (
  id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  country_id  INT UNSIGNED   NOT NULL,
  name        VARCHAR(100)   NOT NULL,
  slug        VARCHAR(120)   NOT NULL,
  latitude    DECIMAL(10,7)  NULL,
  longitude   DECIMAL(10,7)  NULL,
  is_active   TINYINT(1)     NOT NULL DEFAULT 1,
  sort_order  SMALLINT       NOT NULL DEFAULT 0,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cities_country_slug (country_id, slug),
  CONSTRAINT fk_cities_country FOREIGN KEY (country_id) REFERENCES countries (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE communes (
  id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  city_id     INT UNSIGNED   NOT NULL,
  name        VARCHAR(100)   NOT NULL,
  slug        VARCHAR(120)   NOT NULL,
  latitude    DECIMAL(10,7)  NULL,
  longitude   DECIMAL(10,7)  NULL,
  is_active   TINYINT(1)     NOT NULL DEFAULT 1,
  sort_order  SMALLINT       NOT NULL DEFAULT 0,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_communes_city_slug (city_id, slug),
  CONSTRAINT fk_communes_city FOREIGN KEY (city_id) REFERENCES cities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quartiers
CREATE TABLE districts (
  id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  commune_id  INT UNSIGNED   NOT NULL,
  name        VARCHAR(100)   NOT NULL,
  slug        VARCHAR(120)   NOT NULL,
  latitude    DECIMAL(10,7)  NULL,
  longitude   DECIMAL(10,7)  NULL,
  is_active   TINYINT(1)     NOT NULL DEFAULT 1,
  sort_order  SMALLINT       NOT NULL DEFAULT 0,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_districts_commune_slug (commune_id, slug),
  CONSTRAINT fk_districts_commune FOREIGN KEY (commune_id) REFERENCES communes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paramètres : site_id NULL = valeur globale, sinon surcharge par site
CREATE TABLE settings (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  site_id      INT UNSIGNED  NULL,
  site_scope   INT UNSIGNED  AS (IFNULL(site_id, 0)) STORED,
  setting_key  VARCHAR(100)  NOT NULL,
  value        JSON          NULL,
  description  VARCHAR(255)  NULL,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_scope_key (site_scope, setting_key),
  KEY idx_settings_site (site_id),
  CONSTRAINT fk_settings_site FOREIGN KEY (site_id) REFERENCES sites (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. AGENCES & UTILISATEURS DU BACK-OFFICE
-- -----------------------------------------------------------------------------

CREATE TABLE agencies (
  id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  country_id           INT UNSIGNED  NOT NULL,
  name                 VARCHAR(150)  NOT NULL,
  slug                 VARCHAR(170)  NOT NULL,
  partner_type         ENUM('agency','developer','property_manager','other') NOT NULL DEFAULT 'agency' COMMENT 'Agence, promoteur, gestionnaire, autre professionnel',
  legal_name           VARCHAR(190)  NULL,
  legal_form           VARCHAR(60)   NULL COMMENT 'SARL, SA, SAS, entreprise individuelle…',
  rccm                 VARCHAR(60)   NULL COMMENT 'Registre du commerce',
  tax_id               VARCHAR(60)   NULL COMMENT 'Compte contribuable (NCC)',
  logo_path            VARCHAR(255)  NULL,
  description          TEXT          NULL,
  email                VARCHAR(190)  NULL,
  phone                VARCHAR(30)   NULL,
  whatsapp             VARCHAR(30)   NULL,
  website              VARCHAR(255)  NULL,
  address              VARCHAR(255)  NULL,
  city_id              INT UNSIGNED  NULL,
  commune_id           INT UNSIGNED  NULL,
  status               ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
  is_verified          TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Badge « Agence vérifiée »',
  verified_at          DATETIME      NULL,
  is_featured          TINYINT(1)    NOT NULL DEFAULT 0,
  published_properties_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Compteur dénormalisé',
  partner_request_id   INT UNSIGNED  NULL,
  created_by_user_id   INT UNSIGNED  NULL,
  created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at           DATETIME      NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agencies_country_slug (country_id, slug),
  KEY idx_agencies_country_status (country_id, status, is_featured),
  KEY idx_agencies_city (city_id),
  KEY idx_agencies_commune (commune_id),
  CONSTRAINT fk_agencies_country FOREIGN KEY (country_id) REFERENCES countries (id),
  CONSTRAINT fk_agencies_city    FOREIGN KEY (city_id)    REFERENCES cities (id) ON DELETE SET NULL,
  CONSTRAINT fk_agencies_commune FOREIGN KEY (commune_id) REFERENCES communes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zones de couverture d'une agence
CREATE TABLE agency_zones (
  agency_id   INT UNSIGNED NOT NULL,
  commune_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (agency_id, commune_id),
  KEY idx_agency_zones_commune (commune_id),
  CONSTRAINT fk_agency_zones_agency  FOREIGN KEY (agency_id)  REFERENCES agencies (id) ON DELETE CASCADE,
  CONSTRAINT fk_agency_zones_commune FOREIGN KEY (commune_id) REFERENCES communes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comptes du back-office (connexion unique) : équipe interne ET agences partenaires
CREATE TABLE users (
  id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  role                 ENUM('super_admin','country_admin','agency_owner','agency_agent','owner') NOT NULL COMMENT 'owner = particulier qui confie un bien (espace public, jamais /cmsadmin)',
  country_id           INT UNSIGNED  NULL COMMENT 'Obligatoire pour country_admin et les comptes agence',
  agency_id            INT UNSIGNED  NULL COMMENT 'Obligatoire pour les comptes agence',
  first_name           VARCHAR(80)   NOT NULL,
  last_name            VARCHAR(80)   NOT NULL,
  email                VARCHAR(190)  NOT NULL,
  email_verified_at    DATETIME      NULL COMMENT 'Adresse confirmée (obligatoire pour un particulier avant de confier un bien)',
  password_hash        VARCHAR(255)  NOT NULL,
  phone                VARCHAR(30)   NULL,
  whatsapp             VARCHAR(30)   NULL,
  job_title            VARCHAR(100)  NULL,
  is_active            TINYINT(1)    NOT NULL DEFAULT 1,
  must_change_password TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Comptes créés manuellement',
  last_login_at        DATETIME      NULL,
  last_login_ip        VARBINARY(16) NULL,
  password_changed_at  DATETIME      NULL,
  created_by_user_id   INT UNSIGNED  NULL,
  created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at           DATETIME      NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_agency (agency_id),
  KEY idx_users_country_role (country_id, role),
  CONSTRAINT fk_users_country    FOREIGN KEY (country_id)         REFERENCES countries (id),
  CONSTRAINT fk_users_agency     FOREIGN KEY (agency_id)          REFERENCES agencies (id),
  CONSTRAINT fk_users_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_users_scope CHECK (
    (role = 'super_admin'   AND agency_id IS NULL) OR
    (role = 'country_admin' AND agency_id IS NULL AND country_id IS NOT NULL) OR
    (role IN ('agency_owner','agency_agent') AND agency_id IS NOT NULL AND country_id IS NOT NULL) OR
    (role = 'owner'         AND agency_id IS NULL AND country_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE agencies
  ADD CONSTRAINT fk_agencies_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL;

-- Limitation des tentatives de connexion
CREATE TABLE login_attempts (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(190)    NOT NULL,
  ip            VARBINARY(16)   NOT NULL,
  succeeded     TINYINT(1)      NOT NULL DEFAULT 0,
  attempted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_email (email, attempted_at),
  KEY idx_login_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jetons « mot de passe oublié » (seul le hash est stocké)
CREATE TABLE password_resets (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED    NOT NULL,
  token_hash  CHAR(64)        NOT NULL COMMENT 'SHA-256 du jeton envoyé par email',
  expires_at  DATETIME        NOT NULL,
  used_at     DATETIME        NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token (token_hash),
  KEY idx_password_resets_user (user_id),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- « Rester connecté » (motif sélecteur / validateur)
CREATE TABLE remember_tokens (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED    NOT NULL,
  selector        CHAR(24)        NOT NULL,
  validator_hash  CHAR(64)        NOT NULL,
  user_agent      VARCHAR(255)    NULL,
  expires_at      DATETIME        NOT NULL,
  last_used_at    DATETIME        NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_remember_tokens_selector (selector),
  KEY idx_remember_tokens_user (user_id),
  CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. CATALOGUE : TRANSACTIONS, CATÉGORIES, CRITÈRES DYNAMIQUES, ÉQUIPEMENTS
-- -----------------------------------------------------------------------------

CREATE TABLE transaction_types (
  id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  code                 VARCHAR(40)   NOT NULL,
  slug                 VARCHAR(60)   NOT NULL COMMENT 'Segment d’URL : acheter, louer…',
  name                 VARCHAR(80)   NOT NULL,
  name_translations    JSON          NULL,
  default_price_period ENUM('total','month','week','night','year') NOT NULL DEFAULT 'total',
  is_active            TINYINT(1)    NOT NULL DEFAULT 1,
  sort_order           SMALLINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_transaction_types_code (code),
  UNIQUE KEY uq_transaction_types_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arborescence parent / enfant (2 niveaux conseillés), gérée depuis le back-office
CREATE TABLE property_categories (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  parent_id          INT UNSIGNED  NULL,
  country_id         INT UNSIGNED  NULL COMMENT 'NULL = disponible dans tous les pays',
  code               VARCHAR(60)   NOT NULL,
  slug               VARCHAR(80)   NOT NULL,
  name               VARCHAR(100)  NOT NULL,
  name_plural        VARCHAR(100)  NULL,
  name_translations  JSON          NULL,
  icon               VARCHAR(60)   NULL,
  description        TEXT          NULL,
  is_active          TINYINT(1)    NOT NULL DEFAULT 1,
  sort_order         SMALLINT      NOT NULL DEFAULT 0,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_property_categories_code (code),
  UNIQUE KEY uq_property_categories_slug (slug),
  KEY idx_property_categories_parent (parent_id, is_active, sort_order),
  KEY idx_property_categories_country (country_id),
  CONSTRAINT fk_property_categories_parent  FOREIGN KEY (parent_id)  REFERENCES property_categories (id),
  CONSTRAINT fk_property_categories_country FOREIGN KEY (country_id) REFERENCES countries (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions autorisées par catégorie (héritées du parent si aucune ligne pour l'enfant)
CREATE TABLE category_transaction_types (
  category_id          INT UNSIGNED NOT NULL,
  transaction_type_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (category_id, transaction_type_id),
  KEY idx_ctt_transaction (transaction_type_id),
  CONSTRAINT fk_ctt_category    FOREIGN KEY (category_id)         REFERENCES property_categories (id) ON DELETE CASCADE,
  CONSTRAINT fk_ctt_transaction FOREIGN KEY (transaction_type_id) REFERENCES transaction_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sections du formulaire dynamique (général, résidentiel, terrain, commercial, juridique…)
CREATE TABLE attribute_groups (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  code               VARCHAR(40)   NOT NULL,
  name               VARCHAR(100)  NOT NULL,
  name_translations  JSON          NULL,
  sort_order         SMALLINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attribute_groups_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Critères dynamiques.
-- storage = 'column' : valeur rangée dans une colonne indexée de `properties` (column_name),
--                      pour les critères de recherche les plus sollicités ;
-- storage = 'eav'    : valeur rangée dans `property_attribute_values`.
CREATE TABLE property_attributes (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  group_id           INT UNSIGNED  NOT NULL,
  code               VARCHAR(60)   NOT NULL,
  name               VARCHAR(100)  NOT NULL,
  name_translations  JSON          NULL,
  input_type         ENUM('integer','decimal','text','boolean','select','multiselect','date','year') NOT NULL,
  unit               VARCHAR(20)   NULL COMMENT 'm², places…',
  storage            ENUM('eav','column') NOT NULL DEFAULT 'eav',
  column_name        VARCHAR(40)   NULL,
  min_value          DECIMAL(14,2) NULL,
  max_value          DECIMAL(14,2) NULL,
  help_text          VARCHAR(255)  NULL,
  is_filterable      TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Proposé dans les filtres de recherche',
  is_public          TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Affiché sur la fiche publique',
  is_active          TINYINT(1)    NOT NULL DEFAULT 1,
  sort_order         SMALLINT      NOT NULL DEFAULT 0,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_property_attributes_code (code),
  KEY idx_property_attributes_group (group_id, sort_order),
  CONSTRAINT fk_property_attributes_group FOREIGN KEY (group_id) REFERENCES attribute_groups (id),
  CONSTRAINT chk_property_attributes_storage CHECK (
    (storage = 'eav' AND column_name IS NULL) OR
    (storage = 'column' AND column_name IN ('living_area','land_area','rooms','bedrooms','bathrooms'))
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_attribute_options (
  id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  attribute_id        INT UNSIGNED  NOT NULL,
  code                VARCHAR(60)   NOT NULL,
  label               VARCHAR(120)  NOT NULL,
  label_translations  JSON          NULL,
  sort_order          SMALLINT      NOT NULL DEFAULT 0,
  is_active           TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attribute_options_code (attribute_id, code),
  CONSTRAINT fk_attribute_options_attribute FOREIGN KEY (attribute_id) REFERENCES property_attributes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Critères proposés pour une catégorie (les enfants héritent de ceux du parent)
CREATE TABLE category_attributes (
  category_id   INT UNSIGNED NOT NULL,
  attribute_id  INT UNSIGNED NOT NULL,
  is_required   TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order    SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (category_id, attribute_id),
  KEY idx_category_attributes_attribute (attribute_id),
  CONSTRAINT fk_category_attributes_category  FOREIGN KEY (category_id)  REFERENCES property_categories (id) ON DELETE CASCADE,
  CONSTRAINT fk_category_attributes_attribute FOREIGN KEY (attribute_id) REFERENCES property_attributes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Équipements (cases à cocher filtrables)
CREATE TABLE features (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  code               VARCHAR(60)   NOT NULL,
  name               VARCHAR(100)  NOT NULL,
  name_translations  JSON          NULL,
  feature_group      ENUM('comfort','security','outdoor','utilities','connectivity') NOT NULL,
  icon               VARCHAR(60)   NULL,
  is_filterable      TINYINT(1)    NOT NULL DEFAULT 1,
  is_active          TINYINT(1)    NOT NULL DEFAULT 1,
  sort_order         SMALLINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_features_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. ANNONCES
-- -----------------------------------------------------------------------------

CREATE TABLE properties (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference               VARCHAR(20)     NOT NULL COMMENT 'Référence publique (IAN-24531)',
  country_id              INT UNSIGNED    NOT NULL,
  source                  ENUM('agency','private_owner','platform') NOT NULL COMMENT 'Agence partenaire, particulier publié par l’admin, bien propre',
  agency_id               INT UNSIGNED    NULL,
  agent_user_id           INT UNSIGNED    NULL COMMENT 'Agent en charge',
  transaction_type_id     INT UNSIGNED    NOT NULL,
  category_id             INT UNSIGNED    NOT NULL,

  -- Contenu
  title                   VARCHAR(160)    NOT NULL,
  slug                    VARCHAR(190)    NOT NULL,
  description             MEDIUMTEXT      NOT NULL,
  internal_reference      VARCHAR(50)     NULL COMMENT 'Référence propre à l’agence',

  -- Prix
  price                   DECIMAL(15,2)   NULL COMMENT 'NULL = prix sur demande',
  currency_code           CHAR(3)         NOT NULL,
  price_period            ENUM('total','month','week','night','year') NOT NULL DEFAULT 'total',
  is_negotiable           TINYINT(1)      NOT NULL DEFAULT 0,
  charges                 DECIMAL(12,2)   NULL,
  agency_fee_percent      DECIMAL(5,2)    NULL,

  -- Localisation
  city_id                 INT UNSIGNED    NOT NULL,
  commune_id              INT UNSIGNED    NULL,
  district_id             INT UNSIGNED    NULL,
  address                 VARCHAR(255)    NULL,
  latitude                DECIMAL(10,7)   NULL,
  longitude               DECIMAL(10,7)   NULL,
  show_exact_location     TINYINT(1)      NOT NULL DEFAULT 0,

  -- Critères de recherche fréquents (pilotés par property_attributes.storage = 'column')
  living_area             DECIMAL(10,2)   NULL COMMENT 'Surface habitable / utile (m²)',
  land_area               DECIMAL(12,2)   NULL COMMENT 'Superficie du terrain (m²)',
  rooms                   SMALLINT UNSIGNED NULL,
  bedrooms                SMALLINT UNSIGNED NULL,
  bathrooms               SMALLINT UNSIGNED NULL,

  -- Disponibilité du bien (distincte du statut de publication)
  availability            ENUM('available','reserved','sold','rented') NOT NULL DEFAULT 'available',
  available_from          DATE            NULL,

  -- Médias externes
  video_url               VARCHAR(255)    NULL,
  virtual_tour_url        VARCHAR(255)    NULL,
  document_path           VARCHAR(255)    NULL COMMENT 'Plan ou document PDF',

  -- Contact affiché
  contact_name            VARCHAR(120)    NULL,
  contact_phone           VARCHAR(30)     NULL,
  contact_whatsapp        VARCHAR(30)     NULL,
  contact_email           VARCHAR(190)    NULL,

  -- Workflow de validation (cahier des charges §2.1)
  status                  ENUM('draft','pending','published','rejected','unpublished','archived','expired') NOT NULL DEFAULT 'pending',
  deactivated_by_partner  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Dépubliée par le partenaire lui-même (il peut la réactiver)',
  rejection_reason        TEXT            NULL,
  submitted_at            DATETIME        NULL,
  reviewed_by_user_id     INT UNSIGNED    NULL,
  reviewed_at             DATETIME        NULL,
  published_at            DATETIME        NULL,
  expires_at              DATETIME        NULL,
  expiry_reminder_sent_at DATETIME        NULL,
  archived_at             DATETIME        NULL,

  -- Mise en avant
  is_featured             TINYINT(1)      NOT NULL DEFAULT 0,
  featured_until          DATETIME        NULL,

  -- SEO (surcharges facultatives)
  meta_title              VARCHAR(190)    NULL,
  meta_description        VARCHAR(320)    NULL,

  -- Compteurs dénormalisés
  views_count             INT UNSIGNED    NOT NULL DEFAULT 0,
  leads_count             INT UNSIGNED    NOT NULL DEFAULT 0,

  created_by_user_id      INT UNSIGNED    NOT NULL,
  updated_by_user_id      INT UNSIGNED    NULL,
  created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at              DATETIME        NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_properties_reference (reference),
  KEY idx_properties_search (country_id, status, transaction_type_id, category_id, price),
  KEY idx_properties_location (country_id, status, city_id, commune_id, district_id),
  KEY idx_properties_featured (country_id, status, is_featured, published_at),
  KEY idx_properties_published (country_id, status, published_at),
  KEY idx_properties_areas (country_id, status, living_area, land_area),
  KEY idx_properties_bedrooms (country_id, status, bedrooms),
  KEY idx_properties_agency (agency_id, status, updated_at),
  KEY idx_properties_expiry (status, expires_at),
  KEY idx_properties_geo (latitude, longitude),
  FULLTEXT KEY ft_properties_text (title, description),
  CONSTRAINT fk_properties_country     FOREIGN KEY (country_id)          REFERENCES countries (id),
  CONSTRAINT fk_properties_agency      FOREIGN KEY (agency_id)           REFERENCES agencies (id),
  CONSTRAINT fk_properties_agent       FOREIGN KEY (agent_user_id)       REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_properties_transaction FOREIGN KEY (transaction_type_id) REFERENCES transaction_types (id),
  CONSTRAINT fk_properties_category    FOREIGN KEY (category_id)         REFERENCES property_categories (id),
  CONSTRAINT fk_properties_city        FOREIGN KEY (city_id)             REFERENCES cities (id),
  CONSTRAINT fk_properties_commune     FOREIGN KEY (commune_id)          REFERENCES communes (id),
  CONSTRAINT fk_properties_district    FOREIGN KEY (district_id)         REFERENCES districts (id),
  CONSTRAINT fk_properties_reviewed_by FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_properties_created_by  FOREIGN KEY (created_by_user_id)  REFERENCES users (id),
  CONSTRAINT fk_properties_updated_by  FOREIGN KEY (updated_by_user_id)  REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_properties_source CHECK (
    (source = 'agency' AND agency_id IS NOT NULL) OR
    (source IN ('private_owner','platform') AND agency_id IS NULL)
  ),
  CONSTRAINT chk_properties_rejection CHECK (status <> 'rejected' OR rejection_reason IS NOT NULL),
  CONSTRAINT chk_properties_price CHECK (price IS NULL OR price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données confidentielles : JAMAIS jointes dans les requêtes du site public
CREATE TABLE property_private_details (
  property_id       BIGINT UNSIGNED NOT NULL,
  owner_name        VARCHAR(150)    NULL COMMENT 'Propriétaire (bien de particulier)',
  owner_phone       VARCHAR(30)     NULL,
  owner_email       VARCHAR(190)    NULL,
  notary_name       VARCHAR(150)    NULL,
  notary_reference  VARCHAR(100)    NULL,
  internal_notes    TEXT            NULL,
  updated_at        DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (property_id),
  CONSTRAINT fk_private_details_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valeurs des critères EAV (une ligne par valeur ; plusieurs lignes pour un multiselect)
CREATE TABLE property_attribute_values (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  property_id      BIGINT UNSIGNED NOT NULL,
  attribute_id     INT UNSIGNED    NOT NULL,
  value_integer    BIGINT          NULL,
  value_decimal    DECIMAL(14,2)   NULL,
  value_text       VARCHAR(500)    NULL,
  value_boolean    TINYINT(1)      NULL,
  value_date       DATE            NULL,
  value_option_id  INT UNSIGNED    NULL,
  option_scope     INT UNSIGNED    AS (IFNULL(value_option_id, 0)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attribute_values (property_id, attribute_id, option_scope),
  KEY idx_attribute_values_option (attribute_id, value_option_id, property_id),
  KEY idx_attribute_values_integer (attribute_id, value_integer, property_id),
  KEY idx_attribute_values_boolean (attribute_id, value_boolean, property_id),
  CONSTRAINT fk_attribute_values_property  FOREIGN KEY (property_id)     REFERENCES properties (id) ON DELETE CASCADE,
  CONSTRAINT fk_attribute_values_attribute FOREIGN KEY (attribute_id)    REFERENCES property_attributes (id),
  CONSTRAINT fk_attribute_values_option    FOREIGN KEY (value_option_id) REFERENCES property_attribute_options (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_features (
  property_id  BIGINT UNSIGNED NOT NULL,
  feature_id   INT UNSIGNED    NOT NULL,
  PRIMARY KEY (property_id, feature_id),
  KEY idx_property_features_feature (feature_id, property_id),
  CONSTRAINT fk_property_features_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE CASCADE,
  CONSTRAINT fk_property_features_feature  FOREIGN KEY (feature_id)  REFERENCES features (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Galerie : chemin de base, les variantes (thumb, medium, large, .webp) suivent une convention de nommage
-- Révisions d'annonces publiées (migration 0002) : la version en ligne reste visible jusqu'à validation
CREATE TABLE property_revisions (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  property_id           BIGINT UNSIGNED NOT NULL,
  status                ENUM('pending','approved','rejected','superseded') NOT NULL DEFAULT 'pending',
  data                  JSON            NOT NULL COMMENT 'Instantané proposé : fields, attributes, features, images',
  rejection_reason      TEXT            NULL,
  submitted_by_user_id  INT UNSIGNED    NULL,
  reviewed_by_user_id   INT UNSIGNED    NULL,
  submitted_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at           DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_property_revisions_property (property_id, status),
  KEY idx_property_revisions_status (status, submitted_at),
  CONSTRAINT fk_property_revisions_property     FOREIGN KEY (property_id)          REFERENCES properties (id) ON DELETE CASCADE,
  CONSTRAINT fk_property_revisions_submitted_by FOREIGN KEY (submitted_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_property_revisions_reviewed_by  FOREIGN KEY (reviewed_by_user_id)  REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_property_revisions_rejection CHECK (status <> 'rejected' OR rejection_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_images (
  id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  property_id    BIGINT UNSIGNED  NOT NULL,
  revision_id    BIGINT UNSIGNED  NULL COMMENT 'Photo d’une révision en attente (non publique)',
  path           VARCHAR(255)     NOT NULL COMMENT 'uploads/ci/annonces/24531/7f3a9c… (sans suffixe de taille)',
  original_name  VARCHAR(255)     NULL,
  mime_type      VARCHAR(50)      NOT NULL,
  width          SMALLINT UNSIGNED NOT NULL,
  height         SMALLINT UNSIGNED NOT NULL,
  size_bytes     INT UNSIGNED     NOT NULL,
  alt_text       VARCHAR(190)     NULL,
  sort_order     SMALLINT         NOT NULL DEFAULT 0 COMMENT '0 = photo de couverture',
  created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_property_images_property (property_id, sort_order),
  KEY idx_property_images_revision (revision_id),
  CONSTRAINT fk_property_images_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE CASCADE,
  CONSTRAINT fk_property_images_revision FOREIGN KEY (revision_id) REFERENCES property_revisions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique du workflow (qui a soumis / validé / rejeté, avec motif)
CREATE TABLE property_status_history (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  property_id  BIGINT UNSIGNED NOT NULL,
  from_status  VARCHAR(20)     NULL,
  to_status    VARCHAR(20)     NOT NULL,
  reason       TEXT            NULL,
  user_id      INT UNSIGNED    NULL COMMENT 'NULL = action automatique (CRON)',
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_history_property (property_id, created_at),
  CONSTRAINT fk_status_history_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE CASCADE,
  CONSTRAINT fk_status_history_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Statistiques journalières par annonce (tableaux de bord admin et agence)
CREATE TABLE property_stats_daily (
  property_id      BIGINT UNSIGNED NOT NULL,
  stat_date        DATE            NOT NULL,
  views            INT UNSIGNED    NOT NULL DEFAULT 0,
  phone_clicks     INT UNSIGNED    NOT NULL DEFAULT 0,
  whatsapp_clicks  INT UNSIGNED    NOT NULL DEFAULT 0,
  shares           INT UNSIGNED    NOT NULL DEFAULT 0,
  leads            INT UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY (property_id, stat_date),
  KEY idx_property_stats_date (stat_date),
  CONSTRAINT fk_property_stats_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. RELATION CLIENT
-- -----------------------------------------------------------------------------

-- Messages reçus via les formulaires publics
CREATE TABLE leads (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id           INT UNSIGNED    NOT NULL,
  country_id        INT UNSIGNED    NOT NULL,
  type              ENUM('property_contact','agency_contact','general_contact','property_submission') NOT NULL
                    COMMENT 'property_submission = formulaire « Déposer un bien »',
  property_id       BIGINT UNSIGNED NULL,
  agency_id         INT UNSIGNED    NULL COMMENT 'Destinataire (agence de l’annonce ou agence contactée)',
  name              VARCHAR(150)    NOT NULL,
  email             VARCHAR(190)    NULL,
  phone             VARCHAR(30)     NULL,
  message           TEXT            NULL,
  payload           JSON            NULL COMMENT 'Détails du bien proposé (type, commune, prix souhaité…)',
  status            ENUM('new','read','in_progress','closed','spam') NOT NULL DEFAULT 'new',
  assigned_user_id  INT UNSIGNED    NULL,
  handled_at        DATETIME        NULL,
  consent_at        DATETIME        NOT NULL COMMENT 'Consentement au traitement des données',
  source_url        VARCHAR(255)    NULL,
  ip                VARBINARY(16)   NULL,
  user_agent        VARCHAR(255)    NULL,
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leads_country_status (country_id, status, created_at),
  KEY idx_leads_agency_status (agency_id, status, created_at),
  KEY idx_leads_property (property_id),
  KEY idx_leads_type (type, created_at),
  CONSTRAINT fk_leads_site     FOREIGN KEY (site_id)          REFERENCES sites (id),
  CONSTRAINT fk_leads_country  FOREIGN KEY (country_id)       REFERENCES countries (id),
  CONSTRAINT fk_leads_property FOREIGN KEY (property_id)      REFERENCES properties (id) ON DELETE SET NULL,
  CONSTRAINT fk_leads_agency   FOREIGN KEY (agency_id)        REFERENCES agencies (id) ON DELETE SET NULL,
  CONSTRAINT fk_leads_assigned FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_leads_contact CHECK (email IS NOT NULL OR phone IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demandes « Devenir partenaire » (avant création manuelle du compte agence)
CREATE TABLE partner_requests (
  id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  site_id             INT UNSIGNED  NOT NULL,
  country_id          INT UNSIGNED  NOT NULL,
  partner_type        ENUM('agency','developer','property_manager','other') NOT NULL DEFAULT 'agency',
  agency_name         VARCHAR(150)  NOT NULL COMMENT 'Nom commercial',
  legal_name          VARCHAR(190)  NULL COMMENT 'Raison sociale',
  legal_form          VARCHAR(60)   NULL,
  contact_name        VARCHAR(150)  NOT NULL,
  contact_role        VARCHAR(100)  NULL COMMENT 'Fonction du responsable',
  email               VARCHAR(190)  NOT NULL,
  phone               VARCHAR(30)   NOT NULL,
  company_email       VARCHAR(190)  NULL,
  company_phone       VARCHAR(30)   NULL,
  website             VARCHAR(255)  NULL,
  address             VARCHAR(255)  NULL,
  rccm                VARCHAR(60)   NULL,
  tax_id              VARCHAR(60)   NULL COMMENT 'Compte contribuable (NCC)',
  professional_card   VARCHAR(60)   NULL COMMENT 'Carte ou agrément professionnel',
  city_id             INT UNSIGNED  NULL,
  commune_id          INT UNSIGNED  NULL,
  listings_estimate   SMALLINT UNSIGNED NULL COMMENT 'Nombre d’annonces envisagé',
  years_active        SMALLINT UNSIGNED NULL COMMENT 'Années d’activité',
  message             TEXT          NULL,
  status              ENUM('new','contacted','approved','rejected') NOT NULL DEFAULT 'new',
  agency_id           INT UNSIGNED  NULL COMMENT 'Agence créée à partir de la demande',
  handled_by_user_id  INT UNSIGNED  NULL,
  handled_at          DATETIME      NULL,
  internal_notes      TEXT          NULL,
  consent_at          DATETIME      NOT NULL,
  ip                  VARBINARY(16) NULL,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_partner_requests_status (country_id, status, created_at),
  CONSTRAINT fk_partner_requests_site       FOREIGN KEY (site_id)            REFERENCES sites (id),
  CONSTRAINT fk_partner_requests_country    FOREIGN KEY (country_id)         REFERENCES countries (id),
  CONSTRAINT fk_partner_requests_city       FOREIGN KEY (city_id)            REFERENCES cities (id) ON DELETE SET NULL,
  CONSTRAINT fk_partner_requests_commune    FOREIGN KEY (commune_id)         REFERENCES communes (id) ON DELETE SET NULL,
  CONSTRAINT fk_partner_requests_agency     FOREIGN KEY (agency_id)          REFERENCES agencies (id) ON DELETE SET NULL,
  CONSTRAINT fk_partner_requests_handled_by FOREIGN KEY (handled_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE agencies
  ADD CONSTRAINT fk_agencies_partner_request FOREIGN KEY (partner_request_id) REFERENCES partner_requests (id) ON DELETE SET NULL;

-- Notifications du back-office (annonce à valider, nouveau contact…)
CREATE TABLE notifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED    NOT NULL,
  type        VARCHAR(50)     NOT NULL COMMENT 'property.submitted, lead.received…',
  title       VARCHAR(190)    NOT NULL,
  body        VARCHAR(500)    NULL,
  link_url    VARCHAR(255)    NULL,
  data        JSON            NULL,
  read_at     DATETIME        NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. CONTENU & SEO
-- -----------------------------------------------------------------------------

CREATE TABLE pages (
  id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  site_id             INT UNSIGNED  NOT NULL,
  code                VARCHAR(60)   NULL COMMENT 'Pages système : about, how_it_works, legal, terms, privacy…',
  slug                VARCHAR(190)  NOT NULL,
  locale              VARCHAR(10)   NOT NULL DEFAULT 'fr',
  title               VARCHAR(190)  NOT NULL,
  content             MEDIUMTEXT    NULL,
  meta_title          VARCHAR(190)  NULL,
  meta_description    VARCHAR(320)  NULL,
  is_published        TINYINT(1)    NOT NULL DEFAULT 0,
  updated_by_user_id  INT UNSIGNED  NULL,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_site_locale_slug (site_id, locale, slug),
  UNIQUE KEY uq_pages_site_locale_code (site_id, locale, code),
  CONSTRAINT fk_pages_site       FOREIGN KEY (site_id)            REFERENCES sites (id) ON DELETE CASCADE,
  CONSTRAINT fk_pages_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_posts (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  site_id           INT UNSIGNED  NOT NULL,
  slug              VARCHAR(190)  NOT NULL,
  locale            VARCHAR(10)   NOT NULL DEFAULT 'fr',
  title             VARCHAR(190)  NOT NULL,
  excerpt           VARCHAR(500)  NULL,
  content           MEDIUMTEXT    NOT NULL,
  cover_image_path  VARCHAR(255)  NULL,
  author_user_id    INT UNSIGNED  NULL,
  status            ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at      DATETIME      NULL,
  meta_title        VARCHAR(190)  NULL,
  meta_description  VARCHAR(320)  NULL,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_posts_site_locale_slug (site_id, locale, slug),
  KEY idx_blog_posts_published (site_id, status, published_at),
  CONSTRAINT fk_blog_posts_site   FOREIGN KEY (site_id)        REFERENCES sites (id) ON DELETE CASCADE,
  CONSTRAINT fk_blog_posts_author FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bannières publicitaires ET diapositives du hero d'accueil (placement = 'home_hero')
CREATE TABLE banners (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  site_id      INT UNSIGNED  NOT NULL,
  placement    VARCHAR(40)   NOT NULL COMMENT 'home_hero, home_middle, listing_sidebar…',
  title        VARCHAR(190)  NULL,
  subtitle     VARCHAR(255)  NULL,
  caption      VARCHAR(120)  NULL COMMENT 'Légende du lieu photographié (hero)',
  image_path   VARCHAR(255)  NOT NULL,
  link_url     VARCHAR(255)  NULL,
  starts_at    DATETIME      NULL,
  ends_at      DATETIME      NULL,
  is_active    TINYINT(1)    NOT NULL DEFAULT 1,
  sort_order   SMALLINT      NOT NULL DEFAULT 0,
  impressions  INT UNSIGNED  NOT NULL DEFAULT 0,
  clicks       INT UNSIGNED  NOT NULL DEFAULT 0,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_banners_placement (site_id, placement, is_active, sort_order),
  CONSTRAINT fk_banners_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Surcharges SEO par chemin (pages de listing, accueil…)
CREATE TABLE seo_meta (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  site_id           INT UNSIGNED  NOT NULL,
  path              VARCHAR(255)  NOT NULL COMMENT '/acheter/appartement/abidjan',
  meta_title        VARCHAR(190)  NULL,
  meta_description  VARCHAR(320)  NULL,
  og_image_path     VARCHAR(255)  NULL,
  intro_text        TEXT          NULL COMMENT 'Texte d’introduction SEO de la page',
  noindex           TINYINT(1)    NOT NULL DEFAULT 0,
  updated_at        DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seo_meta_site_path (site_id, path),
  CONSTRAINT fk_seo_meta_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE redirects (
  id           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  site_id      INT UNSIGNED       NOT NULL,
  source_path  VARCHAR(255)       NOT NULL,
  target_path  VARCHAR(255)       NOT NULL,
  http_code    SMALLINT UNSIGNED  NOT NULL DEFAULT 301,
  is_active    TINYINT(1)         NOT NULL DEFAULT 1,
  hits         INT UNSIGNED       NOT NULL DEFAULT 0,
  last_hit_at  DATETIME           NULL,
  created_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redirects_site_source (site_id, source_path),
  CONSTRAINT fk_redirects_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
  CONSTRAINT chk_redirects_code CHECK (http_code IN (301, 302, 307, 308, 410))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. JOURNAL D'ACTIVITÉ
-- -----------------------------------------------------------------------------

CREATE TABLE activity_logs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED    NULL COMMENT 'NULL = système',
  country_id   INT UNSIGNED    NULL,
  action       VARCHAR(60)     NOT NULL COMMENT 'property.approved, agency.created, user.login…',
  entity_type  VARCHAR(40)     NULL,
  entity_id    BIGINT UNSIGNED NULL,
  description  VARCHAR(255)    NULL,
  changes      JSON            NULL COMMENT '{"before": {…}, "after": {…}}',
  ip           VARBINARY(16)   NULL,
  user_agent   VARCHAR(255)    NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_logs_entity (entity_type, entity_id, created_at),
  KEY idx_activity_logs_user (user_id, created_at),
  KEY idx_activity_logs_country (country_id, created_at),
  KEY idx_activity_logs_action (action, created_at),
  CONSTRAINT fk_activity_logs_user    FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_logs_country FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Weblogy intermédiaire exclusif (migration 0008) : comptes particuliers, pièces des dossiers
-- de partenariat, biens confiés. Fichiers stockés dans storage/private, jamais servis directement.
-- -----------------------------------------------------------------------------

CREATE TABLE email_verifications (
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL COMMENT 'SHA-256 du jeton envoyé par email',
  expires_at  DATETIME     NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_email_verifications_token (token_hash),
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partner_request_files (
  id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  partner_request_id  INT UNSIGNED  NOT NULL,
  kind                ENUM('rccm','tax','license','identity','other') NOT NULL,
  path                VARCHAR(255)  NOT NULL COMMENT 'Relatif à storage/private (jamais servi directement)',
  original_name       VARCHAR(190)  NOT NULL,
  mime                VARCHAR(100)  NOT NULL,
  size                INT UNSIGNED  NOT NULL,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_partner_request_files_request (partner_request_id),
  CONSTRAINT fk_partner_request_files_request FOREIGN KEY (partner_request_id) REFERENCES partner_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_submissions (
  id                   BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  site_id              INT UNSIGNED     NOT NULL,
  country_id           INT UNSIGNED     NOT NULL,
  user_id              INT UNSIGNED     NOT NULL COMMENT 'Particulier (role owner)',
  transaction_type_id  INT UNSIGNED     NOT NULL,
  category_id          INT UNSIGNED     NOT NULL,
  city_id              INT UNSIGNED     NOT NULL,
  commune_id           INT UNSIGNED     NULL,
  district_id          INT UNSIGNED     NULL,
  address              VARCHAR(255)     NULL COMMENT 'Adresse ou repère : jamais publié',
  description          TEXT             NOT NULL,
  price                DECIMAL(15,2)    NULL,
  price_period         ENUM('total','month','week','night','year') NOT NULL DEFAULT 'total',
  is_negotiable        TINYINT(1)       NOT NULL DEFAULT 0,
  conditions           TEXT             NULL COMMENT 'Caution, avance, charges, disponibilité…',
  living_area          DECIMAL(10,2)    NULL,
  land_area            DECIMAL(12,2)    NULL,
  rooms                SMALLINT UNSIGNED NULL,
  bedrooms             SMALLINT UNSIGNED NULL,
  bathrooms            SMALLINT UNSIGNED NULL,
  title_type           VARCHAR(60)      NULL COMMENT 'Option du critère title_type (TF, ACD…)',
  status               ENUM('submitted','in_review','published','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
  property_id          BIGINT UNSIGNED  NULL COMMENT 'Annonce créée par l’équipe à partir du dossier',
  rejection_reason     TEXT             NULL,
  internal_notes       TEXT             NULL,
  handled_by_user_id   INT UNSIGNED     NULL,
  handled_at           DATETIME         NULL,
  consent_at           DATETIME         NOT NULL COMMENT 'Acceptation de confier la commercialisation à Weblogy',
  ip                   VARBINARY(16)    NULL,
  created_at           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_property_submissions_country (country_id, status, created_at),
  KEY idx_property_submissions_user (user_id, created_at),
  CONSTRAINT fk_property_submissions_site        FOREIGN KEY (site_id)             REFERENCES sites (id),
  CONSTRAINT fk_property_submissions_country     FOREIGN KEY (country_id)          REFERENCES countries (id),
  CONSTRAINT fk_property_submissions_user        FOREIGN KEY (user_id)             REFERENCES users (id),
  CONSTRAINT fk_property_submissions_transaction FOREIGN KEY (transaction_type_id) REFERENCES transaction_types (id),
  CONSTRAINT fk_property_submissions_category    FOREIGN KEY (category_id)         REFERENCES property_categories (id),
  CONSTRAINT fk_property_submissions_city        FOREIGN KEY (city_id)             REFERENCES cities (id),
  CONSTRAINT fk_property_submissions_commune     FOREIGN KEY (commune_id)          REFERENCES communes (id) ON DELETE SET NULL,
  CONSTRAINT fk_property_submissions_district    FOREIGN KEY (district_id)         REFERENCES districts (id) ON DELETE SET NULL,
  CONSTRAINT fk_property_submissions_property    FOREIGN KEY (property_id)         REFERENCES properties (id) ON DELETE SET NULL,
  CONSTRAINT fk_property_submissions_handled_by  FOREIGN KEY (handled_by_user_id)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_submission_files (
  id              BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  submission_id   BIGINT UNSIGNED   NOT NULL,
  kind            ENUM('photo','document') NOT NULL,
  path            VARCHAR(255)      NOT NULL COMMENT 'Relatif à storage/private (jamais servi directement)',
  original_name   VARCHAR(190)      NOT NULL,
  mime            VARCHAR(100)      NOT NULL,
  size            INT UNSIGNED      NOT NULL,
  sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_property_submission_files_submission (submission_id, kind, sort_order),
  CONSTRAINT fk_property_submission_files_submission FOREIGN KEY (submission_id) REFERENCES property_submissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
