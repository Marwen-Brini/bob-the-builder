<?php

declare(strict_types=1);

namespace Bob\Cache;

class QueryCache
{
    protected array $cache = [];
    protected int $maxItems = 1000;
    protected int $ttl = 3600; // 1 hour default
    protected bool $enabled = true;

    public function __construct(int $maxItems = 1000, int $ttl = 3600)
    {
        $this->maxItems = $maxItems;
        $this->ttl = $ttl;
    }

    public function get(string $key): mixed
    {
        if (!$this->enabled || !isset($this->cache[$key])) {
            return null;
        }

        $item = $this->cache[$key];
        
        if ($item['expires'] < time()) {
            unset($this->cache[$key]);
            return null;
        }

        return $item['value'];
    }

    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->enabled) {
            return;
        }

        if (count($this->cache) >= $this->maxItems) {
            $this->evictOldest();
        }

        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + ($ttl ?? $this->ttl),
            'created' => time(),
        ];
    }

    public function forget(string $key): void
    {
        unset($this->cache[$key]);
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

    protected function evictOldest(): void
    {
        $oldest = null;
        $oldestKey = null;

        foreach ($this->cache as $key => $item) {
            if ($oldest === null || $item['created'] < $oldest) {
                $oldest = $item['created'];
                $oldestKey = $key;
            }
        }

        if ($oldestKey !== null) {
            unset($this->cache[$oldestKey]);
        }
    }

    public function generateKey(string $query, array $bindings = []): string
    {
        return md5($query . serialize($bindings));
    }
}