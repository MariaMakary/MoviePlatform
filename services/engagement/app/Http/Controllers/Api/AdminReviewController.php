<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminReviewsRequest;
use App\Http\Resources\AdminReviewResource;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

/** Review moderation (admin only: jwt + admin middleware on the routes). */
class AdminReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function index(Request $request): PaginatedCollection
    {
        $filters = $this->validate($request, AdminReviewsRequest::rules());

        return PaginatedCollection::fromQuery($this->reviews->forAdmin($filters), AdminReviewResource::class, $request);
    }

    public function hide(string $id): JsonResponse
    {
        return $this->setHidden($id, true);
    }

    public function unhide(string $id): JsonResponse
    {
        return $this->setHidden($id, false);
    }

    public function destroy(string $id): Response
    {
        $this->reviews->delete($this->reviews->find($id));

        return response('', 204);
    }

    private function setHidden(string $id, bool $hidden): JsonResponse
    {
        $review = $this->reviews->setHidden($this->reviews->find($id), $hidden);

        return (new AdminReviewResource($review))->response();
    }
}
