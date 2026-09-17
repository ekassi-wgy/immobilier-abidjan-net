<?php

declare(strict_types=1);

/**
 * Données de démonstration : un aperçu réaliste du site avant l'ouverture, en local comme en ligne.
 *
 * Crée, sur le site d'un pays (CI par défaut) :
 *  - 6 partenaires fictifs (vitrine « Nos partenaires ») ;
 *  - 22 annonces publiées couvrant les cinq transactions, les familles de biens et plusieurs villes,
 *    avec photos (photos libres de `public/assets/img/placeholder`, voir CREDITS.md), critères,
 *    équipements et coordonnées ;
 *  - des demandes de contact, deux dossiers de partenariat et 30 jours de statistiques d'audience
 *    (tableau de bord du back-office) ;
 *  - 3 actualités avec image de couverture.
 *
 * Tant que ces données existent, le site est en « mode démonstration » (paramètre `demo.active`) :
 * pastille « Aperçu » visible, toutes les pages en noindex, robots.txt fermé, aucune mesure
 * d'audience. Aucun email n'est envoyé, aucun compte n'est créé (adresses en @demo.invalid).
 *
 * Tout ce qui est créé est inscrit dans le registre `demo.registry` : la purge
 * (`php bin/reset-before-launch.php`) supprime exactement ces éléments, et rien d'autre.
 *
 * Usage : php bin/seed-demo.php [--country=CI] [--yes]
 *         --yes : obligatoire si APP_ENV=production (site en ligne avant ouverture)
 */

use App\Core\App;
use App\Support\Str;

/** @var App $app */
$app = require __DIR__ . '/../app/bootstrap.php';

$options = getopt('', ['country:', 'yes', 'help']);
if (isset($options['help'])) {
    echo "Usage : php bin/seed-demo.php [--country=CI] [--yes]\n";
    exit(0);
}

$db = $app->db();
$iso = strtoupper((string) ($options['country'] ?? 'CI'));

$fail = static function (string $message): never {
    fwrite(STDERR, "✗ {$message}\n");
    exit(1);
};

if ($app->config->get('app.env') === 'production' && !isset($options['yes'])) {
    $fail("APP_ENV=production : relancer avec --yes pour approvisionner le site en ligne (avant ouverture uniquement).");
}

$existing = $db->scalar("SELECT value FROM settings WHERE site_id IS NULL AND setting_key = 'demo.registry'");
if ($existing !== null && $existing !== 'null') {
    $fail('Des données de démonstration existent déjà. Les purger d\'abord : php bin/reset-before-launch.php --confirm');
}

$country = $db->selectOne('SELECT id, iso2, currency_code FROM countries WHERE iso2 = :iso', ['iso' => $iso]) ?? $fail("Pays {$iso} introuvable.");
$countryId = (int) $country['id'];
$site = $db->selectOne("SELECT id FROM sites WHERE country_id = :country AND status <> 'disabled' ORDER BY id LIMIT 1", ['country' => $countryId]) ?? $fail("Aucun site actif pour {$iso}.");
$siteId = (int) $site['id'];
$author = $db->scalar("SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1") ?? $fail('Aucun Super Admin actif : créer un compte avec bin/create-user.php.');
$authorId = (int) $author;
$prefix = (string) ($app->settings()->get('listing.reference_prefix', 'IAN'));
$lifetime = (int) $app->settings()->get('listing.lifetime_days', 90);
$isoDir = strtolower($iso);

mt_srand(2026);

// -- Référentiels (lus par code : aucun identifiant en dur) --------------------------------------

$ids = static function (string $sql, string $key = 'code') use ($db): array {
    $map = [];
    foreach ($db->select($sql) as $row) {
        $map[(string) $row[$key]] = (int) $row['id'];
    }

    return $map;
};
$categories = $ids('SELECT id, code FROM property_categories');
$transactions = $ids('SELECT id, code FROM transaction_types');
$features = $ids('SELECT id, code FROM features');
$attributes = $ids('SELECT id, code FROM property_attributes');
$attributeOptions = [];
foreach ($db->select('SELECT o.id, o.code, a.code AS attribute FROM property_attribute_options o JOIN property_attributes a ON a.id = o.attribute_id') as $row) {
    $attributeOptions[$row['attribute'] . '.' . $row['code']] = (int) $row['id'];
}

$place = static function (string $city, string $commune, ?string $district = null) use ($db, $countryId, $fail): array {
    $row = $db->selectOne(
        'SELECT c.id AS city_id, m.id AS commune_id, m.latitude, m.longitude
         FROM cities c JOIN communes m ON m.city_id = c.id
         WHERE c.country_id = :country AND c.name = :city AND m.name = :commune',
        ['country' => $countryId, 'city' => $city, 'commune' => $commune]
    ) ?? $fail("Lieu introuvable : {$city} / {$commune}");
    $districtId = $district !== null
        ? $db->scalar('SELECT id FROM districts WHERE commune_id = :commune AND name = :name', ['commune' => $row['commune_id'], 'name' => $district])
        : null;

    return [
        'city_id' => (int) $row['city_id'],
        'commune_id' => (int) $row['commune_id'],
        'district_id' => $districtId !== null ? (int) $districtId : null,
        'lat' => (float) $row['latitude'],
        'lng' => (float) $row['longitude'],
    ];
};

// -- Photos : chaque source est traitée une fois (400/800/1600), puis copiée par annonce ---------

