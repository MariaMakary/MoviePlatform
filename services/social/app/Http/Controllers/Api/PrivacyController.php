<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FollowRequest;
use App\Http\Requests\UpdatePrivacyRequest;
use App\Http\Resources\PrivacyResource;
use App\Services\PrivacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PrivacyController extends Controller
{
    public function __construct(private PrivacyService $privacy) {}

    public function show(Request $request): JsonResponse
    {
        return (new PrivacyResource($this->privacy->for($request->user()->id)))->response();
    }

    public function update(Request $request): JsonResponse
    {
        $data = $this->validate($request, UpdatePrivacyRequest::rules());

        // 200 even when this first save creates the row: to the caller the settings always existed.
        return (new PrivacyResource($this->privacy->update($request->user()->id, $data)))->response()->setStatusCode(200);
    }

    public function showFor(string $id): JsonResponse
    {
        $userId = Validator::make(['user_id' => $id], FollowRequest::rules())->validate()['user_id'];

        return (new PrivacyResource($this->privacy->for($userId)))->response();
    }
}
