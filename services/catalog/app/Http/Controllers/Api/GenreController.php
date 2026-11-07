<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenreMoviesRequest;
use App\Http\Resources\GenreResource;
use App\Http\Resources\MovieCardResource;
use App\Models\Genre;
use App\Services\MovieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class GenreController extends Controller
{
    public function __construct(private MovieService $movies) {}

    public function index(): JsonResponse
    {
        return GenreResource::collection(Genre::orderBy('name')->get())->response();
    }

    public function movies(Request $request, string $id): PaginatedCollection
    {
        $data = $this->validate($request, GenreMoviesRequest::rules());
        $genre = Genre::findOrFail($id);

        $movies = $this->movies->search([
            'genre' => $genre->id,
            'sort' => $data['sort'] ?? GenreMoviesRequest::SORTS[0],
        ], (int) ($data['page'] ?? 1));

        return new PaginatedCollection($movies, MovieCardResource::class);
    }
}
