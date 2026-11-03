<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteRequest;
use App\Http\Resources\MovieNightDetailResource;
use App\Services\MovieNightService;
use App\Services\ParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function __construct(private ParticipantService $participants, private MovieNightService $movieNights) {}

    public function invite(Request $request, string $id): JsonResponse
    {
        $data = $this->validate($request, InviteRequest::rules());

        $this->participants->invite($request->user(), $id, $data['user_ids']);

        return (new MovieNightDetailResource($this->movieNights->find($id, $request->user()->id)))->response();
    }
}
