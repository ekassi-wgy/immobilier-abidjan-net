<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Properties;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Site;

/**
 * Accès aux annonces dans le périmètre de l'utilisateur : pays du site, et agence pour un compte agence.
 */
trait PropertySupport
{
    private function site(): Site
    {
        return site() ?? throw new HttpException(403);
    }

    /** Agence du compte connecté (null pour l'équipe, qui voit toutes les annonces du pays). */
    private function scopeAgency(Request $request): ?int
    {
        $user = $this->user($request);

        return $user->isAgency() ? (int) $user->agencyId : null;
    }

    /** @return array<string, mixed> */
    private function findProperty(Request $request, string $reference): array
    {
        if (preg_match('/^[A-Z]{2,10}-\d{1,10}$/', $reference) !== 1) {
            throw new HttpException(404);
        }

        return $this->app->properties()->findByReference($reference, $this->site()->country->id, $this->scopeAgency($request))
            ?? throw new HttpException(404);
    }

    private function requireStaff(Request $request): void
    {
        if (!$this->user($request)->isStaff()) {
            throw new HttpException(403);
        }
    }

    /** URL d'une image dans une taille (400, 800, 1600). */
    private function imageUrl(string $base, int $width = 400): string
    {
        return url($base . '-' . $width . '.webp');
    }
}
