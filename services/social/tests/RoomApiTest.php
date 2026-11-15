<?php

namespace Tests;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Room;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RoomApiTest extends TestCase
{
    use DatabaseTransactions;

    private const JOE = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const ARRIVAL = '0193a7c1-5555-7aaa-8bbb-000000000001';

    private const UNKNOWN = '0193a7c1-9999-7aaa-8bbb-000000000999';

    private array $titles = [self::INCEPTION => 'Inception', self::ARRIVAL => 'Arrival'];

    private bool $catalogDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem'),
            'internal.services.catalog' => 'http://catalog.test',
            'internal.service_token' => 'test-service-token',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-15T18:00:00Z'));
        Event::fake([MessageSent::class]);

        Http::fake(function (Request $request) {
            if ($this->catalogDown) {
                return Http::response(['message' => 'Server error.'], 500);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $id = $query['ids'] ?? '';

            return Http::response(['data' => isset($this->titles[$id]) ? [['id' => $id, 'title' => $this->titles[$id]]] : []]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function auth(string $userId = self::JOE, string $name = 'Joe'): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => $name, 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function room(string $movieId = self::INCEPTION, string $name = 'Inception'): Room
    {
        return Room::create(['movie_id' => $movieId, 'name' => $name]);
    }

    private function messages(Room $room, int $count): array
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            Carbon::setTestNow(Carbon::parse('2026-11-15T10:00:00Z')->addMinutes($i));
            $ids[] = $room->messages()->create(['user_id' => self::TONIO, 'body' => "Message $i"])->id;
        }

        Carbon::setTestNow(Carbon::parse('2026-11-15T18:00:00Z'));

        return $ids;
    }

    public function test_opening_a_movie_room_creates_it_with_the_catalog_title(): void
    {
        $this->json('GET', '/api/rooms/by-movie/'.self::INCEPTION, [], $this->auth());

        $this->seeStatusCode(200);
        $this->seeJson(['movie_id' => self::INCEPTION, 'name' => 'Inception', 'last_message_at' => null]);
        $this->seeInDatabase('rooms', ['movie_id' => self::INCEPTION, 'name' => 'Inception']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Service-Token', 'test-service-token')
            && str_starts_with($request->url(), 'http://catalog.test/api/internal/movies'));
    }

    public function test_opening_a_movie_room_twice_gives_the_same_room(): void
    {
        $this->json('GET', '/api/rooms/by-movie/'.self::INCEPTION, [], $this->auth());
        $first = json_decode($this->response->getContent(), true)['data']['id'];

        $this->json('GET', '/api/rooms/by-movie/'.strtoupper(self::INCEPTION), [], $this->auth(self::TONIO));
        $second = json_decode($this->response->getContent(), true)['data']['id'];

        $this->assertSame($first, $second);
        $this->assertSame(1, Room::where('movie_id', self::INCEPTION)->count());
        Http::assertSentCount(1);
    }

    public function test_an_existing_room_does_not_call_catalog(): void
    {
        $this->room();
        $this->catalogDown = true;

        $this->json('GET', '/api/rooms/by-movie/'.self::INCEPTION, [], $this->auth());

        $this->seeStatusCode(200);
        Http::assertNothingSent();
    }

    public function test_a_movie_catalog_does_not_have_gives_404(): void
    {
        $this->json('GET', '/api/rooms/by-movie/'.self::UNKNOWN, [], $this->auth());

        $this->seeStatusCode(404);
        $this->seeJson(['message' => 'Movie not found.']);
        $this->notSeeInDatabase('rooms', ['movie_id' => self::UNKNOWN]);
    }

    public function test_catalog_down_gives_503_and_creates_nothing(): void
    {
        $this->catalogDown = true;

        $this->json('GET', '/api/rooms/by-movie/'.self::INCEPTION, [], $this->auth());

        $this->seeStatusCode(503);
        $this->seeJson(['message' => 'Catalog is unavailable.']);
        $this->notSeeInDatabase('rooms', ['movie_id' => self::INCEPTION]);
    }

    public function test_a_long_title_is_cut_to_150_characters(): void
    {
        $this->titles[self::ARRIVAL] = str_repeat('a', 200);

        $this->json('GET', '/api/rooms/by-movie/'.self::ARRIVAL, [], $this->auth());

        $this->seeStatusCode(200);
        $this->assertSame(150, mb_strlen(Room::where('movie_id', self::ARRIVAL)->value('name')));
    }

    public function test_rooms_need_a_token(): void
    {
        $this->json('GET', '/api/rooms');
        $this->seeStatusCode(401);

        $this->json('GET', '/api/rooms/by-movie/'.self::INCEPTION);
        $this->seeStatusCode(401);
    }

    public function test_rooms_are_listed_latest_message_first_and_paginated(): void
    {
        $quiet = $this->room(self::ARRIVAL, 'Arrival');
        $older = $this->room(self::UNKNOWN, 'Old talk');
        $busy = $this->room();

        Carbon::setTestNow(Carbon::parse('2026-11-15T09:00:00Z'));
        $older->messages()->create(['user_id' => self::JOE, 'body' => 'Hi']);
        Carbon::setTestNow(Carbon::parse('2026-11-15T12:00:00Z'));
        $busy->messages()->create(['user_id' => self::JOE, 'body' => 'Hello']);
        Carbon::setTestNow(Carbon::parse('2026-11-15T18:00:00Z'));

        $this->json('GET', '/api/rooms?per_page=2', [], $this->auth());

        $this->seeStatusCode(200);
        $body = json_decode($this->response->getContent(), true);
        $this->assertSame([$busy->id, $older->id], array_column($body['data'], 'id'));
        $this->assertSame('2026-11-15T12:00:00Z', $body['data'][0]['last_message_at']);
        $this->assertSame(['current_page' => 1, 'last_page' => 2, 'total' => 3], $body['meta']);

        $this->json('GET', '/api/rooms?per_page=2&page=2', [], $this->auth());
        $this->assertSame([$quiet->id], array_column(json_decode($this->response->getContent(), true)['data'], 'id'));
    }

    public function test_messages_give_the_latest_50_oldest_first(): void
    {
        $room = $this->room();
        $ids = $this->messages($room, 55);

        $this->json('GET', "/api/rooms/{$room->id}/messages", [], $this->auth());

        $this->seeStatusCode(200);
        $body = json_decode($this->response->getContent(), true);
        $this->assertSame(array_slice($ids, 5), array_column($body['data'], 'id'));
        $this->assertSame(['has_more' => true, 'before' => $ids[5]], $body['meta']);
        $this->assertSame(['id', 'room_id', 'user_id', 'body', 'created_at'], array_keys($body['data'][0]));
    }

    public function test_older_messages_load_with_before(): void
    {
        $room = $this->room();
        $ids = $this->messages($room, 55);

        $this->json('GET', "/api/rooms/{$room->id}/messages?before={$ids[5]}", [], $this->auth());

        $this->seeStatusCode(200);
        $body = json_decode($this->response->getContent(), true);
        $this->assertSame(array_slice($ids, 0, 5), array_column($body['data'], 'id'));
        $this->assertSame(['has_more' => false, 'before' => null], $body['meta']);
    }

    public function test_messages_in_the_same_second_are_not_skipped(): void
    {
        $room = $this->room();
        $ids = [];

        for ($i = 0; $i < 3; $i++) {
            $ids[] = $room->messages()->create(['user_id' => self::JOE, 'body' => "Same second $i"])->id;
        }

        $sorted = $ids;
        sort($sorted);

        $this->json('GET', "/api/rooms/{$room->id}/messages?before={$sorted[2]}", [], $this->auth());

        $this->assertSame([$sorted[0], $sorted[1]], array_column(json_decode($this->response->getContent(), true)['data'], 'id'));
    }

    public function test_an_empty_room_has_no_messages(): void
    {
        $room = $this->room();

        $this->json('GET', "/api/rooms/{$room->id}/messages", [], $this->auth());

        $this->seeStatusCode(200);
        $this->seeJsonEquals(['data' => [], 'meta' => ['has_more' => false, 'before' => null]]);
    }

    public function test_a_bad_before_gives_422(): void
    {
        $room = $this->room();
        $other = $this->room(self::ARRIVAL, 'Arrival');
        $foreign = $this->messages($other, 1)[0];

        $this->json('GET', "/api/rooms/{$room->id}/messages?before=abc", [], $this->auth());
        $this->seeStatusCode(422);
        $this->assertArrayHasKey('before', json_decode($this->response->getContent(), true)['errors']);

        $this->json('GET', "/api/rooms/{$room->id}/messages?before=$foreign", [], $this->auth());
        $this->seeStatusCode(422);
        $this->seeJson(['before' => ['This message is not in this room.']]);
    }

    public function test_an_unknown_room_gives_404(): void
    {
        $this->json('GET', '/api/rooms/'.self::UNKNOWN.'/messages', [], $this->auth());
        $this->seeStatusCode(404);
        $this->seeJson(['message' => 'Room not found.']);

        $this->json('POST', '/api/rooms/'.self::UNKNOWN.'/messages', ['body' => 'Hi'], $this->auth());
        $this->seeStatusCode(404);

        $this->json('GET', '/api/rooms/not-a-uuid/messages', [], $this->auth());
        $this->seeStatusCode(404);
    }

    public function test_sending_a_message_saves_it_and_broadcasts_it(): void
    {
        $room = $this->room();

        $this->json('POST', "/api/rooms/{$room->id}/messages", ['body' => '  What an ending!  '], $this->auth(self::JOE, 'Joe'));

        $this->seeStatusCode(201);
        $this->seeJson(['room_id' => $room->id, 'user_id' => self::JOE, 'body' => 'What an ending!', 'created_at' => '2026-11-15T18:00:00Z']);
        $this->seeInDatabase('messages', ['room_id' => $room->id, 'user_id' => self::JOE, 'body' => 'What an ending!']);

        $id = json_decode($this->response->getContent(), true)['data']['id'];

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($room, $id) {
            return $event->broadcastOn()->name === "private-room.{$room->id}"
                && $event->broadcastAs() === 'MessageSent'
                && $event->broadcastWith() === [
                    'id' => $id,
                    'user_id' => self::JOE,
                    'user_name' => 'Joe',
                    'body' => 'What an ending!',
                    'created_at' => '2026-11-15T18:00:00Z',
                ];
        });
    }

    public function test_empty_or_too_long_messages_give_422(): void
    {
        $room = $this->room();

        foreach ([[], ['body' => ''], ['body' => '   '], ['body' => str_repeat('a', 1001)], ['body' => ['x']]] as $payload) {
            $this->json('POST', "/api/rooms/{$room->id}/messages", $payload, $this->auth());

            $this->seeStatusCode(422);
            $this->assertArrayHasKey('body', json_decode($this->response->getContent(), true)['errors']);
        }

        $this->json('POST', "/api/rooms/{$room->id}/messages", ['body' => str_repeat('a', 1000)], $this->auth());
        $this->seeStatusCode(201);

        $this->assertSame(1, Message::where('room_id', $room->id)->count());
        Event::assertDispatchedTimes(MessageSent::class, 1);
    }

    public function test_sending_needs_a_token(): void
    {
        $room = $this->room();

        $this->json('POST', "/api/rooms/{$room->id}/messages", ['body' => 'Hi']);

        $this->seeStatusCode(401);
        Event::assertNotDispatched(MessageSent::class);
    }
}
