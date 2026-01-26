<?php

declare(strict_types=1);

namespace Bob\Database;

use Bob\Exceptions\ConnectionException;

class ConnectionPool
{
    protected array $connections = [];

    protected array $inUse = [];

    protected array $available = [];

    protected array $config = [];

    protected int $maxConnections = 10;

    protected int $minConnections = 1;

    protected int $connectionTimeout = 30;

    protected int $idleTimeout = 600;

    protected int $maxLifetime = 3600;

    protected bool $enabled = true;

    /**
     * Lock file handle for thread safety
     */
    protected $lockHandle = null;

    /**
     * Lock file path
     */
    protected ?string $lockFile = null;

    public function __construct(array $config, int $maxConnections = 10, int $minConnections = 1)
    {
        $this->config = $config;
        $this->maxConnections = max(1, $maxConnections);
        $this->minConnections = max(1, min($minConnections, $this->maxConnections));

        // Initialize lock file for thread safety
        $this->initializeLock();

        // Initialize minimum connections
        $this->initializePool();
    }

    /**
     * Initialize the lock file for thread-safe operations
     */
    protected function initializeLock(): void
    {
        $this->lockFile = sys_get_temp_dir() . '/bob_pool_' . md5(serialize($this->config)) . '.lock';
        $this->lockHandle = fopen($this->lockFile, 'c');
    }

    /**
     * Acquire an exclusive lock for thread-safe operations
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

    protected function initializePool(): void
    {
        for ($i = 0; $i < $this->minConnections; $i++) {
            $connection = $this->createConnection();
            $id = spl_object_hash($connection);
            $this->connections[$id] = [
                'connection' => $connection,
                'created' => time(),
                'lastUsed' => time(),
            ];
            $this->available[] = $id;
        }
    }

    protected function createConnection(): Connection
    {
        return new Connection($this->config);
    }

    public function acquire(): Connection
    {
        if (! $this->enabled) {
            return $this->createConnection();
        }

        $this->lock();

        try {
            // Clean up stale connections
            $this->cleanupStaleConnections();

            // Try to get an available connection
            if (! empty($this->available)) {
                $id = array_shift($this->available);

                // Verify connection is still healthy
                if ($this->isConnectionHealthy($id)) {
                    $this->inUse[$id] = true;
                    $this->connections[$id]['lastUsed'] = time();

                    return $this->connections[$id]['connection'];
                }

                // Connection is dead, remove it and try again
                $this->removeConnection($id);

                return $this->acquire();
            }

            // Create new connection if under limit
            if (count($this->connections) < $this->maxConnections) {
                $connection = $this->createConnection();
                $id = spl_object_hash($connection);
                $this->connections[$id] = [
                    'connection' => $connection,
                    'created' => time(),
                    'lastUsed' => time(),
                ];
                $this->inUse[$id] = true;

                return $connection;
            }
        } finally {
            $this->unlock();
        }

        // Wait for a connection to become available (outside lock to prevent deadlock)
        $start = time();
        while (time() - $start < $this->connectionTimeout) {
            // @codeCoverageIgnoreStart
            $this->lock();
            try {
                if (! empty($this->available)) {
                    $id = array_shift($this->available);
                    if ($this->isConnectionHealthy($id)) {
                        $this->inUse[$id] = true;
                        $this->connections[$id]['lastUsed'] = time();

                        return $this->connections[$id]['connection'];
                    }
                    $this->removeConnection($id);
                }
            } finally {
                $this->unlock();
            }
            usleep(100000); // Sleep for 100ms
            // @codeCoverageIgnoreEnd
        }

        throw ConnectionException::connectionFailed(
            $this->config,
            new \Exception("Connection pool timeout: No connections available after {$this->connectionTimeout} seconds")
        );
    }

    public function release(Connection $connection): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->lock();

        try {
            $id = spl_object_hash($connection);

            if (isset($this->inUse[$id])) {
                unset($this->inUse[$id]);
                $this->available[] = $id;
                $this->connections[$id]['lastUsed'] = time();
            }
        } finally {
            $this->unlock();
        }
    }

    /**
     * Check if a connection is still healthy (not stale or broken)
     */
    protected function isConnectionHealthy(string $id): bool
    {
        if (!isset($this->connections[$id])) {
            return false;
        }

        $item = $this->connections[$id];
        $now = time();

        // Check if connection has exceeded max lifetime
        if ($now - $item['created'] > $this->maxLifetime) {
            return false;
        }

        // Try to verify the connection is still working
        try {
            $pdo = $item['connection']->getPdo();
            // Simple ping query to verify connection
            $pdo->query('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Remove a connection from the pool
     */
    protected function removeConnection(string $id): void
    {
        if (isset($this->connections[$id])) {
            try {
                $this->connections[$id]['connection']->disconnect();
            } catch (\Throwable $e) {
                // Ignore disconnect errors
            }
            unset($this->connections[$id]);
        }
        unset($this->inUse[$id]);

        $key = array_search($id, $this->available, true);
        if ($key !== false) {
            unset($this->available[$key]);
            $this->available = array_values($this->available);
        }
    }

    /**
     * Clean up idle and stale connections
     */
    protected function cleanupStaleConnections(): void
    {
        $now = time();

        foreach ($this->available as $key => $id) {
            // Keep minimum connections
            if (count($this->connections) <= $this->minConnections) {
                break;
            }

            $item = $this->connections[$id] ?? null;
            if ($item === null) {
                unset($this->available[$key]);
                continue;
            }

            // Remove if idle too long or exceeded max lifetime
            $idleTooLong = ($now - $item['lastUsed']) > $this->idleTimeout;
            $exceededLifetime = ($now - $item['created']) > $this->maxLifetime;

            if ($idleTooLong || $exceededLifetime) {
                $this->removeConnection($id);
            }
        }

        $this->available = array_values($this->available);
    }

    public function closeAll(): void
    {
        foreach ($this->connections as $item) {
            $item['connection']->disconnect();
        }

        $this->connections = [];
        $this->available = [];
        $this->inUse = [];
    }

    public function getStats(): array
    {
        return [
            'total' => count($this->connections),
            'available' => count($this->available),
            'in_use' => count($this->inUse),
            'max_connections' => $this->maxConnections,
            'min_connections' => $this->minConnections,
        ];
    }

    public function enable(): void
    {
        $this->enabled = true;
        if (empty($this->connections)) {
            $this->initializePool();
        }
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->closeAll();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setIdleTimeout(int $seconds): void
    {
        $this->idleTimeout = max(1, $seconds);
    }

    public function setConnectionTimeout(int $seconds): void
    {
        $this->connectionTimeout = max(1, $seconds);
    }

    public function setMaxLifetime(int $seconds): void
    {
        $this->maxLifetime = max(1, $seconds);
    }

    public function getMaxLifetime(): int
    {
        return $this->maxLifetime;
    }

    public function __destruct()
    {
        $this->closeAll();

        // Clean up lock file
        if ($this->lockHandle !== null && $this->lockHandle !== false) {
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }

        if ($this->lockFile !== null && file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }
    }
}
