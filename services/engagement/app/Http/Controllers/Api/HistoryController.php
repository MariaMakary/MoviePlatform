<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieIdsRequest;
use App\Http\Requests\StoreHistoryRequest;
use App\Http\Resources\WatchedMovieResource;
use App\Http\Resources\WatchHistoryResource;
use App\Services\HistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class HistoryController extends Controller
{
    public function __construct(private HistoryService $history) {}

    public function index(Request $request): PaginatedCollection
    {
        return PaginatedCollection::fromQuery(
            $this->history->listFor($request->user()->id),
            WatchHistoryResource::class,
            $request,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, StoreHistoryRequest::rules());

        $entry = $this->history->add($request->user()->id, $data['movie_id'], $data['watched_at'] ?? null);

        return (new WatchHistoryResource($entry))->response()->setStatusCode(201);
    }

    public function contains(Request $request): JsonResponse
    {
        $request->merge(['ids' => MovieIdsRequest::parse($request->query('ids'))]);
        $data = $this->validate($request, MovieIdsRequest::rules());

        return WatchedMovieResource::collection($this->history->latestWatchedAt($request->user()->id, $data['ids']))->response();
    }
}
