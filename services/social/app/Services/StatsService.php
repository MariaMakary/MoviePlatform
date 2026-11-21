<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Follow;
use App\Models\Message;
use App\Models\MovieNight;
use Illuminate\Support\Carbon;
use MoviePlatform\Common\MovieNights\MovieNightStatus;

/**
 * Numbers for the admin dashboard (1267, shown by 1269). Grouped queries on every call, no cache. Every row counts:
 * activity of blocked users too, like Engagement's stats. Days are UTC dates.
 */
class StatsService
{
    public const ACTIVITY_DAYS = 30;

    public const MESSAGE_DAYS = 7;

    public const TOP = 10;

    public function overview(): array
    {
        return [
            'activity_per_day' => $this->activityPerDay(),
            'follows' => Follow::count(),
            'movie_nights' => [
                'created' => MovieNight::count(),
                'finalized' => MovieNight::where('status', MovieNightStatus::Finalized)->count(),
            ],
            'messages_last_7_days' => Message::where('created_at', '>=', Carbon::now()->subDays(self::MESSAGE_DAYS))->count(),
            'most_active_users' => $this->mostActiveUsers(),
        ];
    }

    /** One {date, count} per day, oldest first, today included; a day without activity has count 0. */
    private function activityPerDay(): array
    {
        $first = Carbon::now('UTC')->startOfDay()->subDays(self::ACTIVITY_DAYS - 1);

        $counts = Activity::query()->toBase()
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->where('created_at', '>=', $first)
            ->groupByRaw('date(created_at)')
            ->pluck('total', 'day');

        return collect(range(0, self::ACTIVITY_DAYS - 1))
            ->map(function (int $offset) use ($first, $counts) {
                $date = $first->copy()->addDays($offset)->toDateString();

                return ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
            })
            ->all();
    }

    private function mostActiveUsers(): array
    {
        return Activity::query()->toBase()
            ->select('user_id')
            ->selectRaw('count(*) as activities')
            ->groupBy('user_id')
            ->orderByDesc('activities')
            ->orderBy('user_id')
            ->limit(self::TOP)
            ->get()
            ->map(fn (object $row) => ['user_id' => $row->user_id, 'activities' => (int) $row->activities])
            ->all();
    }
}
