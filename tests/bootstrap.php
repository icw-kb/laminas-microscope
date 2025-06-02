<?php

declare(strict_types=1);

// Setup autoloading
require_once __DIR__ . '/../vendor/autoload.php';

// Define application constants
if (!defined('APPLICATION_PATH')) {
    define('APPLICATION_PATH', realpath(__DIR__ . '/../'));
}

// Setup test environment
$_ENV['APPLICATION_ENV'] = 'testing';
putenv('APPLICATION_ENV=testing');

// Test utilities class - Global namespace
class TestHelper
{
    public static function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/laminas-microscope-test-' . uniqid();
        mkdir($tempDir, 0777, true);
        return $tempDir;
    }

    public static function cleanupTempDir(string $dir): void
    {
        if (is_dir($dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }

            rmdir($dir);
        }
    }

    public static function createMockConfig(array $config = []): array
    {
        return array_merge([
            'laminas_microscope' => [
                'enabled' => true,
                'environment' => 'testing',
                'storage' => [
                    'path' => sys_get_temp_dir() . '/laminas-microscope-test',
                    'retention_days' => 7,
                ],
                'components' => [
                    'whoops' => [
                        'enabled' => true,
                        'show_in_production' => false,
                    ],
                    'debug_bar' => [
                        'enabled' => true,
                        'collectors' => ['time', 'memory', 'pdo'],
                    ],
                    'microscope' => [
                        'enabled' => true,
                        'auto_analyze' => false,
                    ],
                ],
            ],
        ], $config);
    }

    public static function createMockServiceManager(): object
    {
        return new class {
            private array $services = [];
            
            public function get(string $name): mixed
            {
                return $this->services[$name] ?? null;
            }
            
            public function set(string $name, mixed $service): void
            {
                $this->services[$name] = $service;
            }
            
            public function has(string $name): bool
            {
                return isset($this->services[$name]);
            }
        };
    }

    public static function assertArrayStructure(array $expected, array $actual, string $message = ''): void
    {
        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                if (!isset($actual[$key]) || !is_array($actual[$key])) {
                    throw new \PHPUnit\Framework\AssertionFailedError(
                        "Key '{$key}' should be an array. {$message}"
                    );
                }
                self::assertArrayStructure($value, $actual[$key], $message);
            } else {
                if (!array_key_exists($key, $actual)) {
                    throw new \PHPUnit\Framework\AssertionFailedError(
                        "Key '{$key}' is missing from array. {$message}"
                    );
                }
            }
        }
    }

    public static function createMockRequest(string $method = 'GET', string $uri = '/', array $headers = []): object
    {
        return new class($method, $uri, $headers) {
            public function __construct(
                private string $method,
                private string $uri,
                private array $headers
            ) {}

            public function getMethod(): string
            {
                return $this->method;
            }

            public function getUri(): string
            {
                return $this->uri;
            }

            public function getHeaders(): array
            {
                return $this->headers;
            }

            public function getHeader(string $name): ?string
            {
                return $this->headers[$name] ?? null;
            }
        };
    }

    public static function createMockResponse(int $statusCode = 200, array $headers = [], string $body = ''): object
    {
        return new class($statusCode, $headers, $body) {
            public function __construct(
                private int $statusCode,
                private array $headers,
                private string $body
            ) {}

            public function getStatusCode(): int
            {
                return $this->statusCode;
            }

            public function getHeaders(): array
            {
                return $this->headers;
            }

            public function getBody(): string
            {
                return $this->body;
            }
        };
    }
}

// Global test functions
function createTempFile(string $content = '', string $extension = 'tmp'): string
{
    $tempFile = tempnam(sys_get_temp_dir(), 'laminas_microscope_test_') . '.' . $extension;
    file_put_contents($tempFile, $content);
    return $tempFile;
}

function cleanupTempFile(string $file): void
{
    if (file_exists($file)) {
        unlink($file);
    }
}
