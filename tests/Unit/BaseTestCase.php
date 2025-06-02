<?php

declare(strict_types=1);

namespace LaminasMicroscopeTest\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for unit tests
 */
abstract class BaseTestCase extends TestCase
{
    protected array $tempFiles = [];
    protected array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFiles = [];
        $this->tempDirs = [];
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
     * Assert that an array has the expected structure
     */
    protected function assertArrayStructure(array $expected, array $actual, string $message = ''): void
    {
        \TestHelper::assertArrayStructure($expected, $actual, $message);
    }

    /**
     * Get mock configuration for testing
     */
    protected function getMockConfig(array $overrides = []): array
    {
        return \TestHelper::createMockConfig($overrides);
    }
}
