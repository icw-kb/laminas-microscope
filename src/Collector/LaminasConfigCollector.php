<?php

declare(strict_types=1);

namespace LaminasMicroscope\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Exception;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\CollectorInterface;
use ReflectionClass;

use function array_keys;
use function count;
use function date_default_timezone_get;
use function dirname;
use function error_reporting;
use function ini_get;
use function is_array;
use function is_string;
use function strpos;
use function strtolower;

use const PHP_SAPI;
use const PHP_VERSION;

class LaminasConfigCollector extends DataCollector implements Renderable, CollectorInterface
{
    use FormatsArrayTrait;
    private ServiceManager $serviceManager;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function collect(): array
    {
        $data = [
            'application_config' => $this->getApplicationConfig(),
            'module_config'      => $this->getModuleConfig(),
            'service_manager'    => $this->getServiceManagerConfig(),
            'php_config'         => $this->getPhpConfig(),
            'environment'        => $this->getEnvironmentData(),
        ];

        // Flatten the data for KVListWidget - it expects key-value pairs with string values
        $flatData = $this->flattenForWidget($data);
        
        // Add count to the flat data
        $flatData['_count'] = count($data);

        return $flatData;
    }

    public function getName(): string
    {
        return 'config';
    }

    public function getWidgets(): array
    {
        return [
            'config'       => [
                'icon'    => 'cog',
                'widget'  => 'PhpDebugBar.Widgets.KVListWidget',
                'map'     => 'config',
                'default' => '{}',
            ],
            'config:badge' => [
                'map'     => 'config._count',
                'default' => 0,
            ],
        ];
    }

    private function getApplicationConfig(): array
    {
        try {
            $config = $this->serviceManager->get('config');
            return $this->formatLeafValues($this->sanitizeConfig($config));
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getModuleConfig(): array
    {
        try {
            $moduleManager = $this->serviceManager->get('ModuleManager');
            $loadedModules = $moduleManager->getLoadedModules();

            $modules = [];
            foreach ($loadedModules as $name => $module) {
                $modules[$name] = [
                    'class' => $module::class,
                    'path'  => $this->getModulePath($module),
                ];
            }

            return $modules;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getServiceManagerConfig(): array
    {
        try {
            $config = $this->serviceManager->get('config');
            return [
                'services'   => array_keys($config['service_manager']['services'] ?? []),
                'factories'  => array_keys($config['service_manager']['factories'] ?? []),
                'invokables' => array_keys($config['service_manager']['invokables'] ?? []),
                'aliases'    => $config['service_manager']['aliases'] ?? [],
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getPhpConfig(): array
    {
        return [
            'version'            => PHP_VERSION,
            'sapi'               => PHP_SAPI,
            'memory_limit'       => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'error_reporting'    => error_reporting(),
            'display_errors'     => ini_get('display_errors'),
            'log_errors'         => ini_get('log_errors'),
            'error_log'          => ini_get('error_log'),
            'timezone'           => date_default_timezone_get(),
        ];
    }

    private function getEnvironmentData(): array
    {
        return [
            'APPLICATION_ENV' => $_ENV['APPLICATION_ENV'] ?? 'unknown',
            'REQUEST_METHOD'  => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'REQUEST_URI'     => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'HTTP_HOST'       => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        ];
    }

    private function getModulePath($module): string
    {
        try {
            $reflection = new ReflectionClass($module);
            return dirname($reflection->getFileName());
        } catch (Exception $e) {
            return 'unknown';
        }
    }

    private function sanitizeConfig(array $config): array
    {
        // Remove sensitive data
        $sensitiveKeys = ['password', 'secret', 'key', 'token', 'auth'];

        return $this->recursiveSanitize($config, $sensitiveKeys);
    }

    private function recursiveSanitize(array $array, array $sensitiveKeys): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->recursiveSanitize($value, $sensitiveKeys);
            } elseif (is_string($key) && $this->isSensitiveKey($key, $sensitiveKeys)) {
                $array[$key] = '*** HIDDEN ***';
            }
        }

        return $array;
    }

    private function isSensitiveKey(string $key, array $sensitiveKeys): bool
    {
        $key = strtolower($key);
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (strpos($key, $sensitiveKey) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Flatten nested config data for KVListWidget display
     * KVListWidget expects flat key-value pairs with string values
     */
    private function flattenForWidget(array $data, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                // For arrays, show count and flatten important items
                if (empty($value)) {
                    $result[$fullKey] = '(empty array)';
                } elseif (isset($value['error'])) {
                    // Show errors directly
                    $result[$fullKey] = $value['error'];
                } elseif (count($value) <= 10) {
                    // Small arrays - flatten them
                    $flattened = $this->flattenForWidget($value, $fullKey);
                    $result = array_merge($result, $flattened);
                } else {
                    // Large arrays - show summary
                    $result[$fullKey] = '(' . count($value) . ' items) ' . json_encode(array_slice($value, 0, 3, true)) . '...';
                }
            } else {
                // Convert ALL non-array values to strings using our formatting
                $formatted = $this->formatSingleValue($value);
                // Ensure it's definitely a string
                $result[$fullKey] = (string) $formatted;
            }
        }
        
        return $result;
    }
}
