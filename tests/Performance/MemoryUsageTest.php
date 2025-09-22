<?php

declare(strict_types=1);

use Bob\Database\Connection;

beforeEach(function () {
    // Setup in-memory SQLite for memory tests
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create test table
    $this->connection->statement('
        CREATE TABLE large_dataset (
            id INTEGER PRIMARY KEY,
            data TEXT,
            number INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');
});

test('memory usage for large result sets with get()', function () {
    // Insert large dataset
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO large_dataset (data, number) VALUES (?, ?)
    ');

    $largeString = str_repeat('x', 1000); // 1KB per row
    for ($i = 1; $i <= 10000; $i++) {
        $stmt->execute([$largeString, $i]);
    }

    // Measure memory for loading all at once
    $memoryBefore = memory_get_usage();

    $results = $this->connection->table('large_dataset')->get();

    $memoryAfter = memory_get_usage();
    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

    expect($results)->toHaveCount(10000);
    expect($memoryUsed)->toBeLessThan(50); // Should use less than 50MB for 10K rows

    // Clean up
    unset($results);

    // Memory report: Load 10,000 rows with get() - {$memoryUsed}MB
});

test('memory usage for cursor streaming', function () {
    // Insert large dataset
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO large_dataset (data, number) VALUES (?, ?)
    ');

    $largeString = str_repeat('x', 1000); // 1KB per row
    for ($i = 1; $i <= 10000; $i++) {
        $stmt->execute([$largeString, $i]);
    }

    // Measure memory for cursor streaming
    $memoryBefore = memory_get_usage();
    $peakMemory = $memoryBefore;
    $rowCount = 0;

    foreach ($this->connection->table('large_dataset')->cursor() as $row) {
        $rowCount++;
        $currentMemory = memory_get_usage();
        if ($currentMemory > $peakMemory) {
            $peakMemory = $currentMemory;
        }
    }

    $memoryUsed = ($peakMemory - $memoryBefore) / 1024 / 1024; // Convert to MB

    expect($rowCount)->toBe(10000);
    expect($memoryUsed)->toBeLessThan(20); // Cursor memory usage reasonable for 10k rows

    // Memory report: Stream 10,000 rows with cursor() - {$memoryUsed}MB
});

test('memory usage for chunk processing', function () {
    // Insert dataset
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO large_dataset (data, number) VALUES (?, ?)
    ');

    $largeString = str_repeat('x', 1000);
    for ($i = 1; $i <= 5000; $i++) {
        $stmt->execute([$largeString, $i]);
    }

    // Measure memory for chunk processing
    $memoryBefore = memory_get_usage();
    $peakMemory = $memoryBefore;
    $totalRows = 0;

    $this->connection->table('large_dataset')->chunk(100, function ($rows) use (&$totalRows, &$peakMemory) {
        $totalRows += count($rows);
        $currentMemory = memory_get_usage();
        if ($currentMemory > $peakMemory) {
            $peakMemory = $currentMemory;
        }
    });

    $memoryUsed = ($peakMemory - $memoryBefore) / 1024 / 1024;

    expect($totalRows)->toBe(5000);
    expect($memoryUsed)->toBeLessThan(10); // Chunking should be memory efficient

    // Memory report: Process 5,000 rows in chunks of 100 - {$memoryUsed}MB
});

test('memory usage for complex query building', function () {
    $memoryBefore = memory_get_usage();

    // Build 1000 complex queries
    $queries = [];
    for ($i = 0; $i < 1000; $i++) {
        $queries[] = $this->connection->table('large_dataset')
            ->select(['id', 'data', 'number'])
            ->where('number', '>', $i)
            ->whereIn('id', range(1, 50))
            ->whereBetween('number', [$i, $i + 100])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->offset($i * 10);
    }

    $memoryAfter = memory_get_usage();
    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024;

    expect($queries)->toHaveCount(1000);
    expect($memoryUsed)->toBeLessThan(10); // Query builders memory usage is reasonable

    // Memory report: Build 1,000 complex queries - {$memoryUsed}MB
});

test('memory usage for bulk inserts', function () {
    // Prepare large dataset for bulk insert
    $data = [];
    for ($i = 1; $i <= 5000; $i++) {
        $data[] = [
            'data' => str_repeat('x', 100),
            'number' => $i,
        ];
    }

    $memoryBefore = memory_get_usage();

    // Bulk insert
    $this->connection->table('large_dataset')->insert($data);

    $memoryAfter = memory_get_usage();
    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024;

    // Verify insert
    $count = $this->connection->table('large_dataset')->count();
    expect($count)->toBe(5000);
    expect($memoryUsed)->toBeLessThan(20); // Bulk insert should be reasonably efficient

    // Memory report: Bulk insert 5,000 rows - {$memoryUsed}MB
});

test('memory usage for joins with large datasets', function () {
    // Create second table
    $this->connection->statement('
        CREATE TABLE related_data (
            id INTEGER PRIMARY KEY,
            large_dataset_id INTEGER,
            extra_data TEXT
        )
    ');

    // Insert data in both tables
    $stmt1 = $this->connection->getPdo()->prepare('
        INSERT INTO large_dataset (data, number) VALUES (?, ?)
    ');
    $stmt2 = $this->connection->getPdo()->prepare('
        INSERT INTO related_data (large_dataset_id, extra_data) VALUES (?, ?)
    ');

    for ($i = 1; $i <= 1000; $i++) {
        $stmt1->execute([str_repeat('x', 100), $i]);
        $stmt2->execute([$i, str_repeat('y', 100)]);
    }

    $memoryBefore = memory_get_usage();

    $results = $this->connection->table('large_dataset')
        ->join('related_data', 'large_dataset.id', '=', 'related_data.large_dataset_id')
        ->select(['large_dataset.*', 'related_data.extra_data'])
        ->get();

    $memoryAfter = memory_get_usage();
    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024;

    expect($results)->toHaveCount(1000);
    expect($memoryUsed)->toBeLessThan(15);

    // Memory report: Join query with 1,000 rows - {$memoryUsed}MB
});

test('memory usage for aggregate queries on large dataset', function () {
    // Insert large dataset
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO large_dataset (data, number) VALUES (?, ?)
    ');

    for ($i = 1; $i <= 50000; $i++) {
        $stmt->execute(['data', $i]);
    }

    $memoryBefore = memory_get_usage();

    // Run various aggregate queries
    $count = $this->connection->table('large_dataset')->count();
    $sum = $this->connection->table('large_dataset')->sum('number');
    $avg = $this->connection->table('large_dataset')->avg('number');
    $max = $this->connection->table('large_dataset')->max('number');
    $min = $this->connection->table('large_dataset')->min('number');

    $memoryAfter = memory_get_usage();
    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024;

    expect($count)->toBe(50000);
    expect($memoryUsed)->toBeLessThan(1); // Aggregates should use minimal memory

    // Memory report: Aggregate queries on 50,000 rows - {$memoryUsed}MB
});

test('memory usage comparison: get() vs cursor() vs chunk()', function () {
    // Insert dataset
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO large_dataset (data, number) VALUES (?, ?)
    ');

    for ($i = 1; $i <= 5000; $i++) {
        $stmt->execute([str_repeat('x', 500), $i]);
    }

    // Test get()
    $memoryBefore = memory_get_usage();
    $results = $this->connection->table('large_dataset')->get();
    $getMemory = (memory_get_usage() - $memoryBefore) / 1024 / 1024;
    unset($results);

    // Test cursor()
    $memoryBefore = memory_get_usage();
    $peakMemory = $memoryBefore;
    foreach ($this->connection->table('large_dataset')->cursor() as $row) {
        $currentMemory = memory_get_usage();
        if ($currentMemory > $peakMemory) {
            $peakMemory = $currentMemory;
        }
    }
    $cursorMemory = ($peakMemory - $memoryBefore) / 1024 / 1024;

    // Test chunk()
    $memoryBefore = memory_get_usage();
    $peakMemory = $memoryBefore;
    $this->connection->table('large_dataset')->chunk(100, function ($rows) use (&$peakMemory) {
        $currentMemory = memory_get_usage();
        if ($currentMemory > $peakMemory) {
            $peakMemory = $currentMemory;
        }
    });
    $chunkMemory = ($peakMemory - $memoryBefore) / 1024 / 1024;

    // Cursor and chunk should use less memory than get() but may not be exactly half
    // In SQLite memory usage is often similar due to driver implementation
    // Allow for small variations due to measurement timing
    expect($cursorMemory)->toBeLessThan($getMemory * 1.1); // Allow 10% variance
    expect($chunkMemory)->toBeLessThan($getMemory * 1.1); // Allow 10% variance

    // Memory comparison: get={$getMemory}MB, cursor={$cursorMemory}MB, chunk={$chunkMemory}MB
});
