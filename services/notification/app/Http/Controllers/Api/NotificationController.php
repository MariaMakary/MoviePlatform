<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\UnreadCountResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): PaginatedCollection
    {
        return PaginatedCollection::fromQuery(
            $this->notifications->listFor($request->user()->id),
            NotificationResource::class,
            $request,
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return (new UnreadCountResource($this->notifications->unreadCount($request->user()->id)))->response();
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notifications->markRead($request->user()->id, $id);

        return (new NotificationResource($notification))->response();
    }

    public function markAllRead(Request $request): Response
    {
        $this->notifications->markAllRead($request->user()->id);

        return response('', 204);
    }
}
