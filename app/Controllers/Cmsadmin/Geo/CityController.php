<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Geo;

/** Référentiel géographique : villes. */
final class CityController extends GeoController
{
    protected function level(): string
    {
        return 'city';
    }

    protected function path(): string
    {
        return 'villes';
    }
}
