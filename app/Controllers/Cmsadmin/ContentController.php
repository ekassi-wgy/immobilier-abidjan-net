<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ContentRepository;
use App\Support\Paginator;
use App\Support\Str;
use App\Support\Validator;

/**
 * Contenu éditorial (lot 2.2). Réservé au Super Admin, limité au site courant.
 *
 * Trois modules : **pages** (À propos, mentions légales… déjà servies publiquement depuis le
 * lot 1.11), **actualités** (blog public) et **bannières** (le hero de l'accueil les lit depuis
 * le lot 1.8).
 *
 * Deux règles propres à ce lot :
 *  - une **page système** (celles qui portent un `code`, attendues par le pied de page) ne se
 *    supprime pas : on la dépublie ;
 *  - toute écriture sur `pages` vide le cache des slugs publiés, dont dépendent les routes.
 */
final class ContentController extends Controller
{
    private const PER_PAGE = 25;

    /** Poids maximal d'une image de couverture ou de bannière. */
    private const MAX_IMAGE_MB = 8;

    // -- Pages ----------------------------------------------------------------------------

    public function pages(Request $request): Response
    {
        $site = $this->scope($request);
        $total = $this->app->content()->pages($site->id, locale(), 0, 0)['total'];
        $paginator = Paginator::fromRequest($request, $total, self::PER_PAGE);

        return $this->render($request, 'content/pages', [
            'rows' => $this->app->content()->pages($site->id, locale(), self::PER_PAGE, $paginator->offset)['rows'],
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('content.pages.title'),
            'activeMenu' => 'content.pages',
        ]);
    }

    public function createPage(Request $request): Response
    {
        $this->scope($request);

        return $this->pageForm($request, null, ['is_published' => 0]);
    }

    public function editPage(Request $request, string $id): Response
    {
        $site = $this->scope($request);
        $row = $this->app->content()->page((int) $id, $site->id) ?? throw new HttpException(404);

        return $this->pageForm($request, (int) $id, $row);
    }

    public function storePage(Request $request): Response
    {
        return $this->savePage($request, null);
    }

    public function updatePage(Request $request, string $id): Response
    {
        return $this->savePage($request, (int) $id);
    }

    public function destroyPage(Request $request, string $id): Response
    {
        $site = $this->scope($request);
        $content = $this->app->content();
        $row = $content->page((int) $id, $site->id) ?? throw new HttpException(404);

        if ($row['code'] !== null) {
            $this->flash('error', __('content.pages.system_page'));

            return $this->backTo($request, 'pages');
        }

        $content->deletePage((int) $id, $site->id);
        $this->app->pages()->flush();
        $this->log($request, 'page.deleted', 'page', (int) $id, (string) $row['title']);
        $this->flash('success', __('content.pages.flash.deleted'));

        return $this->backTo($request, 'pages');
    }

    // -- Actualités -----------------------------------------------------------------------

    public function posts(Request $request): Response
    {
        $site = $this->scope($request);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'statut' => (string) $request->query('statut', ''),
        ];

        $total = $this->app->content()->posts($site->id, locale(), $filters, 0, 0)['total'];
        $paginator = Paginator::fromRequest($request, $total, self::PER_PAGE);

