<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for integration tests
 */
abstract class BaseIntegrationTestCase extends TestCase
{
    protected array $tempFiles = [];
    protected array $tempDirs = [];
    protected object $serviceManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFiles = [];
        $this->tempDirs = [];
        $this->serviceManager = \TestHelper::createMockServiceManager();
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Clean up temporary directories
        foreach ($this->tempDirs as $dir) {
            \TestHelper::cleanupTempDir($dir);
        }

        parent::tearDown();
    }

    /**
     * Create a temporary file for testing
     */
    protected function createTempFile(string $content = '', string $extension = 'tmp'): string
    {
        $tempFile = createTempFile($content, $extension);
        $this->tempFiles[] = $tempFile;
        return $tempFile;
    }

    /**
     * Create a temporary directory for testing
     */
    protected function createTempDir(): string
    {
        $tempDir = \TestHelper::createTempDir();
        $this->tempDirs[] = $tempDir;
        return $tempDir;
    }

    /**
     * Get mock configuration for testing
     */
    protected function getMockConfig(array $overrides = []): array
    {
        return \TestHelper::createMockConfig($overrides);
    }

    /**
     * Create mock service manager with predefined services
     */
    protected function createServiceManager(array $services = []): object
    {
        $sm = \TestHelper::createMockServiceManager();
        
        foreach ($services as $name => $service) {
            $sm->set($name, $service);
        }
        
        return $sm;
    }

    /**
     * Create mock HTTP request
     */
    protected function createMockRequest(string $method = 'GET', string $uri = '/', array $headers = []): object
    {
        return \TestHelper::createMockRequest($method, $uri, $headers);
    }

    /**
     * Create mock HTTP response
     */
    protected function createMockResponse(int $statusCode = 200, array $headers = [], string $body = ''): object
    {
        return \TestHelper::createMockResponse($statusCode, $headers, $body);
    }
}
