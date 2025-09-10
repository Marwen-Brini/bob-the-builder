<?php

namespace Bob\Concerns;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Bob\Logging\QueryLogger;

/**
 * Trait for classes that need query logging capabilities
 */
trait LogsQueries
{
    use LoggerAwareTrait;

    /**
     * The query logger instance
     */
    protected ?QueryLogger $queryLogger = null;

    /**
     * Whether query logging is enabled
     */
    protected bool $loggingEnabled = false;

    /**
     * Set the logger instance
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        
        // If we have a query logger, update its logger too
        if ($this->queryLogger) {
            $this->queryLogger->setLogger($logger);
        }
    }

    /**
     * Get or create the query logger instance
     */
    public function getQueryLogger(): QueryLogger
    {
        if (!$this->queryLogger) {
            $this->queryLogger = new QueryLogger($this->logger ?? null);
            $this->queryLogger->setEnabled($this->loggingEnabled);
        }

        return $this->queryLogger;
    }

    /**
     * Set the query logger instance
     */
    public function setQueryLogger(QueryLogger $queryLogger): void
    {
        $this->queryLogger = $queryLogger;
        
        // If we have a PSR-3 logger, share it with the query logger
        if (isset($this->logger)) {
            $queryLogger->setLogger($this->logger);
        }
    }

    /**
     * Enable query logging
     */
    public function enableQueryLog(): void
    {
        $this->loggingEnabled = true;
        $this->getQueryLogger()->setEnabled(true);
    }

    /**
     * Disable query logging
     */
    public function disableQueryLog(): void
    {
        $this->loggingEnabled = false;
        if ($this->queryLogger) {
            $this->queryLogger->setEnabled(false);
        }
    }

    /**
     * Check if query logging is enabled
     */
    public function isLoggingEnabled(): bool
    {
        return $this->loggingEnabled;
    }

    /**
     * Get the query log
     */
    public function getQueryLog(): array
    {
        return $this->getQueryLogger()->getQueryLog();
    }

    /**
     * Clear the query log
     */
    public function clearQueryLog(): void
    {
        if ($this->queryLogger) {
            $this->queryLogger->clearQueryLog();
        }
    }

    /**
     * Get query statistics
     */
    public function getQueryStatistics(): array
    {
        return $this->getQueryLogger()->getStatistics();
    }

    /**
     * Log a query execution
     */
    public function logQuery(string $query, array $bindings = [], ?float $time = null): void
    {
        if ($this->loggingEnabled) {
            $this->getQueryLogger()->logQuery($query, $bindings, $time);
        }
    }

    /**
     * Log a query error
     */
    protected function logQueryError(string $query, array $bindings, \Exception $exception): void
    {
        if ($this->loggingEnabled) {
            $this->getQueryLogger()->logQueryError($query, $bindings, $exception);
        }
    }

    /**
     * Log a transaction event
     */
    protected function logTransaction(string $event, ?string $savepoint = null): void
    {
        if ($this->loggingEnabled) {
            $this->getQueryLogger()->logTransaction($event, $savepoint);
        }
    }

    /**
     * Log a connection event
     */
    protected function logConnection(string $event, array $config = []): void
    {
        if ($this->loggingEnabled) {
            $this->getQueryLogger()->logConnection($event, $config);
        }
    }
}