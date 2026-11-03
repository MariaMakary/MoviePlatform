<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMovieNightRequest;
use App\Http\Resources\MovieNightDetailResource;
use App\Http\Resources\MovieNightResource;
use App\Services\MovieNightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class MovieNightController extends Controller
{
    public function __construct(private MovieNightService $movieNights) {}

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, StoreMovieNightRequest::rules(), StoreMovieNightRequest::messages());

        $night = $this->movieNights->create($request->user(), $data);

        return (new MovieNightDetailResource($night))->response()->setStatusCode(201);
    }

    public function index(Request $request): PaginatedCollection
    {
        return PaginatedCollection::fromQuery($this->movieNights->forUser($request->user()->id), MovieNightResource::class, $request);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return (new MovieNightDetailResource($this->movieNights->find($id, $request->user()->id)))->response();
    }
}
