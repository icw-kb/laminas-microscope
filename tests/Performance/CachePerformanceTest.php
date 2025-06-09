<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Performance;

use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\Cache\Adapter\FileAdapter;
use LaminasMicroscope\Config\ConfigurationService;

class CachePerformanceTest extends BenchmarkTestCase
{
    private CacheManager $cacheManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $config = new ConfigurationService([
            'laminas_microscope' => [
                'storage' => [
                    'path' => sys_get_temp_dir() . '/lm-test-cache'
                ]
            ]
        ]);
        
        $this->cacheManager = new CacheManager($config);
    }
    
    public function testCacheWritePerformance(): void
    {
        $this->startBenchmark('cache_write_1000');
        
        for ($i = 0; $i < 1000; $i++) {
            $key = "test_key_{$i}";
            $value = [
                'id' => $i,
                'data' => str_repeat('x', 100),
                'timestamp' => time(),
            ];
            
            $this->cacheManager->set($key, $value);
        }
        
        $this->endBenchmark('cache_write_1000');
        
        $this->assertPerformance('cache_write_1000', [
            'max_duration' => 2.0, // 2 seconds max for 1000 writes
            'max_memory' => 50 * 1024 * 1024, // 50MB max
        ]);
    }
    
    public function testCacheReadPerformance(): void
    {
        // Pre-populate cache
        for ($i = 0; $i < 1000; $i++) {
            $this->cacheManager->set("read_test_{$i}", ['data' => "value_{$i}"]);
        }
        
        $this->startBenchmark('cache_read_1000');
        
        $hits = 0;
        for ($i = 0; $i < 1000; $i++) {
            $value = $this->cacheManager->get("read_test_{$i}");
            if ($value !== null) {
                $hits++;
            }
        }
        
        $this->endBenchmark('cache_read_1000');
        
        $this->assertEquals(1000, $hits, 'All cache reads should be hits');
        
        $this->assertPerformance('cache_read_1000', [
            'max_duration' => 1.0, // 1 second max for 1000 reads
            'max_memory' => 20 * 1024 * 1024, // 20MB max
        ]);
    }
    
    public function testCacheRememberPerformance(): void
    {
        $this->startBenchmark('cache_remember_performance');
        
        // Test remember function with expensive computation
        $stats = $this->runIterations(function () {
            $result = $this->cacheManager->remember(
                'expensive_computation',
                function () {
                    // Simulate expensive computation
                    usleep(1000); // 1ms
                    return array_sum(range(1, 1000));
                }
            );
            
            $this->assertEquals(500500, $result);
        }, 100);
        
        $this->endBenchmark('cache_remember_performance');
        
        // First call should be slow, subsequent calls should be fast
        $this->assertLessThan(0.01, $stats['duration']['min'], 'Cached calls should be very fast');
        $this->assertGreaterThan(0.001, $stats['duration']['max'], 'At least one call should hit the expensive computation');
    }
    
    public function testLargeDataCaching(): void
    {
        $largeData = $this->generateLargeDataset(10000);
        
        $this->startBenchmark('large_data_write');
        $this->cacheManager->set('large_dataset', $largeData);
        $this->endBenchmark('large_data_write');
        
        $this->startBenchmark('large_data_read');
        $retrieved = $this->cacheManager->get('large_dataset');
        $this->endBenchmark('large_data_read');
        
        $this->assertEquals(count($largeData), count($retrieved));
        
        $this->assertPerformance('large_data_write', [
            'max_duration' => 5.0, // 5 seconds for large dataset
        ]);
        
        $this->assertPerformance('large_data_read', [
            'max_duration' => 2.0, // 2 seconds for large dataset read
        ]);
    }
    
    public function testCacheInvalidationPerformance(): void
    {
        // Create many cache entries
        for ($i = 0; $i < 1000; $i++) {
            $this->cacheManager->set("pattern_test_{$i}", "value_{$i}");
        }
        
        $this->startBenchmark('cache_invalidation');
        
        $invalidated = $this->cacheManager->invalidatePattern('pattern_test_*');
        
        $this->endBenchmark('cache_invalidation');
        
        $this->assertGreaterThan(0, $invalidated, 'Should invalidate some entries');
        
        $this->assertPerformance('cache_invalidation', [
            'max_duration' => 1.0, // 1 second max for pattern invalidation
        ]);
    }
    
    public function testConcurrentCacheAccess(): void
    {
        $this->startBenchmark('concurrent_access');
        
        // Simulate concurrent access patterns
        $processes = [];
        
        for ($i = 0; $i < 10; $i++) {
            $processes[] = function () use ($i) {
                for ($j = 0; $j < 100; $j++) {
                    $key = "concurrent_{$i}_{$j}";
                    $this->cacheManager->set($key, ['process' => $i, 'iteration' => $j]);
                    $value = $this->cacheManager->get($key);
                    $this->assertNotNull($value);
                }
            };
        }
        
        // Execute all processes
        foreach ($processes as $process) {
            $process();
        }
        
        $this->endBenchmark('concurrent_access');
        
        $this->assertPerformance('concurrent_access', [
            'max_duration' => 3.0, // 3 seconds for concurrent access simulation
        ]);
    }
    
    protected function tearDown(): void
    {
        // Clean up test cache
        $this->cacheManager->flush();
        
        parent::tearDown();
    }
}