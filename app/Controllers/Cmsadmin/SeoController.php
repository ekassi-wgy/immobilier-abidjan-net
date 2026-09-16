<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\SeoRepository;
use App\Support\Paginator;
use App\Support\Validator;

/**
 * Référencement (lot 2.1). Réservé au Super Admin, limité au site courant.
 *
 * Deux outils :
 *  - **Balises méta** (`seo_meta`) : reprendre le titre, la description, l'image de partage ou
 *    l'indexation d'une URL précise, sans toucher au code. Une valeur laissée vide ne remplace
 *    rien : la page garde ce que le contrôleur calcule.
 *  - **Redirections** (`redirects`) : ne pas perdre le référencement d'une URL changée. Le code
 *    410 marque une page définitivement retirée.
 *
 * Une redirection dont la source est aussi la cible, ou qui pointe vers une autre redirection,
 * est refusée : on ne crée pas de boucle.
 */
final class SeoController extends Controller
{
    private const PER_PAGE = 25;

    // -- Balises méta -------------------------------------------------------------------

    public function index(Request $request): Response
    {
        [$site] = $this->scope($request);
        $search = trim((string) $request->query('q', ''));

        $total = $this->app->seo()->paginateMeta($site->id, $search, 0, 0)['total'];
        $paginator = Paginator::fromRequest($request, $total, self::PER_PAGE);
        $page = $this->app->seo()->paginateMeta($site->id, $search, self::PER_PAGE, $paginator->offset);

        return $this->render($request, 'seo/index', [
            'rows' => $page['rows'],
            'search' => $search,
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('seo.title'),
            'activeMenu' => 'seo.meta',
        ]);
    }

    public function create(Request $request): Response
    {
        $this->scope($request);

        return $this->metaForm($request, null, ['path' => (string) $request->query('path', ''), 'noindex' => 0]);
    }

    public function edit(Request $request, string $id): Response
    {
        [$site] = $this->scope($request);
        $row = $this->app->seo()->findMeta((int) $id, $site->id) ?? throw new HttpException(404);

        return $this->metaForm($request, (int) $id, $row);
    }

