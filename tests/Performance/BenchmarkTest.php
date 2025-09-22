<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Grammars\SQLiteGrammar;

beforeEach(function () {
    // Setup in-memory SQLite for benchmarks
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create test table
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            age INTEGER,
            status TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');

    // Insert test data for benchmarks
    $stmt = $this->connection->getPdo()->prepare('
        INSERT INTO users (name, email, age, status) VALUES (?, ?, ?, ?)
    ');

    for ($i = 1; $i <= 1000; $i++) {
        $stmt->execute([
            "User $i",
            "user$i@example.com",
            rand(18, 80),
            ['active', 'inactive', 'pending'][rand(0, 2)],
        ]);
    }
});

test('benchmark simple select query building', function () {
    $iterations = 10000;
    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $builder = $this->connection->table('users');
        $sql = $builder->toSql();
    }

    $elapsed = (microtime(true) - $start) * 1000; // Convert to milliseconds
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(1.0); // Each query should build in less than 1ms
});

test('benchmark complex query with multiple conditions', function () {
    $iterations = 5000;
    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $builder = $this->connection->table('users')
            ->select(['id', 'name', 'email'])
            ->where('status', 'active')
            ->where('age', '>', 25)
            ->whereIn('id', [1, 2, 3, 4, 5])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->offset(20);
        $sql = $builder->toSql();
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(2.0); // Complex queries should still be fast
});

test('benchmark query with joins', function () {
    // Create posts table
    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            title TEXT,
            content TEXT
        )
    ');

    $iterations = 5000;
    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $builder = $this->connection->table('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.status', 'active')
            ->select(['users.name', 'posts.title']);
        $sql = $builder->toSql();
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(2.0);
});

test('benchmark insert query building', function () {
    $iterations = 10000;
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
        'status' => 'active',
    ];

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $builder = $this->connection->table('users');
        $sql = $builder->grammar->compileInsert($builder, $data);
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(1.0);
});

test('benchmark update query building', function () {
    $iterations = 10000;
    $data = ['status' => 'inactive', 'age' => 31];

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $builder = $this->connection->table('users')->where('id', 1);
        $sql = $builder->grammar->compileUpdate($builder, $data);
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(1.0);
});

test('benchmark delete query building', function () {
    $iterations = 10000;

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $builder = $this->connection->table('users')
            ->where('status', 'inactive')
            ->where('age', '<', 18);
        $sql = $builder->grammar->compileDelete($builder);
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(1.0);
});

test('benchmark aggregate functions', function () {
    $iterations = 5000;

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $builder = $this->connection->table('users')->where('status', 'active');
        $sql = $builder->toSql(); // For count query building

        // Also test other aggregates
        $builder2 = $this->connection->table('users');
        $builder2->aggregate = ['function' => 'sum', 'columns' => ['age']];
        $sql2 = $builder2->grammar->compileSelect($builder2);
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / ($iterations * 2); // We're doing 2 queries per iteration

    expect($perQuery)->toBeLessThan(1.0);
});

test('benchmark subquery building', function () {
    $iterations = 3000;

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $subquery = $this->connection->table('posts')
            ->select('user_id')
            ->where('created_at', '>', '2025-01-01');

        $builder = $this->connection->table('users')
            ->whereIn('id', $subquery);
        $sql = $builder->toSql();
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(3.0); // Subqueries are more complex
});

test('benchmark raw expressions', function () {
    $iterations = 10000;

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $builder = $this->connection->table('users')
            ->selectRaw('COUNT(*) as total, AVG(age) as avg_age')
            ->whereRaw('age > ? AND status = ?', [25, 'active'])
            ->groupBy('status');
        $sql = $builder->toSql();
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(1.5);
});

test('benchmark grammar switching', function () {
    $iterations = 5000;
    $grammars = [
        new MySQLGrammar,
        new SQLiteGrammar,
    ];

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $grammar = $grammars[$i % 2];
        $this->connection->setQueryGrammar($grammar);

        $builder = $this->connection->table('users')
            ->where('status', 'active')
            ->orderBy('created_at');
        $sql = $builder->toSql();
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(2.0);
});

test('benchmark query execution with results', function () {
    $iterations = 100; // Fewer iterations for actual execution

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $results = $this->connection->table('users')
            ->where('status', 'active')
            ->limit(10)
            ->get();
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $perQuery = $elapsed / $iterations;

    expect($perQuery)->toBeLessThan(10.0); // Actual execution takes longer
});

test('benchmark prepared statement caching', function () {
    $this->connection->enableStatementCaching();
    $iterations = 1000;

    // First run - statements get cached
    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $this->connection->select('SELECT * FROM users WHERE id = ?', [$i % 100 + 1]);
    }

    $firstRun = (microtime(true) - $start) * 1000;

    // Second run - should use cached statements
    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $this->connection->select('SELECT * FROM users WHERE id = ?', [$i % 100 + 1]);
    }

    $secondRun = (microtime(true) - $start) * 1000;

    // Second run should be faster or equal due to caching
    // Allow 25% variance for timing inconsistencies in CI environments
    expect($secondRun)->toBeLessThanOrEqual($firstRun * 1.25);
});
