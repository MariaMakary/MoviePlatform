<?php

namespace App\Livewire\Concerns;

use App\Exceptions\ServiceUnavailable;

trait WithServiceFallback
{
    public array $unavailable = [];

    protected function fromService(string $section, callable $call, mixed $fallback = null): mixed
    {
        try {
            $result = $call();
            unset($this->unavailable[$section]);

            return $result;
        } catch (ServiceUnavailable $e) {
            $this->unavailable[$section] = $e->service;

            return $fallback;
        }
    }

    public function isUnavailable(string $section): bool
    {
        return isset($this->unavailable[$section]);
    }
}
