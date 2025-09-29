<?php

declare(strict_types=1);

namespace Bob\Schema;

use ArrayAccess;
use JsonSerializable;

/**
 * Fluent class for dynamic property handling
 *
 * This class provides a fluent interface for setting and getting
 * arbitrary properties dynamically, used by column definitions
 * and command objects.
 */
class Fluent implements ArrayAccess, JsonSerializable
{
    /**
     * All of the attributes set on the fluent instance
     */
    protected array $attributes = [];

    /**
     * Create a new fluent instance
     */
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Get an attribute from the fluent instance
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the fluent instance to an array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the object to its JSON representation
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the fluent instance to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Determine if an attribute exists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get an attribute
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset];
    }

    /**
     * Set an attribute
     * @codeCoverageIgnore
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Unset an attribute
     * @codeCoverageIgnore
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Handle dynamic property access
     */
    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Handle dynamic property assignment
     */
    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Check if a property is set
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Unset a property
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Handle dynamic method calls
     */
    public function __call(string $method, array $parameters): self
    {
        $this->attributes[$method] = count($parameters) > 0 ? $parameters[0] : true;
        return $this;
    }
}