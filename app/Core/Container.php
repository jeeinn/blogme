<?php

declare(strict_types=1);

namespace Blogme\Core;

use RuntimeException;

final class Container
{
    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->factories) || array_key_exists($id, $this->instances);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (!array_key_exists($id, $this->factories)) {
            throw new RuntimeException("Container entry not found: {$id}");
        }
        $this->instances[$id] = ($this->factories[$id])($this);
        return $this->instances[$id];
    }
}
