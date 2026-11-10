<?php

namespace App\Livewire\Engagement;

use App\Exceptions\ServiceUnavailable;
use App\Services\AuthSession;
use App\Services\Clients\EngagementClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ListForm extends Component
{
    #[Locked]
    public ?string $listId = null;

    public string $title = '';

    public string $description = '';

    public bool $isPublic = false;

    public function mount(AuthSession $auth, EngagementClient $engagement, ?string $id = null): void
    {
        if ($id === null) {
            return;
        }

        $list = $engagement->list($id);
        abort_unless($list['user_id'] === ($auth->user()['id'] ?? null), 403, 'Only the owner can edit this list.');

        $this->listId = $list['id'];
        $this->title = $list['title'];
        $this->description = $list['description'] ?? '';
        $this->isPublic = $list['is_public'];
    }

    public function save(EngagementClient $engagement)
    {
        $this->title = trim($this->title);
        $this->description = trim($this->description);

        $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'isPublic' => ['boolean'],
        ]);

        $data = [
            'title' => $this->title,
            'description' => $this->description === '' ? null : $this->description,
            'is_public' => $this->isPublic,
        ];

        try {
            $list = $this->listId === null
                ? $engagement->createList($data)
                : $engagement->updateList($this->listId, $data);
        } catch (ServiceUnavailable) {
            $this->addError('form', "Couldn't save the list. Try again in a moment.");

            return null;
        }

        session()->flash('success', $this->listId === null ? 'List created.' : 'List saved.');

        return $this->redirectRoute('lists.show', $list['id']);
    }

    public function render(): View
    {
        return view('livewire.engagement.list-form')->title($this->listId === null ? 'New list' : 'Edit list');
    }
}
