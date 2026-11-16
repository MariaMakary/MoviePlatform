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
        $key = "throttle:$action:".request()->ip();

        if (RateLimiter::tooManyAttempts($key, $limit['attempts'])) {
            $this->addError('form', 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');

            return true;
        }

        RateLimiter::hit($key, $limit['minutes'] * 60);

        return false;
    }
}
