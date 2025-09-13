<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Database\Expression;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create test tables
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            status TEXT,
            created_at DATETIME,
            birth_date DATE,
            login_time TIME,
            metadata JSON
        )
    ');

    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            title TEXT,
            content TEXT,
            published_at DATETIME
        )
    ');

    // Insert test data
    $this->connection->table('users')->insert([
        [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'created_at' => '2024-01-15 10:30:00',
            'birth_date' => '1990-05-20',
            'login_time' => '09:30:00',
            'metadata' => json_encode(['role' => 'admin', 'permissions' => ['read', 'write']])
        ],
        [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'status' => 'inactive',
            'created_at' => '2024-02-20 14:45:00',
            'birth_date' => '1985-08-15',
            'login_time' => '14:15:00',
            'metadata' => json_encode(['role' => 'user', 'permissions' => ['read']])
        ],
        [
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com',
            'status' => 'active',
            'created_at' => '2024-01-20 08:00:00',
            'birth_date' => '1995-01-10',
            'login_time' => '08:00:00',
            'metadata' => json_encode(['role' => 'moderator', 'permissions' => ['read', 'moderate']])
        ],
    ]);
});

test('whereDate filters by date', function () {
    $users = $this->connection->table('users')
        ->whereDate('created_at', '2024-01-15')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('John Doe');

    $users = $this->connection->table('users')
        ->whereDate('created_at', '>', '2024-01-31')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('Jane Smith');
});

test('whereMonth filters by month', function () {
    $users = $this->connection->table('users')
        ->whereMonth('birth_date', '5')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('John Doe');

    $users = $this->connection->table('users')
        ->whereMonth('birth_date', '=', '01')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('Bob Wilson');
});

test('whereYear filters by year', function () {
    $users = $this->connection->table('users')
        ->whereYear('birth_date', '1990')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('John Doe');

    $users = $this->connection->table('users')
        ->whereYear('birth_date', '>', '1990')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('Bob Wilson');
});

test('whereDay filters by day', function () {
    $users = $this->connection->table('users')
        ->whereDay('birth_date', '20')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('John Doe');

    $users = $this->connection->table('users')
        ->whereDay('birth_date', '<', '16')
        ->get();

    expect($users)->toHaveCount(2);
});

test('whereTime filters by time', function () {
    $users = $this->connection->table('users')
        ->whereTime('login_time', '09:30:00')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('John Doe');

    $users = $this->connection->table('users')
        ->whereTime('login_time', '>', '10:00:00')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('Jane Smith');
});

test('whereColumn compares two columns', function () {
    // First update a user to have matching values
    $this->connection->table('users')
        ->where('id', 1)
        ->update(['email' => 'john@example.com', 'name' => 'john@example.com']);

    $users = $this->connection->table('users')
        ->whereColumn('name', 'email')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('john@example.com');

    // Test with operator
    $this->connection->table('posts')->insert([
        ['user_id' => 1, 'title' => 'Post 1', 'published_at' => '2024-01-10 10:00:00'],
        ['user_id' => 2, 'title' => 'Post 2', 'published_at' => '2024-02-25 10:00:00'],
    ]);

    $posts = $this->connection->table('posts')
        ->join('users', 'posts.user_id', '=', 'users.id')
        ->whereColumn('posts.published_at', '>', 'users.created_at')
        ->get();

    expect($posts)->toHaveCount(1);
});

test('when conditionally applies clauses', function () {
    // Test when condition is true
    $status = 'active';
    $users = $this->connection->table('users')
        ->when($status, function ($query, $value) {
            return $query->where('status', $value);
        })
        ->get();

    expect($users)->toHaveCount(2);

    // Test when condition is false
    $status = null;
    $users = $this->connection->table('users')
        ->when($status, function ($query, $value) {
            return $query->where('status', $value);
        })
        ->get();

    expect($users)->toHaveCount(3);

    // Test with default callback
    $sort = null;
    $users = $this->connection->table('users')
        ->when(
            $sort,
            function ($query, $value) {
                return $query->orderBy($value);
            },
            function ($query) {
                return $query->orderBy('name');
            }
        )
        ->get();

    expect($users[0]->name)->toBe('Bob Wilson');
});

test('unless conditionally applies clauses', function () {
    // Test unless condition is false (applies clause)
    $excludeInactive = false;
    $users = $this->connection->table('users')
        ->unless($excludeInactive, function ($query) {
            return $query->where('status', 'active');
        })
        ->get();

    expect($users)->toHaveCount(2);

    // Test unless condition is true (doesn't apply clause)
    $excludeInactive = true;
    $users = $this->connection->table('users')
        ->unless($excludeInactive, function ($query) {
            return $query->where('status', 'active');
        })
        ->get();

    expect($users)->toHaveCount(3);
});

test('orWhereRaw adds or raw where clause', function () {
    $users = $this->connection->table('users')
        ->where('status', 'inactive')
        ->orWhereRaw("name LIKE 'Bob%'")
        ->get();

    expect($users)->toHaveCount(2);
    expect($users[0]->name)->toBeIn(['Jane Smith', 'Bob Wilson']);
    expect($users[1]->name)->toBeIn(['Jane Smith', 'Bob Wilson']);
});

test('orWhereColumn adds or column comparison', function () {
    // First create a scenario where we need orWhereColumn
    // Make one user's name match their email
    $this->connection->table('users')
        ->where('id', 1)
        ->update(['name' => 'john@example.com', 'email' => 'john@example.com']);

    // Query for inactive users OR users where name equals email
    $users = $this->connection->table('users')
        ->where('status', 'inactive')
        ->orWhereColumn('name', 'email')
        ->get();

    // Should find Jane (inactive) and John (name=email)
    expect($users)->toHaveCount(2);
    $names = array_map(fn($u) => $u->name, $users);
    expect($names)->toContain('john@example.com');
    expect($names)->toContain('Jane Smith');
});

test('whereIn works with subquery', function () {
    // Insert posts
    $this->connection->table('posts')->insert([
        ['user_id' => 1, 'title' => 'First Post', 'published_at' => '2024-01-10'],
        ['user_id' => 1, 'title' => 'Second Post', 'published_at' => '2024-01-20'],
        ['user_id' => 2, 'title' => 'Third Post', 'published_at' => '2024-02-15'],
        ['user_id' => 3, 'title' => 'Fourth Post', 'published_at' => '2024-03-01'],
    ]);

    // First let's test a simple whereIn with array
    $usersSimple = $this->connection->table('users')
        ->whereIn('id', [1, 2])
        ->get();
    expect($usersSimple)->toHaveCount(2);

    // Now test whereIn with subquery - all users who have at least one post
    $users = $this->connection->table('users')
        ->whereIn('id', function($query) {
            $query->select('user_id')
                ->from('posts');
        })
        ->get();

    // All 3 users have posts
    expect($users)->toHaveCount(3);
    $names = array_map(fn($u) => $u->name, $users);
    expect($names)->toContain('John Doe');
    expect($names)->toContain('Jane Smith');
    expect($names)->toContain('Bob Wilson');
});