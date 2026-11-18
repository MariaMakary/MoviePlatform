<?php

namespace App\Livewire\Concerns;

use App\Exceptions\ServiceRequestException;
use App\Services\AuthSession;

trait SignsIn
{
    use WithServiceFallback;

    /** $next: a route to go to instead of the page the user came from (e.g. the genre picker after registration). */
    /** True when the user is now logged in. */
    protected function signIn(callable $call, string $greeting, ?string $next = null): bool
    {
        $this->unavailable = [];

        try {
            $auth = $this->fromService('identity', $call);
        } catch (ServiceRequestException $e) {
            $this->addError('form', match ($e->getStatusCode()) {
                401 => 'Invalid email or password.',
                403 => 'Your account is blocked.',
                default => throw $e,
            });

            return false;
        }

        if ($auth === null) {
            return false;
        }

        app(AuthSession::class)->start($auth);
        session()->flash('success', "{$greeting}, {$auth['user']['name']}!");

        if ($next !== null) {
            $this->redirectRoute($next);

            return true;
        }

        $this->redirectIntended(route('home'));

        return true;
    }
}
