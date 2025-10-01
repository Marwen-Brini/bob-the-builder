<?php

if (! function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     */
    function tap($value, $callback)
    {
        $callback($value);

        return $value;
    }
}

if (! function_exists('collect')) {
    /**
     * Create a collection from the given value.
     */
    function collect($value = null)
    {
        return new \Bob\Support\Collection($value);
    }
}

if (! function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     */
    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (! function_exists('last')) {
    /**
     * Get the last element from an array.
     */
    function last($array)
    {
        return is_array($array) ? end($array) : null;
    }
}
