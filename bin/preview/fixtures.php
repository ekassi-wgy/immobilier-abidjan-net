<?php

declare(strict_types=1);

/**
 * Données fictives pour la prévisualisation du back-office (bin/preview/cmsadmin.php).
 * Noms d'agences et de personnes inventés.
 */

$communes = [1 => 'Cocody', 2 => 'Plateau', 3 => 'Marcory', 4 => 'Yopougon', 5 => 'Koumassi', 6 => 'Port-Bouët', 7 => 'Bingerville', 8 => 'Grand-Bassam', 9 => 'Assinie-Mafia'];
$agencies = [1 => 'Lagune Conseil Immobilier', 2 => 'Atlantique Habitat', 3 => 'Maison d’Ébène', 4 => 'Ivoire Clés', 5 => 'Plateau Business Immo'];

$properties = [
    ['reference' => 'IAN-24531', 'title' => 'Villa duplex 6 pièces avec piscine', 'category' => 'Villa', 'transaction' => 'Vente', 'commune' => 'Cocody', 'district' => 'Riviera Golf', 'price' => 385000000, 'currency' => 'FCFA', 'agency' => 'Lagune Conseil Immobilier', 'status' => 'pending', 'updated' => 'il y a 2 h', 'featured' => false],
    ['reference' => 'IAN-24528', 'title' => 'Appartement 4 pièces haut standing', 'category' => 'Appartement', 'transaction' => 'Location', 'period' => 'par mois', 'commune' => 'Marcory', 'district' => 'Zone 4', 'price' => 1200000, 'currency' => 'FCFA', 'agency' => 'Atlantique Habitat', 'status' => 'pending', 'updated' => 'il y a 5 h', 'featured' => false],
    ['reference' => 'IAN-24518', 'title' => 'Plateau de bureaux 320 m² vue lagune', 'category' => 'Bureau', 'transaction' => 'Location', 'period' => 'par mois', 'commune' => 'Plateau', 'district' => 'Avenue Chardy', 'price' => 6400000, 'currency' => 'FCFA', 'agency' => 'Plateau Business Immo', 'status' => 'published', 'updated' => 'hier', 'featured' => true],
    ['reference' => 'IAN-24507', 'title' => 'Terrain 1 000 m² avec ACD', 'category' => 'Terrain nu', 'transaction' => 'Vente', 'commune' => 'Bingerville', 'district' => 'Cité Eloi', 'price' => 45000000, 'currency' => 'FCFA', 'agency' => 'Ivoire Clés', 'status' => 'published', 'updated' => '12 sept.', 'featured' => false],
    ['reference' => 'IAN-24499', 'title' => 'Résidence de vacances pieds dans l’eau', 'category' => 'Résidence de vacances', 'transaction' => 'Location meublée', 'period' => 'par nuit', 'commune' => 'Assinie-Mafia', 'district' => 'Bord de lagune', 'price' => 150000, 'currency' => 'FCFA', 'agency' => 'Maison d’Ébène', 'status' => 'published', 'updated' => '11 sept.', 'featured' => true],
    ['reference' => 'IAN-24486', 'title' => 'Studio meublé proche ambassades', 'category' => 'Appartement', 'transaction' => 'Location meublée', 'period' => 'par mois', 'commune' => 'Cocody', 'district' => 'Ambassades', 'price' => 450000, 'currency' => 'FCFA', 'agency' => 'Atlantique Habitat', 'status' => 'rejected', 'updated' => '10 sept.', 'featured' => false],
    ['reference' => 'IAN-24470', 'title' => 'Magasin 450 m² avec quai de chargement', 'category' => 'Entrepôt', 'transaction' => 'Bail commercial', 'period' => 'par mois', 'commune' => 'Koumassi', 'district' => 'Zone industrielle', 'price' => 2800000, 'currency' => 'FCFA', 'agency' => 'Plateau Business Immo', 'status' => 'expired', 'updated' => '2 sept.', 'featured' => false],
    ['reference' => 'IAN-24455', 'title' => 'Villa basse 4 pièces sur 600 m²', 'category' => 'Villa', 'transaction' => 'Vente', 'commune' => 'Cocody', 'district' => 'Angré 8e tranche', 'price' => 165000000, 'currency' => 'FCFA', 'agency' => 'Lagune Conseil Immobilier', 'status' => 'archived', 'updated' => '28 août', 'featured' => false],
    ['reference' => 'IAN-24441', 'title' => 'Appartement 3 pièces avec terrasse', 'category' => 'Appartement', 'transaction' => 'Location', 'period' => 'par mois', 'commune' => 'Cocody', 'district' => 'Deux-Plateaux Vallon', 'price' => 650000, 'currency' => 'FCFA', 'agency' => 'Ivoire Clés', 'status' => 'unpublished', 'updated' => '25 août', 'featured' => false],
];

