<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CandidateRequest;
use App\Http\Requests\VoteRequest;
use App\Http\Resources\MovieNightDetailResource;
use App\Services\MovieNightService;
use App\Services\VotingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VotingController extends Controller
{
    public function __construct(private VotingService $voting, private MovieNightService $movieNights) {}

    public function addCandidate(Request $request, string $id): JsonResponse
    {
        $data = $this->validate($request, CandidateRequest::rules());

        $this->voting->addCandidate($request->user(), $id, $data['movie_id']);

        return $this->night($request, $id)->setStatusCode(201);
    }

    public function removeCandidate(Request $request, string $id, string $candidateId): JsonResponse
    {
        $this->voting->removeCandidate($request->user(), $id, $candidateId);

        return $this->night($request, $id);
    }

    public function vote(Request $request, string $id): JsonResponse
    {
        $data = $this->validate($request, VoteRequest::rules());

        $this->voting->vote($request->user(), $id, $data['candidate_id']);

        return $this->night($request, $id);
    }

    private function night(Request $request, string $id): JsonResponse
    {
        return (new MovieNightDetailResource($this->movieNights->find($id, $request->user()->id)))->response();
    }
}
