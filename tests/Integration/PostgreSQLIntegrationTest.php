<?php

declare(strict_types=1);

use Bob\Database\Connection;

beforeEach(function () {
    if (! extension_loaded('pdo_pgsql')) {
        $this->markTestSkipped('PDO PostgreSQL extension is not available.');
    }

    // Use environment variables (for CI/CD)
    $dbConfig = [
        'driver' => 'pgsql',
        'host' => $_ENV['POSTGRES_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['POSTGRES_PORT'] ?? 5432,
        'database' => $_ENV['POSTGRES_DATABASE'] ?? 'bob_test',
        'username' => $_ENV['POSTGRES_USERNAME'] ?? 'postgres',
        'password' => $_ENV['POSTGRES_PASSWORD'] ?? 'password',  // CI uses 'password'
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
    ];

    try {
        $this->connection = new Connection($dbConfig);
        $this->connection->getPdo();
    } catch (Exception $e) {
        $this->markTestSkipped('Could not connect to PostgreSQL: '.$e->getMessage());
    }

    // Clean up any existing test table
    try {
        $this->connection->unprepared('DROP TABLE IF EXISTS test_users CASCADE');
    } catch (Exception $e) {
        // Ignore
    }

    // Create test table
    $this->connection->unprepared('
        CREATE TABLE test_users (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            active BOOLEAN DEFAULT true,
            score INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
});

afterEach(function () {
    if (isset($this->connection)) {
        try {
            $this->connection->unprepared('DROP TABLE IF EXISTS test_users CASCADE');
        } catch (Exception $e) {
            // Ignore
        }
    }
});

test('it can insert and select data', function () {
    $builder = $this->connection->table('test_users');

    // Insert data
    $builder->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'active' => true,
        'score' => 100,
    ]);

    // Select data
    $users = $builder->where('name', 'John Doe')->get();

    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('John Doe');
    expect($users[0]['email'])->toBe('john@example.com');
    expect($users[0]['active'])->toBeTrue();
    expect($users[0]['score'])->toBe(100);
})->group('postgres', 'integration');

test('it can update data', function () {
    $builder = $this->connection->table('test_users');

    // Insert initial data
    $builder->insert([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    // Update data
    $affected = $builder->where('email', 'jane@example.com')->update([
        'name' => 'Jane Smith',
        'score' => 200,
    ]);

    expect($affected)->toBe(1);

    // Verify update
    $user = $builder->where('email', 'jane@example.com')->first();
    expect($user['name'])->toBe('Jane Smith');
    expect($user['score'])->toBe(200);
})->group('postgres', 'integration');

test('it can delete data', function () {
    $builder = $this->connection->table('test_users');

    // Insert test data
    $builder->insert([
        ['name' => 'User 1', 'email' => 'user1@example.com'],
        ['name' => 'User 2', 'email' => 'user2@example.com'],
        ['name' => 'User 3', 'email' => 'user3@example.com'],
    ]);

    // Delete specific record
    $deleted = $builder->where('email', 'user2@example.com')->delete();
    expect($deleted)->toBe(1);

    // Verify deletion
    $remaining = $builder->get();
    expect($remaining)->toHaveCount(2);
    expect($remaining[0]['email'])->toBe('user1@example.com');
    expect($remaining[1]['email'])->toBe('user3@example.com');
})->group('postgres', 'integration');

test('it can use where clauses', function () {
    $builder = $this->connection->table('test_users');

    // Insert test data
    $builder->insert([
        ['name' => 'Alice', 'email' => 'alice@example.com', 'score' => 50],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'score' => 75],
        ['name' => 'Charlie', 'email' => 'charlie@example.com', 'score' => 100],
        ['name' => 'David', 'email' => 'david@example.com', 'score' => 125],
    ]);

    // Test various where clauses
    $highScorers = $builder->where('score', '>=', 75)->get();
    expect($highScorers)->toHaveCount(3);

    $specific = $builder->whereIn('name', ['Alice', 'Charlie'])->get();
    expect($specific)->toHaveCount(2);

    $between = $builder->whereBetween('score', [60, 110])->get();
    expect($between)->toHaveCount(2);
})->group('postgres', 'integration');

test('it can use aggregate functions', function () {
    $builder = $this->connection->table('test_users');

    // Insert test data
    $builder->insert([
        ['name' => 'User 1', 'email' => 'user1@example.com', 'score' => 10],
        ['name' => 'User 2', 'email' => 'user2@example.com', 'score' => 20],
        ['name' => 'User 3', 'email' => 'user3@example.com', 'score' => 30],
        ['name' => 'User 4', 'email' => 'user4@example.com', 'score' => 40],
        ['name' => 'User 5', 'email' => 'user5@example.com', 'score' => 50],
    ]);

    expect($builder->count())->toBe(5);
    expect($builder->sum('score'))->toBe(150);
    expect($builder->avg('score'))->toBe(30.0);
    expect($builder->min('score'))->toBe(10);
    expect($builder->max('score'))->toBe(50);
})->group('postgres', 'integration');

test('it can use joins', function () {
    // Create a second table for joining
    $this->connection->unprepared('
        CREATE TABLE test_posts (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES test_users(id),
            title VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');

    try {
        // Insert users
        $userId1 = $this->connection->table('test_users')->insertGetId([
            'name' => 'Author 1',
            'email' => 'author1@example.com',
        ]);

        $userId2 = $this->connection->table('test_users')->insertGetId([
            'name' => 'Author 2',
            'email' => 'author2@example.com',
        ]);

        // Insert posts
        $this->connection->table('test_posts')->insert([
            ['user_id' => $userId1, 'title' => 'Post 1 by Author 1'],
            ['user_id' => $userId1, 'title' => 'Post 2 by Author 1'],
            ['user_id' => $userId2, 'title' => 'Post 1 by Author 2'],
        ]);

        // Test join
        $results = $this->connection->table('test_users')
            ->join('test_posts', 'test_users.id', '=', 'test_posts.user_id')
            ->select('test_users.name', 'test_posts.title')
            ->orderBy('test_posts.title')
            ->get();

        expect($results)->toHaveCount(3);
        expect($results[0]['name'])->toBe('Author 1');
        expect($results[0]['title'])->toBe('Post 1 by Author 1');
    } finally {
        $this->connection->unprepared('DROP TABLE IF EXISTS test_posts CASCADE');
    }
})->group('postgres', 'integration');

test('it can handle transactions', function () {
    $builder = $this->connection->table('test_users');

    // Test successful transaction
    $this->connection->transaction(function () use ($builder) {
        $builder->insert(['name' => 'Transaction User', 'email' => 'trans@example.com']);
    });

    expect($builder->where('email', 'trans@example.com')->exists())->toBeTrue();

    // Test rolled back transaction
    try {
        $this->connection->transaction(function () use ($builder) {
            $builder->insert(['name' => 'Will Rollback', 'email' => 'rollback@example.com']);
            throw new Exception('Force rollback');
        });
    } catch (Exception $e) {
        // Expected
    }

    expect($builder->where('email', 'rollback@example.com')->exists())->toBeFalse();
})->group('postgres', 'integration');

test('it handles PostgreSQL specific features', function () {
    $builder = $this->connection->table('test_users');

    // Test RETURNING clause (PostgreSQL specific)
    $id = $builder->insertGetId([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    expect($id)->toBeGreaterThan(0);

    // Test boolean handling
    $builder->insert([
        'name' => 'Boolean Test',
        'email' => 'bool@example.com',
        'active' => false,
    ]);

    $user = $builder->where('email', 'bool@example.com')->first();
    expect($user['active'])->toBeFalse();

    // Test ILIKE (case-insensitive LIKE)
    $builder->insert([
        'name' => 'CaseSensitive',
        'email' => 'case@example.com',
    ]);

    $results = $builder->whereRaw('name ILIKE ?', ['%sensitive%'])->get();
    expect($results)->toHaveCount(1);
})->group('postgres', 'integration');