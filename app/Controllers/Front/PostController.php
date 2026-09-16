<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Support\Paginator;

/**
 * Actualités immobilières (lot 2.2) : /actualites et /actualites/{slug}
 *
 * Un article n'est public que s'il est publié **et** que sa date de publication est passée :
 * une date future permet de préparer un article à l'avance.
 */
final class PostController extends Controller
{
    private const PER_PAGE = 9;

    public function index(Request $request): Response
    {
        $site = $this->site();
        $content = $this->app->content();

        $total = $content->publishedPosts($site->id, locale(), 0, 0)['total'];
        $paginator = Paginator::fromRequest($request, $total, self::PER_PAGE);
        if ($total > 0 && (int) $request->query('page', 1) > $paginator->pages) {
            throw new HttpException(404);
        }

        return $this->page('front/layouts/app', 'front/pages/posts', [
            'posts' => $content->publishedPosts($site->id, locale(), self::PER_PAGE, $paginator->offset)['rows'],
            'total' => $total,
            'paginator' => $paginator,
            'baseUrl' => 'actualites',
            'query' => [],
        ], [
            'title' => __('front.posts.meta_title'),
            'description' => __('front.posts.meta_description', ['site' => $site->name]),
            'canonical' => absolute_url('actualites'),
            'noindex' => $total === 0,
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $site = $this->site();
        $post = $this->app->content()->publishedPost($slug, $site->id, locale()) ?? throw new HttpException(404);

        $cover = !empty($post['cover_image_path']) ? (string) $post['cover_image_path'] : null;

        return $this->page('front/layouts/app', 'front/pages/post', [
            'post' => $post,
            'cover' => $cover,
        ], [
            'title' => !empty($post['meta_title']) ? (string) $post['meta_title'] : (string) $post['title'],
            'description' => !empty($post['meta_description'])
                ? (string) $post['meta_description']
                : mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($post['excerpt'] ?: $post['content']))) ?? ''), 0, 300),
            'canonical' => absolute_url('actualites/' . $slug),
            'ogImage' => $cover !== null ? absolute_url($cover) : null,
            'preloadImage' => $cover !== null ? url($cover) : null,
            'schema' => $this->schema($post, $cover),
        ]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function schema(array $post, ?string $cover): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => (string) $post['title'],
            'datePublished' => (string) $post['published_at'],
            'dateModified' => $post['updated_at'] !== null ? (string) $post['updated_at'] : (string) $post['published_at'],
            'author' => !empty($post['author']) ? ['@type' => 'Person', 'name' => (string) $post['author']] : null,
            'image' => $cover !== null ? absolute_url($cover) : null,
            'publisher' => ['@type' => 'Organization', 'name' => site()?->name ?? ''],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
