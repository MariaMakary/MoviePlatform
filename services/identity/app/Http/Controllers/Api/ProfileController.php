<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGenresRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private AuthService $auth, private ProfileService $profile) {}

    public function updateGenres(Request $request): JsonResponse
    {
        $data = $this->validate($request, UpdateGenresRequest::rules());

        $user = $this->profile->updateGenres($this->auth->current($request->user()), $data['genre_ids']);

        return (new UserResource($user))->response();
    }
}
