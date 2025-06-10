<?php

declare(strict_types=1);

namespace LaminasMicroscope\Cache\Adapter;

interface CacheAdapterInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl = 0): bool;

    public function delete(string $key): bool;

    public function flush(): bool;

    public function has(string $key): bool;

    public function getStats(): array;

    public function invalidatePattern(string $pattern): int;
}
