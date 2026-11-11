<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Favorite genre picker: a full page right after registration (/profile/genres, with "Skip for now"), and a section of the
 * profile page (`<livewire:auth.favorite-genres embedded />`). Genres come from Catalog, the choice is saved in Identity.
 */
#[Title('Your favorite genres')]
class FavoriteGenres extends Component
{
    use WithServiceFallback;

    public const MAX = 20;

    #[Locked]
    public bool $embedded = false;

    /** Selected Catalog genre ids. */
    public array $selected = [];

    /** The ids last saved in Identity, to show them by name. */
    #[Locked]
    public array $saved = [];

    #[Locked]
    public bool $filled = false;

    public function render(CatalogClient $catalog, IdentityClient $identity): View
    {
        $genres = $this->fromService('genres', fn () => $catalog->genres(), []);

        if (! $this->filled) {
            $user = $this->fromService('identity', fn () => $identity->me());

            if ($user !== null) {
                $this->saved = $this->selected = $user['genre_ids'] ?? [];
                $this->filled = true;
            }
        }

        $names = collect($genres)->pluck('name', 'id');

        return view('livewire.auth.favorite-genres', [
            'genres' => $genres,
            'savedNames' => collect($this->saved)->map(fn (string $id) => $names->get($id))->filter()->sort()->values(),
        ]);
    }

    public function save(IdentityClient $identity)
    {
        $this->resetErrorBag();
        unset($this->unavailable['save']);

        $this->validate(
            ['selected' => ['array', 'max:'.self::MAX], 'selected.*' => ['uuid']],
            ['selected.max' => 'Pick at most '.self::MAX.' genres.'],
        );

        $user = $this->fromService('save', fn () => $identity->updateGenres(array_values(array_unique($this->selected))));

        if ($user === null) {
            return null;
        }

        session()->flash('success', 'Favorite genres saved.');

        return $this->redirectRoute($this->embedded ? 'profile' : 'home');
    }
}
