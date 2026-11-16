<?php

namespace App\Providers;

use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireLogin;
use App\Services\AuthSession;
use App\Services\Clients\ServiceOutages;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ServiceOutages::class);
    }

    public function boot(): void
    {
        Livewire::addPersistentMiddleware([RequireLogin::class, RequireAdmin::class]);

        // 'throttle:web' on every web request (config/throttle.php): per logged-in user, else per IP.
        RateLimiter::for('web', function (Request $request) {
            $limit = config('throttle.web');

            return Limit::perMinutes($limit['minutes'], $limit['attempts'])
                ->by(app(AuthSession::class)->user()['id'] ?? $request->ip());
        });

        // Only for /broadcasting/auth: the Gateway has no user table, the logged-in user lives in the session.
        Auth::viaRequest('session-user', function () {
            $user = app(AuthSession::class)->user();

            return $user ? new GenericUser($user) : null;
        });
    }
}
