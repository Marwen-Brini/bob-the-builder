<?php

namespace Bob\Query;

use BadMethodCallException;
use Closure;

/**
 * Trait to make classes extensible with custom methods
 */
trait Macroable
{
    /**
     * The registered macros.
     *
     * @var array<string, Closure>
     */
    protected static array $macros = [];

    /**
     * Register a custom macro.
     *
     * @param string $name
     * @param Closure $macro
     * @return void
     */
    public static function macro(string $name, Closure $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Register multiple macros at once.
     *
     * @param array<string, Closure> $macros
     * @return void
     */
    public static function mixin(array $macros): void
    {
        foreach ($macros as $name => $macro) {
            static::macro($name, $macro);
        }
    }

    /**
     * Check if a macro is registered.
     *
     * @param string $name
     * @return bool
     */
    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    /**
     * Remove a registered macro.
     *
     * @param string $name
     * @return void
     */
    public static function removeMacro(string $name): void
    {
        unset(static::$macros[$name]);
    }

    /**
     * Clear all registered macros.
     *
     * @return void
     */
    public static function clearMacros(): void
    {
        static::$macros = [];
    }

    /**
     * Get all registered macros.
     *
     * @return array<string, Closure>
     */
    public static function getMacros(): array
    {
        return static::$macros;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        if (!static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method
            ));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            $macro = $macro->bindTo($this, static::class);
        }

        return $macro(...$parameters);
    }

    /**
     * Dynamically handle static calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if (!static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method
            ));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            $macro = $macro->bindTo(null, static::class);
        }

        return $macro(...$parameters);
    }
}