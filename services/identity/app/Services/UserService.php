<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class UserService
{
    public const SEARCH_LIMIT = 10;

    public function find(string $id): User
    {
        return User::findOrFail($id);
    }

    public function findMany(array $ids): Collection
    {
        $users = User::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn (string $id) => $users->get($id))
            ->filter()
            ->values();
    }

    public function search(string $query): Collection
    {
        return User::where('is_blocked', false)
            ->where('name', 'ilike', '%'.addcslashes($query, '%_\\').'%')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::SEARCH_LIMIT)
            ->get();
    }
}
