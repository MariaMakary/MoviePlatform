<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieIdsRequest;
use App\Http\Resources\MovieCardResource;
use App\Services\MovieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function __construct(private MovieService $movies) {}

    public function index(Request $request): JsonResponse
    {
        $request->merge(['ids' => MovieIdsRequest::parse($request->query('ids'))]);
        $data = $this->validate($request, MovieIdsRequest::rules());

        return MovieCardResource::collection($this->movies->findMany($data['ids']))->response();
    }
}
