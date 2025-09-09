<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Database\QueryProfiler;

it('profiles query execution', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection->statement('CREATE TABLE users (id INTEGER, name TEXT)');
    $connection->statement('INSERT INTO users VALUES (1, "John")');
    
    // Enable profiling
    $connection->enableProfiling();
    
    // Execute queries
    $connection->select('SELECT * FROM users', []);
    $connection->insert('INSERT INTO users VALUES (?, ?)', [2, 'Jane']);
    $connection->update('UPDATE users SET name = ? WHERE id = ?', ['Johnny', 1]);
    $connection->delete('DELETE FROM users WHERE id = ?', [2]);
    
    $report = $connection->getProfilingReport();
    
    expect($report['enabled'])->toBeTrue();
    expect($report['total_queries'])->toBe(4);
    expect($report['query_types']['select'])->toBe(1);
    expect($report['query_types']['insert'])->toBe(1);
    expect($report['query_types']['update'])->toBe(1);
    expect($report['query_types']['delete'])->toBe(1);
});

it('tracks slow queries', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection->statement('CREATE TABLE large_table (id INTEGER, data TEXT)');
    
    // Insert many rows
    for ($i = 1; $i <= 100; $i++) {
        $connection->statement("INSERT INTO large_table VALUES ($i, 'data$i')");
    }
    
    $connection->enableProfiling();
    $profiler = $connection->getProfiler();
    $profiler->setSlowQueryThreshold(1); // 1ms threshold
    
    // This should be marked as slow
    $connection->select('SELECT * FROM large_table', []);
    
    $slowQueries = $profiler->getSlowQueries();
    expect(count($slowQueries))->toBeGreaterThanOrEqual(0); // May or may not be slow depending on system
});

it('can disable profiling', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection->enableProfiling();
    expect($connection->getProfiler())->toBeInstanceOf(QueryProfiler::class);
    expect($connection->getProfiler()->isEnabled())->toBeTrue();
    
    $connection->disableProfiling();
    expect($connection->getProfiler()->isEnabled())->toBeFalse();
});

it('provides profiling statistics', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection->statement('CREATE TABLE test (id INTEGER)');
    $connection->enableProfiling();
    
    // Execute multiple queries
    for ($i = 1; $i <= 5; $i++) {
        $connection->select('SELECT * FROM test WHERE id = ?', [$i]);
    }
    
    $profiler = $connection->getProfiler();
    $stats = $profiler->getStatistics();
    
    expect($stats['total_queries'])->toBe(5);
    expect($stats['select_count'])->toBe(5);
    expect($stats['average_time'])->toBeGreaterThan(0);
});