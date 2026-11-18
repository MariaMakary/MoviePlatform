<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class StatsController extends Controller
{
    public function __construct(private StatsService $stats) {}

    public function show(): JsonResponse
    {
        return (new JsonResource($this->stats->overview()))->response();
    }
}
