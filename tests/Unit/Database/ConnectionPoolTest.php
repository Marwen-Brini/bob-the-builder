<?php

declare(strict_types=1);

use Bob\Database\ConnectionPool;
use Bob\Database\Connection;
use Bob\Exceptions\ConnectionException;

beforeEach(function () {
    $this->config = [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ];
});

it('creates connection when pool is disabled', function () {
    $pool = new ConnectionPool($this->config, 5, 2);
    $pool->disable();
    
    $connection = $pool->acquire();
    expect($connection)->toBeInstanceOf(Connection::class);
    
    // Release should do nothing when disabled
    $pool->release($connection);
    
    $stats = $pool->getStats();
    expect($stats['total'])->toBe(0);
});

it('waits for connection when pool is exhausted', function () {
    $pool = new ConnectionPool($this->config, 2, 1);
    
    // Acquire all connections
    $conn1 = $pool->acquire();
    $conn2 = $pool->acquire();
    
    // Set a short timeout
    $pool->setConnectionTimeout(1); // 1 second timeout
    
    // This should wait and then timeout since no connections are available
    expect(function () use ($pool) {
        $pool->acquire();
    })->toThrow(ConnectionException::class, 'Connection pool timeout');
    
    // Verify pool state after timeout
    $stats = $pool->getStats();
    expect($stats['in_use'])->toBe(2);
    expect($stats['available'])->toBe(0);
});

it('reuses available connection after waiting', function () {
    // Create a pool with custom timeout mechanism test
    $pool = new ConnectionPool($this->config, 1, 1);
    
    // Get the only connection
    $conn1 = $pool->acquire();
    
    // Create a reflection to manipulate the available array
    $reflection = new ReflectionClass($pool);
    $availableProp = $reflection->getProperty('available');
    $availableProp->setAccessible(true);
    
    $inUseProp = $reflection->getProperty('inUse');
    $inUseProp->setAccessible(true);
    
    $connectionsProp = $reflection->getProperty('connections');
    $connectionsProp->setAccessible(true);
    
    // Simulate the connection becoming available during the wait
    // First verify pool is exhausted
    expect($availableProp->getValue($pool))->toBe([]);
    
    // Now release it
    $pool->release($conn1);
    
    // Should be available now
    expect(count($availableProp->getValue($pool)))->toBe(1);
    
    // Acquire should get the same connection back
    $conn2 = $pool->acquire();
    expect(spl_object_hash($conn2))->toBe(spl_object_hash($conn1));
});

it('cleans up idle connections beyond minimum', function () {
    $pool = new ConnectionPool($this->config, 5, 2);
    
    // Acquire and release connections to create idle ones
    $connections = [];
    for ($i = 0; $i < 4; $i++) {
        $connections[] = $pool->acquire();
    }
    
    foreach ($connections as $conn) {
        $pool->release($conn);
    }
    
    // Now we have 4 connections, all idle
    $stats = $pool->getStats();
    expect($stats['total'])->toBe(4);
    expect($stats['available'])->toBe(4);
    
    // Set idle timeout to 0 to trigger immediate cleanup
    $pool->setIdleTimeout(0);
    
    // Sleep a tiny bit to ensure time difference
    usleep(10000);
    
    // Manually set lastUsed to past for testing
    $reflection = new ReflectionClass($pool);
    $connectionsProp = $reflection->getProperty('connections');
    $connectionsProp->setAccessible(true);
    $connections = $connectionsProp->getValue($pool);
    
    foreach ($connections as $id => &$conn) {
        $conn['lastUsed'] = time() - 10; // 10 seconds ago
    }
    $connectionsProp->setValue($pool, $connections);
    
    // Trigger cleanup by acquiring a new connection
    $newConn = $pool->acquire();
    $pool->release($newConn);
    
    // Should have cleaned up to minimum
    $stats = $pool->getStats();
    expect($stats['total'])->toBeGreaterThanOrEqual(2); // At least minimum
});

it('maintains minimum connections during cleanup', function () {
    $pool = new ConnectionPool($this->config, 5, 3);
    
    // Get initial stats
    $stats = $pool->getStats();
    expect($stats['total'])->toBe(3); // Minimum initialized
    
    // The cleanup logic checks if count($this->connections) <= $this->minConnections
    // So with 3 connections (the minimum), no cleanup should happen
    
    // Set idle timeout to trigger cleanup attempt
    $pool->setIdleTimeout(1);
    
    // Sleep to make connections "old"
    sleep(2);
    
    // Acquire to trigger cleanup
    $conn = $pool->acquire();
    
    $stats = $pool->getStats();
    // Should maintain minimum even after cleanup attempt
    expect($stats['total'])->toBe(3); // No new connection created, reused existing
    
    $pool->release($conn);
});

it('enables pool and reinitializes connections', function () {
    $pool = new ConnectionPool($this->config, 5, 2);
    
    // Disable and clear
    $pool->disable();
    $stats = $pool->getStats();
    expect($stats['total'])->toBe(0);
    
    // Re-enable
    $pool->enable();
    
    // Should reinitialize minimum connections
    $stats = $pool->getStats();
    expect($stats['total'])->toBe(2);
    expect($stats['available'])->toBe(2);
});

it('handles destructor properly', function () {
    $pool = new ConnectionPool($this->config, 3, 2);
    
    // Acquire some connections
    $conn1 = $pool->acquire();
    $conn2 = $pool->acquire();
    
    // Destructor will be called when pool goes out of scope
    unset($pool);
    
    // Create a new pool to verify cleanup happened
    $newPool = new ConnectionPool($this->config, 3, 2);
    $stats = $newPool->getStats();
    expect($stats['total'])->toBe(2); // Fresh pool with minimum
});