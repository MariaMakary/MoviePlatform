<?php

namespace MoviePlatform\Common\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class JwtUser implements Authenticatable
{
    public function __construct(
        public readonly string $id,
        public readonly Role $role,
        public readonly string $name,
    ) {}

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
