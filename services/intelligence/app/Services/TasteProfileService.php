<?php

namespace App\Services;

use App\Integration\Clients\CatalogClient;
use App\Models\UserTasteProfile;

/**
 * Keeps user_taste_profiles up to date from RatingAdded, MovieWatched and UserGenresUpdated.
 * Runs inside the event consumer's transaction, which also makes an event that arrives twice run once.
 */
class TasteProfileService
{
    /** A score of 3 is neutral: 5 adds +2 to the movie's genres and people, 1 adds −2. */
    private const NEUTRAL_SCORE = 3;

    private const WATCHED_WEIGHT = 1;

    public function __construct(private CatalogClient $catalog) {}

    /**
     * RatingAdded also fires when a rating changes: only the difference with the last score is applied, so 5 then 2
     * leaves the weights exactly as if it had been 2 from the start.
     */
    public function rated(string $userId, string $movieId, int $score): void
    {
        $profile = $this->lockedProfile($userId);
        $seen = $profile->seen_movie_ids;
        $lastScore = $seen[$movieId] ?? null;

        $delta = ($score - self::NEUTRAL_SCORE) - ($lastScore === null ? 0 : $lastScore - self::NEUTRAL_SCORE);

        if ($delta !== 0 && ! $this->addToMovieFeatures($profile, $movieId, $delta)) {
            return;
        }

        $profile->seen_movie_ids = [...$seen, $movieId => $score];
        $profile->save();
    }

    /** +1 once per movie: a movie already seen (watched or rated) adds nothing. */
    public function watched(string $userId, string $movieId): void
    {
        $profile = $this->lockedProfile($userId);

        if (array_key_exists($movieId, $profile->seen_movie_ids)) {
            return;
        }

        if (! $this->addToMovieFeatures($profile, $movieId, self::WATCHED_WEIGHT)) {
            return;
        }

        $profile->seen_movie_ids = [...$profile->seen_movie_ids, $movieId => null];
        $profile->save();
    }

    /** The event carries the full list: it replaces the old one, so saving twice never piles up (+3 is applied in 1245). */
    public function setFavoriteGenres(string $userId, array $genreIds): void
    {
        $profile = $this->lockedProfile($userId);
        $profile->favorite_genre_ids = array_values(array_unique($genreIds));
        $profile->save();
    }

    private function lockedProfile(string $userId): UserTasteProfile
    {
        return UserTasteProfile::where('user_id', $userId)->lockForUpdate()->first()
            ?? UserTasteProfile::create(['user_id' => $userId]);
    }

    /** False when Catalog no longer has the movie: the event is then skipped. */
    private function addToMovieFeatures(UserTasteProfile $profile, string $movieId, int $weight): bool
    {
        $features = $this->catalog->movieFeatures($movieId);

        if ($features === null) {
            return false;
        }

        $profile->genre_weights = $this->addWeight($profile->genre_weights, $features['genre_ids'], $weight);
        $profile->people_weights = $this->addWeight(
            $profile->people_weights,
            array_unique([...$features['cast_ids'], ...$features['director_ids']]),
            $weight,
        );

        return true;
    }

    // A weight that comes back to 0 is removed, so the profile only keeps what matters.
    private function addWeight(array $weights, array $ids, int $weight): array
    {
        foreach ($ids as $id) {
            $weights[$id] = ($weights[$id] ?? 0) + $weight;

            if ($weights[$id] === 0) {
                unset($weights[$id]);
            }
        }

        return $weights;
    }
}
