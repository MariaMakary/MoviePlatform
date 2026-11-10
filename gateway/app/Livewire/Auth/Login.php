<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\SignsIn;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Log in')]
class Login extends Component
{
    use SignsIn;

    public string $email = '';

    public string $password = '';

    public function login(IdentityClient $identity): void
    {
        $this->signIn(fn () => $identity->login($this->email, $this->password), 'Welcome back');
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
