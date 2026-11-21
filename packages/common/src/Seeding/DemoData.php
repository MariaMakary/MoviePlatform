<?php

namespace MoviePlatform\Common\Seeding;

use ArrayAccess;
use Illuminate\Support\Carbon;
use LogicException;
use RuntimeException;

final class DemoData implements ArrayAccess
{
    private function __construct(private array $data) {}

    public static function load(?string $path = null): self
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The demo data is never seeded in production.');
        }

        $path ??= base_path('../../seeds/demo.json');

        return new self(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
    }

    public function user(int $number): array
    {
        return $this->data['users'][$number - 1];
    }

    public function userId(int $number): string
    {
        return $this->user($number)['id'];
    }

    public function userIds(): array
    {
        return array_column($this->data['users'], 'id');
    }

    public function movieId(string $key): string
    {
        return $this->data['movies'][$key]['id'];
    }

    public function genreId(string $key): string
    {
        return $this->data['genres'][$key]['id'];
    }

    public static function at(string $time): Carbon
    {
        return Carbon::parse($time)->utc();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('The demo data is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('The demo data is read-only.');
    }
}
