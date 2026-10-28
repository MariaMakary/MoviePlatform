<?php

namespace MoviePlatform\Common;

use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use MoviePlatform\Common\Auth\JwtDecoder;
use MoviePlatform\Common\Http\Middleware\VerifyJwt;
use RuntimeException;
use UnexpectedValueException;

class CommonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jwt.php', 'jwt');

        $this->app->singleton(JwtDecoder::class, function () {
            $path = config('jwt.public_key_path');

            if (! is_file($path)) {
                throw new RuntimeException("JWT public key not found at $path: check JWT_PUBLIC_KEY_PATH in .env.");
            }

            return new JwtDecoder(file_get_contents($path), config('jwt.leeway'));
        });

        config(['auth.guards.jwt' => ['driver' => 'jwt']]);
    }

    public function boot(): void
    {
        Auth::viaRequest('jwt', function (Request $request) {
            $token = $request->bearerToken();

            if (! $token) {
                return null;
            }

            $decoder = app(JwtDecoder::class);

            try {
                return $decoder->decode($token);
            } catch (UnexpectedValueException|DomainException) {
                return null;
            }
        });

        $this->app['router']->aliasMiddleware('jwt', VerifyJwt::class);
    }
}
