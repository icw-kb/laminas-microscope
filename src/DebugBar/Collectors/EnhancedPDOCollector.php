<?php

declare(strict_types=1);

namespace LaminasMicroscope\DebugBar\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Analyzer\QueryAnalyzer;
use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\Collector\CollectorInterface;
use LaminasMicroscope\Utility\FormatUtility;

use function array_column;
use function array_filter;
use function array_merge;
use function array_slice;
use function array_sum;
use function array_unique;
use function arsort;
use function count;
use function floor;
use function in_array;
use function max;
use function md5;
use function method_exists;
use function microtime;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function strtolower;
use function strtoupper;
use function time;
use function trim;
use function usort;

use const PREG_SET_ORDER;

class EnhancedPDOCollector extends DataCollector implements Renderable, CollectorInterface
{
    private ServiceManager $serviceManager;
    private ?CacheManager $cacheManager;
    private QueryAnalyzer $queryAnalyzer;
    private array $queries       = [];
    private array $connections   = [];
    private array $queryPatterns = [];
    private float $totalTime     = 0;
    private array $config;

    public function __construct(
        ServiceManager $serviceManager,
        ?CacheManager $cacheManager = null,
        array $config = []
    ) {
        $this->serviceManager = $serviceManager;
        $this->cacheManager   = $cacheManager;
        $this->queryAnalyzer  = new QueryAnalyzer($config);
        $this->config         = array_merge([
            'slow_threshold'              => 100, // milliseconds
            'very_slow_threshold'         => 500, // milliseconds
            'enable_explain'              => true,
            'enable_n_plus_one_detection' => true,
            'enable_index_analysis'       => true,
            'cache_analysis_results'      => true,
        ], $config);

        $this->setupQueryLogging();
    }

    public function collect(): array
    {
        $analysis = $this->analyzeQueries();

        return [
            'nb_statements'            => count($this->queries),
            'nb_failed_statements'     => count(array_filter($this->queries, fn($q) => $q['is_success'] === false)),
            'nb_slow_statements'       => count(array_filter($this->queries, fn($q) => $q['is_slow'] ?? false)),
            'nb_very_slow_statements'  => count(array_filter($this->queries, fn($q) => $q['is_very_slow'] ?? false)),
            'nb_duplicate_statements'  => count(array_filter($this->queries, fn($q) => $q['is_duplicate'] ?? false)),
            'accumulated_duration'     => $this->totalTime,
            'accumulated_duration_str' => FormatUtility::formatDuration($this->totalTime / 1000),
            'statements'               => $this->queries,
            'connections'              => $this->connections,
            'analysis'                 => $analysis,
            'performance_score'        => $this->calculatePerformanceScore(),
            'recommendations'          => $this->generateRecommendations($analysis),
        ];
    }

    public function getName(): string
    {
        return 'enhanced_pdo';
    }

    public function getWidgets(): array
    {
        return [
            'enhanced_pdo' => [
                'icon'    => 'database',
                'widget'  => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map'     => 'enhanced_pdo',
                'default' => '[]',
            ],
            'enhanced_pdo:badge' => [
                'map'     => 'enhanced_pdo.nb_statements',
                'default' => 0,
            ],
        ];
    }

    public function addQuery(array $query): void
    {
        $startTime = microtime(true);

        // Basic query processing
        $query['duration_str'] = FormatUtility::formatDuration($query['duration'] / 1000);
        $query['memory_str']   = FormatUtility::formatBytes($query['memory'] ?? 0);
        $query['timestamp']    = time();
        $query['microtime']    = microtime(true);

        // Enhanced analysis
        $query = $this->enhanceQueryData($query);

        // Pattern tracking for N+1 detection
        $this->trackQueryPattern($query);

        $this->queries[]  = $query;
        $this->totalTime += $query['duration'];

        // Cache analysis if enabled
        if ($this->config['cache_analysis_results'] && $this->cacheManager) {
            $cacheKey = 'query_analysis_' . md5($query['sql']);
            $this->cacheManager->set($cacheKey, $query, 'query_results', 300);
        }
    }

