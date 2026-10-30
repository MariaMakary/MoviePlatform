<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MoviePlatform\Common\Auth\JwtUser;

class AuthService
{
    private const DUMMY_HASH = '$2y$10$cf5AjmmM59rn2YPlJxMtOuWeqL7t108JbnNVIHe7XkWJnTG9dPZO2';

    public function __construct(private TokenService $tokens) {}

    public function login(string $email, string $password): IssuedToken
    {
        $user = User::firstWhere('email', Str::lower(trim($email)));

        $matches = Hash::check($password, $user?->password ?? self::DUMMY_HASH);

        if (! $matches || $user?->password === null) {
            abort(401, 'Invalid credentials.');
        }

        $this->ensureNotBlocked($user);

        return $this->tokens->issue($user);
    }

    public function refresh(JwtUser $jwt): IssuedToken
    {
        return $this->tokens->issue($this->current($jwt));
    }

    public function current(JwtUser $jwt): User
    {
        $user = User::find($jwt->id);

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $this->ensureNotBlocked($user);

        return $user;
    }

    private function ensureNotBlocked(User $user): void
    {
        if ($user->is_blocked) {
            abort(403, 'Account blocked.');
        }
    }
}
