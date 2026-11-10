<?php

namespace App\Livewire\Concerns;

trait RedirectsGuestsToLogin
{
    // Livewire calls come from the page, so the browser's referer is the page to come back to after logging in.
    protected function redirectToLogin(string $message)
    {
        session()->put('url.intended', url()->previous(route('home')));
        session()->flash('info', $message);

        return $this->redirectRoute('login');
    }
}
