<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Accueil du site public.
 * Tant que la page d'accueil réelle n'est pas réalisée (lot 1.8), le site répond « en préparation »
 * (en local, la maquette est servie par Preview\FrontPreviewController).
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        throw new HttpException(503, headers: ['Retry-After' => '86400']);
    }
}
