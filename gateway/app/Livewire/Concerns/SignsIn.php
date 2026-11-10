<?php

namespace App\Livewire\Concerns;

use App\Exceptions\ServiceRequestException;
use App\Services\AuthSession;

trait SignsIn
{
    use WithServiceFallback;

    /** $next: a route to go to instead of the page the user came from (e.g. the genre picker after registration). */
    protected function signIn(callable $call, string $greeting, ?string $next = null): void
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

            return;
        }

        if ($auth === null) {
            return;
        }

        app(AuthSession::class)->start($auth);
        session()->flash('success', "{$greeting}, {$auth['user']['name']}!");

        if ($next !== null) {
            $this->redirectRoute($next);

            return;
        }

        $this->redirectIntended(route('home'));
    }
}
