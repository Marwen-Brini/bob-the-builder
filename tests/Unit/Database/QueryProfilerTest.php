<?php

declare(strict_types=1);

use Bob\Database\QueryProfiler;

it('tracks queries when disabled', function () {
    $profiler = new QueryProfiler();
    
    // Start should return empty string when disabled
    $id = $profiler->start('SELECT * FROM users');
    expect($id)->toBe('');
    
    // End should not throw error with empty ID
    $profiler->end('');
    
    // Should have no profiles
    expect($profiler->getProfiles())->toBe([]);
});

it('properly ends non-existent profile', function () {
    $profiler = new QueryProfiler();
    $profiler->enable();
    
    // Ending non-existent profile should not throw error
    $profiler->end('non_existent_id');
    
    expect($profiler->getProfiles())->toBe([]);
});

it('calculates average time with no queries', function () {
    $profiler = new QueryProfiler();
    
    $stats = $profiler->getStatistics();
    expect($stats['average_time'])->toBe(0);
    expect($stats['total_queries'])->toBe(0);
});

it('identifies other query types', function () {
    $profiler = new QueryProfiler();
    $profiler->enable();
    
    // Test various SQL commands
    $queries = [
        'CREATE TABLE test (id INT)',
        'DROP TABLE test',
        'ALTER TABLE test ADD COLUMN name VARCHAR(255)',
        'TRUNCATE TABLE test',
        'BEGIN',
        'COMMIT',
        'ROLLBACK',
    ];
    
    foreach ($queries as $query) {
        $id = $profiler->start($query);
        $profiler->end($id);
    }
    
    $profiles = $profiler->getProfiles();
    foreach ($profiles as $profile) {
        expect($profile['type'])->toBe('other');
    }
    
    // Statistics should not count "other" queries in specific counts
    $stats = $profiler->getStatistics();
    expect($stats['select_count'])->toBe(0);
    expect($stats['insert_count'])->toBe(0);
    expect($stats['update_count'])->toBe(0);
    expect($stats['delete_count'])->toBe(0);
    expect($stats['total_queries'])->toBe(count($queries));
});

it('tracks slow queries with exact threshold', function () {
    $profiler = new QueryProfiler();
    $profiler->enable();
    $profiler->setSlowQueryThreshold(0); // Everything is slow
    
    $id = $profiler->start('SELECT * FROM users');
    usleep(1000); // Sleep for 1ms
    $profiler->end($id);
    
    $slowQueries = $profiler->getSlowQueries();
    expect(count($slowQueries))->toBe(1);
    expect($slowQueries[0]['query'])->toBe('SELECT * FROM users');
    expect($slowQueries[0]['time'])->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/');
});

it('gets slowest queries sorted by duration', function () {
    $profiler = new QueryProfiler();
    $profiler->enable();
    $profiler->setSlowQueryThreshold(0); // Everything is slow
    
    // Create queries with different durations
    $queries = [
        'SELECT * FROM users' => 3000,
        'UPDATE users SET name = "test"' => 1000,
        'DELETE FROM users' => 5000,
        'INSERT INTO users VALUES (1)' => 2000,
    ];
    
    foreach ($queries as $query => $sleep) {
        $id = $profiler->start($query);
        usleep($sleep);
        $profiler->end($id);
    }
    
    $slowest = $profiler->getSlowestQueries(2);
    expect(count($slowest))->toBe(2);
    // Should be sorted by duration descending
    expect($slowest[0]['query'])->toBe('DELETE FROM users');
    expect($slowest[1]['query'])->toBe('SELECT * FROM users');
});

it('limits slowest queries result', function () {
    $profiler = new QueryProfiler();
    $profiler->enable();
    $profiler->setSlowQueryThreshold(0);
    
    // Create 20 slow queries
    for ($i = 1; $i <= 20; $i++) {
        $id = $profiler->start("SELECT * FROM table_$i");
        usleep(1000); // Ensure it's marked as slow
        $profiler->end($id);
    }
    
    // Get only top 5
    $slowest = $profiler->getSlowestQueries(5);
    expect(count($slowest))->toBe(5);
    
    // Get default top 10
    $slowest = $profiler->getSlowestQueries();
    expect(count($slowest))->toBe(10);
});

it('handles edge case with setSlowQueryThreshold', function () {
    $profiler = new QueryProfiler();
    
    // Should enforce minimum of 1ms
    $profiler->setSlowQueryThreshold(-100);
    
    // Check it was set to 1 (we'll verify this by the behavior)
    $profiler->enable();
    $id = $profiler->start('SELECT 1');
    $profiler->end($id);
    
    // Query executed instantly should not be marked as slow with 1ms threshold
    $slowQueries = $profiler->getSlowQueries();
    expect(count($slowQueries))->toBe(0);
});

it('provides comprehensive report', function () {
    $profiler = new QueryProfiler();
    $profiler->enable();
    $profiler->setSlowQueryThreshold(0);
    
    // Execute various queries
    $id = $profiler->start('SELECT * FROM users');
    usleep(1000);
    $profiler->end($id);
    
    $id = $profiler->start('INSERT INTO users VALUES (1)');
    usleep(1000);
    $profiler->end($id);
    
    $report = $profiler->getReport();
    
    expect($report)->toHaveKeys([
        'enabled',
        'total_queries',
        'total_time_ms',
        'average_time_ms',
        'query_types',
        'slow_queries',
        'slow_query_threshold_ms',
        'memory_peak',
    ]);
    
    expect($report['enabled'])->toBeTrue();
    expect($report['total_queries'])->toBe(2);
    expect($report['query_types']['select'])->toBe(1);
    expect($report['query_types']['insert'])->toBe(1);
    expect($report['slow_queries'])->toBe(2);
    expect($report['slow_query_threshold_ms'])->toBe(1); // Min is 1ms
    expect($report['memory_peak'])->toBeGreaterThan(0);
});

it('tracks memory usage in profiles', function () {
    $profiler = new QueryProfiler();
    $profiler->enable();
    
    $id = $profiler->start('SELECT * FROM users');
    // Allocate some memory
    $data = str_repeat('x', 10000);
    $profiler->end($id);
    
    $profiles = $profiler->getProfiles();
    expect($profiles[$id]['memory_used'])->toBeGreaterThanOrEqual(0);
    expect($profiles[$id]['start_memory'])->toBeGreaterThan(0);
    expect($profiles[$id]['end_memory'])->toBeGreaterThan(0);
});

it('resets all data correctly', function () {
    $profiler = new QueryProfiler();
    $profiler->enable();
    $profiler->setSlowQueryThreshold(0);
    
    // Add some data
    $id = $profiler->start('SELECT * FROM users');
    usleep(1000); // Ensure it's marked as slow
    $profiler->end($id);
    
    // Verify data exists
    expect($profiler->getProfiles())->not->toBe([]);
    expect($profiler->getSlowQueries())->not->toBe([]);
    
    // Reset
    $profiler->reset();
    
    // Verify all data cleared
    expect($profiler->getProfiles())->toBe([]);
    expect($profiler->getSlowQueries())->toBe([]);
    
    $stats = $profiler->getStatistics();
    expect($stats['total_queries'])->toBe(0);
    expect($stats['total_time'])->toBe(0);
    expect($stats['select_count'])->toBe(0);
});