$placeholder = APP_ROOT . '/public/assets/img/placeholder';
$sources = [
    'villa-piscine' => $placeholder . '/properties/villa-piscine-palmiers-960.jpg',
    'villa-escalier' => $placeholder . '/properties/villa-escalier-piscine-960.jpg',
    'piscine' => $placeholder . '/properties/piscine-debordement-960.jpg',
    'salon-lumineux' => $placeholder . '/properties/salon-lumineux-960.jpg',
    'salon-contemporain' => $placeholder . '/properties/salon-contemporain-960.jpg',
    'salon-baie' => $placeholder . '/properties/salon-baie-vitree-960.jpg',
    'cuisine' => $placeholder . '/properties/cuisine-ouverte-960.jpg',
    'tour' => $placeholder . '/properties/plateau-tour-affaires-960.jpg',
    'plateau-aerien' => $placeholder . '/properties/plateau-vue-aerienne-960.jpg',
    'plateau-pont' => $placeholder . '/hero/plateau-pont-ado-1920.jpg',
    'lagune' => $placeholder . '/hero/abidjan-lagune-ebrie-1920.jpg',
    'coucher-soleil' => $placeholder . '/hero/coucher-soleil-lagune-ebrie-1920.jpg',
    'riviera' => $placeholder . '/hero/riviera-golf-coproprietes-1920.jpg',
];
$images = $app->images();
$processed = [];
$photo = static function (string $key) use (&$processed, $sources, $images, $isoDir): array {
    return $processed[$key] ??= $images->storeVariants(['tmp_name' => $sources[$key]], $isoDir . '/tmp/demo');
};

$registry = ['seeded_at' => gmdate('Y-m-d H:i:s'), 'country_id' => $countryId, 'site_id' => $siteId, 'agencies' => [], 'properties' => [], 'leads' => [], 'partner_requests' => [], 'posts' => [], 'files' => []];

$now = time();
$date = static fn (int $daysAgo, int $hour = 10): string => gmdate('Y-m-d H:i:s', $now - $daysAgo * 86400 + ($hour - 12) * 3600 + mt_rand(0, 3000));

// -- Partenaires -------------------------------------------------------------------------------

$partners = [
    'lagune' => ['Lagune Immobilier', 'agency', 'Abidjan', 'Marcory', 'SARL', 1, 1, ['Marcory', 'Koumassi', 'Treichville'],
        'Agence familiale installée à Marcory depuis 2009 : vente et location d’appartements dans le sud d’Abidjan.'],
    'prestige' => ['Prestige Habitat Cocody', 'agency', 'Abidjan', 'Cocody', 'SA', 1, 1, ['Cocody', 'Bingerville'],
        'Villas et résidences haut de gamme à Cocody, Riviera et Bingerville.'],
    'plateau' => ['Plateau Business Realty', 'agency', 'Abidjan', 'Plateau', 'SARL', 1, 0, ['Plateau', 'Marcory', 'Koumassi'],
        'Immobilier d’entreprise : bureaux, locaux commerciaux et entrepôts.'],
    'ivoire' => ['Ivoire Résidences Promotion', 'developer', 'Abidjan', 'Cocody', 'SAS', 1, 1, ['Cocody', 'Bingerville', 'Songon'],
        'Promoteur de programmes résidentiels neufs à l’est d’Abidjan.'],
    'bassam' => ['Bassam Terrains & Villas', 'agency', 'Grand-Bassam', 'Grand-Bassam', 'SARL', 0, 0, ['Grand-Bassam', 'Assinie-Mafia'],
        'Terrains et maisons de bord de mer entre Grand-Bassam et Assinie.'],
    'atlantique' => ['Atlantique Gestion Locative', 'property_manager', 'Abidjan', 'Yopougon', 'SARL', 1, 0, ['Yopougon', 'Songon', 'Attécoubé'],
        'Gestion locative et location de logements à Yopougon et Songon.'],
];
$agencyIds = [];
foreach ($partners as $key => [$name, $type, $city, $commune, $legalForm, $verified, $featured, $zones, $description]) {
    $geo = $place($city, $commune);
    $id = $db->insert('agencies', [
        'country_id' => $countryId,
        'name' => $name,
        'slug' => 'demo-' . $key,
        'partner_type' => $type,
        'legal_name' => $name . ' ' . $legalForm,
        'legal_form' => $legalForm,
        'rccm' => 'DEMO-RCCM-' . strtoupper($key),
        'description' => $description,
        'email' => 'contact.' . $key . '@demo.invalid',
        'phone' => '+225 07 00 00 ' . sprintf('%02d %02d', mt_rand(10, 99), mt_rand(10, 99)),
        'city_id' => $geo['city_id'],
        'commune_id' => $geo['commune_id'],
        'status' => 'active',
        'is_verified' => $verified,
        'verified_at' => $verified ? $date(200) : null,
        'is_featured' => $featured,
        'created_by_user_id' => $authorId,
        'created_at' => $date(220),
    ]);
    foreach ($zones as $zone) {
        $zoneId = $db->scalar('SELECT m.id FROM communes m JOIN cities c ON c.id = m.city_id WHERE c.country_id = :country AND m.name = :name', ['country' => $countryId, 'name' => $zone]);
        if ($zoneId !== null) {
            $db->execute('INSERT IGNORE INTO agency_zones (agency_id, commune_id) VALUES (:agency, :commune)', ['agency' => $id, 'commune' => $zoneId]);
        }
    }
    $agencyIds[$key] = $id;
    $registry['agencies'][] = $id;
}

// -- Annonces ----------------------------------------------------------------------------------
// [partenaire|null (particulier), transaction, catégorie, titre, lieu, prix, période, colonnes, critères EAV, équipements, photos, à la une, jours depuis publication, description]

