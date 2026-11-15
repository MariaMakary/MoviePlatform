<?php

namespace App\Services;

use App\Enums\SentimentLabel;
use App\Models\Rating;
use App\Models\Review;
use App\Models\WatchHistoryEntry;
use Illuminate\Support\Facades\DB;

class StatsService
{
    public const TOP = 10;

    public const MIN_RATINGS = 3;

    public const MOVIE_COUNTS = 200;

    public function overview(): array
    {
        return [
            'reviews' => Review::count(),
            'ratings' => Rating::count(),
            'sentiment' => $this->sentiment(),
            'most_watched' => $this->mostWatched(),
            'highest_rated' => $this->highestRated(),
            'movie_counts' => (object) $this->movieCounts(),
        ];
    }

    private function sentiment(): array
    {
        $counts = array_fill_keys([...array_column(SentimentLabel::cases(), 'value'), 'not_analyzed'], 0);

        $rows = Review::query()->toBase()
            ->select('sentiment_label')
            ->selectRaw('count(*) as total')
            ->groupBy('sentiment_label')
            ->get();

        foreach ($rows as $row) {
            $counts[$row->sentiment_label ?? 'not_analyzed'] = (int) $row->total;
        }

        return $counts;
    }

    private function mostWatched(): array
    {
        return WatchHistoryEntry::query()->toBase()
            ->select('movie_id')
            ->selectRaw('count(*) as watches')
            ->groupBy('movie_id')
            ->orderByDesc('watches')
            ->orderBy('movie_id')
            ->limit(self::TOP)
            ->get()
            ->map(fn (object $row) => ['movie_id' => $row->movie_id, 'watches' => (int) $row->watches])
            ->all();
    }

    private function highestRated(): array
    {
        return Rating::query()->toBase()
            ->select('movie_id')
            ->selectRaw('avg(score) as average, count(*) as ratings')
            ->groupBy('movie_id')
            ->havingRaw('count(*) >= ?', [self::MIN_RATINGS])
            ->orderByDesc('average')
            ->orderByDesc('ratings')
            ->orderBy('movie_id')
            ->limit(self::TOP)
            ->get()
            ->map(fn (object $row) => [
                'movie_id' => $row->movie_id,
                'average' => round((float) $row->average, 2),
                'ratings' => (int) $row->ratings,
            ])
            ->all();
    }

    private function movieCounts(): array
    {
        $activity = WatchHistoryEntry::query()->toBase()->select('movie_id')
            ->unionAll(Rating::query()->toBase()->select('movie_id'));

        return DB::query()->fromSub($activity, 'activity')
            ->select('movie_id')
            ->selectRaw('count(*) as total')
            ->groupBy('movie_id')
            ->orderByDesc('total')
            ->orderBy('movie_id')
            ->limit(self::MOVIE_COUNTS)
            ->get()
            ->mapWithKeys(fn (object $row) => [$row->movie_id => (int) $row->total])
            ->all();
    }
}
