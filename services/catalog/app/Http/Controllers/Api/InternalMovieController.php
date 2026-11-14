<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscoverMoviesRequest;
use App\Http\Requests\MovieIdsRequest;
use App\Http\Resources\MovieCardResource;
use App\Services\MovieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalMovieController extends Controller
{
    public function __construct(private MovieService $movies) {}

    public function discover(Request $request): JsonResponse
    {
        $request->merge([
            'genres' => MovieIdsRequest::parse($request->query('genres')),
            'exclude_ids' => MovieIdsRequest::parse($request->query('exclude_ids')),
        ]);
        $data = $this->validate($request, DiscoverMoviesRequest::rules());

        return MovieCardResource::collection($this->movies->discover(
            $data['genres'],
            $data['exclude_ids'] ?? [],
            (int) ($data['limit'] ?? DiscoverMoviesRequest::DEFAULT_LIMIT),
        ))->response();
    }
}
