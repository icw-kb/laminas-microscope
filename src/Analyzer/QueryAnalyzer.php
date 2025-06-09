<?php

declare(strict_types=1);

namespace LaminasMicroscope\Analyzer;

class QueryAnalyzer
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'slow_threshold' => 100,
            'very_slow_threshold' => 500,
            'n_plus_one_threshold' => 3,
            'duplicate_threshold' => 2,
        ], $config);
    }
    
    public function analyzeQuery(string $sql, array $params = [], float $duration = 0): array
    {
        return [
            'type' => $this->detectQueryType($sql),
            'complexity' => $this->calculateComplexity($sql),
            'tables' => $this->extractTables($sql),
            'joins' => $this->analyzeJoins($sql),
            'performance_flags' => $this->analyzePerformance($sql, $duration),
            'optimization_hints' => $this->generateOptimizationHints($sql),
        ];
    }
    
    private function detectQueryType(string $sql): string
    {
        $sql = trim(strtoupper($sql));
        
        if (preg_match('/^SELECT/', $sql)) return 'SELECT';
        if (preg_match('/^INSERT/', $sql)) return 'INSERT';
        if (preg_match('/^UPDATE/', $sql)) return 'UPDATE';
        if (preg_match('/^DELETE/', $sql)) return 'DELETE';
        
        return 'OTHER';
    }
    
    private function calculateComplexity(string $sql): int
    {
        $complexity = 0;
        $sql = strtoupper($sql);
        
        $complexity += substr_count($sql, 'JOIN') * 2;
        $complexity += substr_count($sql, 'SUBQUERY') * 3;
        $complexity += substr_count($sql, 'UNION') * 2;
        $complexity += substr_count($sql, 'GROUP BY');
        $complexity += substr_count($sql, 'ORDER BY');
        
        return $complexity;
    }
    
    private function extractTables(string $sql): array
    {
        preg_match_all('/(?:FROM|JOIN|UPDATE|INTO)\s+`?(\w+)`?/i', $sql, $matches);
        return array_unique($matches[1] ?? []);
    }
    
    private function analyzeJoins(string $sql): array
    {
        preg_match_all('/(LEFT|RIGHT|INNER|OUTER)?\s*JOIN\s+`?(\w+)`?/i', $sql, $matches, PREG_SET_ORDER);
        
        $joins = [];
        foreach ($matches as $match) {
            $joins[] = [
                'type' => trim($match[1] ?: 'INNER'),
                'table' => $match[2],
            ];
        }
        
        return $joins;
    }
    
    private function analyzePerformance(string $sql, float $duration): array
    {
        return [
            'is_slow' => $duration > $this->config['slow_threshold'],
            'is_very_slow' => $duration > $this->config['very_slow_threshold'],
            'has_wildcards' => strpos($sql, 'SELECT *') !== false,
            'missing_where' => $this->isMissingWhere($sql),
        ];
    }
    
    private function isMissingWhere(string $sql): bool
    {
        $sql = strtoupper($sql);
        return (strpos($sql, 'SELECT') === 0 || strpos($sql, 'UPDATE') === 0 || strpos($sql, 'DELETE') === 0) 
               && strpos($sql, 'WHERE') === false;
    }
    
    private function generateOptimizationHints(string $sql): array
    {
        $hints = [];
        
        if (strpos($sql, 'SELECT *') !== false) {
            $hints[] = 'Avoid SELECT * - specify only needed columns';
        }
        
        if (preg_match('/WHERE.*LIKE\s+[\'"]%.*%[\'"]/', $sql)) {
            $hints[] = 'Leading wildcards in LIKE prevent index usage';
        }
        
        if (preg_match('/ORDER BY.*RAND\(\)/', $sql)) {
            $hints[] = 'ORDER BY RAND() is very slow on large datasets';
        }
        
        return $hints;
    }
}