<?php

namespace Tests;

use App\Models\User;
use App\Models\UserGenre;
use App\Services\TokenService;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class FavoriteGenresTest extends TestCase
{
    use DatabaseTransactions;

    private const DRAMA = '0193a7b0-2222-7aaa-8bbb-000000000010';
    private const SCI_FI = '0193a7b0-2222-7aaa-8bbb-000000000011';
    private const COMEDY = '0193a7b0-2222-7aaa-8bbb-000000000012';

    private array $published = [];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $fixtures.'/test-public.pem',
        ]);

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) {
            $this->published[] = [$routingKey, json_decode($message->getBody(), true)];
        });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        $this->user = User::factory()->create();
    }

    private function headers(?User $user = null): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.app(TokenService::class)->issue($user ?? $this->user)->token];
    }

    private function save(mixed $genreIds, ?User $user = null): void
    {
        $this->json('PUT', '/api/me/genres', ['genre_ids' => $genreIds], $this->headers($user));
    }

    private function storedGenres(): array
    {
        return UserGenre::where('user_id', $this->user->id)->orderBy('genre_id')->pluck('genre_id')->all();
    }

    public function test_saving_returns_the_user_with_the_new_genres(): void
    {
        $this->save([self::SCI_FI, self::DRAMA]);

        $this->seeStatusCode(200)->seeJsonEquals(['data' => [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'avatar' => null,
            'bio' => null,
            'role' => 'user',
            'genre_ids' => [self::DRAMA, self::SCI_FI],
        ]]);
        $this->assertSame([self::DRAMA, self::SCI_FI], $this->storedGenres());
    }

    public function test_saving_replaces_the_old_list(): void
    {
        $this->save([self::DRAMA, self::SCI_FI]);
        $this->save([self::SCI_FI, self::COMEDY]);

        $this->seeStatusCode(200);
        $this->assertSame([self::SCI_FI, self::COMEDY], $this->storedGenres());
    }

    public function test_an_empty_list_clears_the_genres(): void
    {
        $this->save([self::DRAMA]);
        $this->save([]);

        $this->seeStatusCode(200)->seeJson(['genre_ids' => []]);
        $this->assertSame([], $this->storedGenres());
    }

    public function test_other_users_genres_are_not_touched(): void
    {
        $other = User::factory()->create();
        $this->save([self::COMEDY], $other);

        $this->save([self::DRAMA]);

        $this->assertSame([self::COMEDY], UserGenre::where('user_id', $other->id)->pluck('genre_id')->all());
    }

    public function test_uppercase_ids_are_saved_in_lowercase(): void
    {
        $this->save([strtoupper(self::DRAMA)]);

        $this->seeStatusCode(200)->seeJson(['genre_ids' => [self::DRAMA]]);
    }

    public function test_user_genres_updated_is_published_with_the_full_new_list(): void
    {
        $this->save([self::DRAMA]);
        $this->save([self::SCI_FI, self::COMEDY]);
        $this->save([]);

        $this->assertCount(3, $this->published);
        [$routingKey, $envelope] = $this->published[1];
        $this->assertSame('user.genres_updated', $routingKey);
        $this->assertSame('UserGenresUpdated', $envelope['event']);
        $this->assertSame(['user_id' => $this->user->id, 'genre_ids' => [self::SCI_FI, self::COMEDY]], $envelope['data']);
        $this->assertSame(['user_id' => $this->user->id, 'genre_ids' => []], $this->published[2][1]['data']);
    }

    public function test_me_returns_the_genre_ids(): void
    {
        $this->get('/api/me', $this->headers())->seeStatusCode(200)->seeJson(['genre_ids' => []]);

        $this->save([self::DRAMA]);

        $this->get('/api/me', $this->headers())->seeStatusCode(200)->seeJson(['genre_ids' => [self::DRAMA]]);
    }

    public function test_login_does_not_return_genre_ids(): void
    {
        $this->save([self::DRAMA]);

        $this->json('POST', '/api/login', ['email' => $this->user->email, 'password' => 'password']);

        $this->seeStatusCode(200);
        $this->assertArrayNotHasKey('genre_ids', $this->response->getData(true)['data']['user']);
    }

    public function test_invalid_lists_get_422_and_change_nothing(): void
    {
        $this->save([self::DRAMA]);
        $this->published = [];

        $cases = [
            'missing' => [null, 'genre_ids'],
            'not an array' => [self::DRAMA, 'genre_ids'],
            'not a uuid' => [['drama'], 'genre_ids.0'],
            'not a string' => [[42], 'genre_ids.0'],
            'repeated id' => [[self::DRAMA, strtoupper(self::DRAMA)], 'genre_ids.0'],
            'too many' => [array_map(fn (int $i) => sprintf('0193a7b0-2222-7aaa-8bbb-%012d', $i), range(1, 21)), 'genre_ids'],
        ];

        foreach ($cases as $case => [$genreIds, $field]) {
            $body = $genreIds === null ? [] : ['genre_ids' => $genreIds];
            $this->json('PUT', '/api/me/genres', $body, $this->headers());

            $this->assertSame(422, $this->response->getStatusCode(), $case);
            $this->assertArrayHasKey($field, $this->response->getData(true)['errors'], $case);
        }

        $this->assertSame([self::DRAMA], $this->storedGenres());
        $this->assertSame([], $this->published);
    }

    public function test_twenty_genres_are_allowed(): void
    {
        $this->save(array_map(fn (int $i) => sprintf('0193a7b0-2222-7aaa-8bbb-%012d', $i), range(1, 20)));

        $this->seeStatusCode(200);
        $this->assertCount(20, $this->storedGenres());
    }

    public function test_a_guest_gets_401(): void
    {
        $this->json('PUT', '/api/me/genres', ['genre_ids' => [self::DRAMA]]);

        $this->seeStatusCode(401);
    }

    public function test_a_blocked_user_gets_403(): void
    {
        $blocked = User::factory()->blocked()->create();

        $this->save([self::DRAMA], $blocked);

        $this->seeStatusCode(403);
        $this->assertSame(0, UserGenre::where('user_id', $blocked->id)->count());
    }

    public function test_user_genres_columns_match_the_erd_and_are_deleted_with_the_user(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, is_nullable from information_schema.columns
             where table_schema = current_schema() and table_name = 'user_genres' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->is_nullable === 'YES' ? ' NULL' : '')])->all();

        $this->assertSame([
            'user_id' => 'uuid',
            'genre_id' => 'uuid',
            'created_at' => 'timestamp without time zone NULL',
        ], $columns);

        $this->save([self::DRAMA]);
        $this->assertNotNull(UserGenre::where('user_id', $this->user->id)->value('created_at'));

        $this->user->delete();
        $this->assertSame(0, UserGenre::where('user_id', $this->user->id)->count());
    }
}
