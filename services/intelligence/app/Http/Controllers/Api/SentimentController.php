<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeSentimentRequest;
use App\Services\SentimentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SentimentController extends Controller
{
    public function __construct(private SentimentService $sentiment) {}

    public function analyze(Request $request): JsonResponse
    {
        $data = $this->validate($request, AnalyzeSentimentRequest::rules());

        return (new JsonResource($this->sentiment->analyze($data['text'])))->response();
    }
}
