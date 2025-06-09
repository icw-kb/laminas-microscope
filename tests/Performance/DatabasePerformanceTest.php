<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Performance;

use LaminasMicroscope\DebugBar\Collectors\EnhancedPDOCollector;
use LaminasMicroscope\Analyzer\QueryAnalyzer;
use Laminas\ServiceManager\ServiceManager;

class DatabasePerformanceTest extends BenchmarkTestCase
{
    private EnhancedPDOCollector $collector;
    private QueryAnalyzer $analyzer;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $serviceManager = new ServiceManager();
        $this->collector = new EnhancedPDOCollector($serviceManager);
        $this->analyzer = new QueryAnalyzer();
    }
    
    public function testQueryAnalysisPerformance(): void
    {
        $queries = $this->generateTestQueries(1000);
        
        $this->startBenchmark('query_analysis_1000');
        
        foreach ($queries as $query) {
            $this->analyzer->analyzeQuery($query['sql'], $query['params'], $query['duration']);
        }
        
        $this->endBenchmark('query_analysis_1000');
        
        $this->assertPerformance('query_analysis_1000', [
            'max_duration' => 2.0, // 2 seconds for 1000 query analyses
            'max_memory' => 30 * 1024 * 1024, // 30MB max
        ]);
    }
    
    public function testNPlusOneDetectionPerformance(): void
    {
        // Generate N+1 pattern queries
        $queries = [];
        
        // Initial query
        $queries[] = [
            'sql' => 'SELECT * FROM users WHERE active = ?',
            'params' => [1],
            'duration' => 50,
        ];
        
        // N+1 queries
        for ($i = 1; $i <= 50; $i++) {
            $queries[] = [
                'sql' => 'SELECT * FROM user_profiles WHERE user_id = ?',
                'params' => [$i],
                'duration' => 10,
            ];
        }
        
        $this->startBenchmark('n_plus_one_detection');
        
        foreach ($queries as $query) {
            $this->collector->addQuery($query);
        }
        
        $analysis = $this->collector->collect();
        
        $this->endBenchmark('n_plus_one_detection');
        
        $this->assertArrayHasKey('analysis', $analysis);
        $this->assertNotEmpty($analysis['analysis']['n_plus_one_patterns']);
        
        $this->assertPerformance('n_plus_one_detection', [
            'max_duration' => 1.0, // 1 second for N+1 detection
        ]);
    }
    
    public function testDuplicateQueryDetectionPerformance(): void
    {
        $baseQuery = 'SELECT * FROM products WHERE category_id = ? AND active = ?';
        $queries = [];
        
        // Generate many duplicate queries
        for ($i = 0; $i < 500; $i++) {
            $queries[] = [
                'sql' => $baseQuery,
                'params' => [rand(1, 10), 1],
                'duration' => rand(10, 100),
            ];
        }
        
        $this->startBenchmark('duplicate_detection');
        
        foreach ($queries as $query) {
            $this->collector->addQuery($query);
        }
        
        $analysis = $this->collector->collect();
        
        $this->endBenchmark('duplicate_detection');
        
        $this->assertGreaterThan(0, $analysis['nb_duplicate_statements']);
        
        $this->assertPerformance('duplicate_detection', [
            'max_duration' => 2.0, // 2 seconds for duplicate detection
        ]);
    }
    
    public function testComplexQueryAnalysisPerformance(): void
    {
        $complexQueries = [
            'SELECT u.*, p.name as profile_name, c.name as company_name FROM users u LEFT JOIN profiles p ON u.id = p.user_id LEFT JOIN companies c ON u.company_id = c.id WHERE u.active = ? AND p.status = ? ORDER BY u.created_at DESC LIMIT ?',
            'SELECT COUNT(*) as total, AVG(price) as avg_price, MAX(price) as max_price FROM products p JOIN categories c ON p.category_id = c.id WHERE c.active = ? GROUP BY c.id HAVING COUNT(*) > ? ORDER BY total DESC',
            'UPDATE users SET last_login = ?, login_count = login_count + 1 WHERE id IN (SELECT user_id FROM sessions WHERE expires_at > ? AND active = ?)',
            'WITH RECURSIVE category_tree AS (SELECT id, name, parent_id, 0 as level FROM categories WHERE parent_id IS NULL UNION ALL SELECT c.id, c.name, c.parent_id, ct.level + 1 FROM categories c JOIN category_tree ct ON c.parent_id = ct.id) SELECT * FROM category_tree ORDER BY level, name',
        ];
        
        $this->startBenchmark('complex_query_analysis');
        
        $stats = $this->runIterations(function () use ($complexQueries) {
            $query = $complexQueries[array_rand($complexQueries)];
            $this->analyzer->analyzeQuery($query, [], rand(50, 200));
        }, 100);
        
        $this->endBenchmark('complex_query_analysis');
        
        $this->assertLessThan(0.01, $stats['duration']['avg'], 'Complex query analysis should be fast on average');
        
        $this->assertPerformance('complex_query_analysis', [
            'max_duration' => 1.0, // 1 second for 100 complex analyses
        ]);
    }
    
    public function testQueryPatternRecognitionPerformance(): void
    {
        $patterns = [
            'INSERT INTO logs (user_id, action, timestamp) VALUES (?, ?, ?)',
            'UPDATE user_stats SET page_views = page_views + 1 WHERE user_id = ?',
            'SELECT * FROM cache WHERE key = ? AND expires_at > ?',
            'DELETE FROM temporary_data WHERE created_at < ?',
        ];
        
        $queries = [];
        
        // Generate 2000 queries following patterns
        for ($i = 0; $i < 2000; $i++) {
            $pattern = $patterns[$i % count($patterns)];
            $queries[] = [
                'sql' => $pattern,
                'params' => [rand(1, 1000), time()],
                'duration' => rand(5, 50),
            ];
        }
        
        $this->startBenchmark('pattern_recognition');
        
        foreach ($queries as $query) {
            $this->collector->addQuery($query);
        }
        
        $analysis = $this->collector->collect();
        
        $this->endBenchmark('pattern_recognition');
        
        $this->assertEquals(2000, $analysis['nb_statements']);
        $this->assertLessThan(2000, $analysis['analysis']['unique_queries']);
        
        $this->assertPerformance('pattern_recognition', [
            'max_duration' => 3.0, // 3 seconds for pattern recognition on 2000 queries
            'max_memory' => 50 * 1024 * 1024, // 50MB max
        ]);
    }
    
    public function testQueryRecommendationPerformance(): void
    {
        // Queries that should trigger recommendations
        $problematicQueries = [
            'SELECT * FROM large_table',
            'SELECT * FROM users WHERE email LIKE \'%@domain.com\'',
            'SELECT * FROM products ORDER BY RAND() LIMIT 10',
            'UPDATE users SET last_seen = NOW()',
            'SELECT COUNT(*) FROM (SELECT * FROM orders WHERE status = \'pending\') as subq',
        ];
        
        $this->startBenchmark('recommendation_generation');
        
        foreach ($problematicQueries as $sql) {
            $this->collector->addQuery([
                'sql' => $sql,
                'params' => [],
                'duration' => rand(100, 1000), // Slow queries
            ]);
        }
        
        $analysis = $this->collector->collect();
        
        $this->endBenchmark('recommendation_generation');
        
        $this->assertNotEmpty($analysis['recommendations']);
        
        $this->assertPerformance('recommendation_generation', [
            'max_duration' => 0.5, // 500ms for recommendation generation
        ]);
    }
    
    private function generateTestQueries(int $count): array
    {
        $queries = [];
        $templates = [
            'SELECT * FROM users WHERE id = ?',
            'INSERT INTO logs (message, level, timestamp) VALUES (?, ?, ?)',
            'UPDATE products SET stock = stock - ? WHERE id = ?',
            'DELETE FROM cache WHERE expires_at < ?',
            'SELECT COUNT(*) FROM orders WHERE created_at > ?',
        ];
        
        for ($i = 0; $i < $count; $i++) {
            $template = $templates[$i % count($templates)];
            $queries[] = [
                'sql' => $template,
                'params' => [rand(1, 1000)],
                'duration' => rand(1, 200),
            ];
        }
        
        return $queries;
    }
}