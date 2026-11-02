<?php

namespace App\Providers;

use App\Models\MovieList;
use App\Policies\ListPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register the policies of this service (docs/lumen.md, section 6).
     *
     * Users are authenticated by the jwt guard of packages/common, not here.
     */
    public function boot(): void
    {
        Gate::policy(MovieList::class, ListPolicy::class);
    }
}
