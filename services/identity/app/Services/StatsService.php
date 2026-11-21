<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use MoviePlatform\Common\Auth\Role;

class StatsService
{
    public const SIGNUP_DAYS = 30;

    public function users(): array
    {
        $totals = User::toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where is_blocked) as blocked')
            ->selectRaw('count(*) filter (where role = ?) as admins', [Role::Admin->value])
            ->first();

        return [
            'total_users' => (int) $totals->total,
            'blocked_users' => (int) $totals->blocked,
            'admins' => (int) $totals->admins,
            'signups' => $this->signupsPerDay(),
        ];
    }

    private function signupsPerDay(): array
    {
        $today = Carbon::now('UTC')->startOfDay();
        $first = $today->copy()->subDays(self::SIGNUP_DAYS - 1);

        $counts = User::toBase()
            ->selectRaw('created_at::date as day, count(*) as signups')
            ->where('created_at', '>=', $first)
            ->where('created_at', '<', $today->copy()->addDay())
            ->groupByRaw('created_at::date')
            ->pluck('signups', 'day');

        return collect(range(0, self::SIGNUP_DAYS - 1))
            ->map(function (int $offset) use ($first, $counts) {
                $date = $first->copy()->addDays($offset)->toDateString();

                return ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
            })
            ->all();
    }
}
