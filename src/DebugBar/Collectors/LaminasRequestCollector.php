<?php

declare(strict_types=1);

namespace LaminasMicroscope\DebugBar\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Exception;
use Laminas\ServiceManager\ServiceManager;
use LaminasMicroscope\Collector\CollectorInterface;

use function function_exists;
use function in_array;
use function is_array;
use function is_string;
use function session_id;
use function session_name;
use function session_status;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function ucwords;

use const PHP_SESSION_ACTIVE;

class LaminasRequestCollector extends DataCollector implements Renderable, CollectorInterface
{
    private ServiceManager $serviceManager;

    public function __construct(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function collect(): array
    {
        return [
            'method'   => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri'      => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'headers'  => $this->getHeaders(),
            'get'      => $_GET,
            'post'     => $this->sanitizePostData($_POST),
            'cookies'  => $_COOKIE,
            'session'  => $this->getSessionData(),
            'server'   => $this->getServerData(),
            'route'    => $this->getRouteData(),
            'response' => $this->getResponseData(),
        ];
    }

    public function getName(): string
    {
        return 'request';
    }

    public function getWidgets(): array
    {
        return [
            'request' => [
                'icon'    => 'globe',
                'widget'  => 'PhpDebugBar.Widgets.VariableListWidget',
                'map'     => 'request',
                'default' => '{}',
            ],
            'request:badge' => [
                'map'     => 'request.status_code',
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

    private function getSessionData(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return ['status' => 'No active session'];
        }

        return [
            'id'   => session_id(),
            'name' => session_name(),
            'data' => $_SESSION ?? [],
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
                            'params'             => $routeMatch->getParams(),
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
                            'headers'        => $headers,
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
}
