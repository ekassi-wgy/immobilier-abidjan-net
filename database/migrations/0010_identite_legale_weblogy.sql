-- =============================================================================
-- 0010 — Identité légale de l'éditeur : Weblogy Tech S.A
--
-- Informations reprises du cachet de la société transmis par le client (17/09/2026) :
--  - dénomination : WEBLOGY TECH S.A (société anonyme) ;
--  - adresse postale : BP 652 Grand-Bassam ;
--  - RCCM : CI-BAS-01-2010-B12-01601 ;
--  - compte contribuable (NCC) : 1020168 E ;
--  - capital social : 10 000 000 FCFA (communiqué par le client) ;
--  - directeur de la publication : M. Robert KRA (communiqué par le client) ;
--  - hébergeur : Weblogy Tech S.A (communiqué par le client) ;
--  - régime d'imposition : réel normal (non repris : ce n'est pas une mention du site).
--
-- Reste à compléter : autorisation
-- d'exercer l'intermédiation immobilière.
--
-- Remplacements ciblés sur les textes de la migration 0009 : une page déjà retouchée dans le
-- back-office garde ses modifications, et rejouer la migration ne change plus rien.
-- =============================================================================

UPDATE pages SET
  content = REPLACE(REPLACE(REPLACE(REPLACE(content,
    'est édité par <strong>Weblogy</strong>, également éditeur d’Abidjan.net.',
    'est édité par <strong>Weblogy Tech S.A</strong> (« Weblogy »), également éditeur d’Abidjan.net.'),
    '<li><strong>Siège social :</strong> Rue Washington Booker, Cocody-Ambassades, 01 BP 12324 01 Abidjan, Côte d’Ivoire</li>',
    '<li><strong>Adresse postale :</strong> BP 652 Grand-Bassam, Côte d’Ivoire</li>\n<li><strong>Bureaux :</strong> Rue Washington Booker, Cocody-Ambassades, 01 BP 12324 01 Abidjan, Côte d’Ivoire</li>'),
    '<li><strong>Forme juridique et capital social :</strong> à compléter avant la mise en ligne</li>',
    '<li><strong>Forme juridique :</strong> société anonyme (S.A)</li>\n<li><strong>Capital social :</strong> 10&nbsp;000&nbsp;000 FCFA</li>'),
    '<li><strong>Registre du commerce (RCCM) :</strong> à compléter avant la mise en ligne</li>',
    '<li><strong>Registre du commerce et du crédit mobilier (RCCM) :</strong> CI-BAS-01-2010-B12-01601</li>\n<li><strong>Compte contribuable (NCC) :</strong> 1020168 E</li>'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'mentions-legales'
  AND content LIKE '%à compléter avant la mise en ligne</li>%'
  AND content NOT LIKE '%CI-BAS-01-2010-B12-01601%';

UPDATE pages SET
  content = REPLACE(content,
    '<p>Weblogy — Rue Washington Booker',
    '<p>Weblogy Tech S.A (RCCM CI-BAS-01-2010-B12-01601) — Rue Washington Booker'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'politique-de-confidentialite'
  AND content LIKE '%<p>Weblogy — Rue Washington Booker%';

-- Base où la première version de cette migration a déjà écrit « à compléter » pour le capital.
UPDATE pages SET
  content = REPLACE(content,
    '<li><strong>Capital social :</strong> à compléter avant la mise en ligne</li>',
    '<li><strong>Capital social :</strong> 10&nbsp;000&nbsp;000 FCFA</li>'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'mentions-legales'
  AND content LIKE '%<li><strong>Capital social :</strong> à compléter avant la mise en ligne</li>%';

-- Directeur de la publication (communiqué par le client).
UPDATE pages SET
  content = REPLACE(content,
    '<li><strong>Directeur de la publication :</strong> à compléter avant la mise en ligne</li>',
    '<li><strong>Directeur de la publication :</strong> M. Robert KRA</li>'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'mentions-legales'
  AND content LIKE '%<li><strong>Directeur de la publication :</strong> à compléter avant la mise en ligne</li>%';

-- Hébergeur : Weblogy Tech S.A (communiqué par le client).
UPDATE pages SET
  content = REPLACE(content,
    '<p>Le site est hébergé sur une infrastructure mutualisée administrée par Weblogy. Les coordonnées complètes de l’hébergeur sont à compléter avant la mise en ligne.</p>',
    '<p>Le site est hébergé par <strong>Weblogy Tech S.A</strong>, société anonyme au capital de 10&nbsp;000&nbsp;000 FCFA — BP 652 Grand-Bassam, Côte d’Ivoire — bureaux : Rue Washington Booker, Cocody-Ambassades, Abidjan — téléphone : +225 05 64 00 00 80 — courriel : <a href="mailto:info@weblogy.com">info@weblogy.com</a>.</p>'),
  updated_at = UTC_TIMESTAMP()
WHERE slug = 'mentions-legales'
  AND content LIKE '%Les coordonnées complètes de l’hébergeur sont à compléter avant la mise en ligne.</p>%';
