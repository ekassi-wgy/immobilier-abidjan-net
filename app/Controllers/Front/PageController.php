<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\FaqContent;

/**
 * Pages éditoriales et légales (lot 1.11) : /a-propos, /mentions-legales…
 *
 * Le contenu vient de la table `pages` ; sa rédaction depuis le back-office arrive au lot 2.2.
 * Une page non publiée répond 404 et son lien n'apparaît pas dans le pied de page : tant que le
 * client n'a pas fourni ses textes légaux, le site ne sert aucune page vide.
 */
final class PageController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $site = $this->site();
        $page = $this->app->pages()->find($slug, $site->id, locale()) ?? throw new HttpException(404);

        $title = (string) $page['title'];

        // FAQ : questions (h3) et réponses présentées en accordéon, avec données structurées FAQPage.
        // Un contenu sans question reconnue s'affiche comme une page ordinaire.
        $faq = ($page['code'] ?? null) === 'faq' ? FaqContent::parse((string) $page['content']) : [];

        return $this->page('front/layouts/app', $faq !== [] ? 'front/pages/faq' : 'front/pages/page', [
            'page' => $page,
            'groups' => $faq,
        ], [
            'schema' => $faq !== [] ? FaqContent::schema($faq) : null,
            'title' => !empty($page['meta_title']) ? (string) $page['meta_title'] : $title,
            'description' => !empty($page['meta_description'])
                ? (string) $page['meta_description']
                : mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $page['content'])) ?? ''), 0, 300),
            'canonical' => absolute_url($slug),
        ]);
    }
}
