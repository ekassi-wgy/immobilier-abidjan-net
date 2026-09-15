<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;

/**
 * Pagination côté serveur : page demandée (?page=), bornée au nombre de pages.
 */
final class Paginator
{
    public readonly int $page;
    public readonly int $pages;
    public readonly int $offset;

    public function __construct(public readonly int $total, public readonly int $perPage, int $requestedPage)
    {
        $this->pages = max(1, (int) ceil($total / max(1, $perPage)));
        $this->page = min(max(1, $requestedPage), $this->pages);
        $this->offset = ($this->page - 1) * $perPage;
    }

    public static function fromRequest(Request $request, int $total, int $perPage = 25): self
    {
        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT);

        return new self($total, $perPage, $page === false ? 1 : $page);
    }

    /** @return array{page: int, pages: int, total: int, perPage: int} Format du partial cmsadmin « pagination » */
    public function toArray(): array
    {
        return ['page' => $this->page, 'pages' => $this->pages, 'total' => $this->total, 'perPage' => $this->perPage];
    }
}
