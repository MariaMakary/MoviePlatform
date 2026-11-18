<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminMoviesRequest;
use App\Http\Requests\ImportMovieRequest;
use App\Http\Requests\UpdateMovieRequest;
use App\Http\Resources\AdminMovieResource;
use App\Services\AdminMovieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class AdminMovieController extends Controller
{
    public function __construct(private AdminMovieService $movies) {}

    public function index(Request $request): PaginatedCollection
    {
        $data = $this->validate($request, AdminMoviesRequest::rules());

        return new PaginatedCollection($this->movies->list($data['q'] ?? null, (int) ($data['page'] ?? 1)), AdminMovieResource::class);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->validate($request, UpdateMovieRequest::rules(), UpdateMovieRequest::messages());

        return (new AdminMovieResource($this->movies->update($id, $data)))->response();
    }

    public function destroy(string $id): Response
    {
        $this->movies->delete($id);

        return response('', 204);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $this->validate($request, ImportMovieRequest::rules());

        return (new AdminMovieResource($this->movies->import((int) $data['tmdb_id'])))->response()->setStatusCode(201);
    }
}
