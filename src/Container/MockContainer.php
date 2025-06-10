<?php

declare(strict_types=1);

namespace LaminasMicroscope\Container;

use Exception;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Mock container for testing and fallback scenarios
 */
class MockContainer implements ContainerInterface
{
    private array $services = [];

    public function get(string $id)
    {
        if (! $this->has($id)) {
            throw new class ("Service '{$id}' not found") extends Exception implements NotFoundExceptionInterface {
            };
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function set(string $id, $service): void
    {
        $this->services[$id] = $service;
    }
}
