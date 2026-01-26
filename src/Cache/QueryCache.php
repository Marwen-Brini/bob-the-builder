<?php

declare(strict_types=1);

namespace Bob\Cache;

class QueryCache
{
    protected array $cache = [];

    protected int $maxItems = 1000;

    protected int $ttl = 3600; // 1 hour default

    protected bool $enabled = true;

    protected ?int $currentTime = null; // For testing

    /**
     * Lock file handle for thread safety
     */
    protected $lockHandle = null;

    /**
     * Lock file path
     */
    protected ?string $lockFile = null;

    public function __construct(int $maxItems = 1000, int $ttl = 3600)
    {
        $this->maxItems = $maxItems;
        $this->ttl = $ttl;
        $this->initializeLock();
    }

    /**
     * Initialize the lock file for thread-safe operations
     */
    protected function initializeLock(): void
    {
        $this->lockFile = sys_get_temp_dir() . '/bob_cache_' . spl_object_hash($this) . '.lock';
        $this->lockHandle = fopen($this->lockFile, 'c');
    }

    /**
     * Acquire an exclusive lock
     */
    protected function lock(): bool
    {
        if ($this->lockHandle === null || $this->lockHandle === false) {
            return false;
        }

        return flock($this->lockHandle, LOCK_EX);
    }

    /**
     * Release the lock
     */
    protected function unlock(): void
    {
        if ($this->lockHandle !== null && $this->lockHandle !== false) {
            flock($this->lockHandle, LOCK_UN);
        }
    }

    public function __destruct()
    {
        if ($this->lockHandle !== null && $this->lockHandle !== false) {
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }

        if ($this->lockFile !== null && file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }
    }

    /**
     * Get current time (allows for testing).
     */
    protected function getCurrentTime(): int
    {
        return $this->currentTime ?? time();
    }

    /**
     * Set current time for testing.
     */
    public function setCurrentTime(?int $time): void
    {
        $this->currentTime = $time;
    }

    public function get(string $key): mixed
    {
        if (! $this->enabled) {
            return null;
        }

        $this->lock();

        try {
            if (! $this->has($key)) {
                return null;
            }

            $item = $this->cache[$key];

            if ($this->isExpired($item)) {
                $this->forgetWithoutLock($key);

                return null;
            }

            return $item['value'];
        } finally {
            $this->unlock();
        }
    }

    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        if (! $this->enabled) {
            return;
        }

        // Handle edge case where maxItems is 0
        if ($this->maxItems <= 0) {
            return;
        }

        $this->lock();

        try {
            if ($this->shouldEvict()) {
                $this->evictOldest();
            }

            $this->cache[$key] = $this->createCacheItem($value, $ttl);
        } finally {
            $this->unlock();
        }
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    public function forget(string $key): bool
    {
        $this->lock();

        try {
            return $this->forgetWithoutLock($key);
        } finally {
            $this->unlock();
        }
    }

    /**
     * Internal forget without locking (for use within locked sections)
     */
    protected function forgetWithoutLock(string $key): bool
    {
        if ($this->has($key)) {
            unset($this->cache[$key]);

            return true;
        }

        return false;
    }

    public function flush(): void
    {
        $this->lock();

        try {
            $this->cache = [];
        } finally {
            $this->unlock();
        }
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function size(): int
    {
        return count($this->cache);
    }

    public function getMaxItems(): int
    {
        return $this->maxItems;
    }

    public function setMaxItems(int $maxItems): void
    {
        $this->maxItems = $maxItems;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    /**
     * Get all cache keys.
     */
    public function keys(): array
    {
        return array_keys($this->cache);
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        $expired = 0;
        $valid = 0;

        foreach ($this->cache as $item) {
            if ($this->isExpired($item)) {
                $expired++;
            } else {
                $valid++;
            }
        }

        return [
            'total' => $this->size(),
            'valid' => $valid,
            'expired' => $expired,
            'max_items' => $this->maxItems,
            'enabled' => $this->enabled,
        ];
    }

    /**
     * Clean up expired items.
     */
    public function cleanExpired(): int
    {
        $removed = 0;
        foreach ($this->cache as $key => $item) {
            if ($this->isExpired($item)) {
                unset($this->cache[$key]);
                $removed++;
            }
        }

        return $removed;
    }

    protected function shouldEvict(): bool
    {
        return count($this->cache) >= $this->maxItems;
    }

    protected function isExpired(array $item): bool
    {
        return $item['expires'] < $this->getCurrentTime();
    }

    protected function createCacheItem(mixed $value, ?int $ttl = null): array
    {
        $currentTime = $this->getCurrentTime();

        return [
            'value' => $value,
            'expires' => $currentTime + ($ttl ?? $this->ttl),
            'created' => $currentTime,
        ];
    }

    protected function evictOldest(): void
    {
        if (empty($this->cache)) {
            return;
        }

        $oldestKey = $this->findOldestKey();

        if ($oldestKey !== null) {
            unset($this->cache[$oldestKey]);
        }
    }

    protected function findOldestKey(): ?string
    {
        $oldest = null;
        $oldestKey = null;

        foreach ($this->cache as $key => $item) {
            if ($oldest === null || $item['created'] < $oldest) {
                $oldest = $item['created'];
                $oldestKey = $key;
            }
        }

        return $oldestKey;
    }

    public function generateKey(string $query, array $bindings = []): string
    {
        $safeBindings = $this->makeSafeForSerialization($bindings);

        return md5($query . serialize($safeBindings));
    }

    /**
     * Convert bindings array to a serialization-safe format.
     *
     * Handles non-serializable values like closures, resources, and certain objects
     * by replacing them with unique identifier strings.
     */
    protected function makeSafeForSerialization(array $bindings): array
    {
        $safe = [];

        foreach ($bindings as $key => $value) {
            $safe[$key] = $this->makeValueSafeForSerialization($value);
        }

        return $safe;
    }

    /**
     * Make a single value safe for serialization.
     */
    protected function makeValueSafeForSerialization(mixed $value): mixed
    {
        if ($value instanceof \Closure) {
            return '__closure_' . spl_object_hash($value);
        }

        if (is_resource($value)) {
            return '__resource_' . get_resource_type($value) . '_' . (int) $value;
        }

        if (is_object($value)) {
            if ($this->isSerializable($value)) {
                return $value;
            }

            return '__object_' . get_class($value) . '_' . spl_object_hash($value);
        }

        if (is_array($value)) {
            return $this->makeSafeForSerialization($value);
        }

        // Scalar values are safe
        return $value;
    }

    /**
     * Check if an object can be serialized.
     */
    protected function isSerializable(object $object): bool
    {
        try {
            serialize($object);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get raw cache data for testing.
     */
    public function getRawCache(): array
    {
        return $this->cache;
    }
}
