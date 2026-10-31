<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\SignsIn;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Sign up')]
class Register extends Component
{
    use SignsIn;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(IdentityClient $identity): void
    {
        $this->signIn(fn () => $identity->register([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]), 'Welcome');
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
