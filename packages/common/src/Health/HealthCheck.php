<?php

namespace MoviePlatform\Common\Health;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class HealthCheck
{
    private const CONNECTION = 'health';

    public function __construct(private DatabaseManager $db, private AmqpConnector $amqp)
    {
    }

    public function run(): array
    {
        $database = $this->database();
        $rabbitmq = $this->rabbitmq();

        return [
            'status' => $database && $rabbitmq ? 'ok' : 'degraded',
            'service' => config('health.service'),
            'database' => $database ? 'ok' : 'fail',
            'rabbitmq' => $rabbitmq ? 'ok' : 'fail',
        ];
    }

    public function database(): bool
    {
        return $this->probe('database', function () {
            $config = (array) config('database.connections.'.config('database.default'));
            $config['options'] = [PDO::ATTR_TIMEOUT => $this->timeout()] + ($config['options'] ?? []);
            config(['database.connections.'.self::CONNECTION => $config]);

            try {
                $this->db->connection(self::CONNECTION)->select('select 1');
            } finally {
                $this->db->purge(self::CONNECTION);
            }
        });
    }

    public function rabbitmq(): bool
    {
        return $this->probe('rabbitmq', fn () => $this->amqp->connect($this->timeout()));
    }

    private function probe(string $check, callable $callback): bool
    {
        try {
            $callback();

            return true;
        } catch (Throwable $e) {
            Log::warning("Health check failed: $check", ['check' => $check, 'error' => get_class($e).': '.$e->getMessage()]);

            return false;
        }
    }

    private function timeout(): int
    {
        return (int) config('health.timeout', 2);
    }
}