$listings = [
    ['prestige', 'sale', 'villa', 'Villa contemporaine 6 pièces avec piscine', ['Abidjan', 'Cocody', 'Riviera Golf'], 450000000, 'total',
        ['living_area' => 380, 'land_area' => 800, 'rooms' => 6, 'bedrooms' => 5, 'bathrooms' => 5],
        ['standing' => 'high', 'condition' => 'good', 'title_type' => 'tf', 'parking_spaces' => 3, 'construction_year' => 2019],
        ['pool', 'garden', 'garage', 'security_guard', 'air_conditioning', 'generator', 'equipped_kitchen'], ['villa-piscine', 'salon-lumineux', 'cuisine', 'piscine', 'salon-contemporain'], true, 3,
        "Au cœur de la Riviera Golf, cette villa contemporaine de 380 m² habitables se déploie sur un terrain arboré de 800 m².\n\nLe rez-de-chaussée s’ouvre sur un double séjour baigné de lumière, prolongé par une terrasse couverte et une piscine. La cuisine est entièrement équipée. À l’étage, cinq chambres climatisées dont une suite parentale avec dressing et salle de bain.\n\nGardiennage, groupe électrogène, garage pour trois véhicules. Titre foncier."],
    ['lagune', 'sale', 'apartment', 'Appartement 4 pièces vue lagune', ['Abidjan', 'Marcory', 'Zone 4'], 165000000, 'total',
        ['living_area' => 180, 'rooms' => 4, 'bedrooms' => 3, 'bathrooms' => 3],
        ['standing' => 'high', 'condition' => 'good', 'title_type' => 'acd', 'apartment_layout' => 'simplex', 'floor' => 6, 'building_floors' => 9, 'parking_spaces' => 2],
        ['elevator', 'air_conditioning', 'security_guard', 'generator', 'equipped_kitchen'], ['salon-baie', 'cuisine', 'salon-contemporain', 'lagune'], true, 5,
        "En étage élevé d’une résidence sécurisée de la Zone 4, appartement traversant de 180 m² avec vue dégagée sur la lagune Ébrié.\n\nSéjour de 60 m² ouvert sur un balcon filant, cuisine équipée, trois chambres avec placards dont une suite. Deux places de parking en sous-sol.\n\nAscenseur, groupe électrogène et gardiennage 24 h/24."],
    ['ivoire', 'sale', 'apartment', 'Duplex neuf 5 pièces avec terrasse', ['Abidjan', 'Cocody', 'Angré'], 210000000, 'total',
        ['living_area' => 240, 'rooms' => 5, 'bedrooms' => 4, 'bathrooms' => 4],
        ['standing' => 'high', 'condition' => 'new', 'title_type' => 'acd', 'apartment_layout' => 'duplex', 'floor' => 3, 'building_floors' => 4, 'parking_spaces' => 2],
        ['terrace', 'elevator', 'air_conditioning', 'access_control', 'fiber_optic'], ['salon-contemporain', 'cuisine', 'salon-lumineux', 'riviera'], true, 8,
        "Dernier duplex disponible dans une résidence neuve d’Angré, livrée cette année.\n\nAu premier niveau : séjour, cuisine ouverte et une chambre. Au second : trois chambres, dont une suite, et une terrasse de 40 m².\n\nPrestations soignées, fibre optique, contrôle d’accès. Frais de mutation à la charge de l’acquéreur."],
    ['prestige', 'sale', 'villa', 'Villa basse 4 pièces sur 500 m²', ['Abidjan', 'Bingerville', 'Abatta'], 95000000, 'total',
        ['living_area' => 200, 'land_area' => 500, 'rooms' => 4, 'bedrooms' => 3, 'bathrooms' => 2],
        ['standing' => 'mid', 'condition' => 'new', 'title_type' => 'acd', 'parking_spaces' => 2],
        ['garden', 'water_tank', 'borehole'], ['villa-escalier', 'salon-lumineux', 'cuisine'], false, 12,
        "Villa de plain-pied récente à Abatta, à dix minutes de la Riviera par l’autoroute de Bingerville.\n\nSéjour lumineux, trois chambres dont une suite, cuisine indépendante, cour et jardin. Château d’eau et forage.\n\nIdéal pour une première acquisition familiale."],
    ['bassam', 'sale', 'villa', 'Villa pieds dans l’eau avec piscine', ['Assinie-Mafia', 'Assinie-Mafia'], 650000000, 'total',
        ['living_area' => 420, 'land_area' => 1500, 'rooms' => 7, 'bedrooms' => 6, 'bathrooms' => 6],
        ['standing' => 'high', 'condition' => 'good', 'title_type' => 'acd', 'parking_spaces' => 4],
        ['pool', 'garden', 'terrace', 'security_guard', 'generator', 'air_conditioning'], ['piscine', 'villa-piscine', 'coucher-soleil', 'salon-baie'], true, 2,
        "Entre océan et lagune, villa d’exception sur 1 500 m² avec accès direct à la plage.\n\nGrand séjour ouvert sur la piscine à débordement, six chambres climatisées, paillote et dépendance pour le personnel.\n\nVendue meublée. Visites sur rendez-vous uniquement."],
    ['ivoire', 'sale', 'residential_land', 'Terrain loti 600 m²', ['Abidjan', 'Songon', 'Songon-Agban'], 18000000, 'total',
        ['land_area' => 600],
        ['land_subdivision' => 'subdivided', 'servicing' => ['water', 'electricity'], 'road_access' => 'laterite', 'boundary_marked' => true, 'title_type' => 'acd'],
        [], ['lagune', 'coucher-soleil'], false, 20,
        "Terrain de 600 m² dans un lotissement approuvé de Songon-Agban, à proximité de la voie principale.\n\nEau et électricité en bordure de lot, bornage effectué. ACD en cours de délivrance, dossier consultable sur rendez-vous."],
    ['bassam', 'sale', 'residential_land', 'Terrain 1 000 m² proche de la plage', ['Grand-Bassam', 'Grand-Bassam', 'Mockeyville'], 45000000, 'total',
        ['land_area' => 1000],
        ['land_subdivision' => 'subdivided', 'servicing' => ['water', 'electricity', 'road'], 'road_access' => 'paved', 'boundary_marked' => true, 'title_type' => 'adu'],
        [], ['coucher-soleil', 'lagune'], false, 15,
        "À deux pas de la plage de Mockeyville, terrain plat de 1 000 m² accessible par une voie bitumée.\n\nViabilisé (eau, électricité), bornage réalisé. Convient à une résidence secondaire ou à un petit projet hôtelier."],
    ['atlantique', 'sale', 'residential_building', 'Immeuble R+4 de 12 appartements', ['Abidjan', 'Yopougon', 'Selmer'], 520000000, 'total',
        ['living_area' => 950, 'land_area' => 600],
        ['levels_count' => 5, 'title_type' => 'tf'],
        ['water_tank', 'security_guard'], ['plateau-aerien', 'salon-lumineux', 'cuisine'], false, 30,
        "Immeuble de rapport entièrement loué : douze appartements de trois pièces répartis sur cinq niveaux.\n\nRevenus locatifs réguliers, état d’entretien satisfaisant, titre foncier. Liste des baux et relevé des loyers communiqués aux acquéreurs sérieux."],
    [null, 'sale', 'apartment', 'Appartement 3 pièces rénové', ['Abidjan', 'Cocody', 'Deux-Plateaux Vallon'], 78000000, 'total',
        ['living_area' => 110, 'rooms' => 3, 'bedrooms' => 2, 'bathrooms' => 2],
        ['standing' => 'mid', 'condition' => 'good', 'title_type' => 'acd', 'apartment_layout' => 'simplex', 'floor' => 2, 'building_floors' => 3],
        ['air_conditioning', 'equipped_kitchen'], ['salon-lumineux', 'cuisine', 'salon-baie'], false, 6,
        "Bien confié par son propriétaire : appartement de 110 m² entièrement rénové aux Deux-Plateaux Vallon.\n\nSéjour, cuisine équipée, deux chambres climatisées et deux salles d’eau. Petite copropriété calme et bien tenue."],
    ['plateau', 'sale', 'office', 'Plateau de bureaux 200 m²', ['Abidjan', 'Plateau', 'Centre des affaires'], 280000000, 'total',
        ['living_area' => 200],
        ['condition' => 'good', 'title_type' => 'tf', 'parking_spaces' => 4, 'strategic_location' => true],
        ['elevator', 'air_conditioning', 'generator', 'fiber_optic', 'access_control'], ['tour', 'plateau-pont', 'plateau-aerien'], false, 10,
        "Au cœur du quartier des affaires, plateau de bureaux de 200 m² livré cloisonné : accueil, open space, trois bureaux fermés et salle de réunion.\n\nImmeuble de standing avec ascenseurs, groupe électrogène et fibre optique. Quatre places de parking."],
    ['lagune', 'rent', 'apartment', 'Appartement 3 pièces avec balcon', ['Abidjan', 'Cocody', 'Riviera Palmeraie'], 450000, 'month',
        ['living_area' => 100, 'rooms' => 3, 'bedrooms' => 2, 'bathrooms' => 2],
        ['standing' => 'mid', 'condition' => 'good', 'apartment_layout' => 'simplex', 'floor' => 1, 'building_floors' => 3],
        ['air_conditioning', 'water_tank', 'security_guard'], ['salon-contemporain', 'cuisine', 'salon-lumineux'], false, 4,
        "À la Riviera Palmeraie, appartement de trois pièces au premier étage d’une petite résidence gardée.\n\nSéjour avec balcon, deux chambres climatisées, deux salles d’eau, cuisine aménagée. Disponible immédiatement."],
    ['prestige', 'rent', 'villa', 'Villa 6 pièces avec jardin', ['Abidjan', 'Cocody', 'Ambassades'], 3500000, 'month',
        ['living_area' => 350, 'land_area' => 900, 'rooms' => 6, 'bedrooms' => 5, 'bathrooms' => 5],
        ['standing' => 'high', 'condition' => 'good', 'parking_spaces' => 3],
        ['garden', 'pool', 'security_guard', 'generator', 'air_conditioning', 'cctv'], ['villa-escalier', 'salon-baie', 'cuisine', 'piscine'], true, 7,
        "Dans le quartier des Ambassades, villa familiale de 350 m² sur un jardin arboré de 900 m² avec piscine.\n\nCinq chambres, bureau, dépendance, gardiennage et vidéosurveillance. Idéale pour une famille d’expatriés ou une résidence de fonction."],
    ['lagune', 'rent', 'apartment', 'Studio moderne proche du boulevard VGE', ['Abidjan', 'Marcory', 'Biétry'], 180000, 'month',
        ['living_area' => 35, 'rooms' => 1, 'bedrooms' => 1, 'bathrooms' => 1],
        ['standing' => 'mid', 'condition' => 'new', 'apartment_layout' => 'studio', 'floor' => 2, 'building_floors' => 4],
        ['air_conditioning', 'security_guard'], ['salon-lumineux', 'cuisine'], false, 1,
        "Studio neuf de 35 m² à Biétry, à quelques minutes du boulevard Valéry-Giscard-d’Estaing.\n\nPièce de vie climatisée avec coin cuisine équipé, salle d’eau moderne. Résidence gardée."],
    ['ivoire', 'rent', 'apartment', 'Appartement 4 pièces standing', ['Abidjan', 'Cocody', 'Deux-Plateaux'], 900000, 'month',
        ['living_area' => 160, 'rooms' => 4, 'bedrooms' => 3, 'bathrooms' => 3],
        ['standing' => 'high', 'condition' => 'new', 'apartment_layout' => 'simplex', 'floor' => 4, 'building_floors' => 5, 'parking_spaces' => 1],
        ['elevator', 'air_conditioning', 'generator', 'access_control', 'fiber_optic'], ['salon-baie', 'salon-contemporain', 'cuisine'], false, 9,
        "Aux Deux-Plateaux, appartement neuf de 160 m² dans une résidence avec ascenseur et groupe électrogène.\n\nTrois chambres dont une suite, séjour lumineux, cuisine équipée, place de parking."],
    [null, 'rent', 'semi_detached', 'Maison jumelée 4 pièces avec cour', ['Abidjan', 'Yopougon', 'Niangon'], 250000, 'month',
        ['living_area' => 120, 'land_area' => 250, 'rooms' => 4, 'bedrooms' => 3, 'bathrooms' => 2],
        ['standing' => 'economic', 'condition' => 'good'],
        ['water_tank'], ['villa-escalier', 'salon-lumineux'], false, 11,
        "Bien confié par son propriétaire : maison jumelée de quatre pièces à Niangon, avec cour privative.\n\nSéjour, trois chambres, deux salles d’eau. Quartier calme, proche des commerces et des transports."],
    ['prestige', 'furnished_rent', 'apartment', 'Appartement meublé 2 pièces', ['Abidjan', 'Cocody', 'Riviera 3'], 45000, 'night',
        ['living_area' => 70, 'rooms' => 2, 'bedrooms' => 1, 'bathrooms' => 1],
        ['standing' => 'high', 'condition' => 'new', 'furnished' => true, 'apartment_layout' => 'simplex', 'floor' => 2, 'building_floors' => 3],
        ['air_conditioning', 'equipped_kitchen', 'fiber_optic', 'security_guard', 'generator'], ['salon-contemporain', 'cuisine', 'salon-baie'], true, 2,
        "Appartement meublé avec goût à la Riviera 3, idéal pour un séjour professionnel ou touristique.\n\nChambre climatisée, séjour avec télévision, cuisine équipée, wifi haut débit. Linge et ménage inclus. Séjour de deux nuits minimum."],
    ['bassam', 'furnished_rent', 'villa', 'Villa meublée avec piscine face à la lagune', ['Assinie-Mafia', 'Assinie-Mafia'], 250000, 'night',
        ['living_area' => 220, 'land_area' => 1000, 'rooms' => 5, 'bedrooms' => 4, 'bathrooms' => 4],
        ['standing' => 'high', 'condition' => 'good', 'furnished' => true],
        ['pool', 'garden', 'terrace', 'air_conditioning', 'generator', 'security_guard'], ['piscine', 'villa-piscine', 'coucher-soleil'], false, 14,
        "Pour vos week-ends à Assinie : villa de quatre chambres entièrement meublée, piscine et ponton sur la lagune.\n\nCuisine équipée, paillote, gardien sur place. Jusqu’à huit personnes."],
    ['plateau', 'furnished_rent', 'apartment', 'Studio meublé au Plateau', ['Abidjan', 'Plateau', 'Indénié'], 550000, 'month',
        ['living_area' => 40, 'rooms' => 1, 'bedrooms' => 1, 'bathrooms' => 1],
        ['standing' => 'mid', 'condition' => 'good', 'furnished' => true, 'apartment_layout' => 'studio', 'floor' => 5, 'building_floors' => 8],
        ['elevator', 'air_conditioning', 'fiber_optic'], ['salon-lumineux', 'tour'], false, 18,
        "Studio meublé et équipé à l’Indénié, à pied des bureaux du Plateau.\n\nLit double, coin cuisine, bureau et connexion fibre. Location au mois, charges d’eau comprises."],
    ['plateau', 'commercial_lease', 'office', 'Bureaux 320 m² en immeuble de standing', ['Abidjan', 'Plateau', 'Centre des affaires'], 4800000, 'month',
        ['living_area' => 320],
        ['condition' => 'good', 'parking_spaces' => 6, 'strategic_location' => true],
        ['elevator', 'air_conditioning', 'generator', 'fiber_optic', 'access_control', 'cctv'], ['tour', 'plateau-aerien', 'plateau-pont'], true, 6,
        "Plateau de bureaux de 320 m² au douzième étage d’une tour du centre des affaires, avec vue sur la lagune.\n\nLivré aménagé : accueil, open space de 30 postes, quatre bureaux, salle de réunion, kitchenette. Six places de parking."],
    ['lagune', 'commercial_lease', 'retail', 'Local commercial 120 m² sur rue passante', ['Abidjan', 'Marcory', 'Zone 4'], 1200000, 'month',
        ['living_area' => 120],
        ['condition' => 'good', 'strategic_location' => true],
        ['air_conditioning'], ['plateau-pont', 'tour'], false, 13,
        "Local commercial de 120 m² avec large vitrine, sur l’un des axes les plus fréquentés de la Zone 4.\n\nConvient à une boutique, un showroom ou une agence. Réserve et sanitaires."],
    ['plateau', 'commercial_lease', 'warehouse', 'Entrepôt 1 500 m² avec quai de chargement', ['Abidjan', 'Koumassi', 'Zone industrielle'], 6000000, 'month',
        ['living_area' => 1500, 'land_area' => 2500],
        ['condition' => 'good', 'loading_dock' => true],
        ['security_guard', 'cctv', 'generator'], ['plateau-aerien', 'lagune'], false, 22,
        "Entrepôt de 1 500 m² sur une parcelle clôturée de 2 500 m² en zone industrielle de Koumassi.\n\nHauteur sous plafond de 8 m, quai de chargement, bureaux attenants, gardiennage. Accès poids lourds."],
    ['lagune', 'lease_transfer', 'retail', 'Restaurant équipé en cession de bail', ['Abidjan', 'Cocody', 'Deux-Plateaux'], 35000000, 'total',
        ['living_area' => 150],
        ['condition' => 'good', 'strategic_location' => true],
        ['air_conditioning', 'equipped_kitchen'], ['cuisine', 'salon-contemporain'], false, 16,
        "Cession du droit au bail d’un restaurant de 60 couverts aux Deux-Plateaux, cuisine professionnelle entièrement équipée.\n\nClientèle établie, loyer modéré, bail récent. Le prix comprend le matériel et l’agencement."],
];

