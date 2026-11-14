<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscoverMoviesRequest;
use App\Http\Requests\MovieIdsRequest;
use App\Http\Resources\MovieCardResource;
use App\Services\MovieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Service-token endpoints (/api/internal/*) for the other services. */
class InternalMovieController extends Controller
{
    public function __construct(private MovieService $movies) {}

    public function index(Request $request): JsonResponse
    {
        $request->merge(['ids' => MovieIdsRequest::parse($request->query('ids'))]);
        $data = $this->validate($request, MovieIdsRequest::rules());

        return MovieCardResource::collection($this->movies->findMany($data['ids']))->response();
    }

    public function discover(Request $request): JsonResponse
    {
        $request->merge(array_filter([
            'genres' => MovieIdsRequest::parse($request->query('genres')),
            'genre_ids' => MovieIdsRequest::parse($request->query('genre_ids')),
            'exclude_ids' => MovieIdsRequest::parse($request->query('exclude_ids')),
        ]));
        $data = $this->validate($request, DiscoverMoviesRequest::rules());

        return MovieCardResource::collection($this->movies->discover(
            $data['genres'] ?? [],
            $data['exclude_ids'] ?? [],
            (int) ($data['limit'] ?? DiscoverMoviesRequest::DEFAULT_LIMIT),
            $data['genre_ids'] ?? [],
        ))->response();
    }

    public function features(string $id): JsonResponse
    {
        return response()->json(['data' => $this->movies->features($id)]);
    }

    public function similar(string $id): JsonResponse
    {
        return MovieCardResource::collection($this->movies->similar($id))->response();
    }
}
