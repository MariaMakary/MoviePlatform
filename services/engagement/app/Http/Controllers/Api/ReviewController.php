<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Http\Requests\UuidParameterRequest;
use App\Http\Resources\ReviewResource;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function forMovie(Request $request, string $id): PaginatedCollection
    {
        $request->merge(['movie_id' => $id]);
        $data = $this->validate($request, UuidParameterRequest::rules('movie_id'));

        return PaginatedCollection::fromQuery($this->reviews->forMovie($data['movie_id']), ReviewResource::class, $request);
    }

    public function forUser(Request $request, string $id): PaginatedCollection
    {
        $request->merge(['user_id' => $id]);
        $data = $this->validate($request, UuidParameterRequest::rules('user_id'));

        return PaginatedCollection::fromQuery($this->reviews->forUser($data['user_id']), ReviewResource::class, $request);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, StoreReviewRequest::rules());

        $review = $this->reviews->create($request->user()->id, $data['movie_id'], $data['body']);

        return (new ReviewResource($review))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $review = $this->reviews->find($id);
        $this->authorize('update', $review);
        $data = $this->validate($request, UpdateReviewRequest::rules());

        return (new ReviewResource($this->reviews->update($review, $data['body'])))->response();
    }

    public function destroy(string $id): Response
    {
        $review = $this->reviews->find($id);
        $this->authorize('delete', $review);

        $this->reviews->delete($review);

        return response('', 204);
    }
}
