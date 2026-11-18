<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/** Per-IP limit for a public form, with the limits in config/throttle.php. */
trait ThrottlesByIp
{
    /** Counts this attempt; when over the limit, shows the error on the form and returns true. */
    protected function tooManyAttempts(string $action): bool
    {
        $limit = config("throttle.$action");
        $key = $this->throttleKey($action);

        if (RateLimiter::tooManyAttempts($key, $limit['attempts'])) {
            $this->addError('form', 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');

            return true;
        }

        RateLimiter::hit($key, $limit['minutes'] * 60);

        return false;
    }

    /** After a successful login: only failed attempts count, so a class sharing one Wi-Fi IP is not locked out. */
    protected function clearAttempts(string $action): void
    {
        RateLimiter::clear($this->throttleKey($action));
    }

    private function throttleKey(string $action): string
    {
        return "throttle:$action:".request()->ip();
    }
}
