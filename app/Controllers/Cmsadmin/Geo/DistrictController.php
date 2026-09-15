<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Geo;

/** Référentiel géographique : quartiers. */
final class DistrictController extends GeoController
{
    protected function level(): string
    {
        return 'district';
    }

    protected function path(): string
    {
        return 'quartiers';
    }
}
