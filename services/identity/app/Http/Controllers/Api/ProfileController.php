<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateGenresRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileController extends Controller
{
    public function __construct(private AuthService $auth, private ProfileService $profile) {}

    public function update(Request $request): JsonResponse
    {
        $data = $this->validate($request, UpdateProfileRequest::rules());

        $user = $this->profile->update($this->auth->current($request->user()), $data['name'], $data['bio'] ?? null);

        return (new UserResource($user))->response();
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $this->validate($request, UpdateAvatarRequest::rules());

        $user = $this->profile->updateAvatar($this->auth->current($request->user()), $request->file('avatar'));

        return (new UserResource($user))->response();
    }

    public function updatePassword(Request $request): Response
    {
        $data = $this->validate($request, ChangePasswordRequest::rules());

        $this->profile->changePassword($this->auth->current($request->user()), $data['current_password'] ?? null, $data['password']);

        return response('', 204);
    }

    public function updateGenres(Request $request): JsonResponse
    {
        $data = $this->validate($request, UpdateGenresRequest::rules());

        $user = $this->profile->updateGenres($this->auth->current($request->user()), $data['genre_ids']);

        return (new UserResource($user))->response();
    }
}
