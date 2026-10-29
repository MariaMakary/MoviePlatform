<?php

namespace Tests\Feature;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Services\Clients\BaseClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BaseClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        config(['services.catalog.url' => 'http://127.0.0.1:8002']);
    }

    private function client(): BaseClient
    {
        return new class extends BaseClient
        {
            protected string $service = 'catalog';

            public function call(string $method, mixed ...$arguments): ?array
            {
                return $this->$method(...$arguments);
            }
        };
    }

    private function failConnections(int &$attempts): void
    {
        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('Connection refused');
        });
    }

    public function test_get_uses_the_service_url_and_sends_json_headers_and_the_session_jwt(): void
    {
        session([BaseClient::SESSION_TOKEN => 'the-jwt']);
        Http::fake(['*' => Http::response(['data' => ['id' => 1]])]);

        $result = $this->client()->call('get', '/api/movies', ['page' => 2]);

        $this->assertSame(['data' => ['id' => 1]], $result);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://127.0.0.1:8002/api/movies?page=2'
            && $request->hasHeader('Authorization', 'Bearer the-jwt')
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_no_authorization_header_without_a_session_jwt(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->client()->call('get', '/api/movies');

        Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization'));
    }

    public function test_post_sends_a_json_body(): void
    {
        Http::fake(['*' => Http::response(['data' => ['score' => 4]], 201)]);

        $this->assertSame(['data' => ['score' => 4]], $this->client()->call('post', '/api/ratings', ['score' => 4]));

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['score'] === 4);
    }

    public function test_204_returns_null(): void
    {
        Http::fake(['*' => Http::response(null, 204)]);

        $this->assertNull($this->client()->call('delete', '/api/ratings/1'));
    }

    public function test_get_is_retried_twice_on_connection_errors_then_throws_service_unavailable(): void
    {
        $attempts = 0;
        $this->failConnections($attempts);

        try {
            $this->client()->call('get', '/api/movies');
            $this->fail('ServiceUnavailable was not thrown.');
        } catch (ServiceUnavailable $e) {
            $this->assertSame('Catalog', $e->service);
            $this->assertSame(503, $e->getStatusCode());
        }

        $this->assertSame(3, $attempts);
    }

    public function test_get_succeeds_when_a_retry_works(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new ConnectionException('Connection refused');
            }

            return Http::response(['data' => []]);
        });

        $this->assertSame(['data' => []], $this->client()->call('get', '/api/movies'));
        $this->assertSame(2, $attempts);
    }

    public function test_health_check_is_not_retried(): void
    {
        $attempts = 0;
        $this->failConnections($attempts);

        try {
            $this->client()->health();
            $this->fail('ServiceUnavailable was not thrown.');
        } catch (ServiceUnavailable) {
            $this->assertSame(1, $attempts);
        }
    }

    public function test_writes_are_never_retried(): void
    {
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $attempts = 0;
            $this->failConnections($attempts);

            try {
                $this->client()->call($method, '/api/ratings');
                $this->fail("ServiceUnavailable was not thrown for $method.");
            } catch (ServiceUnavailable) {
                $this->assertSame(1, $attempts, "$method was retried.");
            }
        }
    }

    public function test_4xx_is_not_retried_and_keeps_the_message_and_status(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return Http::response(['message' => 'Movie not found.'], 404);
        });

        try {
            $this->client()->call('get', '/api/movies/x');
            $this->fail('ServiceRequestException was not thrown.');
        } catch (ServiceRequestException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('Movie not found.', $e->getMessage());
        }

        $this->assertSame(1, $attempts);
    }

    public function test_422_becomes_a_validation_exception_with_the_field_errors(): void
    {
        Http::fake(['*' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => ['score' => ['The score must be between 1 and 5.']],
        ], 422)]);

        try {
            $this->client()->call('post', '/api/ratings', ['score' => 9]);
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(['score' => ['The score must be between 1 and 5.']], $e->errors());
        }
    }

    public function test_5xx_throws_service_unavailable(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Server error.'], 500)]);

        $this->expectException(ServiceUnavailable::class);

        $this->client()->call('get', '/api/movies');
    }
}
