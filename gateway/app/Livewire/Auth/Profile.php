<?php

namespace App\Livewire\Auth;

use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('My profile')]
class Profile extends Component
{
    use WithFileUploads, WithServiceFallback;

    public const AVATAR_RULES = ['required', 'file', 'mimes:jpg,png', 'max:2048'];

    #[Locked]
    public bool $filled = false;

    public string $name = '';

    public string $bio = '';

    public $avatar = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function saveProfile(IdentityClient $identity, AuthSession $auth)
    {
        $this->resetErrorBag();
        unset($this->unavailable['save']);
        $previousName = $auth->user()['name'] ?? null;

        $user = $this->fromService('save', fn () => $identity->updateProfile($this->name, $this->bio));

        if ($user === null) {
            return null;
        }

        $auth->updateUser($user);

        if ($user['name'] !== $previousName) {
            try {
                $auth->refresh();
            } catch (ServiceUnavailable) {
            }
        }

        return $this->saved('Profile saved.');
    }

    public function updatedAvatar()
    {
        $identity = app(IdentityClient::class);
        $auth = app(AuthSession::class);

        unset($this->unavailable['save']);
        $this->validate(['avatar' => self::AVATAR_RULES]);

        $user = $this->fromService('save', fn () => $identity->uploadAvatar(
            $this->avatar->get(),
            $this->avatar->getClientOriginalName(),
        ));

        if ($user === null) {
            return null;
        }

        $this->avatar->delete();
        $this->avatar = null;
        $auth->updateUser($user);

        return $this->saved('Avatar updated.');
    }

    public function changePassword(IdentityClient $identity)
    {
        $this->resetErrorBag();
        unset($this->unavailable['save']);

        $this->fromService('save', fn () => $identity->changePassword([
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]));

        if ($this->isUnavailable('save')) {
            return null;
        }

        return $this->saved('Password changed.');
    }

    public function render(IdentityClient $identity): View
    {
        $user = $this->fromService('identity', fn () => $identity->me());

        if ($user !== null && ! $this->filled) {
            $this->name = $user['name'];
            $this->bio = $user['bio'] ?? '';
            $this->filled = true;
        }

        return view('livewire.auth.profile', ['user' => $user]);
    }

    private function saved(string $message)
    {
        session()->flash('success', $message);

        return $this->redirectRoute('profile');
    }
}
