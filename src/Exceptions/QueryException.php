<?php

declare(strict_types=1);

namespace Bob\Exceptions;

use Exception;
use Throwable;

class QueryException extends Exception
{
    protected string $sql = '';

    protected array $bindings = [];

    /**
     * Whether to include SQL details in exception messages.
     * Set to false in production to prevent information disclosure.
     */
    protected static bool $includeSqlInMessage = true;

    public function __construct(
        string $message = '',
        string $sql = '',
        array $bindings = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->sql = $sql;
        $this->bindings = $bindings;
    }

    /**
     * Enable or disable SQL details in exception messages.
     * Disable in production environments to prevent sensitive data exposure.
     */
    public static function setIncludeSqlInMessage(bool $include): void
    {
        self::$includeSqlInMessage = $include;
    }

    /**
     * Check if SQL details should be included in exception messages.
     */
    public static function shouldIncludeSqlInMessage(): bool
    {
        return self::$includeSqlInMessage;
    }

    /**
     * Format the SQL error message.
     */
    public static function formatMessage(string $sql, array $bindings, ?Throwable $previous = null): string
    {
        $message = $previous ? $previous->getMessage() : 'Database query error';

        if (!self::$includeSqlInMessage) {
            return $message . ' (SQL details hidden - enable debug mode for full query)';
        }

        return sprintf(
            '%s (SQL: %s)',
            $message,
            self::formatSqlWithBindings($sql, $bindings)
        );
    }

    /**
     * Create a new query exception instance.
     */
    public static function fromSqlAndBindings(
        string $sql,
        array $bindings = [],
        ?Throwable $previous = null
    ): self {
        $message = self::formatMessage($sql, $bindings, $previous);

        return new static($message, $sql, $bindings, 0, $previous);
    }

    /**
     * Format SQL with bindings for display.
     */
    protected static function formatSqlWithBindings(string $sql, array $bindings): string
    {
        if (empty($bindings)) {
            return $sql;
        }

        $formatted = $sql;
        foreach ($bindings as $binding) {
            $value = is_string($binding) ? "'{$binding}'" : (string) $binding;
            $formatted = preg_replace('/\?/', $value, $formatted, 1);
        }

        return $formatted;
    }

    /**
     * Get the SQL for the query.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the bindings for the query.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
