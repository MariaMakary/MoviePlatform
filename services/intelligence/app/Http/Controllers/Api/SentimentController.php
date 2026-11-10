<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeSentimentRequest;
use App\Http\Resources\SentimentResource;
use App\Services\SentimentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SentimentController extends Controller
{
    public function __construct(private SentimentService $sentiment) {}

    public function analyze(Request $request): JsonResponse
    {
        $data = $this->validate($request, AnalyzeSentimentRequest::rules());

        return (new SentimentResource($this->sentiment->analyze($data['text'])))->response();
    }
}
