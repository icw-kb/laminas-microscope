<?php

declare(strict_types=1);

namespace LaminasMicroscope\DebugBar;

use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\TimeDataCollector;
use Exception;
use LaminasMicroscope\DebugBar\Collectors\LaminasConfigCollector;
use LaminasMicroscope\DebugBar\Collectors\LaminasRequestCollector;
use LaminasMicroscope\DebugBar\Collectors\PDOCollector;
use LaminasMicroscope\Exception\ConfigurationException;
use Psr\Container\ContainerInterface;

use function array_keys;
use function class_exists;
use function error_log;

class CollectorFactory
{
    private ContainerInterface $container;
    private array $collectorMapping;

    public function __construct(ContainerInterface $container, array $collectorMapping = [])
    {
        $this->container = $container;
        $this->collectorMapping = $collectorMapping;
    }

    /**
     * Create a collector instance
     *
     * @param string $name The collector name
     * @throws ConfigurationException
     */
    public function create(string $name): ?DataCollectorInterface
    {
        if (! isset($this->collectorMapping[$name])) {
            return null;
        }

        $config = $this->collectorMapping[$name];

        try {
            if ($config['factory'] === 'direct') {
                return $this->createDirectCollector($config);
            } elseif ($config['factory'] === 'service') {
                return $this->createServiceCollector($config);
            }

            throw new ConfigurationException("Unknown factory type: {$config['factory']}");
        } catch (Exception $e) {
            // Re-throw configuration exceptions
            if ($e instanceof ConfigurationException) {
                throw $e;
            }

            // Log PDO errors specifically
            if ($name === 'pdo') {
                error_log("LaminasMicroscope: PDO collector initialization failed - " . $e->getMessage());
            }

            return null;
        }
    }

    /**
     * Get collector configuration
     *
     * @return array|null
     */
    public function getCollectorConfig(string $name): ?array
    {
        return $this->collectorMapping[$name] ?? null;
    }

    /**
     * Get all available collector names
     *
     * @return array
     */
    public function getAvailableCollectors(): array
    {
        return array_keys($this->collectorMapping);
    }

    /**
     * Create a collector via direct instantiation
     *
     * @param array $config
     * @throws Exception
     */
    private function createDirectCollector(array $config): DataCollectorInterface
    {
        $className = $config['class'];

        if (! class_exists($className)) {
            throw new ConfigurationException("Collector class not found: $className");
        }

        return new $className();
    }

    /**
     * Create a collector from the service container
     *
     * @param array $config
     * @throws Exception
     */
    private function createServiceCollector(array $config): ?DataCollectorInterface
    {
        $serviceName = $config['service_name'] ?? $config['class'];

        if (! $this->container->has($serviceName)) {
            return null;
        }

        $collector = $this->container->get($serviceName);

        if (! $collector instanceof DataCollectorInterface) {
            throw new ConfigurationException(
                "Service '$serviceName' does not implement DataCollectorInterface"
            );
        }

        return $collector;
    }
}
