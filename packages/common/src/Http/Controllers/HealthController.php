<?php

namespace MoviePlatform\Common\Http\Controllers;

use Illuminate\Http\JsonResponse;
use MoviePlatform\Common\Health\HealthCheck;

class HealthController
{
    public function __invoke(HealthCheck $health): JsonResponse
    {
        $result = $health->run();

        return response()->json($result, $result['status'] === 'ok' ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