$columnsAllowed = ['living_area', 'land_area', 'rooms', 'bedrooms', 'bathrooms'];
foreach ($listings as $index => [$partner, $transaction, $category, $title, $location, $price, $period, $columns, $criteria, $equipment, $photos, $featured, $daysAgo, $description]) {
    $geo = $place(...$location);
    $publishedAt = $date($daysAgo);
    $exact = $index % 4 === 0;
    $communeName = $location[1];

    $data = [
        'reference' => 'TMP-' . bin2hex(random_bytes(6)),
        'country_id' => $countryId,
        'source' => $partner === null ? 'private_owner' : 'agency',
        'agency_id' => $partner === null ? null : $agencyIds[$partner],
        'transaction_type_id' => $transactions[$transaction],
        'category_id' => $categories[$category],
        'title' => $title,
        'slug' => Str::slug($title . ' ' . $communeName),
        'description' => $description,
        'internal_reference' => 'DEMO-' . sprintf('%03d', $index + 1),
        'price' => $price,
        'currency_code' => $country['currency_code'],
        'price_period' => $period,
        'is_negotiable' => (int) ($index % 3 === 0),
        'city_id' => $geo['city_id'],
        'commune_id' => $geo['commune_id'],
        'district_id' => $geo['district_id'],
        'latitude' => round($geo['lat'] + (mt_rand(-150, 150) / 10000), 7),
        'longitude' => round($geo['lng'] + (mt_rand(-150, 150) / 10000), 7),
        'show_exact_location' => (int) $exact,
        'availability' => 'available',
        'contact_name' => $partner === null ? null : 'Contact démonstration',
        'contact_phone' => $partner === null ? null : '+225 07 00 00 00 ' . sprintf('%02d', $index + 10),
        'status' => 'published',
        'submitted_at' => $publishedAt,
        'reviewed_by_user_id' => $authorId,
        'reviewed_at' => $publishedAt,
        'published_at' => $publishedAt,
        'expires_at' => gmdate('Y-m-d H:i:s', strtotime($publishedAt . ' UTC') + $lifetime * 86400),
        'is_featured' => (int) $featured,
        'featured_until' => $featured ? gmdate('Y-m-d H:i:s', $now + 60 * 86400) : null,
        'created_by_user_id' => $authorId,
        'created_at' => $publishedAt,
    ];
    foreach ($columns as $column => $value) {
        if (in_array($column, $columnsAllowed, true)) {
            $data[$column] = $value;
        }
    }

    $id = $db->insert('properties', $data);
    $db->execute('UPDATE properties SET reference = :reference WHERE id = :id', ['reference' => $prefix . '-' . (10000 + $id), 'id' => $id]);
    $registry['properties'][] = $id;

    if ($partner === null) {
        $db->insert('property_private_details', ['property_id' => $id, 'owner_name' => 'Propriétaire démonstration', 'owner_email' => 'proprietaire@demo.invalid', 'internal_notes' => 'Données de démonstration']);
    }
    $db->insert('property_status_history', ['property_id' => $id, 'from_status' => 'pending', 'to_status' => 'published', 'user_id' => $authorId, 'created_at' => $publishedAt]);

    foreach ($criteria as $code => $value) {
        $attributeId = $attributes[$code] ?? null;
        if ($attributeId === null) {
            continue;
        }
        foreach ((array) $value as $item) {
            $row = ['property_id' => $id, 'attribute_id' => $attributeId];
            if (is_bool($item)) {
                $row['value_boolean'] = (int) $item;
            } elseif (is_int($item)) {
                $row['value_integer'] = $item;
            } elseif (isset($attributeOptions[$code . '.' . $item])) {
                $row['value_option_id'] = $attributeOptions[$code . '.' . $item];
            } else {
                continue;
            }
            $db->insert('property_attribute_values', $row);
        }
    }
    foreach ($equipment as $code) {
        if (isset($features[$code])) {
            $db->insert('property_features', ['property_id' => $id, 'feature_id' => $features[$code]]);
        }
    }

    // Photos : copie des trois tailles dans le dossier de l'annonce
    $directory = 'uploads/' . $isoDir . '/annonces/' . $id;
    @mkdir(APP_ROOT . '/public/' . $directory, 0775, true);
    foreach ($photos as $order => $key) {
        $stored = $photo($key);
        $base = $directory . '/' . bin2hex(random_bytes(10));
        foreach ([1600, 800, 400] as $width) {
            copy(APP_ROOT . '/public/' . $stored['path'] . '-' . $width . '.webp', APP_ROOT . '/public/' . $base . '-' . $width . '.webp');
        }
        $db->insert('property_images', [
            'property_id' => $id,
            'path' => $base,
            'original_name' => $key . '.jpg',
            'mime_type' => 'image/webp',
            'width' => $stored['width'],
            'height' => $stored['height'],
            'size_bytes' => $stored['size'],
            'alt_text' => $title,
            'sort_order' => $order,
            'created_at' => $publishedAt,
        ]);
    }
    $registry['files'][] = $directory;

    // Audience : une courbe crédible sur la période de publication (30 jours au plus)
    $views = 0;
    $leadsTotal = 0;
    for ($day = min($daysAgo, 29); $day >= 0; $day--) {
        $dayViews = mt_rand($featured ? 12 : 3, $featured ? 45 : 22);
        $dayLeads = mt_rand(0, 12) === 0 ? 1 : 0;
        $views += $dayViews;
        $leadsTotal += $dayLeads;
        $db->insert('property_stats_daily', [
            'property_id' => $id,
            'stat_date' => gmdate('Y-m-d', $now - $day * 86400),
            'views' => $dayViews,
            'phone_clicks' => mt_rand(0, 3),
            'whatsapp_clicks' => mt_rand(0, 4),
            'shares' => mt_rand(0, 1),
            'leads' => $dayLeads,
        ]);
    }
    $db->execute('UPDATE properties SET views_count = :views, leads_count = :leads WHERE id = :id', ['views' => $views, 'leads' => $leadsTotal, 'id' => $id]);
}

