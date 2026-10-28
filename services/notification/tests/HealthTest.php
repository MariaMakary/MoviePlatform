<?php

namespace Tests;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/api/health')
            ->seeStatusCode(200)
            ->seeJsonEquals(['status' => 'ok', 'service' => 'notification']);
    }
}