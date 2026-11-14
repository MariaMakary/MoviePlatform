<?php

namespace MoviePlatform\Common\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MoviePlatform\Common\CommonServiceProvider;
use MoviePlatform\Common\Exceptions\JsonExceptionRenderer;
use MoviePlatform\Common\Exceptions\ServiceUnavailable;
use MoviePlatform\Common\Integration\InternalClient;
use Orchestra\Testbench\TestCase;

class InternalClientTest extends TestCase
{
    private const TOKEN = 'test-service-token';

    protected function getPackageProviders($app): array
    {
        return [CommonServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('internal.service_token', self::TOKEN);
        $app['config']->set('internal.services.catalog', 'http://catalog.test/');
    }

    private function assertUnavailable(callable $call): ServiceUnavailable
    {
        try {
            $call();
        } catch (ServiceUnavailable $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertSame('catalog', $e->service);

            return $e;
        }

        $this->fail('ServiceUnavailable was not thrown.');
    }

    public function test_get_sends_the_service_token_to_the_configured_url_and_returns_the_json(): void
    {
        Http::fake(['catalog.test/*' => Http::response(['data' => [['id' => 'a']]])]);

        $this->assertSame(['data' => [['id' => 'a']]], InternalClient::to('catalog')->get('/api/internal/discover', ['genres' => 'Drama', 'limit' => 10]));

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'http://catalog.test/api/internal/discover?genres=Drama&limit=10'
            && $request->hasHeader('X-Service-Token', self::TOKEN)
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_post_sends_json(): void
    {
        Http::fake(['catalog.test/*' => Http::response(['ok' => true], 201)]);

        $this->assertSame(['ok' => true], InternalClient::to('catalog')->post('/api/internal/things', ['name' => 'x']));

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['name'] === 'x' && $request->hasHeader('X-Service-Token', self::TOKEN));
    }

    public function test_a_204_returns_null(): void
    {
        Http::fake(['catalog.test/*' => Http::response(null, 204)]);

        $this->assertNull(InternalClient::to('catalog')->post('/api/internal/things'));
    }

    public function test_get_retries_twice_on_connection_errors_then_succeeds(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            if (++$attempts < 3) {
                throw new ConnectionException('Connection refused');
            }

            return Http::response(['data' => []]);
        });

        $this->assertSame(['data' => []], InternalClient::to('catalog')->get('/api/internal/discover'));
        $this->assertSame(3, $attempts);
    }

    public function test_get_gives_up_after_three_connection_errors(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection refused');
        });

        $this->assertUnavailable(fn () => InternalClient::to('catalog')->get('/api/internal/discover'));
        $this->assertSame(3, $attempts);
    }

    public function test_post_is_never_retried(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection refused');
        });

        $this->assertUnavailable(fn () => InternalClient::to('catalog')->post('/api/internal/things'));
        $this->assertSame(1, $attempts);
    }

    public function test_an_error_answer_is_not_retried_and_throws(): void
    {
        $status = 0;
        Http::fake(function () use (&$status) {
            return Http::response(['message' => 'x'], $status);
        });

        foreach ([500, 503, 401, 404, 422] as $status) {
            $this->assertUnavailable(fn () => InternalClient::to('catalog')->get('/api/internal/discover'));
        }

        Http::assertSentCount(5);
    }

    public function test_a_service_without_a_url_throws_without_calling(): void
    {
        Http::fake();
        config(['internal.services.catalog' => '']);

        $this->assertUnavailable(fn () => InternalClient::to('catalog')->get('/api/internal/discover'));
        Http::assertNothingSent();
    }

    public function test_the_log_never_holds_the_token(): void
    {
        $logged = [];
        Log::shouldReceive('warning')->andReturnUsing(function (string $message, array $context) use (&$logged) {
            $logged[] = $message.json_encode($context);
        });
        Http::fake(['catalog.test/*' => Http::response(['message' => 'x'], 500)]);

        $this->assertUnavailable(fn () => InternalClient::to('catalog')->get('/api/internal/discover'));

        $this->assertNotEmpty($logged);
        $this->assertStringNotContainsString(self::TOKEN, implode(' ', $logged));
    }

    public function test_service_unavailable_renders_as_503(): void
    {
        $response = (new JsonExceptionRenderer())->render(request(), new ServiceUnavailable('catalog'));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(['message' => 'Catalog is unavailable.'], json_decode($response->getContent(), true));
    }
}