// Nombre d'annonces en ligne des partenaires (compteur dénormalisé)
foreach ($agencyIds as $agencyId) {
    $db->execute(
        "UPDATE agencies SET published_properties_count = (SELECT COUNT(*) FROM properties WHERE agency_id = :agency AND status = 'published' AND deleted_at IS NULL) WHERE id = :id",
        ['agency' => $agencyId, 'id' => $agencyId]
    );
}
// Photos traitées une seule fois : le dossier temporaire n'est plus utile
foreach ($processed as $stored) {
    $images->deleteVariants($stored['path']);
}
@rmdir(APP_ROOT . '/public/uploads/' . $isoDir . '/tmp/demo');

// -- Demandes de contact (back-office) ---------------------------------------------------------

$people = ['Aya Koffi', 'Jean-Marc Yao', 'Fatou Diabaté', 'Serge Kouamé', 'Mariam Traoré', 'Olivier N’Guessan', 'Awa Bamba', 'Didier Konan'];
$statuses = ['new', 'new', 'new', 'read', 'in_progress', 'in_progress', 'closed', 'closed'];
foreach ($people as $index => $name) {
    $propertyId = $registry['properties'][($index * 3) % count($registry['properties'])];
    $property = $db->selectOne('SELECT reference, title, agency_id FROM properties WHERE id = :id', ['id' => $propertyId]);
    $general = $index === 5;
    $created = $date($index * 2 + 1, 9 + $index);
    $registry['leads'][] = $db->insert('leads', [
        'site_id' => $siteId,
        'country_id' => $countryId,
        'type' => $general ? 'general_contact' : 'property_contact',
        'property_id' => $general ? null : $propertyId,
        'agency_id' => $general ? null : $property['agency_id'],
        'name' => $name,
        'email' => str_replace('-', '.', Str::slug($name)) . '@demo.invalid',
        'phone' => '+225 05 00 00 00 ' . sprintf('%02d', $index + 20),
        'message' => $general
            ? 'Bonjour, je souhaite confier la gestion locative de deux appartements à Cocody. Pouvez-vous me rappeler ?'
            : "Bonjour, je suis intéressé(e) par l’annonce {$property['reference']} : {$property['title']}. Est-elle toujours disponible et quand pourrais-je la visiter ?",
        'payload' => $general ? json_encode(['subject' => 'Autre demande'], JSON_UNESCAPED_UNICODE) : null,
        'status' => $statuses[$index],
        'assigned_user_id' => in_array($statuses[$index], ['in_progress', 'closed'], true) ? $authorId : null,
        'handled_at' => $statuses[$index] === 'closed' ? $created : null,
        'consent_at' => $created,
        'source_url' => '/',
        'created_at' => $created,
    ]);
}

