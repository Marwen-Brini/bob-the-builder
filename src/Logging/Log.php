<?php

namespace Bob\Logging;

use Bob\Database\Connection;
use Psr\Log\LoggerInterface;

/**
 * Static facade for global logging control
 *
 * Usage:
 *   Log::enable();                    // Enable logging globally
 *   Log::disable();                   // Disable logging globally
 *   Log::setLogger($psrLogger);       // Set PSR-3 logger globally
 *   Log::enableFor($connection);      // Enable for specific connection
 *   Log::disableFor($connection);     // Disable for specific connection
 */
class Log
{
    /**
     * Global logging enabled state
     */
    protected static bool $globalEnabled = false;

    /**
     * Global PSR-3 logger instance
     */
    protected static ?LoggerInterface $globalLogger = null;

    /**
     * Global query logger instance
     */
    protected static ?QueryLogger $globalQueryLogger = null;

    /**
     * Registered connections for automatic logging configuration
     */
    protected static array $connections = [];

    /**
     * Global logging configuration
     */
    protected static array $config = [
        'log_bindings' => true,
        'log_time' => true,
        'slow_query_threshold' => 1000,
        'max_query_log' => 100,
    ];

    /**
     * Enable logging globally
     */
    public static function enable(): void
    {
        self::$globalEnabled = true;

        // Enable logging for all registered connections
        foreach (self::$connections as $connection) {
            $connection->enableQueryLog();
        }

        // Update global query logger if exists
        if (self::$globalQueryLogger) {
            self::$globalQueryLogger->setEnabled(true);
        }
    }

    /**
     * Disable logging globally
     */
    public static function disable(): void
    {
        self::$globalEnabled = false;

        // Disable logging for all registered connections
        foreach (self::$connections as $connection) {
            $connection->disableQueryLog();
        }

        // Update global query logger if exists
        if (self::$globalQueryLogger) {
            self::$globalQueryLogger->setEnabled(false);
        }
    }

    /**
     * Check if logging is enabled globally
     */
    public static function isEnabled(): bool
    {
        return self::$globalEnabled;
    }

    /**
     * Set the global PSR-3 logger
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$globalLogger = $logger;

        // Update global query logger
        if (! self::$globalQueryLogger) {
            self::$globalQueryLogger = new QueryLogger($logger);
        } else {
            self::$globalQueryLogger->setLogger($logger);
        }

        // Update all registered connections
        foreach (self::$connections as $connection) {
            $connection->setLogger($logger);
        }
    }

    /**
     * Clear the global PSR-3 logger
     */
    public static function clearLogger(): void
    {
        self::$globalLogger = null;
        self::$globalQueryLogger = null;
    }

    /**
     * Get the global logger
     */
    public static function getLogger(): ?LoggerInterface
    {
        return self::$globalLogger;
    }

    /**
     * Get or create the global query logger
     */
    public static function getQueryLogger(): QueryLogger
    {
        if (! self::$globalQueryLogger) {
            self::$globalQueryLogger = new QueryLogger(self::$globalLogger);
            self::$globalQueryLogger->setEnabled(self::$globalEnabled);

            // Apply configuration
            self::$globalQueryLogger->setLogBindings(self::$config['log_bindings']);
            self::$globalQueryLogger->setLogTime(self::$config['log_time']);
            self::$globalQueryLogger->setSlowQueryThreshold(self::$config['slow_query_threshold']);
        }

        return self::$globalQueryLogger;
    }

    /**
     * Register a connection for automatic logging configuration
     */
    public static function registerConnection(Connection $connection): void
    {
        // Add to registered connections
        $key = spl_object_hash($connection);
        self::$connections[$key] = $connection;

        // Apply current global settings
        if (self::$globalEnabled) {
            $connection->enableQueryLog();
        }

        if (self::$globalLogger) {
            $connection->setLogger(self::$globalLogger);
        }

        // Share the global query logger
        $connection->setQueryLogger(self::getQueryLogger());
    }

    /**
     * Unregister a connection
     */
    public static function unregisterConnection(Connection $connection): void
    {
        $key = spl_object_hash($connection);
        unset(self::$connections[$key]);
    }

    /**
     * Enable logging for a specific connection
     */
    public static function enableFor(Connection $connection): void
    {
        $connection->enableQueryLog();

        // Register if not already registered
        $key = spl_object_hash($connection);
        if (! isset(self::$connections[$key])) {
            self::registerConnection($connection);
        }
    }

