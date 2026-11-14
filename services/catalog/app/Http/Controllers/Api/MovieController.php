<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieIdsRequest;
use App\Http\Requests\MoviePageRequest;
use App\Http\Requests\SearchMoviesRequest;
use App\Http\Resources\MovieCardResource;
use App\Http\Resources\MovieDetailResource;
use App\Services\MovieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class MovieController extends Controller
{
    public function __construct(private MovieService $movies) {}

    public function index(Request $request): JsonResponse
    {
        $request->merge(['ids' => MovieIdsRequest::parse($request->query('ids'))]);
        $data = $this->validate($request, MovieIdsRequest::rules());

        return MovieCardResource::collection($this->movies->findMany($data['ids']))->response();
    }

    public function show(string $id): JsonResponse
    {
        return (new MovieDetailResource($this->movies->find($id)))->response();
    }

    public function similar(string $id): JsonResponse
    {
        return MovieCardResource::collection($this->movies->similar($id))->response();
    }

    public function popular(Request $request): JsonResponse
    {
        $data = $this->validate($request, MoviePageRequest::rules());

        return $this->listResponse($this->movies->popular((int) ($data['page'] ?? 1)));
    }

    public function search(Request $request): PaginatedCollection
    {
        $data = $this->validate($request, SearchMoviesRequest::rules());

        return new PaginatedCollection($this->movies->search($data, (int) ($data['page'] ?? 1)), MovieCardResource::class);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $data = $this->validate($request, MoviePageRequest::rules());

        return $this->listResponse($this->movies->upcoming((int) ($data['page'] ?? 1)));
    }

    private function listResponse(array $list): JsonResponse
    {
        return MovieCardResource::collection($list['movies'])
            ->additional(['meta' => Arr::only($list, ['current_page', 'last_page', 'total'])])
            ->response();
    }
}
