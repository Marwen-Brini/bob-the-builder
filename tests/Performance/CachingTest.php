<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Cache\QueryCache;

it('caches prepared statements', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection->statement('CREATE TABLE test (id INTEGER, name TEXT)');
    $connection->statement('INSERT INTO test VALUES (1, "test")');
    
    // Enable statement caching
    $connection->enableStatementCaching();
    
    // Execute same query multiple times
    $query = 'SELECT * FROM test WHERE id = ?';
    $connection->select($query, [1]);
    $connection->select($query, [1]);
    
    // Check cache size
    expect($connection->getStatementCacheSize())->toBeGreaterThan(0);
    
    // Clear cache
    $connection->clearStatementCache();
    expect($connection->getStatementCacheSize())->toBe(0);
});

it('caches query results', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection->statement('CREATE TABLE users (id INTEGER, name TEXT)');
    $connection->statement('INSERT INTO users VALUES (1, "John")');
    
    // Enable query cache
    $connection->enableQueryCache();
    
    // First query - not cached
    $result1 = $connection->select('SELECT * FROM users WHERE id = ?', [1]);
    
    // Second query - should be cached
    $result2 = $connection->select('SELECT * FROM users WHERE id = ?', [1]);
    
    expect($result1)->toBe($result2);
    
    $cache = $connection->getQueryCache();
    expect($cache)->toBeInstanceOf(QueryCache::class);
    expect($cache->size())->toBeGreaterThan(0);
    
    // Flush cache
    $connection->flushQueryCache();
    expect($cache->size())->toBe(0);
});

it('respects max cached statements limit', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection->statement('CREATE TABLE test (id INTEGER)');
    
    // Set low limit
    $connection->setMaxCachedStatements(2);
    $connection->enableStatementCaching();
    
    // Execute different queries
    $connection->select('SELECT * FROM test WHERE id = 1', []);
    $connection->select('SELECT * FROM test WHERE id = 2', []);
    $connection->select('SELECT * FROM test WHERE id = 3', []);
    
    // Should only cache 2 statements
    expect($connection->getStatementCacheSize())->toBeLessThanOrEqual(2);
});

it('can disable statement caching', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection->statement('CREATE TABLE test (id INTEGER)');
    
    // Enable then disable
    $connection->enableStatementCaching();
    $connection->select('SELECT * FROM test', []);
    expect($connection->getStatementCacheSize())->toBeGreaterThan(0);
    
    $connection->disableStatementCaching();
    expect($connection->getStatementCacheSize())->toBe(0);
});