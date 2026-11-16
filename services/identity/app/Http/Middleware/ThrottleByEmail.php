<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * 'throttle:<name>' limits one auth endpoint per email address, with the limits in config/throttle.php.
 * Over the limit: 429 with a Retry-After header (seconds).
 */
class ThrottleByEmail
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next, string $name): Response
    {
        $limit = config("throttle.$name");
        $email = Str::lower(trim((string) $request->input('email')));
        $key = "throttle:$name:".sha1($email !== '' ? $email : (string) $request->ip());

        if ($this->limiter->tooManyAttempts($key, $limit['attempts'])) {
            $seconds = $this->limiter->availableIn($key);

            throw new ThrottleRequestsException("Too many attempts. Try again in $seconds seconds.", null, [
                'Retry-After' => $seconds,
            ]);
        }

        $this->limiter->hit($key, $limit['minutes'] * 60);

        $response = $next($request);

        if ($limit['clear_on_success'] && $response->isSuccessful()) {
            $this->limiter->clear($key);
        }

        return $response;
    }
}
