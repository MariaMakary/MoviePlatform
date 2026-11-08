<?php

namespace App\Livewire\Engagement;

use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\EngagementClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ListPage extends Component
{
    use WithServiceFallback;

    #[Locked]
    public string $id;

    public ?string $error = null;

    public function remove(string $movieId, EngagementClient $engagement): void
    {
        try {
            $engagement->removeFromList($this->id, $movieId);
        } catch (ServiceUnavailable) {
            $this->error = "Couldn't remove the movie. Try again.";

            return;
        }

        $this->error = null;
    }

    public function deleteList(EngagementClient $engagement)
    {
        try {
            $engagement->deleteList($this->id);
        } catch (ServiceUnavailable) {
            $this->error = "Couldn't delete the list. Try again.";
            $this->dispatch('close-modal', 'delete-list');

            return null;
        }

        session()->flash('success', 'List deleted.');

        return $this->redirectRoute('lists.index');
    }

    public function render(AuthSession $auth, EngagementClient $engagement, CatalogClient $catalog): View
    {
        $list = $this->fromService('list', fn () => $engagement->list($this->id));
        $movies = $list === null ? [] : $this->fromService('catalog', fn () => $catalog->movies($list['movie_ids']), []);

        return view('livewire.engagement.list-page', [
            'list' => $list,
            'movies' => $movies,
            'isOwner' => $list !== null && $list['user_id'] === ($auth->user()['id'] ?? null),
        ])->title($list['title'] ?? 'List');
    }
}
