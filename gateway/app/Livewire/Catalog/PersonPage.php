<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PersonPage extends Component
{
    use WithServiceFallback;

    public const SHORT_BIOGRAPHY = 600;

    #[Locked]
    public string $id;

    public function render(CatalogClient $catalog): View
    {
        $person = $this->fromService('person', fn () => $catalog->person($this->id));

        return view('livewire.catalog.person-page', [
            'person' => $person,
            'acting' => $person['filmography']['acting'] ?? [],
            'directing' => $person['filmography']['directing'] ?? [],
            'longBiography' => mb_strlen($person['biography'] ?? '') > self::SHORT_BIOGRAPHY,
        ])->title($person['name'] ?? 'Person');
    }
}
