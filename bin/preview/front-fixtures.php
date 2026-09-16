<?php

declare(strict_types=1);

/**
 * Données fictives de la charte graphique (/styleguide) : cartes annonce et module de recherche.
 * L'accueil réel (lot 1.8) n'utilise plus ces données. Photos provisoires : public/assets/img/placeholder
 * (voir CREDITS.md). Agences inventées.
 */

$img = static fn (string $name): string => 'img/placeholder/properties/' . $name;

/** Image d'une carte annonce : mêmes clés que ListingPresenter (src, srcset, alt). */
$card = static fn (string $name, string $alt): array => [
    'src' => asset($img($name) . '-960.webp'),
    'srcset' => asset($img($name) . '-480.webp') . ' 480w, ' . asset($img($name) . '-960.webp') . ' 960w',
    'alt' => $alt,
    'placeholder' => false,
];

$properties = [
    [
        'reference' => 'IAN-24531', 'url' => 'annonces/villa-duplex-6-pieces-piscine-riviera-golf-cocody-ian-24531',
        'title' => 'Villa duplex 6 pièces avec piscine et jardin paysager',
        'category' => 'Villa', 'transaction' => 'Vente', 'price' => 385000000, 'currency' => 'FCFA', 'period' => 'total',
        'location' => 'Riviera Golf, Cocody', 'image' => $card('villa-piscine-palmiers', 'Villa blanche avec piscine entourée de palmiers'), 'photos' => 18,
        'badges' => [['label' => 'Nouveau', 'variant' => 'new']],
        'specs' => [['icon' => 'area', 'label' => '420 m²'], ['icon' => 'bed', 'label' => '5 chambres'], ['icon' => 'bath', 'label' => '5 sdb']],
        'agency' => 'Lagune Conseil Immobilier', 'verified' => true, 'phone' => '+225 07 08 09 10 11', 'whatsapp' => '+225 07 08 09 10 11',
        'description' => 'Sur une parcelle de 850 m² en titre foncier, villa contemporaine avec double séjour, cuisine équipée, suite parentale et piscine à débordement.',
    ],
    [
        'reference' => 'IAN-24528', 'url' => 'annonces/appartement-4-pieces-haut-standing-zone-4-marcory-ian-24528',
        'title' => 'Appartement 4 pièces haut standing avec terrasse',
        'category' => 'Appartement', 'transaction' => 'Location', 'price' => 1200000, 'currency' => 'FCFA', 'period' => 'month',
        'location' => 'Zone 4, Marcory', 'image' => $card('salon-contemporain', 'Salon contemporain lumineux avec canapé en cuir'), 'photos' => 12,
        'badges' => [['label' => 'À la une', 'variant' => 'featured']],
        'specs' => [['icon' => 'area', 'label' => '165 m²'], ['icon' => 'bed', 'label' => '3 chambres'], ['icon' => 'bath', 'label' => '2 sdb']],
        'agency' => 'Atlantique Habitat', 'verified' => true, 'phone' => '+225 05 44 33 22 11', 'whatsapp' => '+225 05 44 33 22 11',
        'description' => 'Au 5e étage d’une résidence sécurisée avec ascenseur et groupe électrogène, appartement traversant avec terrasse et deux places de parking.',
    ],
    [
        'reference' => 'IAN-24499', 'url' => 'annonces/residence-vacances-piscine-debordement-assinie-ian-24499',
        'title' => 'Résidence de vacances avec piscine à débordement',
        'category' => 'Résidence de vacances', 'transaction' => 'Location meublée', 'price' => 150000, 'currency' => 'FCFA', 'period' => 'night',
        'location' => 'Assinie-Mafia', 'image' => $card('piscine-debordement', 'Piscine à débordement face à la végétation au coucher du soleil'), 'photos' => 24,
        'badges' => [],
        'specs' => [['icon' => 'bed', 'label' => '4 chambres'], ['icon' => 'pool', 'label' => 'Piscine'], ['icon' => 'wifi', 'label' => 'Wi-Fi']],
        'agency' => 'Maison d’Ébène', 'verified' => true, 'phone' => '+225 01 02 03 04 05', 'whatsapp' => '+225 01 02 03 04 05',
        'description' => 'Maison de vacances entièrement équipée, à deux pas de la plage, avec personnel de maison et gardiennage 24 h/24.',
    ],
    [
        'reference' => 'IAN-24518', 'url' => 'annonces/plateau-bureaux-320-m2-vue-lagune-plateau-ian-24518',
        'title' => 'Plateau de bureaux 320 m² avec vue sur la lagune',
        'category' => 'Bureau', 'transaction' => 'Location', 'price' => 6400000, 'currency' => 'FCFA', 'period' => 'month',
        'location' => 'Centre des affaires, Plateau', 'image' => $card('plateau-tour-affaires', 'Tour de bureaux dans le quartier du Plateau'), 'photos' => 9,
        'badges' => [['label' => 'À la une', 'variant' => 'featured']],
        'specs' => [['icon' => 'area', 'label' => '320 m²'], ['icon' => 'elevator', 'label' => 'Ascenseur'], ['icon' => 'car', 'label' => '6 places']],
        'agency' => 'Plateau Business Immo', 'verified' => true, 'phone' => '+225 27 20 00 00 00', 'whatsapp' => null,
        'description' => 'Plateau livré cloisonné avec salle de réunion, fibre optique, climatisation centralisée et accès sécurisé par badge.',
    ],
    [
        'reference' => 'IAN-24507', 'url' => 'annonces/villa-basse-4-pieces-angre-cocody-ian-24507',
        'title' => 'Villa basse 4 pièces avec escalier extérieur et piscine',
        'category' => 'Villa', 'transaction' => 'Vente', 'price' => 165000000, 'currency' => 'FCFA', 'period' => 'total',
        'location' => 'Angré, Cocody', 'image' => $card('villa-escalier-piscine', 'Villa blanche avec escalier en colimaçon et piscine'), 'photos' => 15,
        'badges' => [],
        'specs' => [['icon' => 'area', 'label' => '210 m²'], ['icon' => 'land-area', 'label' => '600 m²'], ['icon' => 'bed', 'label' => '3 chambres']],
        'agency' => 'Ivoire Clés', 'verified' => false, 'phone' => '+225 07 55 66 77 88', 'whatsapp' => '+225 07 55 66 77 88',
        'description' => 'Villa rénovée en ACD, grand jardin arboré, dépendance pour le personnel et forage. Quartier calme proche des commerces.',
    ],
    [
        'reference' => 'IAN-24441', 'url' => 'annonces/appartement-3-pieces-lumineux-deux-plateaux-ian-24441',
        'title' => 'Appartement 3 pièces lumineux, cuisine ouverte',
        'category' => 'Appartement', 'transaction' => 'Location', 'price' => 650000, 'currency' => 'FCFA', 'period' => 'month',
        'location' => 'Deux-Plateaux Vallon, Cocody', 'image' => $card('cuisine-ouverte', 'Séjour moderne avec cuisine ouverte'), 'photos' => 10,
        'badges' => [['label' => 'Nouveau', 'variant' => 'new']],
        'specs' => [['icon' => 'area', 'label' => '98 m²'], ['icon' => 'bed', 'label' => '2 chambres'], ['icon' => 'air-conditioning', 'label' => 'Climatisé']],
        'agency' => 'Lagune Conseil Immobilier', 'verified' => true, 'phone' => '+225 07 08 09 10 11', 'whatsapp' => '+225 07 08 09 10 11',
        'description' => 'Appartement neuf dans une petite copropriété, finitions soignées, cuisine équipée ouverte sur le séjour.',
    ],
];

