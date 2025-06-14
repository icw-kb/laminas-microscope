<?php

declare(strict_types=1);

namespace LaminasMicroscope\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Exception;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\CollectorInterface;
use Throwable;

use function array_keys;
use function count;
use function file_get_contents;
use function function_exists;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function range;
use function spl_object_id;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function ucwords;

use const JSON_ERROR_NONE;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

class LaminasRequestCollector extends DataCollector implements Renderable, CollectorInterface
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
            'request'    => [
                'method'   => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'uri'      => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
                'scheme'   => ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http',
                'host'     => $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'unknown',
            ],
            'headers'    => $this->formatArray($this->getHeaders()),
            'parameters' => [
                'GET'   => $this->formatArray($_GET),
                'POST'  => $this->formatArray($this->sanitizePostData($_POST)),
                'route' => $this->formatArray($this->getRouteData()),
            ],
            'cookies'    => $this->formatArray($_COOKIE),
            'server'     => $this->formatArray($this->getServerData()),
            'response'   => $this->formatArray($this->getResponseData()),
        ];

        // Add request body for non-POST requests
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ! empty($contentType)) {
            $data['body'] = $this->getRequestBody();
        }

        return $this->makeJavaScriptSafe($data);
    }

    public function getName(): string
    {
        return 'request';
    }

    public function getWidgets(): array
    {
        return [
            'request'       => [
                'icon'    => 'globe',
                'widget'  => 'PhpDebugBar.Widgets.VariableListWidget',
                'map'     => 'request',
                'default' => '{}',
            ],
            'request:badge' => [
                'map'     => 'request.response.status_code',
                'default' => 0,
            ],
        ];
    }

    private function getHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }

    private function sanitizePostData(array $post): array
    {
        $sensitiveKeys = ['password', 'passwd', 'pwd', 'secret', 'token'];

        foreach ($post as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys)) {
                $post[$key] = '*** HIDDEN ***';
            } elseif (is_array($value)) {
                $post[$key] = $this->sanitizePostData($value);
            }
        }

        return $post;
    }

    private function getRequestBody(): array
    {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return ['empty' => true];
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Try to parse JSON
        if (strpos($contentType, 'application/json') !== false) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'type'   => 'json',
                    'parsed' => $this->formatArray($decoded),
                    'raw'    => $input,
                ];
            }
        }

        // Return raw for other content types
        return [
            'type'         => 'raw',
            'content_type' => $contentType,
            'size'         => strlen($input) . ' bytes',
            'preview'      => substr($input, 0, 1000) . (strlen($input) > 1000 ? '...' : ''),
        ];
    }

    private function getServerData(): array
    {
        $relevantKeys = [
            'SERVER_SOFTWARE',
            'REQUEST_METHOD',
            'REQUEST_URI',
            'QUERY_STRING',
            'HTTP_HOST',
            'HTTP_USER_AGENT',
            'HTTP_ACCEPT',
            'HTTP_ACCEPT_LANGUAGE',
            'HTTP_ACCEPT_ENCODING',
            'HTTP_CONNECTION',
            'HTTPS',
            'REMOTE_ADDR',
            'REMOTE_HOST',
            'REMOTE_PORT',
            'SCRIPT_NAME',
            'SCRIPT_FILENAME',
            'SERVER_ADMIN',
            'SERVER_PORT',
            'SERVER_SIGNATURE',
            'REQUEST_TIME',
            'REQUEST_TIME_FLOAT',
            'CONTENT_TYPE',
            'CONTENT_LENGTH',
        ];

        $serverData = [];
        foreach ($relevantKeys as $key) {
            if (isset($_SERVER[$key])) {
                $serverData[$key] = $_SERVER[$key];
            }
        }

        return $serverData;
    }

    private function getRouteData(): array
    {
        try {
            if ($this->serviceManager->has('Application')) {
                $application = $this->serviceManager->get('Application');
                $mvcEvent    = $application->getMvcEvent();

                if ($mvcEvent) {
                    $routeMatch = $mvcEvent->getRouteMatch();
                    if ($routeMatch) {
                        return [
                            'matched_route_name' => $routeMatch->getMatchedRouteName(),
                            'controller'         => $routeMatch->getParam('controller'),
                            'action'             => $routeMatch->getParam('action'),
                            'params'             => $this->formatArray($routeMatch->getParams()),
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            return ['error' => 'Could not retrieve route data: ' . $e->getMessage()];
        }

        return ['status' => 'No route data available'];
    }

    private function getResponseData(): array
    {
        try {
            if ($this->serviceManager->has('Application')) {
                $application = $this->serviceManager->get('Application');
                $mvcEvent    = $application->getMvcEvent();

                if ($mvcEvent) {
                    $response = $mvcEvent->getResponse();
                    if ($response) {
                        $headers = [];
                        foreach ($response->getHeaders() as $header) {
                            $headers[$header->getFieldName()] = $header->getFieldValue();
                        }

                        return [
                            'status_code'    => $response->getStatusCode(),
                            'reason_phrase'  => $response->getReasonPhrase(),
                            'headers'        => $this->formatArray($headers),
                            'content_length' => strlen($response->getContent()),
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            return ['error' => 'Could not retrieve response data: ' . $e->getMessage()];
        }

        return ['status' => 'No response data available'];
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
                
                // For VariableListWidget, return flat strings, not nested objects
                // Ensure we have a non-empty string result
                if (is_string($result) && trim($result) !== '') {
                    return $result;
                } else {
                    return '[OBJECT: ' . $data::class . ']';
                }
            } catch (Throwable $e) {
                unset($visited[$objectId]);
                return '[OBJECT: ' . $data::class . ']';
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
                    
                    // For VariableListWidget, return flat strings, not nested objects
                    if (is_string($result) && trim($result) !== '') {
                        $formatted[$key] = $result;
                    } else {
                        $formatted[$key] = '[OBJECT: ' . $value::class . ']';
                    }
                } catch (Throwable $e) {
                    $formatted[$key] = '[OBJECT: ' . $value::class . ']';
                }
                unset($visited[$objectId]);
            } elseif (is_array($value)) {
                // For nested arrays, check if they should be displayed as a single value or as a list
                $nestedFormatted = $this->formatArray($value, $depth + 1, $maxDepth, $visited);
                
                // If the array is associative and has many items, convert to string
                if (count($value) > 5 && $this->isAssociativeArray($value)) {
                    $jsonResult = json_encode($nestedFormatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    // Ensure JSON encoding succeeded
                    if ($jsonResult !== false) {
                        $formatted[$key] = $jsonResult;
                    } else {
                        $formatted[$key] = '[COMPLEX ARRAY: ' . count($value) . ' items]';
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
}
