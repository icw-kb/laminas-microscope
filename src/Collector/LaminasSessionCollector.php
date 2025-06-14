<?php

declare(strict_types=1);

namespace LaminasMicroscope\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\CollectorInterface;

use function array_merge;
use function count;
use function ini_get;
use function is_array;
use function is_object;
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
use function strlen;

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
     * Format array data recursively, converting objects to strings
     *
     * @param mixed $data
     * @return mixed
     */
    private function formatArray($data)
    {
        if (is_object($data)) {
            // Format objects using the DataFormatter
            return $this->getDataFormatter()->formatVar($data);
        }

        if (! is_array($data)) {
            // Return scalar values as-is
            return $data;
        }

        $formatted = [];
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                // Use the DataFormatter to properly format objects
                $formatted[$key] = $this->getDataFormatter()->formatVar($value);
            } elseif (is_array($value)) {
                // Recursively format nested arrays
                $formatted[$key] = $this->formatArray($value);
            } else {
                // Keep scalar values as-is
                $formatted[$key] = $value;
            }
        }

        return $formatted;
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
