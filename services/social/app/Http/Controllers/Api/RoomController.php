<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomMessagesRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\RoomResource;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class RoomController extends Controller
{
    public function __construct(private RoomService $rooms) {}

    public function index(Request $request): PaginatedCollection
    {
        return PaginatedCollection::fromQuery($this->rooms->latestFirst(), RoomResource::class, $request);
    }

    public function byMovie(string $movieId): JsonResponse
    {
        return (new RoomResource($this->rooms->forMovie($movieId)))->response();
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $data = $this->validate($request, RoomMessagesRequest::rules());

        [$messages, $hasMore] = $this->rooms->messages($id, $data['before'] ?? null);

        return new JsonResponse([
            'data' => MessageResource::collection($messages)->resolve($request),
            'meta' => [
                'has_more' => $hasMore,
                'before' => $hasMore ? $messages->first()->id : null,
            ],
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $data = $this->validate($request, StoreMessageRequest::rules());

        $message = $this->rooms->send($request->user(), $id, $data['body']);

        return (new MessageResource($message))->response()->setStatusCode(201);
    }
}
