<?php

namespace App\Livewire\Dev;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\EngagementClient;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\IntelligenceClient;
use App\Services\Clients\NotificationClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Services')]
class ServiceStatus extends Component
{
    use WithServiceFallback;

    private const CLIENTS = [
        'identity' => IdentityClient::class,
        'catalog' => CatalogClient::class,
        'engagement' => EngagementClient::class,
        'social' => SocialClient::class,
        'intelligence' => IntelligenceClient::class,
        'notification' => NotificationClient::class,
    ];

    public function mount(): void
    {
        abort_unless(app()->isLocal(), 404);
    }

    public function render(): View
    {
        $health = [];

        foreach (self::CLIENTS as $service => $client) {
            $health[$service] = $this->fromService($service, fn () => app($client)->health());
        }

        return view('livewire.dev.service-status', ['health' => $health]);
    }
}
