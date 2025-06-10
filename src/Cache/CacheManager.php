<?php

declare(strict_types=1);

namespace LaminasMicroscope\Cache;

use LaminasMicroscope\Cache\Adapter\CacheAdapterInterface;
use LaminasMicroscope\Cache\Adapter\FileAdapter;
use LaminasMicroscope\Cache\Adapter\RedisAdapter;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Exception\CacheException;

class CacheManager
{
    private ConfigurationService $config;
    private array $adapters   = [];
    private array $defaultTtl = [
        'query_results'    => 300, // 5 minutes
        'analysis_results' => 600, // 10 minutes
        'config_cache'     => 3600, // 1 hour
        'report_cache'     => 1800, // 30 minutes
    ];

    public function __construct(ConfigurationService $config)
    {
        $this->config = $config;
        $this->initializeAdapters();
    }

    public function get(string $key, string $category = 'default'): mixed
    {
        $adapter = $this->getAdapter($category);
        $fullKey = $this->buildKey($key, $category);

        return $adapter->get($fullKey);
    }

    public function set(string $key, mixed $value, string $category = 'default', ?int $ttl = null): bool
    {
        $adapter = $this->getAdapter($category);
        $fullKey = $this->buildKey($key, $category);
        $ttl     = $ttl ?? $this->getTtl($category);

        return $adapter->set($fullKey, $value, $ttl);
    }

    public function delete(string $key, string $category = 'default'): bool
    {
        $adapter = $this->getAdapter($category);
        $fullKey = $this->buildKey($key, $category);

        return $adapter->delete($fullKey);
    }

    public function flush(string $category = 'default'): bool
    {
        $adapter = $this->getAdapter($category);
        return $adapter->flush();
    }

    public function has(string $key, string $category = 'default'): bool
    {
        $adapter = $this->getAdapter($category);
        $fullKey = $this->buildKey($key, $category);

        return $adapter->has($fullKey);
    }

    public function remember(string $key, callable $callback, string $category = 'default', ?int $ttl = null): mixed
    {
        if ($this->has($key, $category)) {
            return $this->get($key, $category);
        }

        $value = $callback();
        $this->set($key, $value, $category, $ttl);

        return $value;
    }

    public function rememberForever(string $key, callable $callback, string $category = 'default'): mixed
    {
        return $this->remember($key, $callback, $category, 0);
    }

    public function increment(string $key, int $value = 1, string $category = 'default'): int
    {
        $adapter = $this->getAdapter($category);
        $fullKey = $this->buildKey($key, $category);

        if ($adapter instanceof RedisAdapter) {
            return $adapter->increment($fullKey, $value);
        }

        $current = $this->get($key, $category) ?? 0;
        $new     = (int) $current + $value;
        $this->set($key, $new, $category);

        return $new;
    }

    public function decrement(string $key, int $value = 1, string $category = 'default'): int
    {
        return $this->increment($key, -$value, $category);
    }

    public function getStats(): array
    {
        $stats = [];

        foreach ($this->adapters as $category => $adapter) {
            $stats[$category] = [
                'type'  => $adapter::class,
                'stats' => $adapter->getStats(),
            ];
        }

        return $stats;
    }

    public function invalidatePattern(string $pattern, string $category = 'default'): int
    {
        $adapter     = $this->getAdapter($category);
        $fullPattern = $this->buildKey($pattern, $category);

        return $adapter->invalidatePattern($fullPattern);
    }

    private function initializeAdapters(): void
    {
        $cacheConfig = $this->config->get('laminas_microscope.cache', []);

        foreach ($cacheConfig as $category => $config) {
            $this->adapters[$category] = $this->createAdapter($config);
        }

        if (empty($this->adapters)) {
            $this->adapters['default'] = $this->createDefaultAdapter();
        }
    }

    private function createAdapter(array $config): CacheAdapterInterface
    {
        $type = $config['adapter'] ?? 'file';

        return match ($type) {
            'redis' => new RedisAdapter($config),
            'file' => new FileAdapter($config),
            default => throw new CacheException("Unsupported cache adapter: {$type}")
        };
    }

    private function createDefaultAdapter(): CacheAdapterInterface
    {
        $storagePath = $this->config->getStoragePath() . '/cache';

        return new FileAdapter([
            'cache_dir'   => $storagePath,
            'permissions' => 0755,
        ]);
    }

    private function getAdapter(string $category): CacheAdapterInterface
    {
        if (! isset($this->adapters[$category])) {
            $this->adapters[$category] = $this->adapters['default'] ?? $this->createDefaultAdapter();
        }

        return $this->adapters[$category];
    }

    private function buildKey(string $key, string $category): string
    {
        $prefix = $this->config->get('laminas_microscope.cache.prefix', 'lm');
        return "{$prefix}:{$category}:{$key}";
    }

    private function getTtl(string $category): int
    {
        return $this->defaultTtl[$category] ?? 300;
    }
}