    public function store(Request $request): Response
    {
        return $this->saveMeta($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        return $this->saveMeta($request, (int) $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        [$site] = $this->scope($request);
        $row = $this->app->seo()->findMeta((int) $id, $site->id) ?? throw new HttpException(404);

        $this->app->seo()->deleteMeta((int) $id, $site->id);
        $this->log($request, 'seo.meta_deleted', 'seo_meta', (int) $id, (string) $row['path']);
        $this->flash('success', __('seo.flash.meta_deleted'));

        return $this->backTo($request, 'seo');
    }

    // -- Redirections -------------------------------------------------------------------

    public function redirects(Request $request): Response
    {
        [$site] = $this->scope($request);
        $search = trim((string) $request->query('q', ''));

        $total = $this->app->seo()->paginateRedirects($site->id, $search, 0, 0)['total'];
        $paginator = Paginator::fromRequest($request, $total, self::PER_PAGE);
        $page = $this->app->seo()->paginateRedirects($site->id, $search, self::PER_PAGE, $paginator->offset);

        return $this->render($request, 'seo/redirects', [
            'rows' => $page['rows'],
            'search' => $search,
            'pagination' => $paginator->toArray(),
            'codes' => SeoRepository::CODES,
        ], [
            'title' => __('seo.redirects.title'),
            'activeMenu' => 'seo.redirects',
        ]);
    }

    public function createRedirect(Request $request): Response
    {
        $this->scope($request);

        return $this->redirectForm($request, null, ['http_code' => 301, 'is_active' => 1]);
    }

    public function editRedirect(Request $request, string $id): Response
    {
        [$site] = $this->scope($request);
        $row = $this->app->seo()->findRedirect((int) $id, $site->id) ?? throw new HttpException(404);

        return $this->redirectForm($request, (int) $id, $row);
    }

    public function storeRedirect(Request $request): Response
    {
        return $this->saveRedirect($request, null);
    }

    public function updateRedirect(Request $request, string $id): Response
    {
        return $this->saveRedirect($request, (int) $id);
    }

    public function destroyRedirect(Request $request, string $id): Response
    {
        [$site] = $this->scope($request);
        $row = $this->app->seo()->findRedirect((int) $id, $site->id) ?? throw new HttpException(404);

        $this->app->seo()->deleteRedirect((int) $id, $site->id);
        $this->log($request, 'seo.redirect_deleted', 'redirect', (int) $id, (string) $row['source_path']);
        $this->flash('success', __('seo.flash.redirect_deleted'));

        return $this->backTo($request, 'seo/redirections');
    }

    // -- Interne ------------------------------------------------------------------------

    /** @return array{0: \App\Models\Site} */
    private function scope(Request $request): array
    {
        if (!$this->user($request)->isSuperAdmin()) {
            throw new HttpException(403);
        }

        return [site() ?? throw new HttpException(403)];
    }

    /** @param array<string, mixed> $values */
    private function metaForm(Request $request, ?int $id, array $values, array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'seo/form', [
            'id' => $id,
            'values' => $values,
            'errors' => $errors,
        ], [
            'title' => __($id === null ? 'seo.create' : 'seo.edit'),
            'activeMenu' => 'seo.meta',
        ], $status);
    }

    private function saveMeta(Request $request, ?int $id): Response
    {
        [$site] = $this->scope($request);
        $seo = $this->app->seo();
        $before = $id !== null ? ($seo->findMeta($id, $site->id) ?? throw new HttpException(404)) : [];

        $v = new Validator($request->all());
        $v->required('path')
            ->maxLength('path', 255)
            ->maxLength('meta_title', 190)
            ->maxLength('meta_description', 320)
            ->maxLength('og_image_path', 255);

        $path = $seo->normalize($v->string('path'));
        $v->rule('path', !$seo->metaPathExists($site->id, $path, $id), __('seo.errors.path_taken'));

        if ($v->fails()) {
            return $this->metaForm($request, $id, $request->all(), $v->errors(), 422);
        }

        $data = [
            'path' => $path,
            'meta_title' => $v->nullableString('meta_title'),
            'meta_description' => $v->nullableString('meta_description'),
            'og_image_path' => $v->nullableString('og_image_path'),
            'intro_text' => $v->nullableString('intro_text'),
            'noindex' => $v->bool('noindex') ? 1 : 0,
        ];
        $newId = $seo->saveMeta($id, $site->id, $data);

        $this->log($request, $id === null ? 'seo.meta_created' : 'seo.meta_updated', 'seo_meta', $newId, $path, $this->diff($before, $data));
        $this->flash('success', __($id === null ? 'seo.flash.meta_created' : 'seo.flash.meta_updated'));

        return $this->backTo($request, 'seo');
    }

    /** @param array<string, mixed> $values */
    private function redirectForm(Request $request, ?int $id, array $values, array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'seo/redirect-form', [
            'id' => $id,
            'values' => $values,
            'errors' => $errors,
            'codes' => SeoRepository::CODES,
        ], [
            'title' => __($id === null ? 'seo.redirects.create' : 'seo.redirects.edit'),
            'activeMenu' => 'seo.redirects',
        ], $status);
    }

    private function saveRedirect(Request $request, ?int $id): Response
    {
        [$site] = $this->scope($request);
        $seo = $this->app->seo();
        $before = $id !== null ? ($seo->findRedirect($id, $site->id) ?? throw new HttpException(404)) : [];

        $v = new Validator($request->all());
        $v->required('source_path', 'target_path')
            ->maxLength('source_path', 255)
            ->maxLength('target_path', 255)
            ->in('http_code', SeoRepository::CODES);

        $source = $seo->normalize($v->string('source_path'));
        $target = $v->string('target_path');
        $code = (int) ($v->int('http_code') ?: 301);

        $v->rule('source_path', !$seo->redirectSourceExists($site->id, $source, $id), __('seo.errors.source_taken'));
        // Une redirection vers elle-même boucle indéfiniment ; 410 n'a pas de cible à comparer.
        $v->rule('target_path', $code === 410 || $seo->normalize($target) !== $source, __('seo.errors.loop'));
        // Enchaîner deux redirections coûte un aller-retour de plus aux moteurs : on l'interdit.
        $v->rule(
            'target_path',
            $code === 410 || preg_match('#^https?://#i', $target) === 1 || $seo->redirect($seo->normalize($target), $site->id) === null,
            __('seo.errors.chain')
        );

        if ($v->fails()) {
            return $this->redirectForm($request, $id, $request->all(), $v->errors(), 422);
        }

        $data = [
            'source_path' => $source,
            'target_path' => $code === 410 ? $source : $target,
            'http_code' => $code,
            'is_active' => $v->bool('is_active') ? 1 : 0,
        ];
        $newId = $seo->saveRedirect($id, $site->id, $data);

        $this->log($request, $id === null ? 'seo.redirect_created' : 'seo.redirect_updated', 'redirect', $newId, $source . ' → ' . $data['target_path'], $this->diff($before, $data));
        $this->flash('success', __($id === null ? 'seo.flash.redirect_created' : 'seo.flash.redirect_updated'));

        return $this->backTo($request, 'seo/redirections');
    }
}
