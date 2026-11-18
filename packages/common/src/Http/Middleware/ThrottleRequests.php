<?php

namespace MoviePlatform\Common\Http\Middleware;

use Closure;
use DomainException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use MoviePlatform\Common\Auth\JwtDecoder;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class ThrottleRequests
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next, string $name = 'api'): Response
    {
        $limit = config("throttle.$name");
        $by = $this->identify($request, $limit['by']);

        if ($by === null) {
            return $next($request);
        }

        $key = "throttle:$name:".sha1($by);

        if ($this->limiter->tooManyAttempts($key, $limit['attempts'])) {
            $seconds = $this->limiter->availableIn($key);

            throw new ThrottleRequestsException("Too many attempts. Try again in $seconds seconds.", null, ['Retry-After' => $seconds]);
        }

        $this->limiter->hit($key, $limit['minutes'] * 60);

        $response = $next($request);

        if (($limit['clear_on_success'] ?? false) && $response->isSuccessful()) {
            $this->limiter->clear($key);
        }

        return $response;
    }

    private function identify(Request $request, string $by): ?string
    {
        if ($by === 'email') {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return $email !== '' ? "email:$email" : 'ip:'.$request->ip();
        }

        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        try {
            return 'user:'.app(JwtDecoder::class)->decode($token)->id;
        } catch (UnexpectedValueException|DomainException) {
            return null;
        }
    }
}
