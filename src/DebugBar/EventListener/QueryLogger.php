<?php

declare(strict_types=1);

namespace LaminasMicroscope\DebugBar\EventListener;

use Laminas\EventManager\AbstractListenerAggregate; // Corrected namespace
use Laminas\EventManager\EventManagerInterface; // Corrected namespace
use Laminas\EventManager\Event; // Corrected namespace
use LaminasMicroscope\DebugBar\Collectors\PDOCollector;

class QueryLogger extends AbstractListenerAggregate
{
    private PDOCollector $collector;

    public function __construct(PDOCollector $collector)
    {
        $this->collector = $collector;
    }

    public function attach(EventManagerInterface $events, $priority = 1): void // Corrected namespace
    {
        // Listen for database query events
        $this->listeners[] = $events->attach('db.query.start', [$this, 'onQueryStart'], $priority);
        $this->listeners[] = $events->attach('db.query.end', [$this, 'onQueryEnd'], $priority);
        $this->listeners[] = $events->attach('db.query.error', [$this, 'onQueryError'], $priority);
    }

    public function onQueryStart(Event $e): void // Corrected namespace
    {
        $params = $e->getParams();
        $queryId = $params['queryId'] ?? uniqid();

        // Store start time
        $e->setParam('startTime', microtime(true));
        $e->setParam('queryId', $queryId);
    }

    public function onQueryEnd(Event $e): void // Corrected namespace
    {
        $params = $e->getParams();
        $startTime = $params['startTime'] ?? microtime(true);
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->collector->addQuery([
            'sql' => $params['sql'] ?? 'Unknown query',
            'params' => $params['parameters'] ?? [],
            'duration' => $duration,
            'memory' => memory_get_usage(true),
            'is_success' => true,
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    public function onQueryError(Event $e): void // Corrected namespace
    {
        $params = $e->getParams();
        $startTime = $params['startTime'] ?? microtime(true);
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $this->collector->addQuery([
            'sql' => $params['sql'] ?? 'Unknown query',
            'params' => $params['parameters'] ?? [],
            'duration' => $duration,
            'memory' => memory_get_usage(true),
            'is_success' => false,
            'error_code' => $params['errorCode'] ?? 'unknown',
            'error_message' => $params['errorMessage'] ?? 'Unknown error',
        ]);
    }
}
