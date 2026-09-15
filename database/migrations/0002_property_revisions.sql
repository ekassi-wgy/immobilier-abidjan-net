-- =============================================================================
-- 0002 — Révisions d'annonces publiées (lot 1.6)
--
-- Décision client (15/09/2026) : quand une agence modifie une annonce publiée, la version en ligne reste
-- visible ; la modification est enregistrée comme révision « en attente » et ne remplace la version publiée
-- qu'après validation par un administrateur (ou est rejetée avec motif).
--
-- - property_revisions.data : instantané JSON complet proposé (champs, critères, équipements, photos) ;
-- - property_images.revision_id : photo ajoutée par une révision en attente, JAMAIS affichée sur le site public
--   tant que la révision n'est pas approuvée (requêtes publiques : revision_id IS NULL).
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS property_revisions (
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

-- Photos proposées par une révision en attente (idempotent : ajout seulement si la colonne n'existe pas)
SET @has_column = (SELECT COUNT(*) FROM information_schema.columns
                   WHERE table_schema = DATABASE() AND table_name = 'property_images' AND column_name = 'revision_id');
SET @sql = IF(@has_column = 0,
  'ALTER TABLE property_images
     ADD COLUMN revision_id BIGINT UNSIGNED NULL COMMENT ''Photo d’une révision en attente (non publique)'' AFTER property_id,
     ADD KEY idx_property_images_revision (revision_id),
     ADD CONSTRAINT fk_property_images_revision FOREIGN KEY (revision_id) REFERENCES property_revisions (id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;
