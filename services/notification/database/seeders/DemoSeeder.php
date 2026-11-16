<?php

namespace Database\Seeders;

use App\Integration\Handlers\MovieNightInviteAnsweredHandler;
use App\Integration\Handlers\MovieNightInviteSentHandler;
use App\Integration\Handlers\UserFollowedHandler;
use App\Integration\Handlers\UserRegisteredHandler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The demo users' notifications for seeds/demo.json (task 1289): welcome, new followers and movie night invitations,
 * made by replaying the events through the real handlers, at the time each event happened. The two newest stay unread.
 * Running it again replaces the demo users' notifications and leaves everyone else alone. Never in production.
 */
class DemoSeeder extends Seeder
{
    private const UNREAD = 2;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The demo data is never seeded in production.');
        }

        $demo = json_decode(file_get_contents(base_path('../../seeds/demo.json')), true, flags: JSON_THROW_ON_ERROR);
        $user = fn (int $n) => $demo['users'][$n - 1];
        $userIds = array_column($demo['users'], 'id');

        try {
            DB::transaction(function () use ($demo, $user, $userIds) {
                DB::table('notifications')->whereIn('user_id', $userIds)->delete();

                foreach ($this->events($demo, $user) as [$handler, $data, $time]) {
                    Carbon::setTestNow(Carbon::parse($time));
                    app($handler)->handle($data, ['occurred_at' => $time]);
                }

                foreach ($userIds as $id) {
                    $unread = DB::table('notifications')->where('user_id', $id)->orderByDesc('created_at')->limit(self::UNREAD)->pluck('id');
                    DB::table('notifications')->where('user_id', $id)->whereNotIn('id', $unread)->update(['read_at' => DB::raw('created_at')]);
                }
            });
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info(DB::table('notifications')->whereIn('user_id', $userIds)->count().' demo notifications.');
    }

    /** [handler, data, occurred_at], oldest first. */
    private function events(array $demo, callable $user): array
    {
        $events = [];

        foreach ($demo['users'] as $row) {
            $events[] = [UserRegisteredHandler::class, ['user_id' => $row['id'], 'name' => $row['name'], 'email' => $row['email']], $row['registered_at']];
        }

        foreach ($demo['follows'] as $row) {
            $events[] = [UserFollowedHandler::class, ['follower_id' => $user($row['follower'])['id'], 'follower_name' => $user($row['follower'])['name'],
                'following_id' => $user($row['following'])['id']], $row['at']];
        }

        foreach ($demo['movie_nights'] as $night) {
            $host = $user($night['host']);
            foreach ($night['participants'] as $row) {
                $events[] = [MovieNightInviteSentHandler::class, ['movie_night_id' => $night['id'], 'user_id' => $user($row['user'])['id'],
                    'host_name' => $host['name'], 'scheduled_at' => $night['scheduled_at']], $night['at']];

                if ($row['status'] !== 'pending') {
                    $events[] = [MovieNightInviteAnsweredHandler::class, ['movie_night_id' => $night['id'], 'host_id' => $host['id'],
                        'user_name' => $user($row['user'])['name'], 'status' => $row['status']], Carbon::parse($night['at'])->addHour()->toIso8601ZuluString()];
                }
            }
        }

        usort($events, fn (array $a, array $b) => strcmp($a[2], $b[2]));

        return $events;
    }
}
