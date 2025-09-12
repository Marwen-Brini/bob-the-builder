<?php

namespace Bob\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Query logger that implements PSR-3 LoggerInterface
 * Provides specialized logging for database queries and operations
 */
class QueryLogger implements LoggerInterface
{
    /**
     * The underlying PSR-3 logger instance
     */
    protected ?LoggerInterface $logger;

    /**
     * Whether query logging is enabled
     */
    protected bool $enabled = true;

    /**
     * Query log storage when no logger is provided
     */
    protected array $queryLog = [];

    /**
     * Maximum number of queries to keep in memory
     */
    protected int $maxQueryLog = 100;

    /**
     * Whether to log query bindings
     */
    protected bool $logBindings = true;

    /**
     * Whether to log execution time
     */
    protected bool $logTime = true;

    /**
     * Slow query threshold in milliseconds
     */
    protected float $slowQueryThreshold = 1000;

    /**
     * Create a new query logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Set the underlying PSR-3 logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Enable or disable query logging
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Enable query logging
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable query logging
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check if logging is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set whether to log query bindings
     */
    public function setLogBindings(bool $logBindings): void
    {
        $this->logBindings = $logBindings;
    }

    /**
     * Set whether to log execution time
     */
    public function setLogTime(bool $logTime): void
    {
        $this->logTime = $logTime;
    }

    /**
     * Set slow query threshold in milliseconds
     */
    public function setSlowQueryThreshold(float $threshold): void
    {
        $this->slowQueryThreshold = $threshold;
    }

    /**
     * Log a query execution
     */
    public function logQuery(string $query, array $bindings = [], ?float $time = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $context = [
            'query' => $query,
        ];

        if ($this->logBindings && ! empty($bindings)) {
            $context['bindings'] = $bindings;
        }

        if ($this->logTime && $time !== null) {
            $context['time'] = round($time, 2).'ms';
        }

        // Determine log level based on execution time
        $level = LogLevel::DEBUG;
        if ($time !== null && $time > $this->slowQueryThreshold) {
            $level = LogLevel::WARNING;
            $context['slow_query'] = true;
        }

        // Always store in internal query log
        $this->addToQueryLog($context);

        // Also log to PSR-3 logger if available
        if ($this->logger) {
            $message = $time !== null && $time > $this->slowQueryThreshold
                ? 'Slow query detected'
                : 'Query executed';

            $this->log($level, $message, $context);
        }
    }

    /**
     * Log a failed query
     */
    public function logQueryError(string $query, array $bindings, \Exception $exception): void
    {
        if (! $this->enabled) {
            return;
        }

        $context = [
            'query' => $query,
            'bindings' => $bindings,
            'error' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];

        $this->error('Query execution failed', $context);
    }

    /**
     * Log a transaction event
     */
    public function logTransaction(string $event, ?string $savepoint = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $context = ['event' => $event];
        if ($savepoint) {
            $context['savepoint'] = $savepoint;
        }

        // Store in internal query log
        $this->addToQueryLog([
            'type' => 'transaction',
            'event' => $event,
            'savepoint' => $savepoint,
        ]);

        // Also log to PSR logger
        $this->info('Transaction '.$event, $context);
    }

    /**
     * Log a connection event
     */
    public function logConnection(string $event, array $config = []): void
    {
        if (! $this->enabled) {
            return;
        }

        // Remove sensitive information
        $safeConfig = $config;
        unset($safeConfig['password']);

        // Store in internal query log
        $this->addToQueryLog([
            'type' => 'connection',
            'event' => $event,
            'config' => $safeConfig,
        ]);

        // Also log to PSR logger
        $this->info('Database connection '.$event, [
            'event' => $event,
            'config' => $safeConfig,
        ]);
    }

    /**
     * Add a query to the internal log
     */
    protected function addToQueryLog(array $query): void
    {
        $this->queryLog[] = $query;

        // Keep only the last N queries
        if (count($this->queryLog) > $this->maxQueryLog) {
            array_shift($this->queryLog);
        }
    }

    /**
     * Get the query log
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Get query statistics
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_queries' => count($this->queryLog),
            'total_time' => 0,
            'average_time' => 0,
            'slow_queries' => 0,
            'queries_by_type' => [],
        ];

        foreach ($this->queryLog as $query) {
            // Calculate time statistics
            if (isset($query['time'])) {
                $time = (float) str_replace('ms', '', $query['time']);
                $stats['total_time'] += $time;

                if ($time > $this->slowQueryThreshold) {
                    $stats['slow_queries']++;
                }
            }

            // Count query types
            if (isset($query['query']) && $query['query'] !== null) {
                $type = $this->getQueryType($query['query']);
                if (! isset($stats['queries_by_type'][$type])) {
                    $stats['queries_by_type'][$type] = 0;
                }
                $stats['queries_by_type'][$type]++;
            }
        }

        if ($stats['total_queries'] > 0) {
            $stats['average_time'] = round($stats['total_time'] / $stats['total_queries'], 2);
        }

        $stats['total_time'] = round($stats['total_time'], 2).'ms';
        $stats['average_time'] .= 'ms';

        return $stats;
    }

    /**
     * Determine the type of a query
     */
    protected function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));

        if (strpos($query, 'SELECT') === 0) {
            return 'SELECT';
        } elseif (strpos($query, 'INSERT') === 0) {
            return 'INSERT';
        } elseif (strpos($query, 'UPDATE') === 0) {
            return 'UPDATE';
        } elseif (strpos($query, 'DELETE') === 0) {
            return 'DELETE';
        } elseif (strpos($query, 'CREATE') === 0) {
            return 'CREATE';
        } elseif (strpos($query, 'DROP') === 0) {
            return 'DROP';
        } elseif (strpos($query, 'ALTER') === 0) {
            return 'ALTER';
        }

        return 'OTHER';
    }

    // PSR-3 LoggerInterface methods

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger && $this->enabled) {
            $this->logger->emergency($message, $context);
        }
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger && $this->enabled) {
            $this->logger->alert($message, $context);
        }
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger && $this->enabled) {
            $this->logger->critical($message, $context);
        }
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger && $this->enabled) {
            $this->logger->error($message, $context);
        } elseif ($this->enabled) {
            $this->addToQueryLog(['level' => 'error', 'message' => $message, 'context' => $context]);
        }
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger && $this->enabled) {
            $this->logger->warning($message, $context);
        } elseif ($this->enabled) {
            $this->addToQueryLog(['level' => 'warning', 'message' => $message, 'context' => $context]);
        }
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger && $this->enabled) {
            $this->logger->notice($message, $context);
        }
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger && $this->enabled) {
            $this->logger->info($message, $context);
        } elseif ($this->enabled) {
            $this->addToQueryLog(['level' => 'info', 'message' => $message, 'context' => $context]);
        }
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger && $this->enabled) {
            $this->logger->debug($message, $context);
        } elseif ($this->enabled) {
            $this->addToQueryLog(['level' => 'debug', 'message' => $message, 'context' => $context]);
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($this->logger && $this->enabled) {
            $this->logger->log($level, $message, $context);
        } elseif ($this->enabled) {
            $this->addToQueryLog(['level' => $level, 'message' => $message, 'context' => $context]);
        }
    }
}
