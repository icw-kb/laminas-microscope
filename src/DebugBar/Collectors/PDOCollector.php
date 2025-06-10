<?php

declare(strict_types=1);

namespace LaminasMicroscope\DebugBar\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\CollectorInterface;
use LaminasMicroscope\Utility\FormatUtility;

use function array_filter;
use function count;
use function method_exists;
use function preg_replace;
use function strtolower;
use function trim;

class PDOCollector extends DataCollector implements Renderable, CollectorInterface
{
    private ServiceManager $serviceManager;
    private array $queries     = [];
    private array $connections = [];
    private float $totalTime   = 0;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
        $this->setupQueryLogging();
    }

    public function collect(): array
    {
        return [
            'nb_statements'            => count($this->queries),
            'nb_failed_statements'     => count(array_filter($this->queries, function ($q) {
                return $q['is_success'] === false;
            })),
            'accumulated_duration'     => $this->totalTime,
            'accumulated_duration_str' => FormatUtility::formatDuration($this->totalTime / 1000), // Convert ms to seconds
            'statements'               => $this->queries,
            'connections'              => $this->connections,
        ];
    }

    public function getName(): string
    {
        return 'pdo';
    }

    public function getWidgets(): array
    {
        return [
            'pdo' => [
                'icon'    => 'database',
                'widget'  => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map'     => 'pdo',
                'default' => '[]',
            ],
            'pdo:badge' => [
                'map'     => 'pdo.nb_statements',
                'default' => 0,
            ],
        ];
    }

    private function setupQueryLogging(): void
    {
        // Hook into Laminas DB adapters only if database configuration exists
        try {
            if ($this->hasDatabaseConfiguration()) {
                $this->hookIntoDbAdapters();
            }
        } catch (Exception $e) {
            // Silently fail if no DB adapters are configured
        }
    }

    private function hookIntoDbAdapters(): void
    {
        try {
            // Try to get common DB adapter service names
            $adapterNames = [Adapter::class, 'db', 'dbAdapter'];

            foreach ($adapterNames as $adapterName) {
                // Use more careful service checking to avoid triggering factories
                try {
                    if ($this->serviceManager->has($adapterName)) {
                        // Check if we can safely get the service
                        $adapter = $this->serviceManager->get($adapterName);
                        $this->hookIntoAdapter($adapter);
                    }
                } catch (Exception $e) {
                    // Skip this adapter if it can't be instantiated
                    // This handles cases where service is registered but config is missing
                    continue;
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

            // Hook into profiler if available
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
                        // Remove sensitive info
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
                    'duration'      => $profile->getElapsedTime() * 1000, // Convert to milliseconds
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

    public function addQuery(array $query): void
    {
        $query['duration_str'] = FormatUtility::formatDuration($query['duration'] / 1000); // Convert ms to seconds
        $query['memory_str']   = FormatUtility::formatBytes($query['memory']);

        // Detect slow queries
        $slowThreshold    = 100; // 100ms
        $query['is_slow'] = $query['duration'] > $slowThreshold;

        // Detect duplicate queries
        $query['is_duplicate'] = $this->isDuplicateQuery($query['sql']);

        $this->queries[]  = $query;
        $this->totalTime += $query['duration'];
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

    private function normalizeSql(string $sql): string
    {
        // Remove parameters and normalize whitespace for duplicate detection
        $sql = preg_replace('/\\\\\\\\?|\\\\\\\\$\\\\\\\\d+|:\\\\\\\\w+/', '?', $sql);
        $sql = preg_replace('/\\\\\\\\s+/', ' ', $sql);
        return trim(strtolower($sql));
    }

    /**
     * Check if database configuration exists to avoid triggering AdapterServiceFactory
     * without proper configuration which causes "Undefined array key 'db'" warnings
     */
    private function hasDatabaseConfiguration(): bool
    {
        try {
            // Get the full config array to check for database configuration
            if ($this->serviceManager->has('config')) {
                $config = $this->serviceManager->get('config');

                // Check for common database configuration keys
                return isset($config['db']) ||
                       isset($config['database']) ||
                       isset($config['databases']) ||
                       isset($config['adapters']);
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}
