<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Performance;

use LaminasMicroscopeTest\Unit\BaseTestCase;
use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\Microscope\Storage\ReportStorage;

abstract class BenchmarkTestCase extends BaseTestCase
{
    protected array $benchmarkResults = [];
    protected float $memoryBefore;
    protected float $timeBefore;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetBenchmarkState();
    }
    
    protected function resetBenchmarkState(): void
    {
        $this->benchmarkResults = [];
        $this->memoryBefore = memory_get_usage(true);
        $this->timeBefore = microtime(true);
    }
    
    protected function startBenchmark(string $name): void
    {
        $this->benchmarkResults[$name] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'peak_memory_before' => memory_get_peak_usage(true),
        ];
    }
    
    protected function endBenchmark(string $name): array
    {
        if (!isset($this->benchmarkResults[$name])) {
            throw new \InvalidArgumentException("Benchmark '{$name}' was not started");
        }
        
        $result = $this->benchmarkResults[$name];
        $result['end_time'] = microtime(true);
        $result['end_memory'] = memory_get_usage(true);
        $result['peak_memory_after'] = memory_get_peak_usage(true);
        
        $result['duration'] = $result['end_time'] - $result['start_time'];
        $result['memory_used'] = $result['end_memory'] - $result['start_memory'];
        $result['peak_memory_used'] = $result['peak_memory_after'] - $result['peak_memory_before'];
        
        $this->benchmarkResults[$name] = $result;
        
        return $result;
    }
    
    protected function assertPerformance(string $benchmarkName, array $expectations): void
    {
        if (!isset($this->benchmarkResults[$benchmarkName])) {
            $this->fail("Benchmark '{$benchmarkName}' not found");
        }
        
        $result = $this->benchmarkResults[$benchmarkName];
        
        if (isset($expectations['max_duration'])) {
            $this->assertLessThan(
                $expectations['max_duration'],
                $result['duration'],
                sprintf(
                    'Benchmark %s took %.4fs, expected less than %.4fs',
                    $benchmarkName,
                    $result['duration'],
                    $expectations['max_duration']
                )
            );
        }
        
        if (isset($expectations['max_memory'])) {
            $this->assertLessThan(
                $expectations['max_memory'],
                $result['memory_used'],
                sprintf(
                    'Benchmark %s used %d bytes, expected less than %d bytes',
                    $benchmarkName,
                    $result['memory_used'],
                    $expectations['max_memory']
                )
            );
        }
        
        if (isset($expectations['max_peak_memory'])) {
            $this->assertLessThan(
                $expectations['max_peak_memory'],
                $result['peak_memory_used'],
                sprintf(
                    'Benchmark %s peak memory %d bytes, expected less than %d bytes',
                    $benchmarkName,
                    $result['peak_memory_used'],
                    $expectations['max_peak_memory']
                )
            );
        }
    }
    
    protected function runIterations(callable $callback, int $iterations = 100): array
    {
        $results = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            $callback();
            
            $results[] = [
                'iteration' => $i + 1,
                'duration' => microtime(true) - $startTime,
                'memory' => memory_get_usage(true) - $startMemory,
            ];
        }
        
        return $this->calculateIterationStats($results);
    }
    
    private function calculateIterationStats(array $results): array
    {
        $durations = array_column($results, 'duration');
        $memories = array_column($results, 'memory');
        
        return [
            'iterations' => count($results),
            'duration' => [
                'min' => min($durations),
                'max' => max($durations),
                'avg' => array_sum($durations) / count($durations),
                'median' => $this->calculateMedian($durations),
                'p95' => $this->calculatePercentile($durations, 95),
                'p99' => $this->calculatePercentile($durations, 99),
            ],
            'memory' => [
                'min' => min($memories),
                'max' => max($memories),
                'avg' => array_sum($memories) / count($memories),
                'median' => $this->calculateMedian($memories),
            ],
            'raw_results' => $results,
        ];
    }
    
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }
    
    private function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        
        if (floor($index) === $index) {
            return $values[(int) $index];
        }
        
        $lower = $values[(int) floor($index)];
        $upper = $values[(int) ceil($index)];
        $fraction = $index - floor($index);
        
        return $lower + ($fraction * ($upper - $lower));
    }
    
    protected function generateLargeDataset(int $size): array
    {
        $data = [];
        
        for ($i = 0; $i < $size; $i++) {
            $data[] = [
                'id' => $i + 1,
                'name' => 'Item ' . ($i + 1),
                'description' => str_repeat('Lorem ipsum dolor sit amet. ', rand(1, 10)),
                'value' => rand(1, 1000),
                'created_at' => date('Y-m-d H:i:s', time() - rand(0, 86400 * 365)),
                'metadata' => json_encode([
                    'tags' => array_slice(['tag1', 'tag2', 'tag3', 'tag4', 'tag5'], 0, rand(1, 5)),
                    'priority' => rand(1, 5),
                    'active' => (bool) rand(0, 1),
                ]),
            ];
        }
        
        return $data;
    }
    
    protected function tearDown(): void
    {
        if (!empty($this->benchmarkResults)) {
            $this->outputBenchmarkSummary();
        }
        
        parent::tearDown();
    }
    
    private function outputBenchmarkSummary(): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "BENCHMARK RESULTS\n";
        echo str_repeat('=', 80) . "\n";
        
        foreach ($this->benchmarkResults as $name => $result) {
            echo sprintf(
                "%-30s | Duration: %8.4fs | Memory: %10s | Peak: %10s\n",
                $name,
                $result['duration'],
                $this->formatBytes($result['memory_used']),
                $this->formatBytes($result['peak_memory_used'])
            );
        }
        
        echo str_repeat('=', 80) . "\n";
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }
        
        return sprintf('%.2f%s', $bytes, $units[$unit]);
    }
}