// -- Dossiers de partenariat (back-office, sans pièce jointe) ----------------------------------

foreach ([
    ['Horizon Immobilier', 'agency', 'Kouadio Brou', 'Gérant', 'new', 1],
    ['Sud-Ouest Habitat', 'developer', 'Nadia Coulibaly', 'Directrice générale', 'contacted', 4],
] as [$name, $type, $contact, $role, $status, $daysAgo]) {
    $abidjan = $place('Abidjan', 'Cocody');
    $registry['partner_requests'][] = $db->insert('partner_requests', [
        'site_id' => $siteId,
        'country_id' => $countryId,
        'partner_type' => $type,
        'agency_name' => $name,
        'legal_name' => $name . ' SARL',
        'legal_form' => 'sarl',
        'contact_name' => $contact,
        'contact_role' => $role,
        'email' => str_replace('-', '.', Str::slug($contact)) . '@demo.invalid',
        'phone' => '+225 07 00 00 11 ' . sprintf('%02d', $daysAgo),
        'city_id' => $abidjan['city_id'],
        'commune_id' => $abidjan['commune_id'],
        'listings_estimate' => 30,
        'years_active' => 6,
        'message' => 'Dossier de démonstration.',
        'status' => $status,
        'consent_at' => $date($daysAgo),
        'created_at' => $date($daysAgo),
    ]);
}

