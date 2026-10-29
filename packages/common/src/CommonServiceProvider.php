<?php

namespace MoviePlatform\Common;

use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use MoviePlatform\Common\Auth\JwtDecoder;
use MoviePlatform\Common\Console\ConsumeEvents;
use MoviePlatform\Common\Console\ReplayDeadLetters;
use MoviePlatform\Common\Http\Middleware\EnsureAdmin;
use MoviePlatform\Common\Http\Middleware\VerifyJwt;
use MoviePlatform\Common\Http\Middleware\VerifyServiceToken;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use RuntimeException;
use UnexpectedValueException;

class CommonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->isLumen()) {
            $this->app->configure('jwt');
            $this->app->configure('internal');
            $this->app->configure('rabbitmq');
            $this->app->configure('auth');
        }

        $this->mergeConfigFrom(__DIR__.'/../config/jwt.php', 'jwt');
        $this->mergeConfigFrom(__DIR__.'/../config/internal.php', 'internal');
        $this->mergeConfigFrom(__DIR__.'/../config/rabbitmq.php', 'rabbitmq');

        $this->app->singleton(RabbitMqConnection::class, fn () => new RabbitMqConnection(config('rabbitmq')));

        $this->app->singleton(EventConsumer::class, fn ($app) => new EventConsumer(
            $app->make(RabbitMqConnection::class),
            $app->make('db')->connection(),
            $app,
            (array) config('events'),
        ));

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

        $middleware = [
            'jwt' => VerifyJwt::class,
            'admin' => EnsureAdmin::class,
            'service' => VerifyServiceToken::class,
        ];

        if (config('events.queue')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            $this->commands([ConsumeEvents::class, ReplayDeadLetters::class]);
        }

        if ($this->isLumen()) {
            $this->app->routeMiddleware($middleware);
        } else {
            foreach ($middleware as $name => $class) {
                $this->app['router']->aliasMiddleware($name, $class);
            }
        }
    }

    private function isLumen(): bool
    {
        return method_exists($this->app, 'configure') && method_exists($this->app, 'routeMiddleware');
    }
}
