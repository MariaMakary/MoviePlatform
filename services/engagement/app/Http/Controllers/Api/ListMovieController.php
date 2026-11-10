<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListMovieRequest;
use App\Http\Resources\ListItemResource;
use App\Services\ListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ListMovieController extends Controller
{
    public function __construct(private ListService $lists) {}

    public function store(Request $request, string $id): JsonResponse
    {
        $list = $this->lists->find($id);
        $this->authorize('update', $list);

        $data = $this->validate($request, ListMovieRequest::rules());

        return (new ListItemResource($this->lists->addMovie($list, $data['movie_id'])))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $id, string $movieId): Response
    {
        $list = $this->lists->find($id);
        $this->authorize('update', $list);

        $request->merge(['movie_id' => $movieId]);
        $data = $this->validate($request, ListMovieRequest::rules());

        $this->lists->removeMovie($list, $data['movie_id']);

        return response('', 204);
    }
}
