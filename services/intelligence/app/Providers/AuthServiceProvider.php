<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use MoviePlatform\Common\Auth\JwtUser;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register the policies of this service (docs/lumen.md, section 6).
     *
     * Users are authenticated by the jwt guard of packages/common, not here.
     */
    public function boot(): void
    {
        // Recommendations are not a model: a user reads their own, an admin anyone's.
        Gate::define('view-recommendations', fn (JwtUser $user, string $userId) => $user->id === $userId || $user->isAdmin());
    }
}
