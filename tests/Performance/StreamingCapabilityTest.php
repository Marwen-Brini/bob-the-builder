<?php

declare(strict_types=1);

use Bob\Database\Connection;

beforeEach(function() {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Create test table with indexes for better performance
    $this->connection->statement('
        CREATE TABLE streaming_test (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_name TEXT NOT NULL,
            email TEXT NOT NULL,
            status TEXT,
            score INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
    // Add indexes for better query performance
    $this->connection->statement('CREATE INDEX idx_status ON streaming_test(status)');
    $this->connection->statement('CREATE INDEX idx_score ON streaming_test(score)');
});

test('can stream 10,000+ rows using cursor', function() {
    // Insert 15,000 rows
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO streaming_test (user_name, email, status, score) VALUES (?, ?, ?, ?)
    ');
    
    echo "\nInserting 15,000 test rows...\n";
    for ($i = 1; $i <= 15000; $i++) {
        $stmt->execute([
            "User $i",
            "user$i@example.com",
            ['active', 'inactive', 'pending'][rand(0, 2)],
            rand(0, 100)
        ]);
    }
    
    // Test streaming with cursor
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    $rowCount = 0;
    $peakMemory = $startMemory;
    
    foreach ($this->connection->table('streaming_test')->cursor() as $row) {
        $rowCount++;
        
        // Simulate some processing
        if (isset($row->user_name) && isset($row->email)) {
            $processed = [
                'id' => $row->id,
                'name' => strtoupper($row->user_name),
                'domain' => explode('@', $row->email)[1] ?? ''
            ];
        }
        
        // Track peak memory
        $currentMemory = memory_get_usage();
        if ($currentMemory > $peakMemory) {
            $peakMemory = $currentMemory;
        }
        
        // Show progress every 5000 rows
        if ($rowCount % 5000 === 0) {
            $memoryUsed = ($currentMemory - $startMemory) / 1024 / 1024;
            echo "Processed $rowCount rows, Memory: " . round($memoryUsed, 2) . "MB\n";
        }
    }
    
    $totalTime = microtime(true) - $startTime;
    $totalMemory = ($peakMemory - $startMemory) / 1024 / 1024;
    
    expect($rowCount)->toBe(15000);
    expect($totalMemory)->toBeLessThan(10); // Should use less than 10MB for streaming
    
    echo "\nStreaming Results:\n";
    echo "- Rows processed: $rowCount\n";
    echo "- Time taken: " . round($totalTime, 2) . " seconds\n";
    echo "- Peak memory: " . round($totalMemory, 2) . "MB\n";
    echo "- Rows per second: " . round($rowCount / $totalTime) . "\n";
});

test('can stream 10,000+ rows with filtering', function() {
    // Insert 12,000 rows
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO streaming_test (user_name, email, status, score) VALUES (?, ?, ?, ?)
    ');
    
    for ($i = 1; $i <= 12000; $i++) {
        $stmt->execute([
            "User $i",
            "user$i@example.com",
            ['active', 'inactive', 'pending'][rand(0, 2)],
            rand(0, 100)
        ]);
    }
    
    // Stream with WHERE conditions
    $startTime = microtime(true);
    $rowCount = 0;
    
    $query = $this->connection->table('streaming_test')
        ->where('status', 'active')
        ->where('score', '>', 50)
        ->orderBy('score', 'desc');
    
    foreach ($query->cursor() as $row) {
        $rowCount++;
    }
    
    $totalTime = microtime(true) - $startTime;
    
    expect($rowCount)->toBeGreaterThan(1000); // Should have filtered results
    expect($totalTime)->toBeLessThan(5); // Should complete within 5 seconds
    
    echo "\nFiltered Streaming Results:\n";
    echo "- Rows processed: $rowCount\n";
    echo "- Time taken: " . round($totalTime, 2) . " seconds\n";
});

test('can chunk process 10,000+ rows', function() {
    // Insert 10,000 rows
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO streaming_test (user_name, email, status, score) VALUES (?, ?, ?, ?)
    ');
    
    for ($i = 1; $i <= 10000; $i++) {
        $stmt->execute([
            "User $i",
            "user$i@example.com",
            ['active', 'inactive'][rand(0, 1)],
            rand(0, 100)
        ]);
    }
    
    // Process in chunks of 500
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    $totalRows = 0;
    $chunkCount = 0;
    $peakMemory = $startMemory;
    
    $this->connection->table('streaming_test')->chunk(500, function($rows) use (&$totalRows, &$chunkCount, &$peakMemory) {
        $chunkCount++;
        $totalRows += count($rows);
        
        // Simulate processing
        foreach ($rows as $row) {
            if (isset($row->user_name)) {
                $processed = strtoupper($row->user_name);
            }
        }
        
        $currentMemory = memory_get_usage();
        if ($currentMemory > $peakMemory) {
            $peakMemory = $currentMemory;
        }
        
        echo "Chunk $chunkCount: Processed " . count($rows) . " rows\n";
    });
    
    $totalTime = microtime(true) - $startTime;
    $totalMemory = ($peakMemory - $startMemory) / 1024 / 1024;
    
    expect($totalRows)->toBe(10000);
    expect($chunkCount)->toBe(20); // 10000 / 500 = 20 chunks
    expect($totalMemory)->toBeLessThan(15); // Should use reasonable memory
    
    echo "\nChunk Processing Results:\n";
    echo "- Total rows: $totalRows\n";
    echo "- Chunks processed: $chunkCount\n";
    echo "- Time taken: " . round($totalTime, 2) . " seconds\n";
    echo "- Peak memory: " . round($totalMemory, 2) . "MB\n";
});

test('can handle 50,000+ rows with cursor efficiently', function() {
    // This is a stress test - insert 50,000 rows
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO streaming_test (user_name, email, status, score) VALUES (?, ?, ?, ?)
    ');
    
    echo "\nInserting 50,000 test rows (this may take a moment)...\n";
    $this->connection->beginTransaction();
    
    for ($i = 1; $i <= 50000; $i++) {
        $stmt->execute([
            "User $i",
            "user$i@example.com",
            'active',
            rand(0, 100)
        ]);
        
        // Commit every 5000 rows for better performance
        if ($i % 5000 === 0) {
            $this->connection->commit();
            $this->connection->beginTransaction();
            echo "Inserted $i rows...\n";
        }
    }
    $this->connection->commit();
    
    // Stream all 50,000 rows
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    $rowCount = 0;
    
    foreach ($this->connection->table('streaming_test')->cursor() as $row) {
        $rowCount++;
        
        if ($rowCount % 10000 === 0) {
            $elapsed = microtime(true) - $startTime;
            $rate = $rowCount / $elapsed;
            echo "Processed $rowCount rows at " . round($rate) . " rows/second\n";
        }
    }
    
    $totalTime = microtime(true) - $startTime;
    $totalMemory = (memory_get_peak_usage() - $startMemory) / 1024 / 1024;
    $rowsPerSecond = $rowCount / $totalTime;
    
    expect($rowCount)->toBe(50000);
    expect($totalMemory)->toBeLessThan(30); // Reasonable memory usage for 50k rows
    expect($rowsPerSecond)->toBeGreaterThan(1000); // Should process at least 1000 rows/second
    
    echo "\n50K Rows Streaming Results:\n";
    echo "- Rows processed: $rowCount\n";
    echo "- Time taken: " . round($totalTime, 2) . " seconds\n";
    echo "- Peak memory: " . round($totalMemory, 2) . "MB\n";
    echo "- Throughput: " . round($rowsPerSecond) . " rows/second\n";
});

test('cursor vs chunk vs get memory comparison for 10,000 rows', function() {
    // Insert 10,000 rows
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO streaming_test (user_name, email, status, score) VALUES (?, ?, ?, ?)
    ');
    
    for ($i = 1; $i <= 10000; $i++) {
        $stmt->execute([
            "User $i",
            "user$i@example.com",
            'active',
            rand(0, 100)
        ]);
    }
    
    $results = [];
    
    // Test get() - loads all into memory
    $startMemory = memory_get_usage();
    $startTime = microtime(true);
    
    $rows = $this->connection->table('streaming_test')->get();
    
    $results['get'] = [
        'memory_mb' => (memory_get_peak_usage() - $startMemory) / 1024 / 1024,
        'time_seconds' => microtime(true) - $startTime,
        'rows' => count($rows)
    ];
    unset($rows);
    
    // Test cursor() - streams one by one
    $startMemory = memory_get_usage();
    $startTime = microtime(true);
    $count = 0;
    
    foreach ($this->connection->table('streaming_test')->cursor() as $row) {
        $count++;
    }
    
    $results['cursor'] = [
        'memory_mb' => (memory_get_peak_usage() - $startMemory) / 1024 / 1024,
        'time_seconds' => microtime(true) - $startTime,
        'rows' => $count
    ];
    
    // Test chunk() - processes in batches
    $startMemory = memory_get_usage();
    $startTime = microtime(true);
    $count = 0;
    
    $this->connection->table('streaming_test')->chunk(250, function($rows) use (&$count) {
        $count += count($rows);
    });
    
    $results['chunk'] = [
        'memory_mb' => (memory_get_peak_usage() - $startMemory) / 1024 / 1024,
        'time_seconds' => microtime(true) - $startTime,
        'rows' => $count
    ];
    
    // Display comparison
    echo "\n=== 10,000 ROWS METHOD COMPARISON ===\n";
    echo str_pad('Method', 10) . str_pad('Memory (MB)', 15) . str_pad('Time (s)', 12) . str_pad('Rows', 10) . "\n";
    echo str_repeat('-', 47) . "\n";
    
    foreach ($results as $method => $data) {
        echo str_pad($method, 10);
        echo str_pad(round($data['memory_mb'], 2) . '', 15);
        echo str_pad(round($data['time_seconds'], 3) . '', 12);
        echo str_pad($data['rows'] . '', 10);
        echo "\n";
    }
    
    // Verify streaming methods are more memory efficient
    expect($results['cursor']['memory_mb'])->toBeLessThan($results['get']['memory_mb']);
    expect($results['chunk']['memory_mb'])->toBeLessThan($results['get']['memory_mb']);
});

test('can handle concurrent streaming operations', function() {
    // Insert test data
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO streaming_test (user_name, email, status, score) VALUES (?, ?, ?, ?)
    ');
    
    for ($i = 1; $i <= 5000; $i++) {
        $stmt->execute([
            "User $i",
            "user$i@example.com",
            ['active', 'inactive'][rand(0, 1)],
            rand(0, 100)
        ]);
    }
    
    // Simulate concurrent streaming operations
    $operations = [
        'active_users' => $this->connection->table('streaming_test')->where('status', 'active'),
        'high_scores' => $this->connection->table('streaming_test')->where('score', '>', 70),
        'all_users' => $this->connection->table('streaming_test')
    ];
    
    $results = [];
    $startTime = microtime(true);
    
    foreach ($operations as $name => $query) {
        $count = 0;
        foreach ($query->cursor() as $row) {
            $count++;
        }
        $results[$name] = $count;
    }
    
    $totalTime = microtime(true) - $startTime;
    
    expect($results['all_users'])->toBe(5000);
    expect($totalTime)->toBeLessThan(10); // All operations should complete quickly
    
    echo "\nConcurrent Streaming Results:\n";
    foreach ($results as $name => $count) {
        echo "- $name: $count rows\n";
    }
    echo "Total time: " . round($totalTime, 2) . " seconds\n";
});