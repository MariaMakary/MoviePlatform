<?php

namespace App\Livewire\Concerns;

trait KeepsPageInRange
{
    private function loadPage(callable $load): ?array
    {
        $this->page = max(1, $this->page);
        $result = $load($this->page);
        $lastPage = max(1, (int) ($result['meta']['last_page'] ?? 1));

        if (($result['data'] ?? null) === [] && $this->page > $lastPage) {
            $this->page = $lastPage;
            $result = $load($this->page);
        }

        return $result;
    }
}
