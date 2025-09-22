<?php

namespace Bob\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The items contained in the collection.
     */
    protected array $items = [];

    /**
     * Create a new collection.
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * Create a new collection instance if the value isn't one already.
     */
    public static function make($items = []): self
    {
        return new static($items);
    }

    /**
     * Get all of the items in the collection.
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Run a map over each of the items.
     */
    public function map(callable $callback): self
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Get the values of a given key.
     */
    public function pluck($value, $key = null): self
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = static::dataGet($item, $value);

            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = static::dataGet($item, $key);
                $results[$itemKey] = $itemValue;
            }
        }

        return new static($results);
    }

    /**
     * Get the collection of items as a plain array.
     */
    public function toArray(): array
    {
        return array_map(function ($value) {
            if ($value instanceof self) {
                return $value->toArray();
            } elseif (is_object($value) && method_exists($value, 'toArray')) {
                return $value->toArray();
            }
            return $value;
        }, $this->items);
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return array_map([$this, 'serializeValue'], $this->items);
    }

    /**
     * Serialize a single value for JSON encoding.
     */
    protected function serializeValue($value)
    {
        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        } elseif ($value instanceof self) {
            // @codeCoverageIgnoreStart
            return $value->toArray();
            // @codeCoverageIgnoreEnd
        }

        return $value;
    }

    /**
     * Get the collection of items as JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Determine if an item exists at an offset.
     */
    public function offsetExists($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get an item at a given offset.
     */
    public function offsetGet($key): mixed
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     */
    public function offsetSet($key, $value): void
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     */
    public function offsetUnset($key): void
    {
        unset($this->items[$key]);
    }

    /**
     * Get an iterator for the items.
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Get the first item from the collection.
     */
    public function first(callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($this->items)) {
                return $default;
            }

            foreach ($this->items as $item) {
                return $item;
            }
        }

        foreach ($this->items as $key => $value) {
            if (call_user_func($callback, $value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get and remove the last item from the collection.
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Push an item onto the end of the collection.
     */
    public function push($value): self
    {
        $this->offsetSet(null, $value);

        return $this;
    }

    /**
     * Get one or a specified number of items randomly from the collection.
     */
    public function random($number = null)
    {
        $requested = is_null($number) ? 1 : $number;

        $count = count($this->items);

        if ($requested > $count) {
            throw new \InvalidArgumentException(
                "You requested {$requested} items, but there are only {$count} items available."
            );
        }

        if (is_null($number)) {
            return $this->items[array_rand($this->items)];
        }

        if ((int) $number === 0) {
            return new static;
        }

        $keys = array_rand($this->items, $number);

        $results = [];

        foreach ((array) $keys as $key) {
            $results[] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Reverse items order.
     */
    public function reverse(): self
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     */
    public function search($value, bool $strict = false)
    {
        if (is_callable($value)) {
            return $this->searchWithCallback($value);
        }

        return array_search($value, $this->items, $strict);
    }

    /**
     * Search using a callback function.
     */
    protected function searchWithCallback(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            if (call_user_func($callback, $item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Get and remove the first item from the collection.
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * Shuffle the items in the collection.
     */
    public function shuffle(?int $seed = null): self
    {
        if (! is_null($seed)) {
            mt_srand($seed);
            $keys = array_keys($this->items);
            shuffle($keys);
            $shuffled = [];
            foreach ($keys as $key) {
                $shuffled[$key] = $this->items[$key];
            }
            return new static($shuffled);
        }

        $items = $this->items;
        shuffle($items);

        return new static($items);
    }

    /**
     * Slice the underlying collection array.
     */
    public function slice(int $offset, ?int $length = null): self
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Sort through each item with a callback.
     */
    public function sort(callable $callback = null): self
    {
        $items = $this->items;

        $callback
            ? uasort($items, $callback)
            : asort($items);

        return new static($items);
    }

    /**
     * Sort the collection using the given callback.
     */
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): self
    {
        $results = [];

        $callback = $this->valueRetriever($callback);

        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options)
                    : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Sort the collection in descending order using the given callback.
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR): self
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Take the first or last {$limit} items.
     */
    public function take(int $limit): self
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Get the collection of items as a plain array.
     */
    public function values(): self
    {
        return new static(array_values($this->items));
    }

    /**
     * Get the unique items from the collection.
     */
    public function unique($key = null, bool $strict = false): self
    {
        if (is_null($key)) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $callback = $this->valueRetriever($key);

        $exists = [];

        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     */
    public function reject($callback = true): self
    {
        $useAsCallable = is_callable($callback);

        return $this->filter(function ($value, $key) use ($callback, $useAsCallable) {
            return $useAsCallable
                ? ! $callback($value, $key)
                : $value != $callback;
        });
    }

    /**
     * Run a filter over each of the items.
     */
    public function filter(callable $callback = null): self
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Get a value retrieving callback.
     */
    protected function valueRetriever($value): callable
    {
        if (is_callable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return static::dataGet($item, $value);
        };
    }

    /**
     * Results array of items from Collection or Arrayable.
     */
    protected function getArrayableItems($items): array
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof JsonSerializable) {
            return $items->jsonSerialize();
        }

        return (array) $items;
    }

    /**
     * Collapse an array of arrays into a single array.
     */
    public static function collapse($array): array
    {
        $results = [];

        foreach ($array as $values) {
            if (is_array($values)) {
                $results = array_merge($results, $values);
            } else {
                $results[] = $values;
            }
        }

        return $results;
    }

    /**
     * Get an item from an array or object using "dot" notation.
     */
    public static function dataGet($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        while (! is_null($segment = array_shift($key))) {
            if ($segment === '*') {
                if (! is_array($target)) {
                    return $default;
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = static::dataGet($item, $key);
                }

                return in_array('*', $key) ? static::collapse($result) : $result;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return $default;
            }
        }

        return $target;
    }
}