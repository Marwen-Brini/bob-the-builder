<?php

namespace Bob\Query;

use Closure;

/**
 * Trait to add query scope functionality to the Builder
 */
trait Scopeable
{
    /**
     * Registered global scopes.
     *
     * @var array<string, Closure>
     */
    protected static array $globalScopes = [];

    /**
     * Registered local scopes.
     *
     * @var array<string, Closure>
     */
    protected static array $localScopes = [];

    /**
     * Applied scopes for this query instance.
     *
     * @var array<string>
     */
    protected array $appliedScopes = [];

    /**
     * Register a global scope that applies to all queries.
     */
    public static function globalScope(string $name, Closure $callback): void
    {
        static::$globalScopes[$name] = $callback;
    }

    /**
     * Register a local scope that can be applied on demand.
     */
    public static function scope(string $name, Closure $callback): void
    {
        static::$localScopes[$name] = $callback;
    }

    /**
     * Apply a scope to the query.
     *
     * @param  mixed  ...$parameters
     * @return $this
     */
    public function withScope(string $scope, ...$parameters): static
    {
        if (isset(static::$localScopes[$scope])) {
            $callback = static::$localScopes[$scope];
            $callback = $callback->bindTo($this, static::class);
            $callback(...$parameters);
            $this->appliedScopes[] = $scope;
        }

        return $this;
    }

    /**
     * Remove a global scope from this query.
     *
     * @return $this
     */
    public function withoutGlobalScope(string $scope): static
    {
        $this->appliedScopes[] = "!{$scope}";

        return $this;
    }

    /**
     * Remove all global scopes from this query.
     *
     * @return $this
     */
    public function withoutGlobalScopes(): static
    {
        foreach (array_keys(static::$globalScopes) as $scope) {
            $this->appliedScopes[] = "!{$scope}";
        }

        return $this;
    }

    /**
     * Apply global scopes to the query.
     *
     * @return $this
     */
    protected function applyGlobalScopes(): static
    {
        foreach (static::$globalScopes as $name => $callback) {
            // Skip if this scope was explicitly removed
            if (in_array("!{$name}", $this->appliedScopes)) {
                continue;
            }

            $callback = $callback->bindTo($this, static::class);
            $callback();
        }

        return $this;
    }

    /**
     * Check if a scope exists.
     */
    public static function hasScope(string $name): bool
    {
        return isset(static::$localScopes[$name]) || isset(static::$globalScopes[$name]);
    }

    /**
     * Remove a scope.
     */
    public static function removeScope(string $name): void
    {
        unset(static::$localScopes[$name], static::$globalScopes[$name]);
    }

    /**
     * Clear all scopes.
     */
    public static function clearScopes(): void
    {
        static::$globalScopes = [];
        static::$localScopes = [];
    }

    /**
     * Get all registered scopes.
     */
    public static function getScopes(): array
    {
        return [
            'global' => static::$globalScopes,
            'local' => static::$localScopes,
        ];
    }
}
