<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUsersRequest;
use App\Http\Resources\AdminUserResource;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

/** User management (admin only: jwt + admin middleware on the routes). */
class AdminUserController extends Controller
{
    public function __construct(private AdminUserService $users) {}

    public function index(Request $request): PaginatedCollection
    {
        $data = $this->validate($request, AdminUsersRequest::rules());

        return PaginatedCollection::fromQuery($this->users->list($data['q'] ?? null), AdminUserResource::class, $request);
    }

    public function block(Request $request, string $id): JsonResponse
    {
        return (new AdminUserResource($this->users->setBlocked($request->user(), $id, true)))->response();
    }

    public function unblock(Request $request, string $id): JsonResponse
    {
        return (new AdminUserResource($this->users->setBlocked($request->user(), $id, false)))->response();
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->users->delete($request->user(), $id);

        return response('', 204);
    }
}
