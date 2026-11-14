<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListIndexRequest;
use App\Http\Requests\PublicListsRequest;
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\PublicListResource;
use App\Services\ListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class ListController extends Controller
{
    public function __construct(private ListService $lists) {}

    public function index(Request $request): PaginatedCollection
    {
        $data = $this->validate($request, ListIndexRequest::rules());

        return PaginatedCollection::fromQuery(
            $this->lists->listFor($request->user()->id, $data['movie_id'] ?? null),
            ListResource::class,
            $request,
        );
    }

    public function publicLists(Request $request): PaginatedCollection
    {
        $data = $this->validate($request, PublicListsRequest::rules());

        return PaginatedCollection::fromQuery($this->lists->publicLists($data['q'] ?? null), PublicListResource::class, $request);
    }

    public function ofUser(Request $request, string $id): PaginatedCollection
    {
        return PaginatedCollection::fromQuery($this->lists->publicListsOf($id), ListResource::class, $request);
    }

    public function show(string $id): ListResource
    {
        return new ListResource($this->lists->findVisible($id, Auth::guard('jwt')->user()?->id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, StoreListRequest::rules());

        return (new ListResource($this->lists->create($request->user()->id, $data)))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id): ListResource
    {
        $list = $this->lists->find($id);
        $this->authorize('update', $list);

        $data = $this->validate($request, UpdateListRequest::rules());

        return new ListResource($this->lists->update($list, $data));
    }

    public function destroy(string $id): Response
    {
        $list = $this->lists->find($id);
        $this->authorize('delete', $list);

        $this->lists->delete($list);

        return response('', 204);
    }
}
