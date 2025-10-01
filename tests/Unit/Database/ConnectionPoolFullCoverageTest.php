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
});
