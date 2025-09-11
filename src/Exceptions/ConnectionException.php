<?php

declare(strict_types=1);

namespace Bob\Exceptions;

use Exception;
use Throwable;

class ConnectionException extends Exception
{
    protected array $config = [];

    public function __construct(
        string $message = '',
        array $config = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->config = $config;
    }

    /**
     * Create exception for connection failure.
     */
    public static function connectionFailed(array $config, ?Throwable $previous = null): self
    {
        $driver = $config['driver'] ?? 'unknown';
        $host = $config['host'] ?? 'unknown';
        $database = $config['database'] ?? 'unknown';

        $message = sprintf(
            "Failed to connect to %s database '%s' at '%s'",
            $driver,
            $database,
            $host
        );

        if ($previous) {
            $message .= ': '.$previous->getMessage();
        }

        return new static($message, $config, 0, $previous);
    }

    /**
     * Create exception for unsupported driver.
     */
    public static function unsupportedDriver(string $driver): self
    {
        return new static(
            sprintf('Unsupported database driver: %s. Supported drivers are: mysql, pgsql, sqlite', $driver),
            ['driver' => $driver]
        );
    }

    /**
     * Create exception for missing configuration.
     */
    public static function missingConfiguration(string $key): self
    {
        return new static(
            sprintf('Database configuration missing required key: %s', $key),
            ['missing_key' => $key]
        );
    }

    /**
     * Create exception for invalid configuration.
     */
    public static function invalidConfiguration(string $key, string $reason): self
    {
        return new static(
            sprintf("Invalid database configuration for '%s': %s", $key, $reason),
            ['invalid_key' => $key, 'reason' => $reason]
        );
    }

    /**
     * Create exception for transaction errors.
     */
    public static function transactionError(string $operation, ?Throwable $previous = null): self
    {
        $message = sprintf('Transaction %s failed', $operation);

        if ($previous) {
            $message .= ': '.$previous->getMessage();
        }

        return new static($message, ['operation' => $operation], 0, $previous);
    }

    /**
     * Get the database configuration that caused the exception.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
