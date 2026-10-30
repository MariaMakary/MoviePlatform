<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\TokenResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function register(Request $request): JsonResponse
    {
        $data = $this->validate($request, RegisterRequest::rules());

        return (new TokenResource($this->auth->register($data)))->response()->setStatusCode(201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $this->validate($request, LoginRequest::rules());

        return (new TokenResource($this->auth->login($data['email'], $data['password'])))->response();
    }

    public function logout(): Response
    {
        return response('', 204);
    }

    public function refresh(Request $request): JsonResponse
    {
        return (new TokenResource($this->auth->refresh($request->user())))->response();
    }

    public function me(Request $request): JsonResponse
    {
        return (new UserResource($this->auth->current($request->user())))->response();
    }
}