// -- Actualités --------------------------------------------------------------------------------

$posts = [
    ['titre-foncier-acd-adu-comprendre-les-documents', 'Titre foncier, ACD, ADU : comprendre les documents avant d’acheter', 'lagune', 6,
        'Avant de signer, vérifiez la nature du document qui prouve la propriété du bien. Tour d’horizon des principaux titres en Côte d’Ivoire.',
        "<p>En Côte d’Ivoire, tous les biens ne présentent pas le même niveau de sécurité juridique. Avant tout engagement, il faut savoir quel document établit les droits du vendeur.</p>\n<h2>Le titre foncier</h2>\n<p>Le titre foncier (TF) inscrit la propriété au livre foncier. C’est la situation la plus sûre pour un acquéreur.</p>\n<h2>L’arrêté de concession définitive</h2>\n<p>L’ACD est délivré par l’administration pour les terrains urbains ; il ouvre la voie à l’immatriculation du bien.</p>\n<h2>Les autres situations</h2>\n<p>Attestation de droit d’usage, lettre d’attribution ou droits coutumiers demandent des vérifications supplémentaires. Dans tous les cas, faites-vous remettre les originaux et consultez un notaire avant de payer.</p>\n<p><em>Article de démonstration.</em></p>"],
    ['louer-a-abidjan-les-frais-a-prevoir', 'Louer à Abidjan : les frais à prévoir avant d’emménager', 'salon-lumineux', 12,
        'Caution, avance sur loyer, frais d’agence : préparez votre budget pour éviter les mauvaises surprises au moment de signer.',
        "<p>Au moment de signer un bail, le loyer du premier mois n’est pas la seule somme à prévoir.</p>\n<h2>La caution</h2>\n<p>Elle garantit le bon état du logement et vous est restituée à la sortie, déduction faite des éventuelles réparations.</p>\n<h2>L’avance sur loyer</h2>\n<p>Le bailleur demande souvent plusieurs mois payés d’avance : faites-le préciser dans le contrat.</p>\n<h2>Les frais d’intermédiation</h2>\n<p>Renseignez-vous dès la première visite sur les frais demandés et exigez un reçu pour chaque paiement.</p>\n<p><em>Article de démonstration.</em></p>"],
    ['cocody-marcory-bingerville-ou-acheter', 'Cocody, Marcory, Bingerville : où acheter selon votre projet ?', 'riviera', 20,
        'Résidence principale, investissement locatif ou terrain à bâtir : chaque commune d’Abidjan répond à des attentes différentes.',
        "<p>Le choix du quartier dépend d’abord de votre projet et de votre budget.</p>\n<h2>Cocody</h2>\n<p>Riviera, Angré, Deux-Plateaux : l’offre résidentielle la plus large, recherchée par les familles.</p>\n<h2>Marcory</h2>\n<p>Zone 4 et Biétry attirent pour leur proximité avec le Plateau et l’aéroport, notamment en location.</p>\n<h2>Bingerville et Songon</h2>\n<p>Plus éloignées, ces communes offrent des terrains et des villas à des prix plus accessibles.</p>\n<p><em>Article de démonstration.</em></p>"],
];
@mkdir(APP_ROOT . '/public/uploads/' . $isoDir . '/actualites', 0775, true);
foreach ($posts as [$slug, $title, $cover, $daysAgo, $excerpt, $content]) {
    if ((int) $db->scalar('SELECT COUNT(*) FROM blog_posts WHERE site_id = :site AND slug = :slug', ['site' => $siteId, 'slug' => $slug]) > 0) {
        continue;
    }
    $coverPath = $images->storeWebp(['tmp_name' => $sources[$cover]], $isoDir . '/actualites', 'demo', 1600, 1067);
    $registry['posts'][] = $db->insert('blog_posts', [
        'site_id' => $siteId,
        'slug' => $slug,
        'locale' => 'fr',
        'title' => $title,
        'excerpt' => $excerpt,
        'content' => $content,
        'cover_image_path' => $coverPath,
        'author_user_id' => $authorId,
        'status' => 'published',
        'published_at' => $date($daysAgo),
        'created_at' => $date($daysAgo),
    ]);
    $registry['files'][] = $coverPath;
}

