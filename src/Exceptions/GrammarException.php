<?php

declare(strict_types=1);

namespace Bob\Exceptions;

use Exception;
use Throwable;

class GrammarException extends Exception
{
    protected string $method = '';
    protected string $grammar = '';

    public function __construct(
        string $message = '',
        string $method = '',
        string $grammar = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->method = $method;
        $this->grammar = $grammar;
    }

    /**
     * Create exception for unsupported method.
     */
    public static function unsupportedMethod(string $method, string $grammar): self
    {
        return new static(
            sprintf("Method '%s' is not supported by %s grammar", $method, $grammar),
            $method,
            $grammar
        );
    }

    /**
     * Create exception for invalid operator.
     */
    public static function invalidOperator(string $operator): self
    {
        return new static(
            sprintf("Invalid SQL operator: %s", $operator),
            '',
            ''
        );
    }

    /**
     * Create exception for compilation errors.
     */
    public static function compilationError(string $component, string $reason): self
    {
        return new static(
            sprintf("Failed to compile %s: %s", $component, $reason),
            $component,
            ''
        );
    }

    /**
     * Create exception for missing component.
     */
    public static function missingComponent(string $component): self
    {
        return new static(
            sprintf("Cannot compile query: missing %s component", $component),
            '',
            ''
        );
    }

    /**
     * Create exception for invalid join type.
     */
    public static function invalidJoinType(string $type): self
    {
        return new static(
            sprintf("Invalid join type: %s. Supported types are: inner, left, right, cross", $type),
            '',
            ''
        );
    }

    /**
     * Create exception for invalid aggregate function.
     */
    public static function invalidAggregate(string $function): self
    {
        return new static(
            sprintf("Invalid aggregate function: %s. Supported functions are: count, sum, avg, min, max", $function),
            '',
            ''
        );
    }

    /**
     * Create exception for parameter binding errors.
     */
    public static function bindingError(string $reason): self
    {
        return new static(
            sprintf("Parameter binding error: %s", $reason),
            '',
            ''
        );
    }

    /**
     * Get the method that caused the exception.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the grammar class that caused the exception.
     */
    public function getGrammar(): string
    {
        return $this->grammar;
    }
}