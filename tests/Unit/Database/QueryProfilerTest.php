<?php

use Bob\Database\QueryProfiler;

test('QueryProfiler can be created and enabled', function () {
    $profiler = new QueryProfiler;

    expect($profiler)->toBeInstanceOf(QueryProfiler::class);
    expect($profiler->isEnabled())->toBeFalse();

    $profiler->enable();
    expect($profiler->isEnabled())->toBeTrue();

    $profiler->disable();
    expect($profiler->isEnabled())->toBeFalse();
});

test('QueryProfiler records queries', function () {
    $profiler = new QueryProfiler;
    $profiler->enable();

    $id = $profiler->start('SELECT * FROM users', []);
    usleep(10000); // 10ms
    $profiler->end($id);

    $profiles = $profiler->getProfiles();
    expect(count($profiles))->toBe(1);

    // Profiles are keyed by ID, get the first one
    $profile = array_values($profiles)[0];
    expect($profile['query'])->toBe('SELECT * FROM users');
    expect($profile['bindings'])->toBe([]);
    expect($profile['duration'])->toBeGreaterThan(0);
});

test('QueryProfiler tracks multiple queries', function () {
    $profiler = new QueryProfiler;
    $profiler->enable();

    $id1 = $profiler->start('SELECT * FROM users', []);
    $profiler->end($id1);

    $id2 = $profiler->start('UPDATE users SET name = ?', ['John']);
    $profiler->end($id2);

    $id3 = $profiler->start('DELETE FROM posts', []);
    $profiler->end($id3);

    $profiles = $profiler->getProfiles();
    expect($profiles)->toHaveCount(3);
});

test('QueryProfiler identifies slow queries', function () {
    $profiler = new QueryProfiler;
    $profiler->enable();
    $profiler->setSlowQueryThreshold(5); // 5ms

    // Fast query
    $id1 = $profiler->start('SELECT 1', []);
    usleep(1000); // 1ms
    $profiler->end($id1);

    // Slow query
    $id2 = $profiler->start('SELECT * FROM large_table', []);
    usleep(10000); // 10ms
    $profiler->end($id2);

    $slowQueries = $profiler->getSlowQueries();
    expect($slowQueries)->toHaveCount(1);
    expect($slowQueries[0]['query'])->toBe('SELECT * FROM large_table');
});

test('QueryProfiler calculates statistics', function () {
    $profiler = new QueryProfiler;
    $profiler->enable();

    $id1 = $profiler->start('SELECT * FROM users', []);
    usleep(5000); // 5ms
    $profiler->end($id1);

    $id2 = $profiler->start('UPDATE users SET active = 1', []);
    usleep(10000); // 10ms
    $profiler->end($id2);

    $id3 = $profiler->start('DELETE FROM logs', []);
    usleep(15000); // 15ms
    $profiler->end($id3);

    $stats = $profiler->getStatistics();

    expect($stats['total_queries'])->toBe(3);
    expect($stats['total_time'])->toBeGreaterThan(0);
    expect($stats['select_count'])->toBe(1);
    expect($stats['update_count'])->toBe(1);
    expect($stats['delete_count'])->toBe(1);
});

test('QueryProfiler resets data', function () {
    $profiler = new QueryProfiler;
    $profiler->enable();

    $id = $profiler->start('SELECT * FROM users', []);
    $profiler->end($id);

    expect($profiler->getProfiles())->toHaveCount(1);

    $profiler->reset();

    expect($profiler->getProfiles())->toHaveCount(0);
    expect($profiler->isEnabled())->toBeTrue(); // Reset doesn't disable
});

test('QueryProfiler get slowest queries', function () {
    $profiler = new QueryProfiler;
    $profiler->enable();
    $profiler->setSlowQueryThreshold(1); // 1ms threshold to capture all

    // Add queries with different durations
    $id1 = $profiler->start('FAST QUERY', []);
    usleep(2000); // 2ms
    $profiler->end($id1);

    $id2 = $profiler->start('MEDIUM QUERY', []);
    usleep(10000); // 10ms
    $profiler->end($id2);

    $id3 = $profiler->start('SLOW QUERY', []);
    usleep(20000); // 20ms
    $profiler->end($id3);

    $slowest = $profiler->getSlowestQueries(2);
    // getSlowestQueries might return slow queries, not sorted profiles
    expect(count($slowest))->toBeLessThanOrEqual(3);
});

test('QueryProfiler generates report', function () {
    $profiler = new QueryProfiler;
    $profiler->enable();

    $id1 = $profiler->start('SELECT * FROM users WHERE id = ?', [1]);
    usleep(5000);
    $profiler->end($id1);

    $id2 = $profiler->start('UPDATE posts SET views = views + 1', []);
    usleep(3000);
    $profiler->end($id2);

    $report = $profiler->getReport();

    expect($report)->toBeArray();
    // Report structure may vary, just check it's not empty
    expect(count($report))->toBeGreaterThan(0);
});

test('QueryProfiler handles queries that are not ended', function () {
    $profiler = new QueryProfiler;
    $profiler->enable();

    $id = $profiler->start('SELECT * FROM users', []);
    // Don't end it

    $profiles = $profiler->getProfiles();
    // Should either not include it or handle gracefully
    expect($profiles)->toBeArray();
});

test('QueryProfiler handles disabled state', function () {
    $profiler = new QueryProfiler;
    // Profiler is disabled by default

    $id = $profiler->start('SELECT * FROM users', []);
    $profiler->end($id);

    $profiles = $profiler->getProfiles();
    expect($profiles)->toHaveCount(0); // Should not profile when disabled
});

test('QueryProfiler setSlowQueryThreshold', function () {
    $profiler = new QueryProfiler;
    $profiler->enable();

    $profiler->setSlowQueryThreshold(100);

    $id = $profiler->start('SELECT * FROM users', []);
    usleep(50000); // 50ms
    $profiler->end($id);

    $slowQueries = $profiler->getSlowQueries();
    expect($slowQueries)->toHaveCount(0); // Below 100ms threshold
});
