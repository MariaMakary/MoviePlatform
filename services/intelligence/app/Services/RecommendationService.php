<?php

namespace App\Services;

use App\Integration\Clients\CatalogClient;
use App\Models\Recommendation;
use App\Models\UserTasteProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scores movies for a user from their taste profile (1244) and keeps their best 20 in `recommendations`.
 *
 * Candidates: Catalog's most popular movies in the user's top 3 genres, and movies similar to their 5 best-rated ones,
 * never a movie they have already seen. Raw score = genre match × 3 + people match × 2 + similar bonus 2 +
 * vote_average × 0.5, each match being 0–1, then divided by the highest possible raw score (12) so it fits 0–1.
 */
class RecommendationService
{
    public const KEEP = 20;

    public const MAX_AGE_MINUTES = 60;

    private const GENRE_FACTOR = 3;

    private const PEOPLE_FACTOR = 2;

    private const SIMILAR_BONUS = 2;

    private const VOTE_FACTOR = 0.5;

    private const MAX_RAW_SCORE = self::GENRE_FACTOR + self::PEOPLE_FACTOR + self::SIMILAR_BONUS + 10 * self::VOTE_FACTOR;

    /** Favorite genres count as if the user had rated three movies of them 4 (task 1244). */
    private const FAVORITE_GENRE_BONUS = 3;

    private const TOP_GENRES = 3;

    private const BEST_RATED = 5;

    /** Only movies the user really liked bring similar movies. */
    private const LIKED_SCORE = 4;

    private const DISCOVER_LIMIT = 50;

    /** People match needs one Catalog call per movie: only the best candidates by the other parts get one. */
    private const PEOPLE_CHECKED = 30;

    public function __construct(private CatalogClient $catalog) {}

    /** The user's stored recommendations, best first. */
    public function stored(string $userId): Collection
    {
        return Recommendation::forUser($userId)->orderByDesc('score')->orderBy('movie_id')->get();
    }

    /** True when there are none yet or the newest is older than an hour (1246 then recomputes). */
    public function isStale(string $userId): bool
    {
        $newest = Recommendation::forUser($userId)->max('updated_at');

        return $newest === null || Carbon::parse($newest)->lt(Carbon::now()->subMinutes(self::MAX_AGE_MINUTES));
    }

    /**
     * Scores, saves and returns the user's best 20. A user without a taste profile gets none.
     *
     * @throws \App\Integration\Clients\CatalogUnavailable (the stored results are then left as they were)
     */
    public function recompute(string $userId): Collection
    {
        $profile = UserTasteProfile::where('user_id', $userId)->first();
        $best = $profile === null ? [] : $this->best($profile);

        DB::transaction(function () use ($userId, $best) {
            $now = Carbon::now();

            if ($best !== []) {
                Recommendation::upsert(
                    // Eloquent adds the uuid id of new rows; an existing row keeps its id.
                    array_map(fn (array $pick) => [
                        'user_id' => $userId, 'movie_id' => $pick['movie_id'], 'score' => $pick['score'], 'reason' => $pick['reason'],
                        'created_at' => $now, 'updated_at' => $now,
                    ], $best),
                    ['user_id', 'movie_id'],
                    ['score', 'reason', 'updated_at'],
                );
            }

            Recommendation::forUser($userId)->whereNotIn('movie_id', array_column($best, 'movie_id'))->delete();
        });

        return $this->stored($userId);
    }

    /** @return array<int, array{movie_id: string, score: float, reason: string}> */
    private function best(UserTasteProfile $profile): array
    {
        $genreWeights = $this->genreWeightsWithFavorites($profile);
        $topGenres = array_slice(array_keys(array_filter($genreWeights, fn ($weight) => $weight > 0)), 0, self::TOP_GENRES);
        $liked = $this->likedMovies($profile->seen_movie_ids);

        if ($topGenres === [] && $liked === []) {
            return [];
        }

        $seen = array_keys($profile->seen_movie_ids);
        $candidates = $this->candidates($topGenres, $liked, $seen);

        if ($candidates === []) {
            return [];
        }

        $genreIds = $this->catalog->genreIdsByName();
        $genreNames = array_flip($genreIds);
        $genreTotal = array_sum(array_map(fn ($id) => $genreWeights[$id], $topGenres)) ?: 1;

        foreach ($candidates as &$candidate) {
            $ids = array_values(array_filter(array_map(fn ($name) => $genreIds[$name] ?? null, $candidate['card']['genres'] ?? [])));
            $candidate['genre_match'] = min(1, array_sum(array_map(fn ($id) => max(0, $genreWeights[$id] ?? 0), $ids)) / $genreTotal);
            $candidate['best_genre'] = $this->bestGenre($ids, $genreWeights, $genreNames);
            $candidate['raw'] = $candidate['genre_match'] * self::GENRE_FACTOR
                + ($candidate['similar_to'] !== null ? self::SIMILAR_BONUS : 0)
                + (float) ($candidate['card']['vote_average'] ?? 0) * self::VOTE_FACTOR;
        }
        unset($candidate);

        $this->addPeopleMatch($candidates, $profile->people_weights);

        usort($candidates, fn ($a, $b) => [$b['raw'], $a['card']['id']] <=> [$a['raw'], $b['card']['id']]);

        return array_map(fn (array $candidate) => [
            'movie_id' => $candidate['card']['id'],
            'score' => round($candidate['raw'] / self::MAX_RAW_SCORE, 4),
            'reason' => $this->reason($candidate),
        ], array_slice($candidates, 0, self::KEEP));
    }

