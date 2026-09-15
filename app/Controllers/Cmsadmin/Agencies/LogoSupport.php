<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Agencies;

use App\Core\Request;
use Throwable;

/**
 * Logo d'une agence : contrôle du fichier envoyé, enregistrement en WebP, remplacement et retrait.
 * Partagé par la fiche agence (équipe) et le profil de l'agence (responsable).
 */
trait LogoSupport
{
    private const LOGO_MAX_BYTES = 2 * 1024 * 1024;

    /** @return string|null Clé de traduction de l'erreur, 'none' si aucun fichier envoyé, null si le fichier est valide */
    private function checkLogo(Request $request): ?string
    {
        return $this->app->images()->check($request->file('logo'), self::LOGO_MAX_BYTES);
    }

    /** Enregistre le nouveau logo et supprime le précédent (échec d'envoi : message flash, sans bloquer). */
    private function storeLogo(Request $request, int $agencyId, ?string $previous): void
    {
        $images = $this->app->images();
        try {
            $path = $images->storeWebp((array) $request->file('logo'), strtolower((string) site()?->country->iso2) . '/agences/' . $agencyId, 'logo', 480, 480, 88);
        } catch (Throwable $exception) {
            $this->app->logger()->exception($exception, ['agency_id' => $agencyId]);
            $this->flash('error', __('upload.failed'));

            return;
        }
        $this->app->agencies()->updateLogo($agencyId, $path);
        $images->delete($previous);
    }

    /** Retrait du logo actuel demandé dans le formulaire. */
    private function removeLogo(int $agencyId, ?string $current): void
    {
        if ($current === null) {
            return;
        }
        $this->app->images()->delete($current);
        $this->app->agencies()->updateLogo($agencyId, null);
    }
}
