<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Services\ActivityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;
use MoviePlatform\Common\Seeding\DemoData;

/**
 * The demo users' follows, privacy settings, movie night, chat and activity feed of seeds/demo.json (task 1289).
 * The feed is built by replaying Engagement's events through ActivityService, exactly as the consumer does.
 * Running it again replaces only the demo rows: a real user's follows, votes, participations and messages around the
 * demo users stay. Never in production.
 */
class DemoSeeder extends Seeder
{
    public function __construct(private ActivityService $activities) {}

    public function run(): void
    {
        $demo = DemoData::load();
        $user = $demo->userId(...);
        $movie = $demo->movieId(...);
        $at = DemoData::at(...);

        DB::transaction(function () use ($demo, $user, $movie, $at) {
            $this->forgetDemoRows($demo);

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

    private function save(string $table, string $id, array $fields): void
    {
        if (DB::table($table)->where('id', $id)->update($fields) === 0) {
            DB::table($table)->insert(['id' => $id, ...$fields]);
        }
    }

    private function forgetDemoRows(DemoData $demo): void
    {
        $userIds = $demo->userIds();

        DB::table('follows')->whereIn('follower_id', $userIds)->whereIn('following_id', $userIds)->delete();
        DB::table('privacy_settings')->whereIn('user_id', $userIds)->delete();
        Activity::whereIn('user_id', $userIds)->delete();
        DB::table('messages')->whereIn('id', array_merge(...array_map(fn (array $room) => array_column($room['messages'], 'id'), $demo['rooms'])))->delete();

        foreach ($demo['movie_nights'] as $night) {
            DB::table('votes')->where('movie_night_id', $night['id'])->whereIn('user_id', $userIds)->delete();
            DB::table('participants')->where('movie_night_id', $night['id'])->whereIn('user_id', $userIds)->delete();
        }
    }

    private function movieNight(array $night, callable $user, callable $movie, callable $at): void
    {
        $created = $at($night['at']);
        $this->save('movie_nights', $night['id'], ['host_id' => $user($night['host']), 'title' => $night['title'],
            'description' => $night['description'], 'scheduled_at' => $at($night['scheduled_at']), 'location' => $night['location'],
            'status' => MovieNightStatus::from($night['status'])->value, 'created_at' => $created, 'updated_at' => $created]);

        // The host takes part like in MovieNightService::create.
        $participants = [['user' => $night['host'], 'status' => ParticipantStatus::Accepted->value], ...$night['participants']];
        foreach ($participants as $row) {
            $status = ParticipantStatus::from($row['status']);
            DB::table('participants')->insert(['movie_night_id' => $night['id'], 'user_id' => $user($row['user']), 'status' => $status->value,
                'responded_at' => $status === ParticipantStatus::Pending ? null : $created, 'created_at' => $created]);
        }

        foreach ($night['candidates'] as $row) {
            $this->save('candidates', $row['id'], ['movie_night_id' => $night['id'], 'movie_id' => $movie($row['movie']),
                'added_by' => $user($row['added_by']), 'created_at' => $created]);
        }

        foreach ($night['votes'] as $row) {
            DB::table('votes')->insert(['movie_night_id' => $night['id'], 'user_id' => $user($row['user']), 'candidate_id' => $row['candidate'],
                'created_at' => $created, 'updated_at' => $created]);
        }
    }

    /** Engagement's events for the demo data, oldest first: [event, data, occurred_at]. */
    private function events(DemoData $demo, callable $user, callable $movie): array
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