$search = [
    'transactions' => [
        ['slug' => 'acheter', 'label' => 'Acheter'],
        ['slug' => 'louer', 'label' => 'Louer'],
        ['slug' => 'location-meublee', 'label' => 'Location meublée'],
    ],
    // Groupés par famille, comme SearchOptions::propertyTypes()
    'propertyTypes' => [
        'Résidentiel' => ['appartement' => 'Appartement', 'villa' => 'Villa / Maison individuelle'],
        'Terrains' => ['terrain-nu' => 'Terrain nu (résidentiel)', 'terrain-agricole' => 'Terrain agricole'],
        'Commercial & Bureaux' => ['bureau' => 'Bureau / Plateau de bureaux', 'local-commercial' => 'Local commercial / Boutique', 'magasin-entrepot' => 'Magasin / Entrepôt / Hangar'],
        'Hôtellerie & Tourisme' => ['residence-vacances' => 'Résidence de vacances'],
    ],
    'budgets' => [
        'acheter' => ['25000000' => '25 millions FCFA', '50000000' => '50 millions FCFA', '100000000' => '100 millions FCFA', '200000000' => '200 millions FCFA', '400000000' => '400 millions FCFA'],
        'louer' => ['250000' => '250 000 FCFA / mois', '500000' => '500 000 FCFA / mois', '1000000' => '1 million FCFA / mois', '2500000' => '2,5 millions FCFA / mois'],
        'location-meublee' => ['50000' => '50 000 FCFA / nuit', '100000' => '100 000 FCFA / nuit', '1000000' => '1 million FCFA / mois'],
    ],
    'locationPlaceholder' => 'Ville, commune ou quartier',
];

