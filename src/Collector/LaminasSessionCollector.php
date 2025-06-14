<?php

declare(strict_types=1);

namespace LaminasMicroscope\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\CollectorInterface;
use Throwable;

use function array_keys;
use function array_merge;
use function count;
use function ini_get;
use function is_array;
use function is_object;
use function is_string;
use function json_encode;
use function range;
use function round;
use function serialize;
use function session_cache_expire;
use function session_cache_limiter;
use function session_get_cookie_params;
use function session_id;
use function session_module_name;
use function session_name;
use function session_save_path;
use function session_status;
use function spl_object_id;
use function strlen;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const PHP_SESSION_ACTIVE;
use const PHP_SESSION_DISABLED;
use const PHP_SESSION_NONE;

class LaminasSessionCollector extends DataCollector implements Renderable, CollectorInterface
{
    private ServiceManager $serviceManager;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function collect(): array
    {
        $data = [
            'status'    => $this->getSessionStatus(),
            'handler'   => session_module_name(),
            'save_path' => session_save_path(),
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $data = array_merge($data, [
                'id'            => session_id(),
                'name'          => session_name(),
                'cache_limiter' => session_cache_limiter(),
                'cache_expire'  => session_cache_expire() . ' minutes',
                'cookie_params' => $this->formatCookieParams(session_get_cookie_params()),
                'session_data'  => $this->formatSessionData($_SESSION ?? []),
                'session_size'  => $this->calculateSessionSize($_SESSION ?? []),
                'session_count' => count($_SESSION ?? []),
            ]);

            // Add session configuration
            $data['configuration'] = $this->getSessionConfiguration();
        }

        return $data;
    }

    public function getName(): string
    {
        return 'session';
    }

    public function getWidgets(): array
    {
        return [
            'session'       => [
                'icon'    => 'archive',
                'widget'  => 'PhpDebugBar.Widgets.VariableListWidget',
                'map'     => 'session',
                'default' => '{}',
            ],
            'session:badge' => [
                'map'     => 'session.session_count',
                'default' => 0,
            ],
        ];
    }

    private function getSessionStatus(): string
    {
        switch (session_status()) {
            case PHP_SESSION_DISABLED:
                return 'Disabled';
            case PHP_SESSION_NONE:
                return 'None (not started)';
            case PHP_SESSION_ACTIVE:
                return 'Active';
            default:
                return 'Unknown';
        }
    }

    private function formatCookieParams(array $params): array
    {
        return [
            'lifetime' => $params['lifetime'] . ' seconds',
            'path'     => $params['path'],
            'domain'   => $params['domain'] ?: '(not set)',
            'secure'   => $params['secure'] ? 'Yes' : 'No',
            'httponly' => $params['httponly'] ? 'Yes' : 'No',
            'samesite' => $params['samesite'] ?? 'Not set',
        ];
    }

    /**
     * @param array $data
     * @return mixed
     */
    private function formatSessionData(array $data)
    {
        return $this->formatArray($data);
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

    private function calculateSessionSize(array $data): string
    {
        $serialized = serialize($data);
        $bytes      = strlen($serialized);

        if ($bytes < 1024) {
            return $bytes . ' bytes';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
    }

    private function getSessionConfiguration(): array
    {
        $config = [];

        // Common session directives
        $directives = [
            'session.auto_start',
            'session.cache_expire',
            'session.cookie_domain',
            'session.cookie_httponly',
            'session.cookie_lifetime',
            'session.cookie_path',
            'session.cookie_secure',
            'session.gc_divisor',
            'session.gc_maxlifetime',
            'session.gc_probability',
            'session.name',
            'session.referer_check',
            'session.save_handler',
            'session.save_path',
            'session.serialize_handler',
            'session.use_cookies',
            'session.use_only_cookies',
            'session.use_strict_mode',
            'session.use_trans_sid',
        ];

        foreach ($directives as $directive) {
            $value = ini_get($directive);
            if ($value !== false) {
                $config[$directive] = $value;
            }
        }

        return $config;
    }
}
