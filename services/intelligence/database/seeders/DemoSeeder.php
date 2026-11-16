<?php

namespace Database\Seeders;

use App\Services\RecommendationService;
use App\Services\TasteProfileService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MoviePlatform\Common\Exceptions\ServiceUnavailable;
use RuntimeException;

/**
 * The demo users' taste profiles, recommendations and review sentiment for seeds/demo.json (task 1289).
 * Profiles are built by TasteProfileService from the same events the consumer gets (favourite genres, watches,
 * ratings), so Catalog must be running: it gives each movie's genres and people. Sentiment comes from the file,
 * so no Azure call is needed. Running it again replaces the demo users' rows. Never in production.
 */
class DemoSeeder extends Seeder
{
    public function __construct(private TasteProfileService $profiles, private RecommendationService $recommendations) {}

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The demo data is never seeded in production.');
        }

        $demo = json_decode(file_get_contents(base_path('../../seeds/demo.json')), true, flags: JSON_THROW_ON_ERROR);
        $user = fn (int $n) => $demo['users'][$n - 1]['id'];
        $movie = fn (string $key) => $demo['movies'][$key]['id'];
        $userIds = array_column($demo['users'], 'id');

        try {
            DB::transaction(function () use ($demo, $user, $movie, $userIds) {
                foreach ($userIds as $id) {
                    $this->profiles->forgetUser($id);
                    $this->recommendations->forgetUser($id);
                }

                DB::table('sentiment_results')->whereIn('review_id', array_column($demo['reviews'], 'id'))->delete();
                foreach ($demo['reviews'] as $row) {
                    DB::table('sentiment_results')->insert(['id' => (string) Str::uuid(), 'review_id' => $row['id'],
                        'label' => $row['sentiment'][0], 'score' => $row['sentiment'][1], 'created_at' => Carbon::parse($row['at']), 'updated_at' => Carbon::parse($row['at'])]);
                }

                foreach ($demo['users'] as $row) {
                    $this->profiles->setFavoriteGenres($row['id'], array_map(fn (string $genre) => $demo['genres'][$genre]['id'], $row['genres']));
                }

                foreach ($this->events($demo, $user, $movie) as [$event, $userId, $movieId, $score]) {
                    $event === 'watched'
                        ? $this->profiles->watched($userId, $movieId)
                        : $this->profiles->rated($userId, $movieId, $score);
                }
            });
        } catch (ServiceUnavailable $e) {
            throw new RuntimeException('Catalog is not answering: start it and seed Catalog first (README, Demo data).', previous: $e);
        }

        $failed = 0;
        foreach ($userIds as $id) {
            try {
                $this->recommendations->recompute($id);
            } catch (ServiceUnavailable) {
                $failed++;
            }
        }

        $this->command?->info(count($userIds).' taste profiles and '.count($demo['reviews']).' sentiment results.'
            .($failed > 0 ? " Recommendations were not computed for $failed users: they are computed on their next rating." : ''));
    }

    /** Watches and ratings, oldest first: [watched|rated, user id, movie id, score]. */
    private function events(array $demo, callable $user, callable $movie): array
    {
        $events = [
            ...array_map(fn (array $row) => ['watched', $user($row['user']), $movie($row['movie']), null, $row['watched_at']], $demo['watch_history']),
            ...array_map(fn (array $row) => ['rated', $user($row['user']), $movie($row['movie']), $row['score'], $row['at']], $demo['ratings']),
        ];
        usort($events, fn (array $a, array $b) => strcmp($a[4], $b[4]));

        return $events;
    }
}
