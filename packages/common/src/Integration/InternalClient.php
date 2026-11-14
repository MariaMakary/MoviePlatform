<?php

namespace MoviePlatform\Common\Integration;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MoviePlatform\Common\Exceptions\ServiceUnavailable;
use Throwable;

class InternalClient
{
    private const GET_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 100;

    public function __construct(private string $service) {}

    public static function to(string $service): static
    {
        return new static($service);
    }

    public function get(string $path, array $query = []): ?array
    {
        return $this->send('GET', $path, ['query' => $query], retry: true);
    }

    public function post(string $path, array $data = []): ?array
    {
        return $this->send('POST', $path, ['json' => $data]);
    }

    private function send(string $method, string $path, array $options, bool $retry = false): ?array
    {
        try {
            $response = $this->request($retry)->send($method, $path, $options);
        } catch (ConnectionException $e) {
            throw $this->unavailable($method, $path, null, $e);
        }

        if (! $response->successful()) {
            throw $this->unavailable($method, $path, $response);
        }

        return $response->status() === 204 ? null : $response->json();
    }

    private function request(bool $retry): PendingRequest
    {
        $url = (string) config("internal.services.{$this->service}");

        if ($url === '') {
            throw $this->unavailable('', '', null, null, "no URL in internal.services.{$this->service}");
        }

        $request = Http::baseUrl(rtrim($url, '/'))
            ->withHeaders(['X-Service-Token' => (string) config('internal.service_token')])
            ->acceptJson()
            ->timeout((int) config('internal.timeout', 3))
            ->connectTimeout((int) config('internal.timeout', 3));

        if ($retry) {
            $request->retry(self::GET_ATTEMPTS, self::RETRY_DELAY_MS, fn (Throwable $e) => $e instanceof ConnectionException, throw: false);
        }

        return $request;
    }

    private function unavailable(string $method, string $path, ?Response $response, ?Throwable $previous = null, ?string $error = null): ServiceUnavailable
    {
        Log::warning("Internal call to {$this->service} failed", [
            'method' => $method,
            'path' => $path,
            'status' => $response?->status(),
            'error' => $error ?? $previous?->getMessage(),
        ]);

        return new ServiceUnavailable($this->service, $previous);
    }
}
