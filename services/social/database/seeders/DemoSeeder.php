<?php

namespace Database\Seeders;

use App\Services\ActivityService;
use App\Services\UserDataService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The demo users' follows, privacy settings, movie night, chat and activity feed of seeds/demo.json (task 1289).
 * The feed is built by replaying Engagement's events through ActivityService, exactly as the consumer does.
 * Running it again replaces the demo users' data (UserDataService::deleteAll) and leaves everyone else alone.
 * Never in production.
 */
class DemoSeeder extends Seeder
{
    public function __construct(private UserDataService $users, private ActivityService $activities) {}

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
            foreach ($demo['users'] as $row) {
                $this->users->deleteAll($row['id']);
            }

            foreach ($demo['follows'] as $row) {
                DB::table('follows')->insert(['follower_id' => $user($row['follower']), 'following_id' => $user($row['following']), 'created_at' => $at($row['at'])]);
            }

            foreach ($demo['privacy'] as $row) {
                DB::table('privacy_settings')->insert(['user_id' => $user($row['user']), 'show_reviews' => $row['show_reviews'],
                    'show_ratings' => $row['show_ratings'], 'show_lists' => $row['show_lists'], 'show_history' => $row['show_history'],
                    'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
            }

            foreach ($demo['movie_nights'] as $night) {
                $this->movieNight($night, $user, $movie, $at);
            }

            foreach ($demo['rooms'] as $room) {
                // One room per movie: reuse it when someone already chatted about this movie.
                $roomId = DB::table('rooms')->where('movie_id', $movie($room['movie']))->value('id');
                if ($roomId === null) {
                    $roomId = $room['id'];
                    DB::table('rooms')->insert(['id' => $roomId, 'movie_id' => $movie($room['movie']),
                        'name' => $demo['movies'][$room['movie']]['title'], 'created_at' => $at($room['at'])]);
                }

                foreach ($room['messages'] as $message) {
                    DB::table('messages')->insert(['id' => $message['id'], 'room_id' => $roomId, 'user_id' => $user($message['user']),
                        'body' => $message['body'], 'created_at' => $at($message['at'])]);
                }
            }

            foreach ($this->events($demo, $user, $movie) as [$event, $data, $time]) {
                $this->activities->record($event, $data, ['id' => (string) Str::uuid(), 'occurred_at' => $time]);
            }
        });

        $this->command?->info(count($demo['follows']).' follows, '.count($demo['movie_nights']).' movie night, '
            .count($demo['rooms']).' chat room and the activity feed.');
    }

    private function movieNight(array $night, callable $user, callable $movie, callable $at): void
    {
        $created = $at($night['at']);
        DB::table('movie_nights')->insert(['id' => $night['id'], 'host_id' => $user($night['host']), 'title' => $night['title'],
            'description' => $night['description'], 'scheduled_at' => $at($night['scheduled_at']), 'location' => $night['location'],
            'status' => $night['status'], 'created_at' => $created, 'updated_at' => $created]);

        // The host takes part like in MovieNightService::create.
        $participants = [['user' => $night['host'], 'status' => 'accepted'], ...$night['participants']];
        foreach ($participants as $row) {
            DB::table('participants')->insert(['movie_night_id' => $night['id'], 'user_id' => $user($row['user']), 'status' => $row['status'],
                'responded_at' => $row['status'] === 'pending' ? null : $created, 'created_at' => $created]);
        }

        foreach ($night['candidates'] as $row) {
            DB::table('candidates')->insert(['id' => $row['id'], 'movie_night_id' => $night['id'], 'movie_id' => $movie($row['movie']),
                'added_by' => $user($row['added_by']), 'created_at' => $created]);
        }

        foreach ($night['votes'] as $row) {
            DB::table('votes')->insert(['movie_night_id' => $night['id'], 'user_id' => $user($row['user']), 'candidate_id' => $row['candidate'],
                'created_at' => $created, 'updated_at' => $created]);
        }
    }

    /** Engagement's events for the demo data, oldest first: [event, data, occurred_at]. */
    private function events(array $demo, callable $user, callable $movie): array
    {
        $events = [];

        foreach ($demo['watch_history'] as $row) {
            $events[] = ['MovieWatched', ['user_id' => $user($row['user']), 'movie_id' => $movie($row['movie']), 'watched_at' => $row['watched_at']], $row['watched_at']];
        }
        foreach ($demo['ratings'] as $row) {
            $events[] = ['RatingAdded', ['rating_id' => $row['id'], 'user_id' => $user($row['user']), 'movie_id' => $movie($row['movie']), 'score' => $row['score']], $row['at']];
        }
        foreach ($demo['reviews'] as $row) {
            $events[] = ['ReviewCreated', ['review_id' => $row['id'], 'user_id' => $user($row['user']), 'movie_id' => $movie($row['movie']), 'body' => $row['body']], $row['at']];
        }
        foreach ($demo['lists'] as $row) {
            $events[] = ['ListCreated', ['list_id' => $row['id'], 'user_id' => $user($row['user']), 'title' => $row['title'], 'is_public' => $row['is_public']], $row['at']];
        }

        usort($events, fn (array $a, array $b) => strcmp($a[2], $b[2]));

        return $events;
    }
}
