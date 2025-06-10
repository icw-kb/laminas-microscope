<?php

declare(strict_types=1);

namespace LaminasMicroscope\Cache\Adapter;

use LaminasMicroscope\Exception\CacheException;
use LaminasMicroscope\Utility\ErrorHandler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function array_merge;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function is_array;
use function is_dir;
use function is_writable;
use function iterator_count;
use function mkdir;
use function serialize;
use function strpos;
use function substr;
use function sys_get_temp_dir;
use function time;
use function unlink;
use function unserialize;

use const LOCK_EX;

class FileAdapter implements CacheAdapterInterface
{
    private string $cacheDir;
    private int $permissions;
    private array $stats = [
        'hits'    => 0,
        'misses'  => 0,
        'writes'  => 0,
        'deletes' => 0,
    ];

    public function __construct(array $config = [])
    {
        $this->cacheDir    = $config['cache_dir'] ?? sys_get_temp_dir() . '/laminas-microscope-cache';
        $this->permissions = $config['permissions'] ?? 0755;

        $this->ensureCacheDirectory();
    }

    public function get(string $key): mixed
    {
        $file = $this->getFilePath($key);

        if (! file_exists($file)) {
            $this->stats['misses']++;
            return null;
        }

        $content = ErrorHandler::executeWithDefault(
            fn() => file_get_contents($file),
            null,
            'FileAdapter::get'
        );

        if ($content === null) {
            $this->stats['misses']++;
            return null;
        }

        $data = unserialize($content);

        if (! is_array($data) || ! isset($data['expires'], $data['value'])) {
            $this->delete($key);
            $this->stats['misses']++;
            return null;
        }

        if ($data['expires'] > 0 && $data['expires'] < time()) {
            $this->delete($key);
            $this->stats['misses']++;
            return null;
        }

        $this->stats['hits']++;
        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $file    = $this->getFilePath($key);
        $expires = $ttl > 0 ? time() + $ttl : 0;

        $data = [
            'value'   => $value,
            'expires' => $expires,
            'created' => time(),
        ];

        $result = ErrorHandler::executeWithDefault(
            function () use ($file, $data) {
                $dir = dirname($file);
                if (! is_dir($dir)) {
                    mkdir($dir, $this->permissions, true);
                }

                return file_put_contents($file, serialize($data), LOCK_EX) !== false;
            },
            false,
            'FileAdapter::set'
        );

        if ($result) {
            $this->stats['writes']++;
        }

        return $result;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (! file_exists($file)) {
            return true;
        }

        $result = ErrorHandler::executeWithDefault(
            fn() => unlink($file),
            false,
            'FileAdapter::delete'
        );

        if ($result) {
            $this->stats['deletes']++;
        }

        return $result;
    }

    public function flush(): bool
    {
        return ErrorHandler::executeWithDefault(
            function () {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        unlink($file->getRealPath());
                    }
                }

                return true;
            },
            false,
            'FileAdapter::flush'
        );
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function getStats(): array
    {
        return array_merge($this->stats, [
            'cache_dir'   => $this->cacheDir,
            'total_files' => $this->countCacheFiles(),
            'cache_size'  => $this->getCacheSize(),
        ]);
    }

    public function invalidatePattern(string $pattern): int
    {
        if (! is_dir($this->cacheDir)) {
            return 0;
        }

        $count = 0;

        // Handle simple wildcard patterns
        if (strpos($pattern, '*') !== false) {
            // For file-based cache, we need to match against the actual key names
            // Since we hash the keys, we'll need to check file contents for pattern matching
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'cache') {
                    // For now, delete all cache files when using wildcard patterns
                    // A more sophisticated implementation would store key mappings
                    if (unlink($file->getRealPath())) {
                        $count++;
                        $this->stats['deletes']++;
                    }
                }
            }
        } else {
            // Exact key match
            if ($this->delete($pattern)) {
                $count = 1;
            }
        }

        return $count;
    }

    private function getFilePath(string $key): string
    {
        $hash   = hash('sha256', $key);
        $prefix = substr($hash, 0, 2);

        return $this->cacheDir . '/' . $prefix . '/' . $hash . '.cache';
    }

    private function ensureCacheDirectory(): void
    {
        ErrorHandler::handleSafely(function () {
            if (! is_dir($this->cacheDir)) {
                if (! mkdir($this->cacheDir, $this->permissions, true) && ! is_dir($this->cacheDir)) {
                    throw new CacheException("Failed to create cache directory: {$this->cacheDir}");
                }
            }

            if (! is_writable($this->cacheDir)) {
                throw new CacheException("Cache directory is not writable: {$this->cacheDir}");
            }
        }, 'FileAdapter::ensureCacheDirectory');
    }

    private function countCacheFiles(): int
    {
        if (! is_dir($this->cacheDir)) {
            return 0;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        return iterator_count($iterator);
    }

    private function getCacheSize(): int
    {
        if (! is_dir($this->cacheDir)) {
            return 0;
        }

        $size     = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
