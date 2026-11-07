<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Services\PersonService;
use Illuminate\Http\JsonResponse;

class PersonController extends Controller
{
    public function __construct(private PersonService $people) {}

    public function show(string $id): JsonResponse
    {
        return (new PersonResource($this->people->find($id)))->response();
    }
}
