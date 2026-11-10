<?php

namespace App\Services\Clients;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

abstract class BaseClient
{
    public const SESSION_TOKEN = 'jwt';

    private const TIMEOUT_SECONDS = 3;

    private const CONNECT_TIMEOUT_SECONDS = 1;

    private const GET_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 100;

    protected string $service;

    public function health(): ?array
    {
        return $this->send('GET', '/api/health', [], retry: false, accept: [503]);
    }

    protected function get(string $path, array $query = []): ?array
    {
        return $this->send('GET', $path, ['query' => $query], retry: true);
    }

    protected function post(string $path, array $data = []): ?array
    {
        return $this->send('POST', $path, ['json' => $data]);
    }

    protected function put(string $path, array $data = []): ?array
    {
        return $this->send('PUT', $path, ['json' => $data]);
    }

    protected function patch(string $path, array $data = []): ?array
    {
        return $this->send('PATCH', $path, ['json' => $data]);
    }

    protected function delete(string $path, array $data = []): ?array
    {
        return $this->send('DELETE', $path, ['json' => $data]);
    }

    protected function upload(string $path, string $field, string $contents, string $fileName): ?array
    {
        return $this->send('POST', $path, ['multipart' => [
            ['name' => $field, 'contents' => $contents, 'filename' => $fileName],
        ]], multipart: true);
    }

    /** POST that answers with a file instead of JSON (e.g. an .ics): returns the raw body. Errors as for `post`. */
    protected function postForFile(string $path, array $data = []): string
    {
        return $this->respond('POST', $path, ['json' => $data])->body();
    }

    private function send(string $method, string $path, array $options, bool $retry = false, array $accept = [], bool $multipart = false): ?array
    {
        $response = $this->respond($method, $path, $options, $retry, $accept, $multipart);

        return $response->status() === 204 ? null : $response->json();
    }

    /** Sends the request and returns a successful (or accepted) response; throws for everything else. */
    private function respond(string $method, string $path, array $options, bool $retry = false, array $accept = [], bool $multipart = false): Response
    {
        try {
            $response = $this->request($retry, $multipart)->send($method, $path, $options);
        } catch (ConnectionException $e) {
            throw $this->unavailable($method, $path, $e);
        }

        if ($response->successful() || (in_array($response->status(), $accept, true) && is_array($response->json()))) {
            return $response;
        }

        if ($response->status() === 422) {
            throw ValidationException::withMessages(
                $response->json('errors') ?? ['form' => [$response->json('message') ?? 'The given data was invalid.']]
            );
        }

        if ($response->serverError()) {
            throw $this->unavailable($method, $path, null, $response->status());
        }

        throw new ServiceRequestException($this->name(), $response->status(), $response->json('message') ?? 'Request failed.');
    }

    private function request(bool $retry, bool $multipart = false): PendingRequest
    {
        $request = Http::baseUrl(config("services.{$this->service}.url"))
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS);

        if ($multipart) {
            $request->asMultipart();
        }

        if ($token = session(self::SESSION_TOKEN)) {
            $request->withToken($token);
        }

        if ($retry) {
            $request->retry(self::GET_ATTEMPTS, self::RETRY_DELAY_MS, fn (Throwable $e) => $e instanceof ConnectionException, throw: false);
        }

        return $request;
    }

    private function unavailable(string $method, string $path, ?Throwable $previous, ?int $status = null): ServiceUnavailable
    {
        Log::warning("{$this->name()} service unavailable", [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'error' => $previous?->getMessage(),
        ]);

        return new ServiceUnavailable($this->name(), $previous);
    }

    private function name(): string
    {
        return Str::headline($this->service);
    }
}
