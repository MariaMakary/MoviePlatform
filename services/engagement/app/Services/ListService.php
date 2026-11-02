<?php

namespace App\Services;

use App\Integration\Events\ListCreated;
use App\Models\MovieList;
use Illuminate\Database\Eloquent\Builder;
use MoviePlatform\Common\Integration\EventPublisher;

class ListService
{
    public function __construct(private EventPublisher $events) {}

    public function listFor(string $userId, ?string $movieId = null): Builder
    {
        return MovieList::ownedBy($userId)
            ->when($movieId !== null, fn (Builder $query) => $query->withExists([
                'items as contains_movie' => fn (Builder $items) => $items->where('movie_id', $movieId),
            ]))
            ->latest()
            ->orderByDesc('id');
    }

    public function publicListsOf(string $userId): Builder
    {
        return MovieList::ownedBy($userId)->publiclyVisible()->latest()->orderByDesc('id');
    }

    public function find(string $id): MovieList
    {
        return MovieList::find($id) ?? abort(404, 'List not found.');
    }

    // The same 404 as an unknown id, so nobody can tell that a private list exists.
    public function findVisible(string $id, ?string $userId): MovieList
    {
        $list = $this->find($id);

        return $list->isVisibleTo($userId) ? $list : abort(404, 'List not found.');
    }

    public function create(string $userId, array $data): MovieList
    {
        $list = MovieList::create(['user_id' => $userId] + $data);

        $this->events->publish(ListCreated::ROUTING_KEY, ListCreated::data($list));

        return $list;
    }

    public function update(MovieList $list, array $data): MovieList
    {
        $list->update($data);

        return $list;
    }

    public function delete(MovieList $list): void
    {
        $list->delete();
    }
}
