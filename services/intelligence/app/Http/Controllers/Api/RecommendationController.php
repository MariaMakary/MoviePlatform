<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecommendationResource;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    public function __construct(private RecommendationService $recommendations) {}

    public function show(string $userId): JsonResponse
    {
        $this->authorize('view-recommendations', $userId);

        return RecommendationResource::collection($this->recommendations->forUser($userId))->response();
    }
}