    private function enhanceQueryData(array $query): array
    {
        // Performance classification
        $query['is_slow']      = $query['duration'] > $this->config['slow_threshold'];
        $query['is_very_slow'] = $query['duration'] > $this->config['very_slow_threshold'];

        // Query type detection
        $query['type'] = $this->detectQueryType($query['sql']);

        // Duplicate detection with improved algorithm
        $query['is_duplicate']    = $this->isDuplicateQuery($query['sql']);
        $query['duplicate_count'] = $this->getDuplicateCount($query['sql']);

        // Query complexity analysis
        $query['complexity'] = $this->analyzeQueryComplexity($query['sql']);

        // Table analysis
        $query['tables']      = $this->extractTables($query['sql']);
        $query['table_count'] = count($query['tables']);

        // Join analysis
        $query['joins']      = $this->analyzeJoins($query['sql']);
        $query['join_count'] = count($query['joins']);

        // Index usage analysis (if EXPLAIN is enabled)
        if ($this->config['enable_explain'] && $query['type'] === 'SELECT') {
            $query['explain']     = $this->getQueryExplanation($query['sql'], $query['params'] ?? []);
            $query['index_usage'] = $this->analyzeIndexUsage($query['explain'] ?? []);
        }

        return $query;
    }

    private function analyzeQueries(): array
    {
        return [
            'total_queries'           => count($this->queries),
            'unique_queries'          => $this->countUniqueQueries(),
            'query_types'             => $this->analyzeQueryTypes(),
            'slow_queries'            => $this->getSlowQueries(),
            'duplicate_queries'       => $this->getDuplicateQueries(),
            'n_plus_one_patterns'     => $this->detectNPlusOnePatterns(),
            'table_usage'             => $this->analyzeTableUsage(),
            'index_issues'            => $this->findIndexIssues(),
            'performance_bottlenecks' => $this->identifyBottlenecks(),
        ];
    }

    private function detectQueryType(string $sql): string
    {
        $sql = trim(strtoupper($sql));

        $types = [
            'SELECT'   => '/^SELECT/',
            'INSERT'   => '/^INSERT/',
            'UPDATE'   => '/^UPDATE/',
            'DELETE'   => '/^DELETE/',
            'CREATE'   => '/^CREATE/',
            'ALTER'    => '/^ALTER/',
            'DROP'     => '/^DROP/',
            'TRUNCATE' => '/^TRUNCATE/',
            'SHOW'     => '/^SHOW/',
            'DESCRIBE' => '/^DESCRIBE|^DESC/',
            'EXPLAIN'  => '/^EXPLAIN/',
        ];

        foreach ($types as $type => $pattern) {
            if (preg_match($pattern, $sql)) {
                return $type;
            }
        }

        return 'UNKNOWN';
    }

    private function analyzeQueryComplexity(string $sql): array
    {
        $sql        = strtoupper($sql);
        $complexity = [
            'score'   => 0,
            'factors' => [],
        ];

        // Join complexity
        $joinCount = preg_match_all('/\b(JOIN|LEFT JOIN|RIGHT JOIN|INNER JOIN|OUTER JOIN)\b/', $sql);
        if ($joinCount > 0) {
            $complexity['score']    += $joinCount * 2;
            $complexity['factors'][] = "Multiple joins ({$joinCount})";
        }

        // Subquery complexity
        $subqueryCount = preg_match_all('/\(.*SELECT.*\)/', $sql);
        if ($subqueryCount > 0) {
            $complexity['score']    += $subqueryCount * 3;
            $complexity['factors'][] = "Subqueries ({$subqueryCount})";
        }

        // Function complexity
        $functionCount = preg_match_all('/\b(COUNT|SUM|AVG|MAX|MIN|GROUP_CONCAT)\s*\(/', $sql);
        if ($functionCount > 0) {
            $complexity['score']    += $functionCount;
            $complexity['factors'][] = "Aggregate functions ({$functionCount})";
        }

        // ORDER BY complexity
        if (preg_match('/ORDER BY/', $sql)) {
            $complexity['score']    += 1;
            $complexity['factors'][] = "Ordering";
        }

        // GROUP BY complexity
        if (preg_match('/GROUP BY/', $sql)) {
            $complexity['score']    += 1;
            $complexity['factors'][] = "Grouping";
        }

        // HAVING complexity
        if (preg_match('/HAVING/', $sql)) {
            $complexity['score']    += 2;
            $complexity['factors'][] = "Having clause";
        }

        return $complexity;
    }

