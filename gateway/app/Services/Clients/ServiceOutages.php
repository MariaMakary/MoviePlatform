<?php

namespace App\Services\Clients;

class ServiceOutages
{
    private array $down = [];

    public function isDown(string $service): bool
    {
        return isset($this->down[$service]);
    }

    public function markDown(string $service): bool
    {
        if ($this->isDown($service)) {
            return false;
        }

        $this->down[$service] = true;

        return true;
    }
}
