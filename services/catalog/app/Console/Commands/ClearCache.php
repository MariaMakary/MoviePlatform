<?php

namespace App\Console\Commands;

use App\Integration\Clients\TmdbClient;
use Illuminate\Console\Command;

class ClearCache extends Command
{
    protected $signature = 'catalog:clear-cache';

    protected $description = 'Forget the cached TMDB lists (popular, upcoming, search, discover).';

    public function handle(): int
    {
        TmdbClient::clearCache();

        $this->info('TMDB list cache cleared.');

        return self::SUCCESS;
    }
}
