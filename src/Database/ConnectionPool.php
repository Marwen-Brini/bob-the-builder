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

    protected bool $enabled = true;

    public function __construct(array $config, int $maxConnections = 10, int $minConnections = 1)
    {
        $this->config = $config;
        $this->maxConnections = max(1, $maxConnections);
        $this->minConnections = max(1, min($minConnections, $this->maxConnections));

        // Initialize minimum connections
        $this->initializePool();
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

        // Clean up idle connections
        $this->cleanupIdleConnections();

        // Try to get an available connection
        if (! empty($this->available)) {
            $id = array_shift($this->available);
            $this->inUse[$id] = true;
            $this->connections[$id]['lastUsed'] = time();

            return $this->connections[$id]['connection'];
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

        // Wait for a connection to become available
        $start = time();
        while (time() - $start < $this->connectionTimeout) {
            if (! empty($this->available)) {
                $id = array_shift($this->available);
                $this->inUse[$id] = true;
                $this->connections[$id]['lastUsed'] = time();

                return $this->connections[$id]['connection'];
            }
            usleep(100000); // Sleep for 100ms
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

        $id = spl_object_hash($connection);

        if (isset($this->inUse[$id])) {
            unset($this->inUse[$id]);
            $this->available[] = $id;
            $this->connections[$id]['lastUsed'] = time();
        }
    }

    protected function cleanupIdleConnections(): void
    {
        $now = time();
        $keepMinimum = count($this->connections) - count($this->available);

        foreach ($this->available as $key => $id) {
            if (count($this->connections) <= $this->minConnections) {
                break;
            }

            if ($now - $this->connections[$id]['lastUsed'] > $this->idleTimeout) {
                $this->connections[$id]['connection']->disconnect();
                unset($this->connections[$id]);
                unset($this->available[$key]);
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

    public function __destruct()
    {
        $this->closeAll();
    }
}
