<?php

namespace Tests;

use Illuminate\Support\Facades\Log;
use MoviePlatform\Common\Testing\FakesHealthDependencies;

class HealthTest extends TestCase
{
    use FakesHealthDependencies;

    private function assertHealth(int $code, string $database, string $rabbitmq): void
    {
        $this->get('/api/health')
            ->seeStatusCode($code)
            ->seeJsonEquals([
                'status' => $code === 200 ? 'ok' : 'degraded',
                'service' => 'identity',
                'database' => $database,
                'rabbitmq' => $rabbitmq,
            ]);

        foreach (['s3cret', 'amqp://', 'missing-', 'Exception'] as $detail) {
            $this->assertStringNotContainsString($detail, $this->response->getContent());
        }
    }

    public function test_everything_up_returns_200(): void
    {
        $this->fakeDatabase(true);
        $this->fakeBroker(true);

        $this->assertHealth(200, 'ok', 'ok');
    }

    public function test_database_down_returns_503(): void
    {
        $this->fakeDatabase(false);
        $this->fakeBroker(true);

        $this->assertHealth(503, 'fail', 'ok');
    }

    public function test_broker_down_returns_503(): void
    {
        $this->fakeDatabase(true);
        $this->fakeBroker(false);

        $this->assertHealth(503, 'ok', 'fail');
    }

    public function test_both_down_returns_503(): void
    {
        $this->fakeDatabase(false);
        $this->fakeBroker(false);

        $this->assertHealth(503, 'fail', 'fail');
    }

    public function test_the_real_errors_are_logged(): void
    {
        Log::spy();
        $this->fakeDatabase(false);
        $this->fakeBroker(false);

        $this->get('/api/health');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context) => $context['check'] === 'database' && $context['error'] !== '')
            ->once();
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context) => $context['check'] === 'rabbitmq' && str_contains($context['error'], 'Connection refused'))
            ->once();
    }
}
