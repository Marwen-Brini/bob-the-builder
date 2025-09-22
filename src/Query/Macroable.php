<?php

namespace Bob\Query;

use BadMethodCallException;
use Closure;
use ReflectionClass;
use ReflectionMethod;

/**
 * Trait to make classes extensible with custom methods
 */
trait Macroable
{
    /**
     * The registered macros.
     *
     * @var array<string, callable>
     */
    protected static array $macros = [];

    /**
     * Register a custom macro.
     */
    public static function macro(string $name, callable $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Flush all macros.
     */
    public static function flushMacros(): void
    {
        static::$macros = [];
    }

    /**
     * Register multiple macros at once.
     *
     * @param  array<string, callable>  $macros
     */
    public static function mixin(array $macros): void
    {
        foreach ($macros as $name => $macro) {
            static::macro($name, $macro);
        }
    }

    /**
     * Mix in methods from a mixin class.
     *
     * @param  object|string  $mixin
     * @param  bool  $replace  Whether to replace existing macros
     */
    public static function mixinClass(object|string $mixin, bool $replace = true): void
    {
        $class = is_string($mixin) ? new $mixin : $mixin;
        $methods = (new ReflectionClass($class))->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }

            $name = $method->getName();

            if (!$replace && static::hasMacro($name)) {
                continue;
            }

            $method->setAccessible(true);

            static::macro($name, function (...$args) use ($class, $method) {
                return $method->invoke($class, ...$args);
            });
        }
    }

    /**
     * Check if a macro is registered.
     */
    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    /**
     * Remove a registered macro.
     */
    public static function removeMacro(string $name): bool
    {
        if (static::hasMacro($name)) {
            unset(static::$macros[$name]);
            return true;
        }
        return false;
    }

    /**
     * Clear all registered macros.
     */
    public static function clearMacros(): void
    {
        static::$macros = [];
    }

    /**
     * Get all registered macros.
     *
     * @return array<string, callable>
     */
    public static function getMacros(): array
    {
        return static::$macros;
    }

    /**
     * Get a specific macro.
     *
     * @return callable|null
     */
    public static function getMacro(string $name): ?callable
    {
        return static::$macros[$name] ?? null;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        $macro = static::getMacro($method);

        if ($macro === null) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method
            ));
        }

        return $this->invokeMacro($macro, $parameters);
    }

    /**
     * Dynamically handle static calls to the class.
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public static function __callStatic(string $method, array $parameters)
    {
        $macro = static::getMacro($method);

        if ($macro === null) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method
            ));
        }

        return static::invokeStaticMacro($macro, $parameters);
    }

    /**
     * Invoke a macro in instance context.
     *
     * @return mixed
     */
    protected function invokeMacro(callable $macro, array $parameters)
    {
        if ($macro instanceof Closure) {
            $macro = $macro->bindTo($this, static::class);
        }

        return $macro(...$parameters);
    }

    /**
     * Invoke a macro in static context.
     *
     * @return mixed
     */
    protected static function invokeStaticMacro(callable $macro, array $parameters)
    {
        if ($macro instanceof Closure) {
            // For static context, we rebind without $this
            $bound = Closure::bind($macro, null, static::class);
            if ($bound !== null) {
                $macro = $bound;
            }
        }

        return $macro(...$parameters);
    }
}
