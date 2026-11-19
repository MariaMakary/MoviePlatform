<?php

namespace App\Livewire\Admin;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin/users: user management (1273), on Identity's admin endpoints (1271). Blocking and deleting go through Identity
 * only: its events make Engagement, Social, Intelligence and Notification hide or delete the user's data.
 */
#[Title('Users · Admin')]
class Users extends Component
{
    use WithServiceFallback;

    private const PER_PAGE = 20;

    public const MAX_SEARCH = 100;

    private const ACTIONS = ['block', 'unblock', 'delete'];

    private const MODAL = 'manage-user';

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: 1)]
    public int $page = 1;

    /** The action waiting for confirmation in the modal: block, unblock or delete, on $pendingId. */
    #[Locked]
    public ?string $pendingAction = null;

    #[Locked]
    public ?string $pendingId = null;

    /** Only shown in the modal and the message. */
    #[Locked]
    public string $pendingName = '';

    public ?string $notice = null;

    public ?string $error = null;

    // A new search starts again from the first page.
    public function updatedQ(): void
    {
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function confirm(string $action, string $userId, string $name): void
    {
        // The admin's own row has no buttons; Identity refuses it too (422).
        if (! in_array($action, self::ACTIONS, true) || $userId === $this->ownId()) {
            return;
        }

        $this->pendingAction = $action;
        $this->pendingId = $userId;
        $this->pendingName = Str::limit($name, 100);
        $this->dispatch('open-modal', name: self::MODAL);
    }

    public function apply(IdentityClient $identity): void
    {
        [$action, $id, $name] = [$this->pendingAction, $this->pendingId, $this->pendingName];

        if ($action === null || $id === null) {
            return;
        }

        $this->notice = $this->error = null;

        try {
            match ($action) {
                'block' => $identity->blockUser($id),
                'unblock' => $identity->unblockUser($id),
                'delete' => $identity->deleteUser($id),
            };
            $this->notice = ['block' => "$name is blocked.", 'unblock' => "$name is unblocked.", 'delete' => "$name was deleted."][$action];
        } catch (ServiceUnavailable $e) {
            $this->error = "{$e->service} isn't responding, so nothing changed. Try again in a moment.";
        } catch (ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first() ?? $e->getMessage();
        } catch (ServiceRequestException $e) {
            if ($e->getStatusCode() !== 404) {
                throw $e;
            }

            $this->error = 'This user no longer exists.';
        }

        $this->pendingAction = $this->pendingId = null;
        $this->pendingName = '';
        $this->dispatch('close-modal', name: self::MODAL);
    }

    public function render(IdentityClient $identity): View
    {
        $this->page = max(1, $this->page);
        $search = Str::limit(trim($this->q), self::MAX_SEARCH, '');

        $result = $this->fromService('users', fn () => $identity->adminUsers($search, $this->page, self::PER_PAGE));

        return view('livewire.admin.users', [
            'users' => $result['data'] ?? [],
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
            'ownId' => $this->ownId(),
            'searching' => $search !== '',
        ]);
    }

    private function ownId(): ?string
    {
        return app(AuthSession::class)->user()['id'] ?? null;
    }
}
