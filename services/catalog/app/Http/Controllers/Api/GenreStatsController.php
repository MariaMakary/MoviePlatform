<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenreCountsRequest;
use App\Http\Resources\GenreCountResource;
use App\Services\GenreStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenreStatsController extends Controller
{
    public function __construct(private GenreStatsService $stats) {}

    public function counts(Request $request): JsonResponse
    {
        $data = $this->validate($request, GenreCountsRequest::rules());

        return GenreCountResource::collection($this->stats->totals($data['movie_counts']))->response();
    }
}
