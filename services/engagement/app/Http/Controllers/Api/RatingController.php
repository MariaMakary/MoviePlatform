<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RatingSummaryRequest;
use App\Http\Requests\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Services\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function __construct(private RatingService $ratings) {}

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, StoreRatingRequest::rules());

        $rating = $this->ratings->rate($request->user()->id, $data['movie_id'], (int) $data['score']);

        return (new RatingResource($rating))->response()->setStatusCode(200);
    }

    public function summary(Request $request, string $id): JsonResponse
    {
        $request->merge(['movie_id' => $id]);
        $data = $this->validate($request, RatingSummaryRequest::rules());

        return (new JsonResource($this->ratings->summaryFor($data['movie_id'], Auth::guard('jwt')->user()?->id)))->response();
    }
}
