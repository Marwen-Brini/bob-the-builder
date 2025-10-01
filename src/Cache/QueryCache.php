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

    public function __construct(int $maxItems = 1000, int $ttl = 3600)
    {
        $this->maxItems = $maxItems;
        $this->ttl = $ttl;
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

        if (! $this->has($key)) {
            return null;
        }

        $item = $this->cache[$key];

        if ($this->isExpired($item)) {
            $this->forget($key);

            return null;
        }

        return $item['value'];
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

        if ($this->shouldEvict()) {
            $this->evictOldest();
        }

        $this->cache[$key] = $this->createCacheItem($value, $ttl);
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    public function forget(string $key): bool
    {
        if ($this->has($key)) {
            unset($this->cache[$key]);

            return true;
        }

        return false;
    }

    public function flush(): void
    {
        $this->cache = [];
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
        return md5($query.serialize($bindings));
    }

    /**
     * Get raw cache data for testing.
     */
    public function getRawCache(): array
    {
        return $this->cache;
    }
}
