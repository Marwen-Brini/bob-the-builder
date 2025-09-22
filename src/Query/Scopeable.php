<?php

namespace Bob\Query;

use Closure;
use InvalidArgumentException;

/**
 * Trait to add query scope functionality to the Builder
 */
trait Scopeable
{
    /**
     * Registered global scopes.
     *
     * @var array<string, callable>
     */
    protected static array $globalScopes = [];

    /**
     * Registered local scopes.
     *
     * @var array<string, callable>
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
    public static function globalScope(string $name, callable $callback): void
    {
        static::$globalScopes[$name] = $callback;
    }

    /**
     * Register a local scope that can be applied on demand.
     */
    public static function scope(string $name, callable $callback): void
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
        if (!static::hasLocalScope($scope)) {
            throw new InvalidArgumentException("Scope [{$scope}] not found.");
        }

        $this->applyScope($scope, $parameters);
        $this->recordAppliedScope($scope);

        return $this;
    }

    /**
     * Apply multiple scopes at once.
     *
     * @param  array  $scopes  Array of scope names or [name => parameters] pairs
     * @return $this
     */
    public function withScopes(array $scopes): static
    {
        foreach ($scopes as $scope => $parameters) {
            if (is_numeric($scope)) {
                $this->withScope($parameters);
            } else {
                $this->withScope($scope, ...(array) $parameters);
            }
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
        $this->recordRemovedScope($scope);
        return $this;
    }

    /**
     * Remove multiple global scopes.
     *
     * @param  array  $scopes
     * @return $this
     */
    public function withoutGlobalScopes(array $scopes = []): static
    {
        if (empty($scopes)) {
            $scopes = array_keys(static::$globalScopes);
        }

        foreach ($scopes as $scope) {
            $this->withoutGlobalScope($scope);
        }

        return $this;
    }

    /**
     * Apply global scopes to the query.
     *
     * @return $this
     */
    public function applyGlobalScopes(): static
    {
        foreach (static::$globalScopes as $name => $callback) {
            if ($this->shouldSkipGlobalScope($name)) {
                continue;
            }

            $this->applyScope($name, [], true);
        }

        return $this;
    }

    /**
     * Check if a local scope exists.
     */
    public static function hasLocalScope(string $name): bool
    {
        return isset(static::$localScopes[$name]);
    }

    /**
     * Check if a global scope exists.
     */
    public static function hasGlobalScope(string $name): bool
    {
        return isset(static::$globalScopes[$name]);
    }

    /**
     * Check if any scope exists.
     */
    public static function hasScope(string $name): bool
    {
        return static::hasLocalScope($name) || static::hasGlobalScope($name);
    }

    /**
     * Get a specific local scope.
     */
    public static function getLocalScope(string $name): ?callable
    {
        return static::$localScopes[$name] ?? null;
    }

    /**
     * Get a specific global scope.
     */
    public static function getGlobalScope(string $name): ?callable
    {
        return static::$globalScopes[$name] ?? null;
    }

    /**
     * Remove a scope.
     */
    public static function removeScope(string $name): bool
    {
        $removed = false;

        if (isset(static::$localScopes[$name])) {
            unset(static::$localScopes[$name]);
            $removed = true;
        }

        if (isset(static::$globalScopes[$name])) {
            unset(static::$globalScopes[$name]);
            $removed = true;
        }

        return $removed;
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
     * Clear only local scopes.
     */
    public static function clearLocalScopes(): void
    {
        static::$localScopes = [];
    }

    /**
     * Clear only global scopes.
     */
    public static function clearGlobalScopes(): void
    {
        static::$globalScopes = [];
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

    /**
     * Get applied scopes for this instance.
     */
    public function getAppliedScopes(): array
    {
        return $this->appliedScopes;
    }

    /**
     * Check if a scope has been applied.
     */
    public function hasAppliedScope(string $scope): bool
    {
        return in_array($scope, $this->appliedScopes, true);
    }

    /**
     * Check if a global scope should be skipped.
     */
    protected function shouldSkipGlobalScope(string $scope): bool
    {
        return in_array("!{$scope}", $this->appliedScopes, true);
    }

    /**
     * Apply a scope callback.
     */
    protected function applyScope(string $name, array $parameters = [], bool $isGlobal = false): void
    {
        $callback = $isGlobal
            ? static::$globalScopes[$name]
            : static::$localScopes[$name];

        if ($callback instanceof Closure) {
            $callback = $callback->bindTo($this, static::class);
            $callback(...$parameters);
        } else {
            // For non-closure callables, pass $this as first parameter
            $callback($this, ...$parameters);
        }
    }

    /**
     * Record that a scope was applied.
     */
    protected function recordAppliedScope(string $scope): void
    {
        if (!$this->hasAppliedScope($scope)) {
            $this->appliedScopes[] = $scope;
        }
    }

    /**
     * Record that a global scope was removed.
     */
    protected function recordRemovedScope(string $scope): void
    {
        $removedMarker = "!{$scope}";
        if (!in_array($removedMarker, $this->appliedScopes, true)) {
            $this->appliedScopes[] = $removedMarker;
        }
    }

    /**
     * Reset applied scopes for this instance.
     */
    public function resetAppliedScopes(): void
    {
        $this->appliedScopes = [];
    }
}
