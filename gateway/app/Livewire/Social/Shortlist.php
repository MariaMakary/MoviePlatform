<?php

namespace App\Livewire\Social;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;

class Shortlist extends Component
{
    use WithServiceFallback;

    public const MIN_SEARCH_LENGTH = 2;

    private const MAX_RESULTS = 6;

    #[Locked]
    public string $nightId;

    public string $search = '';

    public ?string $actionError = null;

    public function getListeners(): array
    {
        return ["echo-private:movie-night.{$this->nightId},.VotesUpdated" => '$refresh'];
    }

    public function add(string $movieId, SocialClient $social): void
    {
        if ($this->change(fn () => $social->addCandidate($this->nightId, $movieId))) {
            $this->search = '';
        }
    }

    public function remove(string $candidateId, SocialClient $social): void
    {
        $this->change(fn () => $social->removeCandidate($this->nightId, $candidateId));
    }

    public function vote(string $candidateId, SocialClient $social): void
    {
        $this->change(fn () => $social->vote($this->nightId, $candidateId));
    }

    public function render(SocialClient $social, CatalogClient $catalog, AuthSession $auth): View
    {
        $night = $this->fromService('shortlist', fn () => $social->movieNight($this->nightId));
        $userId = $auth->user()['id'] ?? null;
        $candidates = collect($night['candidates'] ?? []);

        $cards = $candidates->isEmpty() ? collect()
            : collect($this->fromService('cards', fn () => $catalog->movies($candidates->pluck('movie_id')->all()), []))->keyBy('id');

        $open = MovieNightStatus::tryFrom($night['status'] ?? '') === MovieNightStatus::Open;
        $canVote = $open && ParticipantStatus::tryFrom($night['my_status'] ?? '') === ParticipantStatus::Accepted;
        $hosting = ($night['host_id'] ?? null) === $userId;

        return view('livewire.social.shortlist', [
            'night' => $night,
            'candidates' => $candidates->map(fn (array $candidate) => [
                ...$candidate,
                'card' => $cards->get($candidate['movie_id']),
                'can_remove' => $open && ($hosting || $candidate['added_by'] === $userId),
            ])->all(),
            'votesCast' => $night['votes_cast'] ?? 0,
            'myVote' => $night['my_vote'] ?? null,
            'open' => $open,
            'canVote' => $canVote,
            'results' => $canVote ? $this->searchResults($catalog, $candidates) : [],
        ]);
    }

    private function change(callable $call): bool
    {
        $this->actionError = null;

        try {
            $call();
        } catch (ServiceUnavailable $e) {
            $this->actionError = "{$e->service} isn't responding, so nothing changed. Try again in a moment.";

            return false;
        } catch (ValidationException $e) {
            $this->actionError = collect($e->errors())->flatten()->first() ?? 'Nothing changed.';

            return false;
        } catch (ServiceRequestException $e) {
            $this->actionError = $e->getMessage();

            return false;
        }

        $this->dispatch('shortlist-changed');

        return true;
    }

    private function searchResults(CatalogClient $catalog, Collection $candidates): array
    {
        $query = trim($this->search);

        if (mb_strlen($query) < self::MIN_SEARCH_LENGTH) {
            unset($this->unavailable['search']);

            return [];
        }

        $onShortlist = $candidates->pluck('movie_id')->all();
        $movies = $this->fromService('search', fn () => $catalog->search(['q' => mb_substr($query, 0, 100)])['data'], []);

        return collect($movies)
            ->reject(fn (array $movie) => in_array($movie['id'], $onShortlist, true))
            ->take(self::MAX_RESULTS)
            ->values()
            ->all();
    }
}
