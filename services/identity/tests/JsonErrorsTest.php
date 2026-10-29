<?php

namespace Tests;

use App\Models\User;
use App\Services\TokenService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Mockery;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;
use Psr\Log\LoggerInterface;
use RuntimeException;

class JsonErrorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $fixtures.'/test-public.pem',
        ]);

        Gate::define('update-fixture', fn ($user) => false);

        $router = $this->app->router;
        $router->post('/api/test-validate', '\Tests\Fixtures\ErrorTestController@store');
        $router->group(['middleware' => 'jwt'], function ($router) {
            $router->put('/api/test-authorize', '\Tests\Fixtures\ErrorTestController@update');
        });
        $router->get('/api/test-crash', function () {
            throw new RuntimeException('SQLSTATE[08006] password=s3cret');
        });
        $router->get('/api/test-missing-model', function () {
            throw (new ModelNotFoundException())->setModel(User::class, ['0193a7c0-1111-7aaa-8bbb-000000000001']);
        });
        $router->get('/api/test-list', function (Request $request) {
            $query = new class
            {
                public function paginate(int $perPage, array $columns, string $pageName, int $page): LengthAwarePaginator
                {
                    $items = array_map(fn ($i) => ['n' => $i], range(1, 250));

                    return new LengthAwarePaginator(array_slice($items, ($page - 1) * $perPage, $perPage), 250, $perPage, $page);
                }
            };

            return PaginatedCollection::fromQuery($query, JsonResource::class, $request);
        });
    }

    public function test_failed_validation_returns_422_with_message_and_errors(): void
    {
        $this->json('POST', '/api/test-validate', ['email' => 'not-an-email'])
            ->seeStatusCode(422)
            ->seeJsonEquals([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'email' => ['The email must be a valid email address.'],
                    'password' => ['The password field is required.'],
                ],
            ]);
    }

    public function test_unknown_route_returns_404_json(): void
    {
        $this->get('/api/does-not-exist')
            ->seeStatusCode(404)
            ->seeJsonEquals(['message' => 'Not found.']);
    }

    public function test_wrong_method_returns_405_json(): void
    {
        $this->post('/api/health')
            ->seeStatusCode(405)
            ->seeJsonEquals(['message' => 'Method not allowed.']);
    }

    public function test_missing_model_returns_404_json(): void
    {
        $this->get('/api/test-missing-model')
            ->seeStatusCode(404)
            ->seeJsonEquals(['message' => 'User not found.']);
    }

    public function test_refused_policy_returns_403_json(): void
    {
        $user = new User(['name' => 'Maria Makary']);
        $user->setKeyType('string')->setIncrementing(false);
        $user->forceFill(['id' => '0193a7c0-1111-7aaa-8bbb-000000000001', 'role' => 'user']);
        $token = app(TokenService::class)->issue($user);

        $this->put('/api/test-authorize', [], ['Authorization' => "Bearer $token"])
            ->seeStatusCode(403)
            ->seeJsonEquals(['message' => 'This action is unauthorized.']);
    }

    public function test_unexpected_exception_returns_500_without_details_and_is_logged(): void
    {
        $logger = Mockery::spy(LoggerInterface::class);
        $this->app->instance(LoggerInterface::class, $logger);

        $this->get('/api/test-crash')
            ->seeStatusCode(500)
            ->seeJsonEquals(['message' => 'Server error.']);

        $this->assertStringNotContainsString('s3cret', $this->response->getContent());
        $logger->shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'SQLSTATE'))->once();
    }

    public function test_paginated_list_returns_exactly_three_meta_fields(): void
    {
        $this->get('/api/test-list?page=2&per_page=5')
            ->seeStatusCode(200)
            ->seeJsonEquals([
                'data' => [['n' => 6], ['n' => 7], ['n' => 8], ['n' => 9], ['n' => 10]],
                'meta' => ['current_page' => 2, 'last_page' => 50, 'total' => 250],
            ]);
    }

    public function test_per_page_is_20_by_default_and_capped_at_100(): void
    {
        $this->get('/api/test-list');
        $this->assertCount(20, json_decode($this->response->getContent(), true)['data']);

        $this->get('/api/test-list?per_page=500');
        $body = json_decode($this->response->getContent(), true);
        $this->assertCount(100, $body['data']);
        $this->assertSame(['current_page' => 1, 'last_page' => 3, 'total' => 250], $body['meta']);
    }
}
