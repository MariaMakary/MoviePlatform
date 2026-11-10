<?php

namespace App\Console\Commands;

use App\Integration\Clients\TmdbUnavailable;
use App\Services\MovieSyncService;
use Illuminate\Console\Command;

class SyncPopular extends Command
{
    private const MAX_PAGES = 500;

    protected $signature = 'catalog:sync-popular {pages=1 : Number of TMDB pages of popular movies (20 movies each)}';

    protected $description = 'Save the popular TMDB movies with their genres, cast, directors and trailer.';

    public function handle(MovieSyncService $sync): int
    {
        $pages = (int) $this->argument('pages');

        if ($pages < 1 || $pages > self::MAX_PAGES) {
            $this->error('pages must be between 1 and '.self::MAX_PAGES.'.');

            return self::INVALID;
        }

        $bar = $this->output->createProgressBar($pages * MovieSyncService::MOVIES_PER_PAGE);

        try {
            $count = $sync->syncPopular($pages, fn () => $bar->advance());
        } catch (TmdbUnavailable $e) {
            $this->newLine();
            $this->error($e->getMessage().' Movies saved before the error are kept; run the command again.');

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine();
        $this->info("Synced {$count} movies.");

        return self::SUCCESS;
    }
}
