<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;

class ActivityController extends Controller
{
    public function __construct(private ActivityService $activities) {}

    public function feed(Request $request): PaginatedCollection
    {
        return PaginatedCollection::fromQuery($this->activities->feedFor($request->user()->id), ActivityResource::class, $request);
    }

    public function ofUser(Request $request, string $id): PaginatedCollection
    {
        $query = $this->activities->ofUser($this->validUserId($id), Auth::guard('jwt')->user()?->id);

        return PaginatedCollection::fromQuery($query, ActivityResource::class, $request);
    }
}
