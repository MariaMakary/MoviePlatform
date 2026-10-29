<?php

namespace MoviePlatform\Common\Tests;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use MoviePlatform\Common\CommonServiceProvider;
use MoviePlatform\Common\Health\HealthCheck;
use MoviePlatform\Common\Http\Controllers\HealthController;
use MoviePlatform\Common\Testing\FakesHealthDependencies;
use Orchestra\Testbench\TestCase;

class HealthCheckTest extends TestCase
{
    use FakesHealthDependencies;

    protected function getPackageProviders($app): array
    {
        return [CommonServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('health.service', 'common-test');
    }

    protected function defineRoutes($router): void
    {
        Route::get('/api/health', HealthController::class);
    }

    public function test_everything_up_returns_200(): void
    {
        $this->fakeDatabase(true);
        $this->fakeBroker(true);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['status' => 'ok', 'service' => 'common-test', 'database' => 'ok', 'rabbitmq' => 'ok']);
    }

    public function test_failures_return_503_without_details_and_are_logged(): void
    {
        Log::spy();
        $this->fakeDatabase(false);
        $this->fakeBroker(false);

        $response = $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'degraded', 'service' => 'common-test', 'database' => 'fail', 'rabbitmq' => 'fail']);

        $this->assertStringNotContainsString('s3cret', $response->getContent());
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_the_real_connector_fails_when_nothing_listens(): void
    {
        config(['rabbitmq.host' => '127.0.0.1', 'rabbitmq.port' => 1]);

        $this->assertFalse(app(HealthCheck::class)->rabbitmq());
    }
}