    /** Genre id => weight, favorites included, highest first. */
    private function genreWeightsWithFavorites(UserTasteProfile $profile): array
    {
        $weights = $profile->genre_weights;

        foreach ($profile->favorite_genre_ids as $genreId) {
            $weights[$genreId] = ($weights[$genreId] ?? 0) + self::FAVORITE_GENRE_BONUS;
        }

        arsort($weights);

        return $weights;
    }

    /** The user's 5 best-rated movies scored 4 or 5, best first: movie id => score. */
    private function likedMovies(array $seen): array
    {
        $rated = array_filter($seen, fn ($score) => $score !== null && $score >= self::LIKED_SCORE);
        arsort($rated);

        return array_slice($rated, 0, self::BEST_RATED, true);
    }

    /** Movie id => {card, similar_to}, without the movies the user has seen. */
    private function candidates(array $topGenres, array $liked, array $seen): array
    {
        $candidates = [];
        $seenSet = array_flip($seen);

        if ($liked !== []) {
            $titles = array_column($this->catalog->movies(array_keys($liked)), 'title', 'id');

            foreach (array_keys($liked) as $likedId) {
                foreach ($this->catalog->similar($likedId) as $card) {
                    if (! isset($seenSet[$card['id']]) && ! isset($candidates[$card['id']])) {
                        $candidates[$card['id']] = ['card' => $card, 'similar_to' => $titles[$likedId] ?? null];
                    }
                }
            }
        }

        if ($topGenres !== []) {
            foreach ($this->catalog->discover($topGenres, $seen, self::DISCOVER_LIMIT) as $card) {
                if (! isset($seenSet[$card['id']]) && ! isset($candidates[$card['id']])) {
                    $candidates[$card['id']] = ['card' => $card, 'similar_to' => null];
                }
            }
        }

        return array_values($candidates);
    }

    /** Adds people match × 2 to the best candidates (one Catalog call each). */
    private function addPeopleMatch(array &$candidates, array $peopleWeights): void
    {
        $positive = array_filter($peopleWeights, fn ($weight) => $weight > 0);

        if ($positive === []) {
            return;
        }

        arsort($positive);
        // Matching the user's 3 favorite people counts as a full match.
        $peopleTotal = array_sum(array_slice($positive, 0, 3));

        usort($candidates, fn ($a, $b) => $b['raw'] <=> $a['raw']);

        foreach (array_slice(array_keys($candidates), 0, self::PEOPLE_CHECKED) as $index) {
            $features = $this->catalog->movieFeatures($candidates[$index]['card']['id']);
            $people = $features === null ? [] : array_unique([...$features['cast_ids'], ...$features['director_ids']]);
            $match = min(1, array_sum(array_map(fn ($id) => $positive[$id] ?? 0, $people)) / $peopleTotal);
            $candidates[$index]['raw'] += $match * self::PEOPLE_FACTOR;
        }
    }

    private function bestGenre(array $genreIds, array $weights, array $names): ?string
    {
        $best = null;

        foreach ($genreIds as $id) {
            if (($weights[$id] ?? 0) > 0 && ($best === null || $weights[$id] > $weights[$best])) {
                $best = $id;
            }
        }

        return $best === null ? null : ($names[$best] ?? null);
    }

    private function reason(array $candidate): string
    {
        return match (true) {
            $candidate['similar_to'] !== null => 'Because you liked '.$candidate['similar_to'],
            $candidate['best_genre'] !== null => 'You like '.$candidate['best_genre'],
            default => 'Popular in your genres',
        };
    }
}
