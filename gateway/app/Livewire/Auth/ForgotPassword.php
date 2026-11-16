<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\ThrottlesByIp;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Forgot password')]
class ForgotPassword extends Component
{
    use ThrottlesByIp;
    use WithServiceFallback;

    public string $email = '';

    public bool $sent = false;

    public function send(IdentityClient $identity): void
    {
        $this->unavailable = [];

        if ($this->tooManyAttempts('password_forgot')) {
            return;
        }

        $this->fromService('identity', fn () => $identity->forgotPassword($this->email));

        // Same message whether the account exists or not.
        $this->sent = ! $this->isUnavailable('identity');
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
