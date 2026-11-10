<?php

namespace Tests;

class JsonErrorsTest extends TestCase
{
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
}