return [
    'shared' => static function (string $role): array {
        $users = [
            'super_admin' => ['name' => 'Emmanuel Kassi', 'email' => 'admin@immobilier.abidjan.net', 'role' => 'super_admin', 'role_label' => 'Super administrateur'],
            'country_admin' => ['name' => 'Awa Koné', 'email' => 'moderation@immobilier.abidjan.net', 'role' => 'country_admin', 'role_label' => 'Admin Côte d’Ivoire'],
            'agency' => ['name' => 'Serge Yao', 'email' => 'contact@lagune-conseil.ci', 'role' => 'agency', 'role_label' => 'Agence partenaire', 'agency_name' => 'Lagune Conseil Immobilier'],
        ];

        return [
            'user' => $users[$role],
            'site' => ['name' => 'immobilier.abidjan.net', 'country' => 'Côte d’Ivoire', 'currency' => 'FCFA', 'url' => '/'],
            'counters' => $role === 'agency'
                ? ['pending_properties' => 2, 'new_leads' => 5]
                : ['pending_properties' => 12, 'new_leads' => 9, 'partner_requests' => 3],
            'csrfToken' => 'dev-csrf-token',
        ];
    },

    'dashboard' => static function (string $role) use ($properties): array {
        $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Africa/Abidjan');
        $labels = $views = $leads = [];
        $start = new DateTimeImmutable('-29 days');
        mt_srand(42);
        for ($i = 0; $i < 30; $i++) {
            $day = $start->modify("+{$i} days");
            $labels[] = (new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Africa/Abidjan', null, 'd MMM'))->format($day);
            $weekend = in_array((int) $day->format('N'), [6, 7], true);
            $base = 1450 + $i * 22 + ($weekend ? 380 : 0);
            $views[] = $base + mt_rand(-160, 160);
            $leads[] = (int) round($base / 95) + mt_rand(-3, 4);
        }
        $isAgency = $role === 'agency';
        $scale = $isAgency ? 0.08 : 1;
        $views = array_map(static fn (int $v): int => (int) round($v * $scale), $views);
        $leads = array_map(static fn (int $v): int => max(0, (int) round($v * ($isAgency ? 0.12 : 1))), $leads);

        return [
            'today' => $formatter->format(new DateTimeImmutable()),
            'stats' => $isAgency
                ? [
                    ['label' => 'Annonces publiées', 'value' => 38, 'hint' => '4 ce mois', 'tone' => 'up'],
                    ['label' => 'En attente', 'value' => 2, 'hint' => 'examen sous 24 h ouvrées'],
                    ['label' => 'Vues des fiches', 'value' => array_sum($views), 'hint' => '+9 % sur 30 jours', 'tone' => 'up'],
                    ['label' => 'Contacts reçus', 'value' => array_sum($leads), 'hint' => '5 non lus', 'link' => ['label' => 'Répondre', 'url' => 'contacts']],
                ]
                : [
                    ['label' => 'Annonces publiées', 'value' => 1284, 'hint' => '+38 ce mois', 'tone' => 'up'],
                    ['label' => 'À valider', 'value' => 12, 'hint' => 'dont 3 depuis plus de 48 h', 'tone' => 'alert', 'link' => ['label' => 'Examiner', 'url' => 'annonces?statut=en-attente']],
                    ['label' => 'Agences actives', 'value' => 46, 'hint' => '3 demandes de partenariat'],
                    ['label' => 'Contacts sur 30 jours', 'value' => array_sum($leads), 'hint' => '+14 % vs mois précédent', 'tone' => 'up'],
                ],
            'chart' => [
                'labels' => $labels,
                'views' => $views,
                'leads' => $leads,
                'totals' => ['views' => array_sum($views), 'leads' => array_sum($leads)],
            ],
            'pendingProperties' => array_map(
                static fn (array $p): array => $p + ['submitted' => $p['updated']],
                array_values(array_filter($properties, static fn (array $p): bool => $p['status'] === 'pending'))
            ) + [],
            'topProperties' => [
                ['reference' => 'IAN-24518', 'title' => 'Plateau de bureaux 320 m² vue lagune', 'commune' => 'Plateau', 'views' => 2140, 'leads' => 41],
                ['reference' => 'IAN-24499', 'title' => 'Résidence de vacances pieds dans l’eau', 'commune' => 'Assinie-Mafia', 'views' => 1875, 'leads' => 63],
                ['reference' => 'IAN-24507', 'title' => 'Terrain 1 000 m² avec ACD', 'commune' => 'Bingerville', 'views' => 1432, 'leads' => 28],
                ['reference' => 'IAN-24390', 'title' => 'Penthouse 5 pièces, terrasse panoramique', 'commune' => 'Cocody', 'views' => 1210, 'leads' => 19],
                ['reference' => 'IAN-24377', 'title' => 'Maison jumelée 4 pièces', 'commune' => 'Yopougon', 'views' => 986, 'leads' => 22],
            ],
            'latestLeads' => [
                ['name' => 'Mariam Traoré', 'property' => 'Villa duplex 6 pièces · Riviera Golf', 'channel' => 'whatsapp', 'received' => '12 min'],
                ['name' => 'Jean-Marc Kouadio', 'property' => 'Plateau de bureaux 320 m² · Plateau', 'channel' => 'form', 'received' => '1 h'],
                ['name' => 'Fatou Diallo', 'property' => 'Résidence de vacances · Assinie', 'channel' => 'phone', 'received' => '3 h'],
                ['name' => 'Olivier N’Guessan', 'property' => 'Terrain 1 000 m² · Bingerville', 'channel' => 'form', 'received' => 'hier'],
                ['name' => 'Aïcha Bamba', 'property' => 'Appartement 4 pièces · Zone 4', 'channel' => 'whatsapp', 'received' => 'hier'],
            ],
        ];
    },

    'properties' => static function (string $role, array $query) use ($properties, $communes, $agencies): array {
        $slugs = ['' => null, 'en-attente' => 'pending', 'publiees' => 'published', 'rejetees' => 'rejected', 'expirees' => 'expired', 'archivees' => 'archived'];
        $statut = array_key_exists($query['statut'] ?? '', $slugs) ? ($query['statut'] ?? '') : '';
        $list = $role === 'agency'
            ? array_values(array_filter($properties, static fn (array $p): bool => $p['agency'] === 'Lagune Conseil Immobilier'))
            : $properties;
        if ($slugs[$statut] !== null) {
            $list = array_values(array_filter($list, static fn (array $p): bool => $p['status'] === $slugs[$statut]));
        }
        if (($query['vide'] ?? '') === '1') {
            $list = [];
        }

        return [
            'statusTabs' => [
                ['slug' => '', 'label' => 'Toutes', 'count' => $role === 'agency' ? 41 : 1438],
                ['slug' => 'en-attente', 'label' => 'À valider', 'count' => $role === 'agency' ? 2 : 12],
                ['slug' => 'publiees', 'label' => 'Publiées', 'count' => $role === 'agency' ? 38 : 1284],
                ['slug' => 'rejetees', 'label' => 'Rejetées', 'count' => $role === 'agency' ? 0 : 17],
                ['slug' => 'expirees', 'label' => 'Expirées', 'count' => $role === 'agency' ? 1 : 61],
                ['slug' => 'archivees', 'label' => 'Archivées', 'count' => $role === 'agency' ? 0 : 64],
            ],
            'filters' => [
                'q' => (string) ($query['q'] ?? ''),
                'statut' => $statut,
                'categorie' => (string) ($query['categorie'] ?? ''),
                'commune' => (string) ($query['commune'] ?? ''),
                'agence' => (string) ($query['agence'] ?? ''),
            ],
            'categories' => [1 => 'Appartement', 2 => 'Villa', 3 => 'Terrain nu', 4 => 'Bureau', 5 => 'Local commercial', 6 => 'Entrepôt', 7 => 'Résidence de vacances'],
            'communes' => $communes,
            'agencies' => $role === 'agency' ? [] : $agencies,
            'properties' => $list,
            'pagination' => ['page' => max(1, (int) ($query['page'] ?? 1)), 'pages' => $list === [] ? 1 : 160, 'total' => $list === [] ? 0 : 1438, 'perPage' => 9],
        ];
    },

    'form' => static function (string $role, bool $edit, bool $withErrors) use ($communes): array {
        return [
            'property' => $edit ? [
                'reference' => 'IAN-24531', 'title' => 'Villa duplex 6 pièces avec piscine', 'status' => 'pending', 'updated' => 'aujourd’hui à 09:42',
                'transaction' => 'sale', 'category_id' => 21, 'commune_id' => 1, 'district_id' => 2, 'price' => 385000000, 'negotiable' => true,
                'description' => 'Belle villa contemporaine sur 850 m² de terrain, dans une rue calme de la Riviera Golf.',
                'living_area' => 420, 'land_area' => 850, 'rooms' => 6, 'bedrooms' => 5, 'bathrooms' => 5, 'standing' => 'high', 'condition' => 'new',
                'features' => ['1', '2', '4', '6', '7'], 'title_type' => 'tf', 'agent_name' => 'Serge Yao', 'agent_phone' => '+225 07 08 09 10 11',
            ] : null,
            'old' => [],
            'errors' => $withErrors ? ['title' => 'Le titre est obligatoire.', 'price' => 'Indiquez un prix supérieur à 0.'] : [],
            'transactions' => ['sale' => 'Vente', 'rent' => 'Location', 'furnished' => 'Location meublée', 'commercial_lease' => 'Bail commercial'],
            'categories' => [
                'Résidentiel' => [20 => 'Appartement', 21 => 'Villa / Maison individuelle', 22 => 'Maison jumelée', 23 => 'Immeuble résidentiel', 24 => 'Chambre / Colocation'],
                'Terrains' => [30 => 'Terrain nu', 31 => 'Terrain agricole', 32 => 'Terrain industriel'],
                'Commercial & Bureaux' => [40 => 'Bureau / Plateau', 41 => 'Local commercial / Boutique', 42 => 'Magasin / Entrepôt'],
                'Hôtellerie & Tourisme' => [50 => 'Hôtel / Résidence hôtelière', 51 => 'Maison d’hôtes', 52 => 'Résidence de vacances'],
            ],
            'communes' => $communes,
            'districts' => [1 => 'Riviera 3', 2 => 'Riviera Golf', 3 => 'Angré', 4 => 'Deux-Plateaux', 5 => 'Ambassades', 6 => 'M’Pouto'],
            'features' => [1 => 'Piscine', 2 => 'Jardin', 3 => 'Balcon / terrasse', 4 => 'Garage', 5 => 'Climatisation', 6 => 'Gardiennage', 7 => 'Groupe électrogène', 8 => 'Forage', 9 => 'Caméras de surveillance', 10 => 'Ascenseur', 11 => 'Fibre optique', 12 => 'Cuisine équipée'],
            'titleTypes' => ['tf' => 'Titre foncier (TF)', 'acd' => 'Arrêté de concession définitive (ACD)', 'adu' => 'Attestation de droit d’usage (ADU)', 'letter' => 'Lettre d’attribution', 'certificate' => 'Certificat de propriété', 'customary' => 'Coutumier'],
            'standings' => ['economic' => 'Économique', 'mid' => 'Moyen standing', 'high' => 'Haut standing'],
        ];
    },
];
