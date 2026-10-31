<?php

namespace App\Livewire;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Home')]
class Home extends Component
{
    use WithServiceFallback;

    private const SECTIONS = ['popular', 'upcoming'];

    public array $movies = ['popular' => [], 'upcoming' => []];

    public array $page = ['popular' => 0, 'upcoming' => 0];

    public array $lastPage = ['popular' => 1, 'upcoming' => 1];

    public function render(): View
    {
        $down = null;

        foreach (self::SECTIONS as $section) {
            if ($this->page[$section] !== 0) {
                continue;
            }

            if ($down !== null) {
                $this->unavailable[$section] = $down;

                continue;
            }

            $this->loadNextPage($section);
            $down = $this->unavailable[$section] ?? null;
        }

        return view('livewire.home');
    }

    public function loadMore(string $section): void
    {
        if (in_array($section, self::SECTIONS, true) && $this->hasMore($section)) {
            $this->loadNextPage($section);
        }
    }

    public function hasMore(string $section): bool
    {
        return $this->page[$section] < $this->lastPage[$section];
    }

    private function loadNextPage(string $section): void
    {
        $next = $this->page[$section] + 1;

        try {
            $result = $this->fromService($section, fn () => app(CatalogClient::class)->$section($next));
        } catch (ServiceRequestException $e) {
            Log::warning("Home section {$section} failed", ['status' => $e->getStatusCode(), 'message' => $e->getMessage()]);
            $this->unavailable[$section] = $e->service;

            return;
        }

        if ($result === null) {
            return;
        }

        $shown = array_column($this->movies[$section], 'id');

        foreach ($result['data'] as $movie) {
            if (! in_array($movie['id'], $shown, true)) {
                $this->movies[$section][] = $movie;
                $shown[] = $movie['id'];
            }
        }

        $this->page[$section] = $result['meta']['current_page'];
        $this->lastPage[$section] = $result['meta']['last_page'];
    }
}