// -- Mode démonstration ------------------------------------------------------------------------

$saveSetting = static function (string $key, mixed $value, string $description) use ($db): void {
    $db->execute(
        'INSERT INTO settings (site_id, setting_key, value, description) VALUES (NULL, :key, :value, :description)
         ON DUPLICATE KEY UPDATE value = VALUES(value), description = VALUES(description)',
        ['key' => $key, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'description' => $description]
    );
};
$saveSetting('demo.registry', $registry, 'Données de démonstration créées par bin/seed-demo.php (purge : bin/reset-before-launch.php)');
$saveSetting('demo.active', true, 'Mode démonstration : pastille Aperçu, noindex, robots fermé, pas de mesure d’audience');
$app->sites()->flush();
$app->cache()->clear();

$app->activity()->log('system.demo_seeded', null, $countryId, null, null, sprintf(
    '%d partenaires, %d annonces, %d contacts, %d actualités',
    count($registry['agencies']),
    count($registry['properties']),
    count($registry['leads']),
    count($registry['posts'])
));

printf(
    "✓ Données de démonstration créées (%s) : %d partenaires, %d annonces, %d demandes de contact, %d dossiers de partenariat, %d actualités.\n",
    $iso,
    count($registry['agencies']),
    count($registry['properties']),
    count($registry['leads']),
    count($registry['partner_requests']),
    count($registry['posts'])
);
echo "  Mode démonstration actif : pastille « Aperçu », pages en noindex, robots.txt fermé, pas de mesure d'audience.\n";
echo "  Avant l'ouverture : php bin/reset-before-launch.php (simulation) puis --confirm.\n";
