<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieIdsRequest;
use App\Http\Requests\MoviePageRequest;
use App\Http\Resources\MovieCardResource;
use App\Services\MovieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MovieController extends Controller
{
    public function __construct(private MovieService $movies) {}

    public function index(Request $request): JsonResponse
    {
        $request->merge(['ids' => MovieIdsRequest::parse($request->query('ids'))]);
        $data = $this->validate($request, MovieIdsRequest::rules());

        return MovieCardResource::collection($this->movies->findMany($data['ids']))->response();
    }

    public function popular(Request $request): JsonResponse
    {
        $data = $this->validate($request, MoviePageRequest::rules());

        return $this->listResponse($this->movies->popular((int) ($data['page'] ?? 1)));
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
