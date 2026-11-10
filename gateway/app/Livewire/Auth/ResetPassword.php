<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Opened from the link in the reset email: /reset-password?token=…&email=… */
#[Title('Choose a new password')]
class ResetPassword extends Component
{
    use WithServiceFallback;

    #[Locked, Url]
    public string $token = '';

    #[Locked, Url]
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function resetPassword(IdentityClient $identity)
    {
        $this->unavailable = [];

        $this->fromService('identity', fn () => $identity->resetPassword([
            'email' => $this->email,
            'token' => $this->token,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]));

        if ($this->isUnavailable('identity')) {
            return null;
        }

        session()->flash('success', 'Password changed, you can log in.');

        return $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password', [
            'brokenLink' => $this->token === '' || $this->email === '',
        ]);
    }
}
