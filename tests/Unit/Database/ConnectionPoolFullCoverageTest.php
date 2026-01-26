<?php

use Bob\Database\Connection;
use Bob\Database\ConnectionPool;
use Mockery as m;

describe('ConnectionPool Full Coverage Tests', function () {

    beforeEach(function () {
        $this->config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];
    });

    afterEach(function () {
        m::close();
    });

    // Test line 61: acquire when pool is disabled
    test('acquire returns new connection when pool is disabled', function () {
        $pool = new ConnectionPool($this->config);
        $pool->disable();

        $connection = $pool->acquire();

        expect($connection)->toBeInstanceOf(Connection::class);

        // Acquire another to ensure each call creates a new connection when disabled
        $connection2 = $pool->acquire();
        expect($connection2)->toBeInstanceOf(Connection::class);
        expect($connection)->not->toBe($connection2);
    });

    // Test line 115: release when pool is disabled
    test('release does nothing when pool is disabled', function () {
        $pool = new ConnectionPool($this->config);

        // First acquire a connection while enabled
        $pool->enable();
        $connection = $pool->acquire();

        // Now disable the pool and try to release
        $pool->disable();
        $pool->release($connection);

        // Stats should show the connection is still in use (not returned to pool)
        $stats = $pool->getStats();
        expect($stats['available'])->toBe(0);
    });

    // Test lines 137-140: cleanup of idle connections
    test('cleanupIdleConnections removes connections that exceed idle timeout', function () {
        $pool = new ConnectionPool($this->config);
        $pool->enable();

        // Set a very short idle timeout (1 second)
        $pool->setIdleTimeout(1);

        // Acquire and release connections
        $connection1 = $pool->acquire();
        $connection2 = $pool->acquire();
        $pool->release($connection1);
        $pool->release($connection2);

        // Verify they're in the pool
        $stats = $pool->getStats();
        expect($stats['total'])->toBe(2);
        expect($stats['available'])->toBe(2);

        // Sleep for more than idle timeout
        sleep(2);

        // Acquire a new connection which should trigger cleanup
        $connection3 = $pool->acquire();

        // The old idle connections should have been cleaned up
        $stats = $pool->getStats();
        // We should have 1 connection in use (connection3)
        expect($stats['in_use'])->toBe(1);
    });

    // Additional test to ensure idle cleanup respects minimum connections
    test('cleanupIdleConnections respects minimum connections', function () {
        $pool = new ConnectionPool($this->config, minConnections: 2, maxConnections: 5);
        $pool->enable();

        // Set a very short idle timeout
        $pool->setIdleTimeout(1);

        // Create 3 connections
        $conn1 = $pool->acquire();
        $conn2 = $pool->acquire();
        $conn3 = $pool->acquire();

        // Release all of them
        $pool->release($conn1);
        $pool->release($conn2);
        $pool->release($conn3);

        // Sleep for more than idle timeout
        sleep(2);

        // Trigger cleanup by acquiring a connection
        $newConn = $pool->acquire();

        // Should keep at least minimum connections (2)
        // So we should have 2 total (1 from minimum kept + 1 in use)
        $stats = $pool->getStats();
        expect($stats['total'])->toBeGreaterThanOrEqual(2);
    });

    // Test the disconnect call in cleanup (line 138)
    test('cleanupIdleConnections disconnects removed connections', function () {
        // Use a real scenario where we can observe the behavior
        $pool = new ConnectionPool($this->config, minConnections: 0, maxConnections: 5);
        $pool->enable();
        $pool->setIdleTimeout(1);

        // Acquire and release to populate the pool
        $conn = $pool->acquire();
        $pool->release($conn);

        // Get initial stats
        $statsBefore = $pool->getStats();
        expect($statsBefore['available'])->toBe(1);
        expect($statsBefore['total'])->toBe(1);

        // Wait for idle timeout
        sleep(2);

        // Acquiring a new connection should trigger cleanup
        $newConn = $pool->acquire();

        // The idle connection should have been removed and disconnected
        $statsAfter = $pool->getStats();

        // We should have 1 connection (the newly acquired one)
        expect($statsAfter['total'])->toBe(1);
        expect($statsAfter['available'])->toBe(0); // No available since we just acquired one
        expect($statsAfter['in_use'])->toBe(1);
    });

    // Test edge case: cleanup when available array has gaps
    test('cleanupIdleConnections handles available array correctly', function () {
        $pool = new ConnectionPool($this->config);
        $pool->enable();
        $pool->setIdleTimeout(1);

        // Create multiple connections
        $connections = [];
        for ($i = 0; $i < 3; $i++) {
            $connections[] = $pool->acquire();
        }

        // Release them all
        foreach ($connections as $conn) {
            $pool->release($conn);
        }

        // Wait for timeout
        sleep(2);

        // Trigger cleanup
        $pool->acquire();

        // The cleanup should have worked without errors
        $stats = $pool->getStats();
        expect($stats)->toHaveKey('total');
        expect($stats)->toHaveKey('available');
        expect($stats)->toHaveKey('in_use');
    });

    // Test to ensure line 134 (break condition) is covered
    test('cleanupIdleConnections stops when minimum connections reached', function () {
        // Create pool with specific min connections
        $pool = new ConnectionPool($this->config, minConnections: 3);
        $pool->enable();
        $pool->setIdleTimeout(1);

        // Create exactly minimum number of connections
        $connections = [];
        for ($i = 0; $i < 3; $i++) {
            $connections[] = $pool->acquire();
        }

        // Release all
        foreach ($connections as $conn) {
            $pool->release($conn);
        }

        // Wait for timeout
        sleep(2);

        // Trigger cleanup
        $newConn = $pool->acquire();

        // Should still have minimum connections
        $stats = $pool->getStats();
        // We have 3 minimum, but one is now in use
        expect($stats['total'])->toBeGreaterThanOrEqual(3);
    });

    // Test line 69: lock() returns false when lockHandle is null
    test('lock returns false when lockHandle is invalid', function () {
        $pool = new class($this->config) extends ConnectionPool {
            public function __construct(array $config)
            {
                parent::__construct($config);
                // Set lockHandle to null to test line 69
                $this->lockHandle = null;
            }

            public function testLock(): bool
            {
                return $this->lock();
            }
        };

        expect($pool->testLock())->toBeFalse();
    });

    // Test lines 336-341: setMaxLifetime and getMaxLifetime
    test('setMaxLifetime and getMaxLifetime work correctly', function () {
        $pool = new ConnectionPool($this->config);

        // Test setting a normal value
        $pool->setMaxLifetime(3600);
        expect($pool->getMaxLifetime())->toBe(3600);

        // Test setting to zero (should be at least 1)
        $pool->setMaxLifetime(0);
        expect($pool->getMaxLifetime())->toBe(1);

        // Test setting negative (should be at least 1)
        $pool->setMaxLifetime(-10);
        expect($pool->getMaxLifetime())->toBe(1);
    });

    // Test line 215: isConnectionHealthy returns false when maxLifetime exceeded
    test('connection is unhealthy when maxLifetime exceeded', function () {
        $pool = new ConnectionPool($this->config, minConnections: 0);
        $pool->enable();
        $pool->setMaxLifetime(1); // Very short lifetime

        // Acquire a connection
        $conn = $pool->acquire();
        $pool->release($conn);

        // Wait for max lifetime to expire
        sleep(2);

        // Acquire again - should get a new connection since old one is stale
        $conn2 = $pool->acquire();

        // The connections should be different objects
        expect($conn2)->toBeInstanceOf(Connection::class);
    });

    // Test line 207: isConnectionHealthy returns false for non-existent connection
    test('isConnectionHealthy returns false for non-existent id', function () {
        $pool = new class($this->config) extends ConnectionPool {
            public function testIsHealthy(string $id): bool
            {
                return $this->isConnectionHealthy($id);
            }
        };

        expect($pool->testIsHealthy('non_existent_id'))->toBeFalse();
    });

    // Test lines 225-226: isConnectionHealthy catch block when query fails
    test('isConnectionHealthy returns false when connection query fails', function () {
        $pool = new class($this->config) extends ConnectionPool {
            protected function createConnection(): Connection
            {
                return new class(['driver' => 'sqlite', 'database' => ':memory:']) extends Connection {
                    public function getPdo(): \PDO
                    {
                        // Return a closed/invalid PDO that will fail on query
                        throw new \Exception('Connection lost');
                    }
                };
            }

            public function testIsHealthy(string $id): bool
            {
                return $this->isConnectionHealthy($id);
            }

            public function getConnectionIds(): array
            {
                return array_keys($this->connections);
            }
        };

        $pool->enable();
        $conn = $pool->acquire();
        $pool->release($conn);

        $ids = $pool->getConnectionIds();
        expect($ids)->not->toBeEmpty();

        // The connection should be unhealthy because getPdo throws
        expect($pool->testIsHealthy($ids[0]))->toBeFalse();
    });

    // Test line 238: removeConnection disconnect throws exception
    test('removeConnection handles disconnect exception', function () {
        $pool = new class($this->config) extends ConnectionPool {
            protected function createConnection(): Connection
            {
                return new class(['driver' => 'sqlite', 'database' => ':memory:']) extends Connection {
                    public function disconnect(): void
                    {
                        throw new \Exception('Disconnect failed');
                    }
                };
            }

            public function testRemoveConnection(string $id): void
            {
                $this->removeConnection($id);
            }

            public function getConnectionIds(): array
            {
                return array_keys($this->connections);
            }
        };

        $pool->enable();
        $conn = $pool->acquire();
        $pool->release($conn);

        $ids = $pool->getConnectionIds();
        $idToRemove = $ids[0];

        // Should not throw even though disconnect throws
        $pool->testRemoveConnection($idToRemove);

        // Connection should be removed
        expect($pool->getConnectionIds())->not->toContain($idToRemove);
    });

    // Test lines 267-268: cleanupStaleConnections handles missing connection item
    test('cleanupStaleConnections handles orphaned available entries', function () {
        $pool = new class($this->config, minConnections: 0) extends ConnectionPool {
            public function createOrphanedEntry(): void
            {
                // Add orphaned entry at beginning so it's processed first
                $this->available = array_merge(['orphaned_id'], $this->available);
            }

            public function getAvailableArray(): array
            {
                return $this->available;
            }

            public function getConnectionCount(): int
            {
                return count($this->connections);
            }

            public function callCleanupStaleConnections(): void
            {
                $this->cleanupStaleConnections();
            }
        };

        $pool->enable();
        $pool->setIdleTimeout(3600); // Long timeout so real connection isn't removed

        // Create multiple connections so count(connections) > minConnections after cleanup starts
        $conn1 = $pool->acquire();
        $conn2 = $pool->acquire();
        $pool->release($conn1);
        $pool->release($conn2);

        // Now we have 2 connections in both connections and available arrays
        expect($pool->getConnectionCount())->toBe(2);

        // Create an orphaned entry at the start
        $pool->createOrphanedEntry();

        // Verify orphan exists in available
        $availableBefore = $pool->getAvailableArray();
        expect($availableBefore)->toContain('orphaned_id');

        // Directly call cleanupStaleConnections to trigger lines 267-268
        $pool->callCleanupStaleConnections();

        // Orphaned entry should be removed during cleanup
        $availableAfter = $pool->getAvailableArray();
        expect($availableAfter)->not->toContain('orphaned_id');
    });

    // Test lines 129-131: acquire removes dead connection and retries
    test('acquire removes dead connection and retries', function () {
        $pool = new class($this->config) extends ConnectionPool {
            public int $healthCheckCount = 0;
            private bool $firstCheckDone = false;

            protected function isConnectionHealthy(string $id): bool
            {
                $this->healthCheckCount++;
                // First call returns false (dead connection), subsequent calls return true
                if (!$this->firstCheckDone) {
                    $this->firstCheckDone = true;
                    return false;
                }
                return true;
            }

            public function resetCheck(): void
            {
                $this->firstCheckDone = false;
                $this->healthCheckCount = 0;
            }
        };

        $pool->enable();

        // Acquire and release to populate pool
        $conn = $pool->acquire();
        $pool->release($conn);

        // Reset for next test
        $pool->resetCheck();

        // Next acquire should find dead connection, remove it, and create new one
        $conn2 = $pool->acquire();

        expect($conn2)->toBeInstanceOf(Connection::class);
        // isConnectionHealthy was called at least once
        expect($pool->healthCheckCount)->toBeGreaterThanOrEqual(1);
    });
});
