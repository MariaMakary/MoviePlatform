<?php

namespace App\Console\Commands;

use App\Integration\Clients\TmdbUnavailable;
use App\Services\MovieSyncService;
use Illuminate\Console\Command;

class SyncGenres extends Command
{
    protected $signature = 'catalog:sync-genres';

    protected $description = 'Save every TMDB movie genre (new ones are added, existing ones updated).';

    public function handle(MovieSyncService $sync): int
    {
        try {
            $count = $sync->syncGenres();
        } catch (TmdbUnavailable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$count} genres.");

        return self::SUCCESS;
    }
}
