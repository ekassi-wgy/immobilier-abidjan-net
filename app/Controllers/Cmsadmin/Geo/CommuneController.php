<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Geo;

/** Référentiel géographique : communes. */
final class CommuneController extends GeoController
{
    protected function level(): string
    {
        return 'commune';
    }

    protected function path(): string
    {
        return 'communes';
    }
}
