<?php

namespace Tests;

use MoviePlatform\Common\Testing\FakesHealthDependencies;

class HealthTest extends TestCase
{
    use FakesHealthDependencies;

    public function test_health_endpoint_returns_ok(): void
    {
        $this->fakeDatabase(true);
        $this->fakeBroker(true);

        $this->get('/api/health')
            ->seeStatusCode(200)
            ->seeJsonEquals(['status' => 'ok', 'service' => 'engagement', 'database' => 'ok', 'rabbitmq' => 'ok']);
    }
}
