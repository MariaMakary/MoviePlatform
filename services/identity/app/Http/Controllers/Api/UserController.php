<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchUsersRequest;
use App\Http\Requests\UserIdsRequest;
use App\Http\Resources\PublicUserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): JsonResponse
    {
        $request->merge(['ids' => UserIdsRequest::parse($request->query('ids'))]);
        $data = $this->validate($request, UserIdsRequest::rules());

        return PublicUserResource::collection($this->users->findMany($data['ids']))->response();
    }

    public function show(string $id): JsonResponse
    {
        return (new PublicUserResource($this->users->find($id)))->response();
    }

    public function search(Request $request): JsonResponse
    {
        $data = $this->validate($request, SearchUsersRequest::rules());

        return PublicUserResource::collection($this->users->search($data['q']))->response();
    }
}
