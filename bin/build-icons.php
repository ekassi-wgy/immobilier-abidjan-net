<?php

declare(strict_types=1);

/**
 * Génère le sprite SVG des icônes du site public à partir de Phosphor Icons (graisse « light », licence MIT).
 * Le téléchargement n'a lieu qu'au moment du build : le site sert ensuite public/assets/img/icons.svg en local.
 *
 * Usage : php bin/build-icons.php
 * Ajouter une icône : compléter $icons (nom Phosphor => identifiant court), relancer, commiter le sprite.
 * Dans une vue : <?= icon('search') ?>
 */

const PHOSPHOR_VERSION = '2.1.1';
const SOURCE = 'https://cdn.jsdelivr.net/npm/@phosphor-icons/core@' . PHOSPHOR_VERSION . '/assets/light/%s-light.svg';
const TARGET = __DIR__ . '/../public/assets/img/icons.svg';

$icons = [
    // Navigation & interface
    'magnifying-glass' => 'search',
    'list' => 'menu',
    'x' => 'close',
    'arrow-right' => 'arrow-right',
    'arrow-left' => 'arrow-left',
    'arrow-up-right' => 'arrow-up-right',
    'caret-down' => 'caret-down',
    'caret-left' => 'caret-left',
    'caret-right' => 'caret-right',
    'sliders-horizontal' => 'filters',
    'squares-four' => 'view-grid',
    'rows' => 'view-list',
    'map-trifold' => 'view-map',
    'play' => 'play',
    'pause' => 'pause',
    'plus' => 'plus',
    'minus' => 'minus',
    'check' => 'check',
    'info' => 'info',
    'share-network' => 'share',
    'link-simple' => 'link',
    'facebook-logo' => 'facebook',
    'file-pdf' => 'document',
    'play-circle' => 'video',
    'cube-focus' => 'tour-360',
    'images' => 'images',
    'camera' => 'camera',
    'calendar-blank' => 'calendar',
    // Contact & confiance
    'heart' => 'heart',
    'phone' => 'phone',
    'whatsapp-logo' => 'whatsapp',
    'envelope-simple' => 'mail',
    'seal-check' => 'verified',
    'shield-check' => 'shield',
    'map-pin' => 'pin',
    'user' => 'user',
    // Types de biens
    'house-line' => 'house',
    'buildings' => 'buildings',
    'building-office' => 'office',
    'storefront' => 'store',
    'warehouse' => 'warehouse',
    'factory' => 'factory',
    'tree-palm' => 'land',
    'bed' => 'bed',
    'key' => 'key',
    // Caractéristiques & équipements
    'ruler' => 'area',
    'selection' => 'land-area',
    'door-open' => 'rooms',
    'bathtub' => 'bath',
    'car' => 'car',
    'swimming-pool' => 'pool',
    'plant' => 'garden',
    'snowflake' => 'air-conditioning',
    'lightning' => 'generator',
    'drop' => 'water',
    'wifi-high' => 'wifi',
    'video-camera' => 'cctv',
    'elevator' => 'elevator',
    'scroll' => 'title-deed',
];

$context = stream_context_create(['http' => ['timeout' => 20, 'header' => "User-Agent: immobilier-abidjan-net-build\r\n"]]);
$symbols = [];

foreach ($icons as $source => $id) {
    $svg = @file_get_contents(sprintf(SOURCE, $source), false, $context);
    if ($svg === false || !preg_match('#<svg[^>]*viewBox="([^"]+)"[^>]*>(.*)</svg>#s', $svg, $match)) {
        fwrite(STDERR, "Icône introuvable : {$source}\n");
        exit(1);
    }
    $symbols[] = sprintf('<symbol id="i-%s" viewBox="%s">%s</symbol>', $id, $match[1], trim($match[2]));
}

$sprite = "<!-- Phosphor Icons " . PHOSPHOR_VERSION . " (light) — MIT License — https://phosphoricons.com -->\n"
    . '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor">' . "\n"
    . implode("\n", $symbols) . "\n</svg>\n";

file_put_contents(TARGET, $sprite);
printf("%d icônes → %s (%.1f Ko)\n", count($symbols), realpath(TARGET), filesize(TARGET) / 1024);
