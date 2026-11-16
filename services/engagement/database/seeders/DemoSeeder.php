<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The demo users' watchlists, watch history, ratings, reviews and lists of seeds/demo.json (task 1289).
 * Review sentiment comes from the file (the same values Intelligence seeds), so no Azure call is needed.
 * Running it again replaces the demo users' rows and leaves everyone else alone. Never in production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The demo data is never seeded in production.');
        }

        $demo = json_decode(file_get_contents(base_path('../../seeds/demo.json')), true, flags: JSON_THROW_ON_ERROR);
        $user = fn (int $n) => $demo['users'][$n - 1]['id'];
        $movie = fn (string $key) => $demo['movies'][$key]['id'];
        $at = fn (string $time) => Carbon::parse($time)->utc();

        DB::transaction(function () use ($demo, $user, $movie, $at) {
            $userIds = array_column($demo['users'], 'id');
            foreach (['watchlist', 'watch_history', 'ratings', 'reviews', 'lists'] as $table) {
                DB::table($table)->whereIn('user_id', $userIds)->delete();
            }

            foreach ($demo['watchlist'] as $row) {
                DB::table('watchlist')->insert(['id' => (string) Str::uuid(), 'user_id' => $user($row['user']), 'movie_id' => $movie($row['movie']),
                    'created_at' => $at($row['at']), 'updated_at' => $at($row['at'])]);
            }

            foreach ($demo['watch_history'] as $row) {
                DB::table('watch_history')->insert(['id' => (string) Str::uuid(), 'user_id' => $user($row['user']), 'movie_id' => $movie($row['movie']),
                    'watched_at' => $at($row['watched_at'])]);
            }

            foreach ($demo['ratings'] as $row) {
                DB::table('ratings')->insert(['id' => $row['id'], 'user_id' => $user($row['user']), 'movie_id' => $movie($row['movie']),
                    'score' => $row['score'], 'created_at' => $at($row['at']), 'updated_at' => $at($row['at'])]);
            }

            foreach ($demo['reviews'] as $row) {
                DB::table('reviews')->insert(['id' => $row['id'], 'user_id' => $user($row['user']), 'movie_id' => $movie($row['movie']),
                    'body' => $row['body'], 'sentiment_label' => $row['sentiment'][0], 'sentiment_score' => $row['sentiment'][1],
                    'created_at' => $at($row['at']), 'updated_at' => $at($row['at'])]);
            }

            foreach ($demo['lists'] as $row) {
                DB::table('lists')->insert(['id' => $row['id'], 'user_id' => $user($row['user']), 'title' => $row['title'],
                    'description' => $row['description'], 'is_public' => $row['is_public'],
                    'created_at' => $at($row['at']), 'updated_at' => $at($row['at'])]);

                foreach ($row['movies'] as $key) {
                    DB::table('list_movie')->insert(['list_id' => $row['id'], 'movie_id' => $movie($key),
                        'created_at' => $at($row['at']), 'updated_at' => $at($row['at'])]);
                }
            }
        });

        $this->command?->info(count($demo['ratings']).' ratings, '.count($demo['reviews']).' reviews, '.count($demo['lists'])
            .' lists, '.count($demo['watch_history']).' watches and '.count($demo['watchlist']).' watchlist items.');
    }
}
