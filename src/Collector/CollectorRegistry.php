<?php

declare(strict_types=1);

namespace LaminasMicroscope\Collector;

class CollectorRegistry
{
    /** @var array<string, object> */
    private array $collectors = [];

    public function register(object $collector): void
    {
        if (method_exists($collector, 'getName')) {
            $name = $collector->getName();
            $this->collectors[$name] = $collector;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->collectors[$name]);
    }

    public function get(string $name): object|null
    {
        return $this->collectors[$name] ?? null;
    }

    /**
     * @return array<string, object>
     */
    public function all(): array
    {
        return $this->collectors;
    }
}
