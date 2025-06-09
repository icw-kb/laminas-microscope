<?php

declare(strict_types=1);

namespace LaminasMicroscope\Cache\Adapter;

use LaminasMicroscope\Exception\CacheException;
use Redis;

class RedisAdapter implements CacheAdapterInterface
{
    private Redis $redis;
    private string $prefix;
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
    ];

    public function __construct(array $config = [])
    {
        if (!extension_loaded('redis')) {
            throw new CacheException('Redis extension is not loaded');
        }

        $this->redis = new Redis();
        $this->prefix = $config['prefix'] ?? 'lm_cache:';
        
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 2.5;
        $database = $config['database'] ?? 0;
        $password = $config['password'] ?? null;

        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new CacheException("Failed to connect to Redis at {$host}:{$port}");
        }

        if ($password !== null) {
            if (!$this->redis->auth($password)) {
                throw new CacheException('Redis authentication failed');
            }
        }

        if ($database > 0) {
            if (!$this->redis->select($database)) {
                throw new CacheException("Failed to select Redis database {$database}");
            }
        }

        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
    }

    public function get(string $key): mixed
    {
        $prefixedKey = $this->prefix . $key;
        $value = $this->redis->get($prefixedKey);
        
        if ($value === false) {
            $this->stats['misses']++;
            return null;
        }

        $this->stats['hits']++;
        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $prefixedKey = $this->prefix . $key;
        
        $result = $ttl > 0 
            ? $this->redis->setex($prefixedKey, $ttl, $value)
            : $this->redis->set($prefixedKey, $value);

        if ($result) {
            $this->stats['writes']++;
        }

        return $result;
    }

    public function delete(string $key): bool
    {
        $prefixedKey = $this->prefix . $key;
        $result = $this->redis->del($prefixedKey) > 0;
        
        if ($result) {
            $this->stats['deletes']++;
        }

        return $result;
    }

    public function flush(): bool
    {
        $pattern = $this->prefix . '*';
        $keys = $this->redis->keys($pattern);
        
        if (empty($keys)) {
            return true;
        }

        return $this->redis->del($keys) > 0;
    }

    public function has(string $key): bool
    {
        $prefixedKey = $this->prefix . $key;
        return $this->redis->exists($prefixedKey) > 0;
    }

    public function increment(string $key, int $value = 1): int
    {
        $prefixedKey = $this->prefix . $key;
        return $this->redis->incrBy($prefixedKey, $value);
    }

    public function getStats(): array
    {
        $info = $this->redis->info();
        
        return array_merge($this->stats, [
            'redis_version' => $info['redis_version'] ?? 'unknown',
            'connected_clients' => $info['connected_clients'] ?? 0,
            'used_memory' => $info['used_memory'] ?? 0,
            'used_memory_human' => $info['used_memory_human'] ?? '0B',
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0,
        ]);
    }

    public function invalidatePattern(string $pattern): int
    {
        $fullPattern = $this->prefix . $pattern;
        $keys = $this->redis->keys($fullPattern);
        
        if (empty($keys)) {
            return 0;
        }

        $deleted = $this->redis->del($keys);
        $this->stats['deletes'] += $deleted;
        
        return $deleted;
    }

    public function __destruct()
    {
        if ($this->redis->isConnected()) {
            $this->redis->close();
        }
    }
}