<?php

namespace App\Providers;

use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireLogin;
use App\Services\Clients\ServiceOutages;
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
    }
}
