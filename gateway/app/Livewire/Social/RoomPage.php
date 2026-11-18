<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RoomPage extends Component
{
    use WithServiceFallback;

    public const MAX_BODY = 1000;

    #[Locked]
    public string $movieId;

    #[Locked]
    public ?array $room = null;

    #[Locked]
    public array $messages = [];

    #[Locked]
    public bool $hasMore = false;

    #[Locked]
    public ?string $before = null;

    #[Locked]
    public array $people = [];

    public string $body = '';

    public function mount(string $id, SocialClient $social): void
    {
        $this->movieId = strtolower($id);
        $this->loadRoom($social);
    }

    public function getListeners(): array
    {
        return $this->room === null ? [] : ["echo-private:room.{$this->room['id']},.MessageSent" => 'receive'];
    }

    public function loadOlder(SocialClient $social): void
    {
        if ($this->room === null || ! $this->hasMore) {
            return;
        }

        $page = $this->fromService('older', fn () => $social->roomMessages($this->room['id'], $this->before));

        if ($page === null) {
            return;
        }

        $known = array_column($this->messages, 'id');
        $older = array_filter($page['data'], fn (array $message) => ! in_array($message['id'], $known, true));

        $this->messages = [...array_values($older), ...$this->messages];
        $this->hasMore = $page['meta']['has_more'];
        $this->before = $page['meta']['before'];
        $this->dispatch('room-older-loaded');
    }

    public function send(SocialClient $social): void
    {
        $this->validate(['body' => ['required', 'string', 'max:'.self::MAX_BODY]], [
            'body.required' => 'Write a message first.',
            'body.max' => 'A message can be at most '.self::MAX_BODY.' characters.',
        ]);

        if ($this->room === null) {
            return;
        }

        $message = $this->fromService('send', fn () => $social->sendMessage($this->room['id'], $this->body));

        if ($message === null) {
            return;
        }

        $this->body = '';
        $this->append($message);
        $this->dispatch('room-message-added', mine: true);
    }

    public function receive(array $event): void
    {
        if ($this->room === null || ! Str::isUuid($event['id'] ?? null) || ! Str::isUuid($event['user_id'] ?? null)) {
            return;
        }

        $this->append([
            'id' => $event['id'],
            'user_id' => $event['user_id'],
            'body' => mb_substr((string) ($event['body'] ?? ''), 0, self::MAX_BODY),
            'created_at' => (string) ($event['created_at'] ?? ''),
        ]);

        if (! isset($this->people[$event['user_id']]) && is_string($event['user_name'] ?? null)) {
            $this->people[$event['user_id']] = ['name' => mb_substr($event['user_name'], 0, 100), 'avatar' => null, 'partial' => true];
        }

        $this->dispatch('room-message-added', mine: false);
    }

    public function render(SocialClient $social, IdentityClient $identity, AuthSession $auth): View
    {
        if ($this->room === null && $this->loadRoom($social)) {
            $this->redirectRoute('movies.discussion', $this->movieId);
        }

        $this->loadPeople($identity);

        return view('livewire.social.room-page', [
            'userId' => $auth->user()['id'] ?? null,
            'maxBody' => self::MAX_BODY,
        ])->title($this->room ? $this->room['name'].' · Discussion' : 'Discussion');
    }

    private function loadRoom(SocialClient $social): bool
    {
        $page = $this->fromService('room', function () use ($social) {
            $room = $social->roomForMovie($this->movieId);

            return ['room' => $room, ...$social->roomMessages($room['id'])];
        });

        if ($page === null) {
            return false;
        }

        $this->room = $page['room'];
        $this->messages = $page['data'];
        $this->hasMore = $page['meta']['has_more'];
        $this->before = $page['meta']['before'];

        return true;
    }

    private function loadPeople(IdentityClient $identity): void
    {
        $missing = collect($this->messages)->pluck('user_id')->unique()
            ->reject(fn (string $id) => isset($this->people[$id]) && empty($this->people[$id]['partial']))
            ->values()->all();

        if ($missing === []) {
            return;
        }

        $users = $this->fromService('people', fn () => $identity->users($missing), []);

        if ($this->isUnavailable('people')) {
            return;
        }

        foreach ($missing as $id) {
            $this->people[$id] = ['name' => 'Deleted user', 'avatar' => null];
        }

        foreach ($users as $user) {
            $this->people[$user['id']] = ['name' => $user['name'], 'avatar' => $user['avatar'] ?? null];
        }
    }

    private function append(array $message): void
    {
        if (! in_array($message['id'], array_column($this->messages, 'id'), true)) {
            $this->messages[] = $message;
        }
    }
}
