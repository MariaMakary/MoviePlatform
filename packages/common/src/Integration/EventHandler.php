<?php

namespace MoviePlatform\Common\Integration;

interface EventHandler
{
    public function handle(array $data, array $envelope): void;
}
