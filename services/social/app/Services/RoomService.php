<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Integration\Clients\CatalogClient;
use App\Models\Message;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\Auth\JwtUser;
use Throwable;

class RoomService
{
    public const MESSAGES_PER_CALL = 50;

    public function __construct(private CatalogClient $catalog) {}

    public function latestFirst(): Builder
    {
        return $this->withLastMessage()
            ->orderByRaw('last_message_at DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->orderBy('id');
    }

    public function forMovie(string $movieId): Room
    {
        $movieId = strtolower($movieId);
        $room = $this->withLastMessage()->where('movie_id', $movieId)->first();

        if ($room !== null) {
            return $room;
        }

        $title = $this->catalog->movieTitle($movieId);

        if ($title === null) {
            abort(404, 'Movie not found.');
        }

        Room::createOrFirst(['movie_id' => $movieId], ['name' => mb_substr($title, 0, 150)]);

        return $this->withLastMessage()->where('movie_id', $movieId)->firstOrFail();
    }

    public function find(string $id): Room
    {
        $room = Room::find(strtolower($id));

        if ($room === null) {
            abort(404, 'Room not found.');
        }

        return $room;
    }

    public function messages(string $roomId, ?string $before): array
    {
        $room = $this->find($roomId);
        $query = $room->messages()->orderByDesc('created_at')->orderByDesc('id');

        if ($before !== null) {
            $cursor = $room->messages()->find(strtolower($before));

            if ($cursor === null) {
                throw ValidationException::withMessages(['before' => 'This message is not in this room.']);
            }

            $query->whereRaw('(created_at, id) < (?, ?)', [$cursor->getRawOriginal('created_at'), $cursor->id]);
        }

        $page = $query->limit(self::MESSAGES_PER_CALL + 1)->get();

        return [
            $page->take(self::MESSAGES_PER_CALL)->reverse()->values(),
            $page->count() > self::MESSAGES_PER_CALL,
        ];
    }

    public function send(JwtUser $user, string $roomId, string $body): Message
    {
        $message = DB::transaction(
            fn () => $this->find($roomId)->messages()->create(['user_id' => $user->id, 'body' => trim($body)])
        );

        try {
            event(new MessageSent($message, $user->name));
        } catch (Throwable $e) {
            Log::warning('MessageSent was not broadcast', ['room_id' => $message->room_id, 'error' => $e->getMessage()]);
        }

        return $message;
    }

    public function removeMovie(string $movieId): void
    {
        Room::where('movie_id', $movieId)->delete();
    }

    private function withLastMessage(): Builder
    {
        return Room::query()
            ->select('rooms.*')
            ->selectSub(Message::selectRaw('max(created_at)')->whereColumn('messages.room_id', 'rooms.id'), 'last_message_at');
    }
}
