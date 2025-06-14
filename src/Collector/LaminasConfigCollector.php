<?php

declare(strict_types=1);

namespace LaminasMicroscope\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Exception;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\CollectorInterface;
use ReflectionClass;
use Throwable;

use function array_keys;
use function count;
use function date_default_timezone_get;
use function dirname;
use function error_reporting;
use function ini_get;
use function is_array;
use function is_object;
use function is_string;
use function json_encode;
use function range;
use function spl_object_id;
use function strpos;
use function strtolower;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const PHP_SAPI;
use const PHP_VERSION;

class LaminasConfigCollector extends DataCollector implements Renderable, CollectorInterface
{
    use JavaScriptSafeTrait;
    private ServiceManager $serviceManager;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function collect(): array
    {
        $data = [
            'application_config' => $this->formatArray($this->getApplicationConfig()),
            'module_config'      => $this->formatArray($this->getModuleConfig()),
            'service_manager'    => $this->formatArray($this->getServiceManagerConfig()),
            'php_config'         => $this->formatArray($this->getPhpConfig()),
            'environment'        => $this->formatArray($this->getEnvironmentData()),
        ];

        return [
            'config' => $this->makeJavaScriptSafe($data),
            'count'  => count($data),
        ];
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
                'widget'  => 'PhpDebugBar.Widgets.VariableListWidget',
                'map'     => 'config',
                'default' => '{}',
            ],
            'config:badge' => [
                'map'     => 'config.count',
                'default' => 0,
            ],
        ];
    }

    private function getApplicationConfig(): array
    {
        try {
            $config = $this->serviceManager->get('config');
            return $this->sanitizeConfig($config);
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

    /**
     * @param object $module
     */
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

    private function recursiveSanitize($data, array $sensitiveKeys, int $depth = 0, int $maxDepth = 10): array
    {
        // Prevent infinite recursion
        if ($depth > $maxDepth) {
            return ['[MAX DEPTH REACHED]'];
        }

        // Ensure we have an array to work with
        if (! is_array($data)) {
            return (array) $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->recursiveSanitize($value, $sensitiveKeys, $depth + 1, $maxDepth);
            } elseif (is_string($key) && $this->isSensitiveKey($key, $sensitiveKeys)) {
                $result[$key] = '*** HIDDEN ***';
            } else {
                // Let formatArray() handle objects and other data types
                $result[$key] = $value;
            }
        }

        return $result;
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
     * Format data recursively for VariableListWidget compatibility
     * Ensures all values are either primitives, arrays, or objects with a 'value' property
     *
     * @param mixed $data
     * @param int $depth Current recursion depth
     * @param int $maxDepth Maximum recursion depth
     * @param array $visited Already visited objects to prevent circular references
     * @return mixed
     */
    private function formatArray($data, int $depth = 0, int $maxDepth = 10, array &$visited = [])
    {
        // Prevent infinite recursion
        if ($depth > $maxDepth) {
            return ['value' => '[MAX DEPTH REACHED]'];
        }

        if (is_object($data)) {
            // Check for circular references
            $objectId = spl_object_id($data);
            if (isset($visited[$objectId])) {
                return ['value' => '[CIRCULAR REFERENCE]'];
            }
            $visited[$objectId] = true;

            // Format objects using the DataFormatter but wrap in value object
            try {
                $result = $this->getDataFormatter()->formatVar($data);
                unset($visited[$objectId]);
                
                // Always wrap objects in value property for VariableListWidget
                // Ensure we have a non-empty string result
                if (is_string($result) && trim($result) !== '') {
                    return ['value' => $result];
                } else {
                    return ['value' => '[OBJECT: ' . $data::class . ']'];
                }
            } catch (Throwable $e) {
                unset($visited[$objectId]);
                return ['value' => '[OBJECT: ' . $data::class . ']'];
            }
        }

        if (! is_array($data)) {
            // Return scalar values as-is (VariableListWidget handles these correctly)
            return $data;
        }

        $formatted = [];
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                // Check for circular references
                $objectId = spl_object_id($value);
                if (isset($visited[$objectId])) {
                    $formatted[$key] = ['value' => '[CIRCULAR REFERENCE]'];
                    continue;
                }
                $visited[$objectId] = true;

                try {
                    $result = $this->getDataFormatter()->formatVar($value);
                    
                    // Always wrap objects in value property for VariableListWidget
                    if (is_string($result) && trim($result) !== '') {
                        $formatted[$key] = ['value' => $result];
                    } else {
                        $formatted[$key] = ['value' => '[OBJECT: ' . $value::class . ']'];
                    }
                } catch (Throwable $e) {
                    $formatted[$key] = ['value' => '[OBJECT: ' . $value::class . ']'];
                }
                unset($visited[$objectId]);
            } elseif (is_array($value)) {
                // For nested arrays, check if they should be displayed as a single value or as a list
                $nestedFormatted = $this->formatArray($value, $depth + 1, $maxDepth, $visited);
                // If the array is associative and has many items, wrap it as a value object
                if (count($value) > 5 && $this->isAssociativeArray($value)) {
                    $jsonResult = json_encode($nestedFormatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    // Ensure JSON encoding succeeded
                    if ($jsonResult !== false) {
                        $formatted[$key] = ['value' => $jsonResult];
                    } else {
                        $formatted[$key] = ['value' => '[COMPLEX ARRAY: ' . count($value) . ' items]'];
                    }
                } else {
                    $formatted[$key] = $nestedFormatted;
                }
            } else {
                // Keep scalar values as-is
                $formatted[$key] = $value;
            }
        }

        return $formatted;
    }

    /**
     * Check if an array is associative (has string keys)
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
