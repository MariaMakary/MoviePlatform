<?php

namespace MoviePlatform\Common\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaginatedCollection implements Responsable
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public function __construct(private LengthAwarePaginator $paginator, private string $resource)
    {
    }

    public static function fromQuery(object $query, string $resource, Request $request): static
    {
        return new static($query->paginate(static::perPage($request), ['*'], 'page', static::page($request)), $resource);
    }

    public static function perPage(Request $request): int
    {
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return $perPage === false ? self::DEFAULT_PER_PAGE : max(1, min($perPage, self::MAX_PER_PAGE));
    }

    public static function page(Request $request): int
    {
        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT);

        return $page === false ? 1 : max(1, $page);
    }

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->resource::collection($this->paginator->items())->resolve($request),
            'meta' => [
                'current_page' => $this->paginator->currentPage(),
                'last_page' => $this->paginator->lastPage(),
                'total' => $this->paginator->total(),
            ],
        ]);
    }
}
