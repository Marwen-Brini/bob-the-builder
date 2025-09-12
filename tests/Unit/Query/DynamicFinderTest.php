<?php

declare(strict_types=1);

use Bob\Database\Connection;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Create test table
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            email TEXT,
            status TEXT,
            created_at TEXT
        )
    ');
    
    // Insert test data
    $this->connection->table('users')->insert([
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'created_at' => '2024-01-01'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'inactive', 'created_at' => '2024-02-01'],
        ['id' => 3, 'name' => 'Bob Wilson', 'email' => 'bob@example.com', 'status' => 'active', 'created_at' => '2024-03-01'],
    ]);
});

it('handles orWhereBy dynamic finder', function () {
    $users = $this->connection->table('users')
        ->where('status', 'inactive')
        ->orWhereByEmail('john@example.com')
        ->get();
    
    expect($users)->toHaveCount(2);
    expect($users[0]['name'])->toBe('John Doe');
    expect($users[1]['name'])->toBe('Jane Smith');
});

it('handles firstWhere dynamic finder', function () {
    $user = $this->connection->table('users')
        ->firstWhereEmail('jane@example.com');
    
    expect($user['name'])->toBe('Jane Smith');
    expect($user['status'])->toBe('inactive');
});

it('handles countBy dynamic finder', function () {
    $count = $this->connection->table('users')
        ->countByStatus('active');
    
    expect($count)->toBe(2);
    
    $count = $this->connection->table('users')
        ->countByStatus('inactive');
    
    expect($count)->toBe(1);
});

it('handles existsBy dynamic finder', function () {
    $exists = $this->connection->table('users')
        ->existsByEmail('john@example.com');
    
    expect($exists)->toBeTrue();
    
    $exists = $this->connection->table('users')
        ->existsByEmail('nonexistent@example.com');
    
    expect($exists)->toBeFalse();
});

it('handles deleteBy dynamic finder', function () {
    $deleted = $this->connection->table('users')
        ->deleteByStatus('inactive');
    
    expect($deleted)->toBe(1);
    
    // Verify deletion
    $remaining = $this->connection->table('users')->count();
    expect($remaining)->toBe(2);
    
    // Verify only active users remain
    $users = $this->connection->table('users')->get();
    foreach ($users as $user) {
        expect($user['status'])->toBe('active');
    }
});

it('handles orderBy with Asc/Desc dynamic finder', function () {
    // Order by name ascending
    $users = $this->connection->table('users')
        ->orderByNameAsc()
        ->get();
    
    expect($users[0]['name'])->toBe('Bob Wilson');
    expect($users[1]['name'])->toBe('Jane Smith');
    expect($users[2]['name'])->toBe('John Doe');
    
    // Order by created_at descending
    $users = $this->connection->table('users')
        ->orderByCreatedAtDesc()
        ->get();
    
    expect($users[0]['created_at'])->toBe('2024-03-01');
    expect($users[1]['created_at'])->toBe('2024-02-01');
    expect($users[2]['created_at'])->toBe('2024-01-01');
});

it('handles groupBy dynamic finder', function () {
    // Insert more data for grouping
    $this->connection->table('users')->insert([
        ['id' => 4, 'name' => 'Alice Cooper', 'email' => 'alice@example.com', 'status' => 'active', 'created_at' => '2024-01-15'],
        ['id' => 5, 'name' => 'Charlie Brown', 'email' => 'charlie@example.com', 'status' => 'inactive', 'created_at' => '2024-02-15'],
    ]);
    
    $statusCounts = $this->connection->table('users')
        ->select($this->connection->raw('status, COUNT(*) as count'))
        ->groupByStatus()
        ->get();
    
    expect($statusCounts)->toHaveCount(2);
    
    $counts = [];
    foreach ($statusCounts as $row) {
        $counts[$row['status']] = $row['count'];
    }
    
    expect($counts['active'])->toBe(3);
    expect($counts['inactive'])->toBe(2);
});

it('handles findAllBy dynamic finder', function () {
    $activeUsers = $this->connection->table('users')
        ->findAllByStatus('active');
    
    expect($activeUsers)->toHaveCount(2);
    expect($activeUsers[0]['name'])->toBe('John Doe');
    expect($activeUsers[1]['name'])->toBe('Bob Wilson');
});

it('returns null for unmatched dynamic finder', function () {
    $builder = $this->connection->table('users');
    
    // Use reflection to test the protected method
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('handleDynamicFinder');
    $method->setAccessible(true);
    
    $result = $method->invoke($builder, 'nonExistentMethod', []);
    
    expect($result)->toBeNull();
});

it('can get registered finder patterns', function () {
    $builder = $this->connection->table('users');
    
    // Register a custom pattern
    $builder::registerFinder('/^customFindBy(.+)$/', function ($matches, $params) {
        return 'custom';
    });
    
    $patterns = $builder::getFinderPatterns();
    expect($patterns)->toHaveKey('/^customFindBy(.+)$/');
    
    // Clean up
    $builder::clearFinders();
});

it('handles custom finder patterns with non-closure callables', function () {
    $builder = $this->connection->table('users');
    
    // Define a callable array
    $handler = new class {
        public function handle($matches, $params) {
            return 'handled by callable';
        }
    };
    
    // Register with callable array
    $builder::registerFinder('/^testPattern(.+)$/', [$handler, 'handle']);
    
    // Use reflection to test
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('handleDynamicFinder');
    $method->setAccessible(true);
    
    $result = $method->invoke($builder, 'testPatternSomething', []);
    expect($result)->toBe('handled by callable');
    
    // Clean up
    $builder::clearFinders();
});