        return $this->render($request, 'content/posts', [
            'rows' => $this->app->content()->posts($site->id, locale(), $filters, self::PER_PAGE, $paginator->offset)['rows'],
            'filters' => $filters,
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('content.posts.title'),
            'activeMenu' => 'content.posts',
        ]);
    }

    public function createPost(Request $request): Response
    {
        $this->scope($request);

        return $this->postForm($request, null, ['status' => 'draft']);
    }

    public function editPost(Request $request, string $id): Response
    {
        $site = $this->scope($request);
        $row = $this->app->content()->post((int) $id, $site->id) ?? throw new HttpException(404);

        return $this->postForm($request, (int) $id, $row);
    }

    public function storePost(Request $request): Response
    {
        return $this->savePost($request, null);
    }

    public function updatePost(Request $request, string $id): Response
    {
        return $this->savePost($request, (int) $id);
    }

    public function destroyPost(Request $request, string $id): Response
    {
        $site = $this->scope($request);
        $content = $this->app->content();
        $row = $content->post((int) $id, $site->id) ?? throw new HttpException(404);

        $content->deletePost((int) $id, $site->id);
        $this->app->images()->delete($row['cover_image_path'] !== null ? (string) $row['cover_image_path'] : null);
        $this->log($request, 'post.deleted', 'blog_post', (int) $id, (string) $row['title']);
        $this->flash('success', __('content.posts.flash.deleted'));

        return $this->backTo($request, 'actualites');
    }

    // -- Bannières ------------------------------------------------------------------------

    public function banners(Request $request): Response
    {
        $site = $this->scope($request);
        $placement = (string) $request->query('emplacement', '');

        $total = $this->app->content()->banners($site->id, $placement, 0, 0)['total'];
        $paginator = Paginator::fromRequest($request, $total, self::PER_PAGE);

        return $this->render($request, 'content/banners', [
            'rows' => $this->app->content()->banners($site->id, $placement, self::PER_PAGE, $paginator->offset)['rows'],
            'placement' => $placement,
            'placements' => ContentRepository::PLACEMENTS,
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('content.banners.title'),
            'activeMenu' => 'content.banners',
        ]);
    }

    public function createBanner(Request $request): Response
    {
        $this->scope($request);

        return $this->bannerForm($request, null, ['placement' => 'home_hero', 'is_active' => 1, 'sort_order' => 0]);
    }

    public function editBanner(Request $request, string $id): Response
    {
        $site = $this->scope($request);
        $row = $this->app->content()->banner((int) $id, $site->id) ?? throw new HttpException(404);

        return $this->bannerForm($request, (int) $id, $row);
    }

    public function storeBanner(Request $request): Response
    {
        return $this->saveBanner($request, null);
    }

    public function updateBanner(Request $request, string $id): Response
    {
        return $this->saveBanner($request, (int) $id);
    }

    public function destroyBanner(Request $request, string $id): Response
    {
        $site = $this->scope($request);
        $content = $this->app->content();
        $row = $content->banner((int) $id, $site->id) ?? throw new HttpException(404);

        $content->deleteBanner((int) $id, $site->id);
        $this->app->images()->delete((string) $row['image_path']);
        $this->log($request, 'banner.deleted', 'banner', (int) $id, (string) ($row['title'] ?? $row['placement']));
        $this->flash('success', __('content.banners.flash.deleted'));

        return $this->backTo($request, 'bannieres');
    }

    // -- Interne ------------------------------------------------------------------------

    private function scope(Request $request): \App\Models\Site
    {
        if (!$this->user($request)->isSuperAdmin()) {
            throw new HttpException(403);
        }

        return site() ?? throw new HttpException(403);
    }

    /** @param array<string, mixed> $values */
    private function pageForm(Request $request, ?int $id, array $values, array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'content/page-form', [
            'id' => $id,
            'values' => $values,
            'errors' => $errors,
            'isSystem' => ($values['code'] ?? null) !== null,
        ], [
            'title' => __($id === null ? 'content.pages.create' : 'content.pages.edit'),
            'activeMenu' => 'content.pages',
        ], $status);
    }

    private function savePage(Request $request, ?int $id): Response
    {
        $site = $this->scope($request);
        $content = $this->app->content();
        $before = $id !== null ? ($content->page($id, $site->id) ?? throw new HttpException(404)) : [];

        $input = $request->all();
        if (trim((string) ($input['slug'] ?? '')) === '') {
            $input['slug'] = Str::slug((string) ($input['title'] ?? ''), 190);
        }

        $v = new Validator($input);
        $v->required('title', 'slug', 'content')
            ->maxLength('title', 190)
            ->maxLength('meta_title', 190)
            ->maxLength('meta_description', 320)
            ->slug('slug');

        $slug = $v->string('slug');
        $v->rule('slug', !$content->pageSlugExists($site->id, locale(), $slug, $id), __('content.errors.slug_taken'));
        // Une page dont le slug reprend celui d'une transaction serait masquée par la recherche.
        $v->rule('slug', !$this->app->searchFilters()->transaction($slug), __('content.errors.slug_reserved'));

        if ($v->fails()) {
            return $this->pageForm($request, $id, $input + ['code' => $before['code'] ?? null], $v->errors(), 422);
        }

        $data = [
            'slug' => $slug,
            'title' => $v->string('title'),
            'content' => $v->string('content'),
            'meta_title' => $v->nullableString('meta_title'),
            'meta_description' => $v->nullableString('meta_description'),
            'is_published' => $v->bool('is_published') ? 1 : 0,
        ];
        $newId = $content->savePage($id, $site->id, $data, $this->user($request)->id);

        // Les routes publiques sont déclarées depuis la liste des slugs publiés.
        $this->app->pages()->flush();

        $this->log($request, $id === null ? 'page.created' : 'page.updated', 'page', $newId, $data['title'], $this->diff($before, $data));
        $this->flash('success', __($id === null ? 'content.pages.flash.created' : 'content.pages.flash.updated'));

        return $this->backTo($request, 'pages');
    }

    /** @param array<string, mixed> $values */
    private function postForm(Request $request, ?int $id, array $values, array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'content/post-form', [
            'id' => $id,
            'values' => $values,
            'errors' => $errors,
            'cover' => !empty($values['cover_image_path']) ? url((string) $values['cover_image_path']) : null,
        ], [
            'title' => __($id === null ? 'content.posts.create' : 'content.posts.edit'),
            'activeMenu' => 'content.posts',
        ], $status);
    }

    private function savePost(Request $request, ?int $id): Response
    {
        $site = $this->scope($request);
        $content = $this->app->content();
        $before = $id !== null ? ($content->post($id, $site->id) ?? throw new HttpException(404)) : [];

        $input = $request->all();
        if (trim((string) ($input['slug'] ?? '')) === '') {
            $input['slug'] = Str::slug((string) ($input['title'] ?? ''), 190);
        }

        $v = new Validator($input);
        $v->required('title', 'slug', 'content')
            ->maxLength('title', 190)
            ->maxLength('excerpt', 500)
            ->maxLength('meta_title', 190)
            ->maxLength('meta_description', 320)
            ->slug('slug')
            ->in('status', ['draft', 'published']);

        $slug = $v->string('slug');
        $v->rule('slug', !$content->postSlugExists($site->id, locale(), $slug, $id), __('content.errors.slug_taken'));

        $cover = $before['cover_image_path'] ?? null;
        $file = $request->file('cover');
        $error = $this->app->images()->check($file, self::MAX_IMAGE_MB * 1024 * 1024);
        if ($error !== null && $error !== 'none') {
            $v->add('cover', __($error, ['max' => self::MAX_IMAGE_MB . ' Mo']));
        }

        if ($v->fails()) {
            return $this->postForm($request, $id, $input + ['cover_image_path' => $cover], $v->errors(), 422);
        }

        if ($error === null) {
            $new = $this->app->images()->storeWebp($file, strtolower(site()->country->iso2) . '/actualites', 'post', 1600, 1067);
            $this->app->images()->delete($cover !== null ? (string) $cover : null);
            $cover = $new;
        }

        $status = $v->string('status') !== '' ? $v->string('status') : 'draft';
        $publishedAt = $before['published_at'] ?? null;
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = gmdate('Y-m-d H:i:s');
        }

        $data = [
            'slug' => $slug,
            'title' => $v->string('title'),
            'excerpt' => $v->nullableString('excerpt'),
            'content' => $v->string('content'),
            'cover_image_path' => $cover,
            'status' => $status,
            'published_at' => $status === 'published' ? $publishedAt : null,
            'meta_title' => $v->nullableString('meta_title'),
            'meta_description' => $v->nullableString('meta_description'),
        ];
        if ($id === null) {
            $data['author_user_id'] = $this->user($request)->id;
        }

        $newId = $content->savePost($id, $site->id, $data);
        $this->log($request, $id === null ? 'post.created' : 'post.updated', 'blog_post', $newId, $data['title'], $this->diff($before, $data));
        $this->flash('success', __($id === null ? 'content.posts.flash.created' : 'content.posts.flash.updated'));

        return $this->backTo($request, 'actualites');
    }

    /** @param array<string, mixed> $values */
    private function bannerForm(Request $request, ?int $id, array $values, array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'content/banner-form', [
            'id' => $id,
            'values' => $values,
            'errors' => $errors,
            'placements' => ContentRepository::PLACEMENTS,
            'image' => !empty($values['image_path']) ? url((string) $values['image_path']) : null,
        ], [
            'title' => __($id === null ? 'content.banners.create' : 'content.banners.edit'),
            'activeMenu' => 'content.banners',
        ], $status);
    }

    private function saveBanner(Request $request, ?int $id): Response
    {
        $site = $this->scope($request);
        $content = $this->app->content();
        $before = $id !== null ? ($content->banner($id, $site->id) ?? throw new HttpException(404)) : [];

        $v = new Validator($request->all());
        $v->required('placement')
            ->in('placement', ContentRepository::PLACEMENTS)
            ->maxLength('title', 190)
            ->maxLength('subtitle', 255)
            ->maxLength('caption', 120)
            ->maxLength('link_url', 255)
            ->integer('sort_order', 0, 999);

        $image = $before['image_path'] ?? null;
        $file = $request->file('image');
        $error = $this->app->images()->check($file, self::MAX_IMAGE_MB * 1024 * 1024);
        if ($error !== null && $error !== 'none') {
            $v->add('image', __($error, ['max' => self::MAX_IMAGE_MB . ' Mo']));
        }
        // Une bannière sans visuel n'a pas d'objet : l'image est obligatoire à la création.
        if ($id === null && $error === 'none') {
            $v->add('image', __('content.errors.image_required'));
        }

        if ($v->fails()) {
            return $this->bannerForm($request, $id, $request->all() + ['image_path' => $image], $v->errors(), 422);
        }

        if ($error === null) {
            $new = $this->app->images()->storeWebp($file, strtolower(site()->country->iso2) . '/bannieres', 'banner', 1920, 1280);
            $this->app->images()->delete($image !== null ? (string) $image : null);
            $image = $new;
        }

        $data = [
            'placement' => $v->string('placement'),
            'title' => $v->nullableString('title'),
            'subtitle' => $v->nullableString('subtitle'),
            'caption' => $v->nullableString('caption'),
            'image_path' => $image,
            'link_url' => $v->nullableString('link_url'),
            'starts_at' => $this->dateTime($request->input('starts_at')),
            'ends_at' => $this->dateTime($request->input('ends_at')),
            'is_active' => $v->bool('is_active') ? 1 : 0,
            'sort_order' => $v->int('sort_order'),
        ];

        $newId = $content->saveBanner($id, $site->id, $data);
        $this->log($request, $id === null ? 'banner.created' : 'banner.updated', 'banner', $newId, (string) ($data['title'] ?? $data['placement']), $this->diff($before, $data));
        $this->flash('success', __($id === null ? 'content.banners.flash.created' : 'content.banners.flash.updated'));

        return $this->backTo($request, 'bannieres');
    }

    /** Champ date natif (AAAA-MM-JJ) → début de journée UTC, ou null. */
    private function dateTime(mixed $value): ?string
    {
        $date = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date . ' 00:00:00' : null;
    }
}
