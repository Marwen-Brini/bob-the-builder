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
    protected function handleDynamicFinder(string $method, array $parameters)
    {
        // Default patterns for common finders
        $defaultPatterns = [
            '/^findBy(.+)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);

                return $this->where($column, '=', $params[0] ?? null)->first();
            },
            '/^findAllBy(.+)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);

                return $this->where($column, '=', $params[0] ?? null)->get();
            },
            '/^whereBy(.+)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);

                return $this->where($column, '=', $params[0] ?? null);
            },
            '/^orWhereBy(.+)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);

                return $this->orWhere($column, '=', $params[0] ?? null);
            },
            '/^firstWhere(.+)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);

                return $this->where($column, '=', $params[0] ?? null)->first();
            },
            '/^countBy(.+)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);

                return $this->where($column, '=', $params[0] ?? null)->count();
            },
            '/^existsBy(.+)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);

                return $this->where($column, '=', $params[0] ?? null)->exists();
            },
            '/^deleteBy(.+)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);

                return $this->where($column, '=', $params[0] ?? null)->delete();
            },
            '/^orderBy(.+)(Asc|Desc)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);
                $direction = strtolower($matches[2]);

                return $this->orderBy($column, $direction);
            },
            '/^groupBy(.+)$/' => function ($matches, $params) {
                $column = $this->camelToSnake($matches[1]);

                return $this->groupBy($column);
            },
        ];

        // Check custom patterns first
        foreach (static::$finderPatterns as $pattern => $handler) {
            if (preg_match($pattern, $method, $matches)) {
                if ($handler instanceof \Closure) {
                    $handler = $handler->bindTo($this, static::class);
                }

                return $handler($matches, $parameters);
            }
        }

        // Check default patterns
        foreach ($defaultPatterns as $pattern => $handler) {
            if (preg_match($pattern, $method, $matches)) {
                if ($handler instanceof \Closure) {
                    $handler = $handler->bindTo($this, static::class);
                }

                return $handler($matches, $parameters);
            }
        }

        return null;
    }

    /**
     * Convert camelCase to snake_case.
     */
    protected function camelToSnake(string $value): string
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
