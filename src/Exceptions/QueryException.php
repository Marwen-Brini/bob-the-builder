<?php

declare(strict_types=1);

namespace Bob\Exceptions;

use Exception;
use Throwable;

class QueryException extends Exception
{
    protected string $sql = '';

    protected array $bindings = [];

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
     * Format the SQL error message.
     */
    public static function formatMessage(string $sql, array $bindings, ?Throwable $previous = null): string
    {
        $message = $previous ? $previous->getMessage() : 'Database query error';

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