    private function extractTables(string $sql): array
    {
        $tables = [];

        // Simple table extraction - can be enhanced
        if (preg_match_all('/FROM\s+`?(\w+)`?|JOIN\s+`?(\w+)`?|UPDATE\s+`?(\w+)`?|INTO\s+`?(\w+)`?/i', $sql, $matches)) {
            foreach ($matches as $matchGroup) {
                foreach ($matchGroup as $match) {
                    if (! empty($match) && ! in_array(strtoupper($match), ['FROM', 'JOIN', 'UPDATE', 'INTO'])) {
                        $tables[] = $match;
                    }
                }
            }
        }

        return array_unique($tables);
    }

    private function analyzeJoins(string $sql): array
    {
        $joins = [];

        if (preg_match_all('/(LEFT|RIGHT|INNER|OUTER)?\s*JOIN\s+`?(\w+)`?\s+.*?ON\s+(.*?)(?=\s+(?:LEFT|RIGHT|INNER|OUTER)?\s*JOIN|\s+WHERE|\s+GROUP|\s+ORDER|\s+LIMIT|$)/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $joins[] = [
                    'type'      => trim($match[1] ?: 'INNER'),
                    'table'     => $match[2],
                    'condition' => trim($match[3]),
                ];
            }
        }

        return $joins;
    }

    private function getQueryExplanation(string $sql, array $params = []): array
    {
        // This would require actual database connection
        // Placeholder for EXPLAIN functionality
        return [];
    }

    private function analyzeIndexUsage(array $explain): array
    {
        // Analyze EXPLAIN output for index usage
        // Placeholder implementation
        return [
            'using_index'     => false,
            'full_table_scan' => false,
            'recommendations' => [],
        ];
    }

    private function trackQueryPattern(array $query): void
    {
        if (! $this->config['enable_n_plus_one_detection']) {
            return;
        }

        $pattern   = $this->normalizeQueryForPattern($query['sql']);
        $timestamp = $query['microtime'] ?? microtime(true);

        if (! isset($this->queryPatterns[$pattern])) {
            $this->queryPatterns[$pattern] = [];
        }

        $this->queryPatterns[$pattern][] = [
            'timestamp' => $timestamp,
            'params'    => $query['params'] ?? [],
            'duration'  => $query['duration'],
        ];
    }

    private function detectNPlusOnePatterns(): array
    {
        $patterns   = [];
        $timeWindow = 1.0; // 1 second window

        foreach ($this->queryPatterns as $pattern => $executions) {
            if (count($executions) < 3) { // Need at least 3 similar queries
                continue;
            }

            // Group executions by time window
            $groups = [];
            foreach ($executions as $execution) {
                $timeGroup = floor($execution['timestamp'] / $timeWindow);
                if (! isset($groups[$timeGroup])) {
                    $groups[$timeGroup] = [];
                }
                $groups[$timeGroup][] = $execution;
            }

            // Look for groups with many similar queries
            foreach ($groups as $group) {
                if (count($group) >= 3) {
                    $patterns[] = [
                        'pattern'        => $pattern,
                        'count'          => count($group),
                        'total_duration' => array_sum(array_column($group, 'duration')),
                        'avg_duration'   => array_sum(array_column($group, 'duration')) / count($group),
                        'severity'       => $this->calculateNPlusOneSeverity(count($group)),
                        'recommendation' => $this->generateNPlusOneRecommendation($pattern, count($group)),
                    ];
                }
            }
        }

        return $patterns;
    }

    private function normalizeQueryForPattern(string $sql): string
    {
        // More sophisticated normalization for pattern detection
        $sql = preg_replace('/\b\d+\b/', '?', $sql); // Replace numbers
        $sql = preg_replace('/\'[^\']*\'/', '?', $sql); // Replace string literals
        $sql = preg_replace('/\s+/', ' ', $sql); // Normalize whitespace
        return trim(strtolower($sql));
    }

    private function calculateNPlusOneSeverity(int $count): string
    {
        if ($count >= 10) {
            return 'HIGH';
        }
        if ($count >= 5) {
            return 'MEDIUM';
        }
        return 'LOW';
    }

    private function generateNPlusOneRecommendation(string $pattern, int $count): string
    {
        return "Consider using eager loading or batch queries to reduce {$count} similar queries to 1-2 queries.";
    }

    private function isDuplicateQuery(string $sql): bool
    {
        $normalizedSql = $this->normalizeSql($sql);

        foreach ($this->queries as $existingQuery) {
            if ($this->normalizeSql($existingQuery['sql']) === $normalizedSql) {
                return true;
            }
        }

        return false;
    }

    private function getDuplicateCount(string $sql): int
    {
        $normalizedSql = $this->normalizeSql($sql);
        $count         = 0;

        foreach ($this->queries as $existingQuery) {
            if ($this->normalizeSql($existingQuery['sql']) === $normalizedSql) {
                $count++;
            }
        }

        return $count;
    }

    private function normalizeSql(string $sql): string
    {
        $sql = preg_replace('/\?|\$\d+|:\w+/', '?', $sql);
        $sql = preg_replace('/\s+/', ' ', $sql);
        return trim(strtolower($sql));
    }

    private function countUniqueQueries(): int
    {
        $unique = [];
        foreach ($this->queries as $query) {
            $normalized          = $this->normalizeSql($query['sql']);
            $unique[$normalized] = true;
        }
        return count($unique);
    }

    private function analyzeQueryTypes(): array
    {
        $types = [];
        foreach ($this->queries as $query) {
            $type = $query['type'] ?? 'UNKNOWN';
            if (! isset($types[$type])) {
                $types[$type] = ['count' => 0, 'total_duration' => 0];
            }
            $types[$type]['count']++;
            $types[$type]['total_duration'] += $query['duration'];
        }

        foreach ($types as &$type) {
            $type['avg_duration'] = $type['total_duration'] / $type['count'];
        }

        return $types;
    }

    private function getSlowQueries(): array
    {
        return array_filter($this->queries, fn($q) => $q['is_slow'] ?? false);
    }

    private function getDuplicateQueries(): array
    {
        return array_filter($this->queries, fn($q) => $q['is_duplicate'] ?? false);
    }

    private function analyzeTableUsage(): array
    {
        $tableStats = [];

        foreach ($this->queries as $query) {
            foreach ($query['tables'] ?? [] as $table) {
                if (! isset($tableStats[$table])) {
                    $tableStats[$table] = [
                        'query_count'    => 0,
                        'total_duration' => 0,
                        'operations'     => [],
                    ];
                }

                $tableStats[$table]['query_count']++;
                $tableStats[$table]['total_duration'] += $query['duration'];

                $type = $query['type'] ?? 'UNKNOWN';
                if (! isset($tableStats[$table]['operations'][$type])) {
                    $tableStats[$table]['operations'][$type] = 0;
                }
                $tableStats[$table]['operations'][$type]++;
            }
        }

        foreach ($tableStats as &$stats) {
            $stats['avg_duration'] = $stats['total_duration'] / $stats['query_count'];
        }

        return $tableStats;
    }

    private function findIndexIssues(): array
    {
        // Placeholder for index analysis
        return [];
    }

    private function identifyBottlenecks(): array
    {
        $bottlenecks = [];

        // Slowest queries
        $slowestQueries = $this->queries;
        usort($slowestQueries, fn($a, $b) => ($b['duration'] ?? 0) <=> ($a['duration'] ?? 0));
        $bottlenecks['slowest_queries'] = array_slice($slowestQueries, 0, 5);

        // Most frequent queries
        $queryFrequency = [];
        foreach ($this->queries as $query) {
            $normalized = $this->normalizeSql($query['sql']);
            if (! isset($queryFrequency[$normalized])) {
                $queryFrequency[$normalized] = ['count' => 0, 'example' => $query];
            }
            $queryFrequency[$normalized]['count']++;
        }

        arsort($queryFrequency);
        $bottlenecks['most_frequent'] = array_slice($queryFrequency, 0, 5);

        return $bottlenecks;
    }

    private function calculatePerformanceScore(): int
    {
        if (empty($this->queries)) {
            return 100;
        }

        $score        = 100;
        $totalQueries = count($this->queries);

        // Penalize slow queries
        $slowQueries = count(array_filter($this->queries, fn($q) => $q['is_slow'] ?? false));
        $score      -= ($slowQueries / $totalQueries) * 30;

        // Penalize duplicates
        $duplicateQueries = count(array_filter($this->queries, fn($q) => $q['is_duplicate'] ?? false));
        $score           -= ($duplicateQueries / $totalQueries) * 20;

        // Penalize N+1 patterns
        $nPlusOnePatterns = $this->detectNPlusOnePatterns();
        $score           -= count($nPlusOnePatterns) * 15;

        return max(0, (int) $score);
    }

    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];

        if (! empty($analysis['slow_queries'])) {
            $recommendations[] = [
                'type'     => 'performance',
                'priority' => 'high',
                'message'  => 'Optimize slow queries by adding indexes or rewriting query logic',
                'count'    => count($analysis['slow_queries']),
            ];
        }

        if (! empty($analysis['duplicate_queries'])) {
            $recommendations[] = [
                'type'     => 'efficiency',
                'priority' => 'medium',
                'message'  => 'Reduce duplicate queries by implementing result caching',
                'count'    => count($analysis['duplicate_queries']),
            ];
        }

        if (! empty($analysis['n_plus_one_patterns'])) {
            $recommendations[] = [
                'type'     => 'architecture',
                'priority' => 'high',
                'message'  => 'Implement eager loading to eliminate N+1 query patterns',
                'count'    => count($analysis['n_plus_one_patterns']),
            ];
        }

        return $recommendations;
    }

    private function setupQueryLogging(): void
    {
        try {
            $this->hookIntoDbAdapters();
        } catch (Exception $e) {
            // Silently fail if no DB adapters are configured
        }
    }

    private function hookIntoDbAdapters(): void
    {
        try {
            $adapterNames = [Adapter::class, 'db', 'dbAdapter'];

            foreach ($adapterNames as $adapterName) {
                if ($this->serviceManager->has($adapterName)) {
                    $adapter = $this->serviceManager->get($adapterName);
                    $this->hookIntoAdapter($adapter);
                }
            }
        } catch (Exception $e) {
            // Continue silently if no adapters found
        }
    }

    private function hookIntoAdapter($adapter): void
    {
        try {
            if (method_exists($adapter, 'getPlatform')) {
                $platform            = $adapter->getPlatform();
                $this->connections[] = [
                    'name'   => $adapter::class,
                    'driver' => $platform::class,
                    'params' => $this->getConnectionParams($adapter),
                ];
            }

            if (method_exists($adapter, 'getProfiler')) {
                $profiler = $adapter->getProfiler();
                if ($profiler && method_exists($profiler, 'getProfiles')) {
                    $this->collectFromProfiler($profiler);
                }
            }
        } catch (Exception $e) {
            // Continue silently
        }
    }

    private function getConnectionParams($adapter): array
    {
        try {
            if (method_exists($adapter, 'getDriver')) {
                $driver = $adapter->getDriver();
                if (method_exists($driver, 'getConnection')) {
                    $connection = $driver->getConnection();
                    if (method_exists($connection, 'getConnectionParameters')) {
                        $params = $connection->getConnectionParameters();
                        unset($params['password'], $params['passwd']);
                        return $params;
                    }
                }
            }
        } catch (Exception $e) {
            // Continue silently
        }

        return [];
    }

    private function collectFromProfiler($profiler): void
    {
        try {
            $profiles = $profiler->getProfiles();
            foreach ($profiles as $profile) {
                $this->addQuery([
                    'sql'           => $profile->getSql(),
                    'params'        => $profile->getParams() ?? [],
                    'duration'      => $profile->getElapsedTime() * 1000,
                    'memory'        => 0,
                    'is_success'    => true,
                    'error_code'    => null,
                    'error_message' => null,
                ]);
            }
        } catch (Exception $e) {
            // Continue silently
        }
    }
}
