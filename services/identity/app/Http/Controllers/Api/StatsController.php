<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserStatsResource;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(private StatsService $stats) {}

    public function show(): JsonResponse
    {
        return (new UserStatsResource($this->stats->users()))->response();
    }
}
