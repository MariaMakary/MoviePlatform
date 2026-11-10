<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FollowRequest;
use App\Http\Resources\FollowResource;
use App\Http\Resources\FollowStatsResource;
use App\Http\Resources\FollowUserResource;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class FollowController extends Controller
{
    public function __construct(private FollowService $follows) {}

    public function store(Request $request, string $userId): JsonResponse
    {
        $follow = $this->follows->follow($request->user(), $this->validUserId($userId));

        return (new FollowResource($follow))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $userId): Response
    {
        $this->follows->unfollow($request->user()->id, $this->validUserId($userId));

        return response('', 204);
    }

    public function followers(Request $request, string $id): PaginatedCollection
    {
        return PaginatedCollection::fromQuery($this->follows->followers($this->validUserId($id)), FollowUserResource::class, $request);
    }

    public function following(Request $request, string $id): PaginatedCollection
    {
        return PaginatedCollection::fromQuery($this->follows->following($this->validUserId($id)), FollowUserResource::class, $request);
    }

    public function stats(string $id): JsonResponse
    {
        $stats = $this->follows->stats($this->validUserId($id), Auth::guard('jwt')->user()?->id);

        return (new FollowStatsResource($stats))->response();
    }

    private function validUserId(string $userId): string
    {
        return Validator::make(['user_id' => $userId], FollowRequest::rules())->validate()['user_id'];
    }
}
