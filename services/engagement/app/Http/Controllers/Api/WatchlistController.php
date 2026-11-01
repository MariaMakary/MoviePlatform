<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieIdsRequest;
use App\Http\Requests\WatchlistMovieRequest;
use App\Http\Resources\WatchlistItemResource;
use App\Services\WatchlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class WatchlistController extends Controller
{
    public function __construct(private WatchlistService $watchlist) {}

    public function index(Request $request): PaginatedCollection
    {
        return PaginatedCollection::fromQuery(
            $this->watchlist->listFor($request->user()->id),
            WatchlistItemResource::class,
            $request,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, WatchlistMovieRequest::rules());

        $item = $this->watchlist->add($request->user()->id, $data['movie_id']);

        return (new WatchlistItemResource($item))->response()->setStatusCode($item->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $movieId): Response
    {
        $request->merge(['movie_id' => $movieId]);
        $data = $this->validate($request, WatchlistMovieRequest::rules());

        $this->watchlist->remove($request->user()->id, $data['movie_id']);

        return response('', 204);
    }

    public function contains(Request $request): JsonResponse
    {
        $request->merge(['ids' => MovieIdsRequest::parse($request->query('ids'))]);
        $data = $this->validate($request, MovieIdsRequest::rules());

        return (new JsonResource($this->watchlist->movieIdsIn($request->user()->id, $data['ids'])))->response();
    }
}
