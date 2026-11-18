<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\SignsIn;
use App\Livewire\Concerns\ThrottlesByIp;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Log in')]
class Login extends Component
{
    use SignsIn;
    use ThrottlesByIp;

    public string $email = '';

    public string $password = '';

    public function login(IdentityClient $identity): void
    {
        if ($this->tooManyAttempts('login')) {
            return;
        }

        if ($this->signIn(fn () => $identity->login($this->email, $this->password), 'Welcome back')) {
            $this->clearAttempts('login');
        }
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
