-- =============================================================================
-- 0008 — Weblogy intermédiaire exclusif
--
-- Nouveau modèle métier : Partenaire → Weblogy → Prospect, et Particulier → Weblogy → annonce.
--
--  1. Comptes particuliers : rôle `owner` dans `users` (même authentification, même hachage,
--     même blocage des tentatives que le back-office), adresse email vérifiée avant de pouvoir
--     confier un bien (`email_verified_at`, table `email_verifications`). Un particulier n'a
--     jamais accès à /cmsadmin : il dispose d'un espace sur le site public (/mon-espace).
--  2. Annonces : statut `draft` (brouillon d'un partenaire, jamais public ni soumis) et
--     `deactivated_by_partner` (un partenaire ne réactive que ce qu'il a lui-même désactivé,
--     jamais une annonce dépubliée par Weblogy).
--  3. Partenaires : type de professionnel (agence, promoteur, gestionnaire, autre) et forme
--     juridique ; le dossier de partenariat recueille l'identité légale et des pièces
--     justificatives (`partner_request_files`, stockées hors du dossier public).
--  4. Biens confiés par les particuliers : `property_submissions` et leurs photos et documents
--     (`property_submission_files`, hors du dossier public). L'équipe convertit un dossier en
--     annonce (`property_id`).
--  5. Paramètre `workflow.auto_publish_partner` : publication directe des annonces des
--     partenaires validés. Faux par défaut : chaque annonce reste soumise à la validation.
--
-- Idempotente : peut être rejouée sans effet. Testée sur MySQL 8.0.40.
-- =============================================================================

DROP PROCEDURE IF EXISTS im_add_column;
DELIMITER //
CREATE PROCEDURE im_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN definition TEXT)
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = col) = 0 THEN
    SET @ddl := CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', definition);
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END //
DELIMITER ;

-- 1. Comptes particuliers -----------------------------------------------------------------

ALTER TABLE users
  MODIFY role ENUM('super_admin','country_admin','agency_owner','agency_agent','owner') NOT NULL
  COMMENT 'owner = particulier qui confie un bien (espace public, jamais /cmsadmin)';

CALL im_add_column('users', 'email_verified_at', 'DATETIME NULL COMMENT ''Adresse confirmée (obligatoire pour un particulier avant de confier un bien)'' AFTER email');

-- Les comptes internes et partenaires, créés par invitation, ont déjà prouvé leur adresse.
UPDATE users SET email_verified_at = COALESCE(password_changed_at, last_login_at, created_at)
WHERE role <> 'owner' AND email_verified_at IS NULL;

SET @has_check := (SELECT COUNT(*) FROM information_schema.table_constraints
                   WHERE table_schema = DATABASE() AND table_name = 'users' AND constraint_name = 'chk_users_scope');
SET @ddl := IF(@has_check > 0, 'ALTER TABLE users DROP CONSTRAINT chk_users_scope', 'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE users ADD CONSTRAINT chk_users_scope CHECK (
  (role = 'super_admin'   AND agency_id IS NULL) OR
  (role = 'country_admin' AND agency_id IS NULL AND country_id IS NOT NULL) OR
  (role IN ('agency_owner','agency_agent') AND agency_id IS NOT NULL AND country_id IS NOT NULL) OR
  (role = 'owner'         AND agency_id IS NULL AND country_id IS NOT NULL)
);

CREATE TABLE IF NOT EXISTS email_verifications (
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL COMMENT 'SHA-256 du jeton envoyé par email',
  expires_at  DATETIME     NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_email_verifications_token (token_hash),
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Annonces -------------------------------------------------------------------------------

ALTER TABLE properties
  MODIFY status ENUM('draft','pending','published','rejected','unpublished','archived','expired') NOT NULL DEFAULT 'pending';

CALL im_add_column('properties', 'deactivated_by_partner', 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Dépubliée par le partenaire lui-même (il peut la réactiver)'' AFTER status');

-- 3. Partenaires ---------------------------------------------------------------------------

CALL im_add_column('agencies', 'partner_type', 'ENUM(''agency'',''developer'',''property_manager'',''other'') NOT NULL DEFAULT ''agency'' COMMENT ''Agence, promoteur, gestionnaire, autre professionnel'' AFTER slug');
CALL im_add_column('agencies', 'legal_form', 'VARCHAR(60) NULL COMMENT ''SARL, SA, SAS, entreprise individuelle…'' AFTER legal_name');

CALL im_add_column('partner_requests', 'partner_type', 'ENUM(''agency'',''developer'',''property_manager'',''other'') NOT NULL DEFAULT ''agency'' AFTER country_id');
CALL im_add_column('partner_requests', 'legal_name', 'VARCHAR(190) NULL COMMENT ''Raison sociale'' AFTER agency_name');
CALL im_add_column('partner_requests', 'legal_form', 'VARCHAR(60) NULL AFTER legal_name');
CALL im_add_column('partner_requests', 'tax_id', 'VARCHAR(60) NULL COMMENT ''Compte contribuable (NCC)'' AFTER rccm');
CALL im_add_column('partner_requests', 'professional_card', 'VARCHAR(60) NULL COMMENT ''Carte ou agrément professionnel'' AFTER tax_id');
CALL im_add_column('partner_requests', 'company_email', 'VARCHAR(190) NULL AFTER phone');
CALL im_add_column('partner_requests', 'company_phone', 'VARCHAR(30) NULL AFTER company_email');
CALL im_add_column('partner_requests', 'website', 'VARCHAR(255) NULL AFTER company_phone');
CALL im_add_column('partner_requests', 'address', 'VARCHAR(255) NULL AFTER website');
CALL im_add_column('partner_requests', 'contact_role', 'VARCHAR(100) NULL COMMENT ''Fonction du responsable'' AFTER contact_name');
CALL im_add_column('partner_requests', 'years_active', 'SMALLINT UNSIGNED NULL COMMENT ''Années d’activité'' AFTER listings_estimate');

CREATE TABLE IF NOT EXISTS partner_request_files (
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

-- 4. Biens confiés par les particuliers ----------------------------------------------------

CREATE TABLE IF NOT EXISTS property_submissions (
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

CREATE TABLE IF NOT EXISTS property_submission_files (
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

-- 5. Paramètre de publication des partenaires ----------------------------------------------

INSERT INTO settings (site_id, setting_key, value, description)
SELECT NULL, 'workflow.auto_publish_partner', 'false', 'Annonces des partenaires validés publiées sans validation par Weblogy'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE site_id IS NULL AND setting_key = 'workflow.auto_publish_partner');

DROP PROCEDURE IF EXISTS im_add_column;
