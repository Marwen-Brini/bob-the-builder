<?php

namespace Bob\Query;

/**
 * Trait to add dynamic finder methods to the Builder
 * Allows methods like findBySlug(), whereByStatus(), etc.
 */
trait DynamicFinder
{
    /**
     * Custom finder patterns and their handlers.
     *
     * @var array<string, callable>
     */
    protected static array $finderPatterns = [];

    /**
     * Register a custom finder pattern.
     *
     * @param  string  $pattern  Regular expression pattern
     * @param  callable  $handler  Handler function
     */
    public static function registerFinder(string $pattern, callable $handler): void
    {
        static::$finderPatterns[$pattern] = $handler;
    }

    /**
     * Handle dynamic finder methods.
     *
     * @return mixed
     */
    public function handleDynamicFinder(string $method, array $parameters)
    {
        // Check custom patterns first
        $result = $this->matchCustomPatterns($method, $parameters);
        if ($result !== null) {
            return $result;
        }

        // Check default patterns
        return $this->matchDefaultPatterns($method, $parameters);
    }

    /**
     * Match against custom registered patterns.
     *
     * @return mixed
     */
    protected function matchCustomPatterns(string $method, array $parameters)
    {
        foreach (static::$finderPatterns as $pattern => $handler) {
            if (preg_match($pattern, $method, $matches)) {
                return $this->executeHandler($handler, $matches, $parameters);
            }
        }

        return null;
    }

    /**
     * Match against default patterns.
     *
     * @return mixed
     */
    protected function matchDefaultPatterns(string $method, array $parameters)
    {
        $defaultPatterns = $this->getDefaultPatterns();

        foreach ($defaultPatterns as $pattern => $handler) {
            if (preg_match($pattern, $method, $matches)) {
                return $this->executeHandler($handler, $matches, $parameters);
            }
        }

        return null;
    }

    /**
     * Execute a pattern handler.
     *
     * @return mixed
     */
    protected function executeHandler(callable $handler, array $matches, array $parameters)
    {
        if ($handler instanceof \Closure) {
            $handler = $handler->bindTo($this, static::class);
        }

        return $handler($matches, $parameters);
    }

    /**
     * Get default finder patterns.
     *
     * @return array<string, callable>
     */
    protected function getDefaultPatterns(): array
    {
        return [
            '/^findBy(.+)$/' => $this->createFindByHandler(),
            '/^findAllBy(.+)$/' => $this->createFindAllByHandler(),
            '/^whereBy(.+)$/' => $this->createWhereByHandler(),
            '/^orWhereBy(.+)$/' => $this->createOrWhereByHandler(),
            '/^firstWhere(.+)$/' => $this->createFirstWhereHandler(),
            '/^countBy(.+)$/' => $this->createCountByHandler(),
            '/^existsBy(.+)$/' => $this->createExistsByHandler(),
            '/^deleteBy(.+)$/' => $this->createDeleteByHandler(),
            '/^orderBy(.+)(Asc|Desc)$/' => $this->createOrderByHandler(),
            '/^groupBy(.+)$/' => $this->createGroupByHandler(),
        ];
    }

    /**
     * Create handler for findBy pattern.
     */
    protected function createFindByHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);

            return $this->where($column, '=', $params[0] ?? null)->first();
        };
    }

    /**
     * Create handler for findAllBy pattern.
     */
    protected function createFindAllByHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);

            return $this->where($column, '=', $params[0] ?? null)->get();
        };
    }

    /**
     * Create handler for whereBy pattern.
     */
    protected function createWhereByHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);

            return $this->where($column, '=', $params[0] ?? null);
        };
    }

    /**
     * Create handler for orWhereBy pattern.
     */
    protected function createOrWhereByHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);

            return $this->orWhere($column, '=', $params[0] ?? null);
        };
    }

    /**
     * Create handler for firstWhere pattern.
     */
    protected function createFirstWhereHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);

            return $this->where($column, '=', $params[0] ?? null)->first();
        };
    }

    /**
     * Create handler for countBy pattern.
     */
    protected function createCountByHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);

            return $this->where($column, '=', $params[0] ?? null)->count();
        };
    }

    /**
     * Create handler for existsBy pattern.
     */
    protected function createExistsByHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);

            return $this->where($column, '=', $params[0] ?? null)->exists();
        };
    }

    /**
     * Create handler for deleteBy pattern.
     */
    protected function createDeleteByHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);

            return $this->where($column, '=', $params[0] ?? null)->delete();
        };
    }

    /**
     * Create handler for orderBy pattern.
     */
    protected function createOrderByHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);
            $direction = strtolower($matches[2]);

            return $this->orderBy($column, $direction);
        };
    }

    /**
     * Create handler for groupBy pattern.
     */
    protected function createGroupByHandler(): callable
    {
        return function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);

            return $this->groupBy($column);
        };
    }

    /**
     * Convert camelCase to snake_case.
     */
    public function camelToSnake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    /**
     * Clear all custom finder patterns.
     */
    public static function clearFinders(): void
    {
        static::$finderPatterns = [];
    }

    /**
     * Get all registered finder patterns.
     */
    public static function getFinderPatterns(): array
    {
        return static::$finderPatterns;
    }
}
