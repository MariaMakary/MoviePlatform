<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Collection;

class MovieService
{
    public function findMany(array $ids): Collection
    {
        $movies = Movie::with('genres')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn (string $id) => $movies->get($id))
            ->filter()
            ->values();
    }
}
