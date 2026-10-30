<?php

namespace Tests;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Auth\Role;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class RegisterTest extends TestCase
{
    use DatabaseTransactions;

    private array $published = [];

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
    }

    private function register(array $fields = []): void
    {
        $this->json('POST', '/api/register', [
            'name' => 'Maria Makary',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$fields,
        ]);
    }

    public function test_register_returns_201_with_a_token_the_jwt_middleware_accepts(): void
    {
        $this->register();

        $this->seeStatusCode(201)->seeJson(['user' => [
            'id' => User::firstWhere('email', 'maria@example.com')->id,
            'name' => 'Maria Makary', 'email' => 'maria@example.com', 'avatar' => null, 'role' => 'user',
        ]]);
        $data = $this->response->getData(true)['data'];
        $this->assertArrayHasKey('expires_at', $data);

        $this->get('/api/me', ['Authorization' => "Bearer {$data['token']}"])
            ->seeStatusCode(200)
            ->seeJson(['email' => 'maria@example.com']);
    }

    public function test_the_user_is_saved_with_a_hashed_password_and_a_lowercase_email(): void
    {
        $this->register(['email' => 'Maria@Example.COM']);

        $this->seeStatusCode(201);
        $user = User::firstWhere('email', 'maria@example.com');
        $this->assertNotNull($user);
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_role_and_is_blocked_cannot_be_set_by_the_request(): void
    {
        $this->register(['role' => 'admin', 'is_blocked' => true]);

        $user = User::firstWhere('email', 'maria@example.com');
        $this->assertSame(Role::User, $user->role);
        $this->assertFalse($user->is_blocked);
    }

    public function test_user_registered_is_published_with_the_agreed_payload(): void
    {
        $this->register();

        $user = User::firstWhere('email', 'maria@example.com');
        $this->assertCount(1, $this->published);
        [$routingKey, $envelope] = $this->published[0];
        $this->assertSame('user.registered', $routingKey);
        $this->assertSame('UserRegistered', $envelope['event']);
        $this->assertSame(['user_id' => $user->id, 'name' => 'Maria Makary', 'email' => 'maria@example.com'], $envelope['data']);
    }

    public function test_a_taken_email_in_any_case_gets_422_on_email_and_publishes_nothing(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);

        $this->register(['email' => 'MARIA@example.com']);

        $this->seeStatusCode(422)
            ->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => ['email' => ['The email has already been taken.']]]);
        $this->assertSame(1, User::where('email', 'maria@example.com')->count());
        $this->assertSame([], $this->published);
    }

    public function test_register_validates_its_fields(): void
    {
        $this->register([
            'name' => str_repeat('a', 101),
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $this->seeStatusCode(422)->seeJsonStructure(['message', 'errors' => ['name', 'email', 'password']]);
        $this->assertSame([], $this->published);
    }

    public function test_the_password_must_be_confirmed(): void
    {
        $this->register(['password_confirmation' => 'password124']);

        $this->seeStatusCode(422)->seeJsonStructure(['errors' => ['password']]);
    }
}