return [
    'styleguide' => [
        'properties' => $properties,
        'search' => $search,
        'colors' => [
            ['token' => 'navy', 'hex' => '#143D8A', 'name' => 'Bleu nuit', 'usage' => 'Titres forts, prix, actions sombres'],
            ['token' => 'navy-deep', 'hex' => '#0B2358', 'name' => 'Bleu nuit profond', 'usage' => 'Pied de page, voiles photo'],
            ['token' => 'blue', 'hex' => '#2650DB', 'name' => 'Bleu action', 'usage' => 'Bouton principal, liens'],
            ['token' => 'blue-bright', 'hex' => '#106AFF', 'name' => 'Bleu vif', 'usage' => 'Focus, accents ponctuels'],
            ['token' => 'blue-tint', 'hex' => '#E0EEFF', 'name' => 'Bleu pâle', 'usage' => 'Badges, états sélectionnés'],
            ['token' => 'mist', 'hex' => '#E8EFF6', 'name' => 'Brume', 'usage' => 'Sections alternées'],
            ['token' => 'snow', 'hex' => '#F6F9FC', 'name' => 'Neige', 'usage' => 'Fonds secondaires'],
            ['token' => 'line', 'hex' => '#E5E7EB', 'name' => 'Filet', 'usage' => 'Bordures, séparateurs'],
            ['token' => 'ink', 'hex' => '#1B2540', 'name' => 'Encre', 'usage' => 'Texte principal'],
            ['token' => 'muted', 'hex' => '#5E6B85', 'name' => 'Gris ardoise', 'usage' => 'Texte secondaire'],
            ['token' => 'subtle', 'hex' => '#8A94A8', 'name' => 'Gris doux', 'usage' => 'Icônes, mentions'],
            ['token' => 'red', 'hex' => '#E02020', 'name' => 'Rouge', 'usage' => 'Badge « Nouveau », erreurs'],
            ['token' => 'whatsapp', 'hex' => '#1DA851', 'name' => 'Vert WhatsApp', 'usage' => 'Contact WhatsApp uniquement'],
        ],
        'icons' => ['search', 'menu', 'close', 'arrow-right', 'arrow-left', 'arrow-up-right', 'caret-down', 'caret-left', 'caret-right', 'filters', 'view-grid', 'view-list', 'view-map', 'play', 'pause', 'plus', 'minus', 'check', 'info', 'share', 'images', 'camera', 'calendar', 'heart', 'phone', 'whatsapp', 'mail', 'verified', 'shield', 'pin', 'user', 'house', 'buildings', 'office', 'store', 'warehouse', 'factory', 'land', 'bed', 'key', 'area', 'land-area', 'rooms', 'bath', 'car', 'pool', 'garden', 'air-conditioning', 'generator', 'water', 'wifi', 'cctv', 'elevator', 'title-deed'],
    ],
];
