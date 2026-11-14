<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeatherSuggestionRequest;
use App\Http\Resources\WeatherSuggestionResource;
use App\Services\WeatherSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function __construct(private WeatherSuggestionService $suggestions) {}

    public function weather(Request $request): JsonResponse
    {
        $data = $this->validate($request, WeatherSuggestionRequest::rules());

        return (new WeatherSuggestionResource($this->suggestions->suggest((float) $data['lat'], (float) $data['lon'])))->response();
    }
}