    /**
     * Disable logging for a specific connection
     */
    public static function disableFor(Connection $connection): void
    {
        $connection->disableQueryLog();
    }

    /**
     * Get query log from all connections
     */
    public static function getQueryLog(): array
    {
        // If we have a global query logger, all connections share it
        // so we only need to get logs from there
        if (self::$globalQueryLogger) {
            return self::$globalQueryLogger->getQueryLog();
        }

        // Otherwise collect from all registered connections
        $allQueries = [];
        foreach (self::$connections as $connection) {
            $allQueries = array_merge($allQueries, $connection->getQueryLog()); // @codeCoverageIgnore
        }

        return $allQueries;
    }

    /**
     * Clear query log for all connections
     */
    public static function clearQueryLog(): void
    {
        // Clear global query logger
        if (self::$globalQueryLogger) {
            self::$globalQueryLogger->clearQueryLog();
        }

        // Clear all registered connections
        foreach (self::$connections as $connection) {
            $connection->clearQueryLog();
        }
    }

    /**
     * Get statistics from all connections
     */
    public static function getStatistics(): array
    {
        $stats = [
            'total_queries' => 0,
            'total_time' => 0,
            'average_time' => 0,
            'slow_queries' => 0,
            'queries_by_type' => [],
            'connections' => count(self::$connections),
        ];

        // Collect from global query logger
        if (self::$globalQueryLogger) {
            $globalStats = self::$globalQueryLogger->getStatistics();
            $stats = self::mergeStatistics($stats, $globalStats);
        }

        // Calculate averages
        if ($stats['total_queries'] > 0) {
            $stats['average_time'] = round($stats['total_time'] / $stats['total_queries'], 2).'ms';
        }

        $stats['total_time'] = $stats['total_time'].'ms';

        return $stats;
    }

    /**
     * Configure global logging settings
     */
    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config);

        // Apply to global query logger if exists
        if (self::$globalQueryLogger) {
            if (isset($config['log_bindings'])) {
                self::$globalQueryLogger->setLogBindings($config['log_bindings']);
            }
            if (isset($config['log_time'])) {
                self::$globalQueryLogger->setLogTime($config['log_time']);
            }
            if (isset($config['slow_query_threshold'])) {
                self::$globalQueryLogger->setSlowQueryThreshold($config['slow_query_threshold']);
            }
        }
    }

    /**
     * Log a query manually
     */
    public static function logQuery(string $query, array $bindings = [], ?float $time = null): void
    {
        if (self::$globalEnabled) {
            self::getQueryLogger()->logQuery($query, $bindings, $time);
        }
    }

    /**
     * Log an error manually
     */
    public static function logError(string $message, array $context = []): void
    {
        if (self::$globalEnabled) {
            self::getQueryLogger()->error($message, $context);
        }
    }

    /**
     * Log info manually
     */
    public static function logInfo(string $message, array $context = []): void
    {
        if (self::$globalEnabled) {
            self::getQueryLogger()->info($message, $context);
        }
    }

    /**
     * Reset all global state
     */
    public static function reset(): void
    {
        self::$globalEnabled = false;
        self::$globalLogger = null;
        self::$globalQueryLogger = null;
        self::$connections = [];
        self::$config = [
            'log_bindings' => true,
            'log_time' => true,
            'slow_query_threshold' => 1000,
            'max_query_log' => 100,
        ];
    }

    /**
     * Merge statistics from multiple sources
     */
    protected static function mergeStatistics(array $stats1, array $stats2): array
    {
        $merged = $stats1;

        $merged['total_queries'] += $stats2['total_queries'] ?? 0;

        // Handle time values (remove 'ms' suffix if present)
        $time1 = is_string($stats1['total_time']) ? (float) str_replace('ms', '', $stats1['total_time']) : $stats1['total_time'];
        $time2 = is_string($stats2['total_time']) ? (float) str_replace('ms', '', $stats2['total_time']) : $stats2['total_time'];
        $merged['total_time'] = $time1 + $time2;

        $merged['slow_queries'] += $stats2['slow_queries'] ?? 0;

        // Merge query types
        if (isset($stats2['queries_by_type'])) {
            foreach ($stats2['queries_by_type'] as $type => $count) {
                if (! isset($merged['queries_by_type'][$type])) {
                    $merged['queries_by_type'][$type] = 0;
                }
                $merged['queries_by_type'][$type] += $count;
            }
        }

        return $merged;
    }
}
