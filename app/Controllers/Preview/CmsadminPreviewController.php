<?php

declare(strict_types=1);

namespace App\Controllers\Preview;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * PROVISOIRE — écran de contrôle du back-office, déclaré uniquement si app.preview (APP_ENV=local).
 *
 * Tous les écrans à données fictives ont été remplacés par leur module réel : annonces (lot 1.6),
 * espace agence (lot 1.7), tableau de bord de l'équipe (lot 1.12). Il ne reste que la page 500,
 * qu'aucune navigation normale ne permet d'atteindre alors qu'elle doit rester vérifiable.
 */
final class CmsadminPreviewController extends Controller
{
    public function serverError(Request $request): Response
    {
        return $this->page('cmsadmin/layouts/auth', 'cmsadmin/pages/errors/error', ['code' => 500], [
            'title' => __('errors.500.title'),
            'variant' => 'center',
        ], 500);
    }
}
