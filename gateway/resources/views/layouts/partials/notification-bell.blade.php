@inject('authSession', 'App\Services\AuthSession')

@if ($authSession->check())
    <livewire:notifications.bell />
@endif
