<?php

namespace App\Services;

use App\Integration\Events\ListCreated;
use App\Models\ListItem;
use App\Models\MovieList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\Integration\EventPublisher;

class ListService
{
    /** How many movies of a public list are sent for its poster collage. */
    public const COLLAGE_SIZE = 4;

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

    /** Every public list of non-blocked authors, newest first, optionally matching a title. */
    public function publicLists(?string $search): Builder
    {
        $search = trim((string) $search);

        return MovieList::publiclyVisible()
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'ILIKE', '%'.$this->escapeLike($search).'%'))
            ->withCount('items as movie_count')
            // In the order of the list page (oldest first), so the collage shows the list's first movies.
            ->selectRaw("(select coalesce(json_agg(first.movie_id), '[]') from (select movie_id from list_movie where list_id = lists.id order by created_at, movie_id limit ?) as first) as first_movie_ids", [self::COLLAGE_SIZE])
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

        return $list->isVisibleTo($userId)
            ? $list->load(['items' => fn ($items) => $items->orderBy('created_at')->orderBy('movie_id')])
            : abort(404, 'List not found.');
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

    // createOrFirst relies on the primary key, so two clicks at the same time still give one row.
    public function addMovie(MovieList $list, string $movieId): ListItem
    {
        return DB::transaction(function () use ($list, $movieId) {
            $item = ListItem::createOrFirst(['list_id' => $list->id, 'movie_id' => $movieId]);

            if (! $item->wasRecentlyCreated) {
                throw ValidationException::withMessages(['movie_id' => 'Already in this list']);
            }

            $list->touch();

            return $item;
        });
    }

    // A typed % or _ is searched as itself, not as a LIKE wildcard.
    private function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }

    public function removeMovie(MovieList $list, string $movieId): void
    {
        DB::transaction(function () use ($list, $movieId) {
            if ($list->items()->where('movie_id', $movieId)->delete() > 0) {
                $list->touch();
            }
        });
    }
}
