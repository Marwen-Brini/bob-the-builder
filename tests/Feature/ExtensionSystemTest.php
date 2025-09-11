<?php

use Bob\Database\Connection;
use Bob\Query\Builder;

beforeEach(function () {
    // Use SQLite in-memory database for testing
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
            slug TEXT,
            created_at TEXT
        )
    ');

    // Insert test data
    $this->connection->table('users')->insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'slug' => 'john-doe', 'created_at' => '2024-01-01'],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'inactive', 'slug' => 'jane-smith', 'created_at' => '2024-01-02'],
        ['name' => 'Bob Wilson', 'email' => 'bob@example.com', 'status' => 'active', 'slug' => 'bob-wilson', 'created_at' => '2024-01-03'],
    ]);

    // Clear any existing extensions
    Builder::clearMacros();
    Builder::clearScopes();
    Builder::clearFinders();
});

afterEach(function () {
    // Clean up extensions after each test
    Builder::clearMacros();
    Builder::clearScopes();
    Builder::clearFinders();
});

test('can register and use macros', function () {
    // Register a macro
    Builder::macro('whereActive', function () {
        return $this->where('status', '=', 'active');
    });

    // Test that the macro exists
    expect(Builder::hasMacro('whereActive'))->toBeTrue();

    // Use the macro
    $activeUsers = $this->connection->table('users')
        ->whereActive()
        ->get();

    expect($activeUsers)->toHaveCount(2);
    expect($activeUsers[0]['name'])->toBe('John Doe');
    expect($activeUsers[1]['name'])->toBe('Bob Wilson');
});

test('can register multiple macros at once', function () {
    Builder::mixin([
        'whereActive' => function () {
            return $this->where('status', '=', 'active');
        },
        'whereInactive' => function () {
            return $this->where('status', '=', 'inactive');
        },
        'whereEmail' => function ($email) {
            return $this->where('email', '=', $email);
        },
    ]);

    // Test multiple macros
    expect(Builder::hasMacro('whereActive'))->toBeTrue();
    expect(Builder::hasMacro('whereInactive'))->toBeTrue();
    expect(Builder::hasMacro('whereEmail'))->toBeTrue();

    // Test whereInactive
    $inactiveUsers = $this->connection->table('users')
        ->whereInactive()
        ->get();

    expect($inactiveUsers)->toHaveCount(1);
    expect($inactiveUsers[0]['name'])->toBe('Jane Smith');

    // Test whereEmail with parameter
    $user = $this->connection->table('users')
        ->whereEmail('bob@example.com')
        ->first();

    expect($user['name'])->toBe('Bob Wilson');
});

test('can remove macros', function () {
    Builder::macro('testMacro', function () {
        return $this;
    });

    expect(Builder::hasMacro('testMacro'))->toBeTrue();

    Builder::removeMacro('testMacro');

    expect(Builder::hasMacro('testMacro'))->toBeFalse();
});

test('can register and use local scopes', function () {
    // Register a local scope
    Builder::scope('active', function () {
        return $this->where('status', '=', 'active');
    });

    // Test that the scope exists
    expect(Builder::hasScope('active'))->toBeTrue();

    // Use the scope
    $activeUsers = $this->connection->table('users')
        ->withScope('active')
        ->get();

    expect($activeUsers)->toHaveCount(2);
});

test('can use parameterized scopes', function () {
    // Register parameterized scopes
    Builder::scope('ofStatus', function ($status) {
        return $this->where('status', '=', $status);
    });

    Builder::scope('createdAfter', function ($date) {
        return $this->where('created_at', '>', $date);
    });

    // Use scopes with parameters
    $users = $this->connection->table('users')
        ->withScope('ofStatus', 'active')
        ->withScope('createdAfter', '2024-01-02')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('Bob Wilson');
});

test('can use built-in dynamic finders', function () {
    // Test findBy
    $user = $this->connection->table('users')->findByEmail('john@example.com');
    expect($user['name'])->toBe('John Doe');

    // Test findAllBy
    $activeUsers = $this->connection->table('users')->findAllByStatus('active');
    expect($activeUsers)->toHaveCount(2);

    // Test whereBy
    $query = $this->connection->table('users')->whereByStatus('inactive');
    $users = $query->get();
    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('Jane Smith');

    // Test countBy
    $count = $this->connection->table('users')->countByStatus('active');
    expect($count)->toBe(2);

    // Test existsBy
    $exists = $this->connection->table('users')->existsByEmail('jane@example.com');
    expect($exists)->toBeTrue();

    $notExists = $this->connection->table('users')->existsByEmail('notfound@example.com');
    expect($notExists)->toBeFalse();
});

test('converts camelCase to snake_case in finders', function () {
    // Create a table with snake_case column
    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY,
            post_title TEXT,
            post_status TEXT,
            created_at TEXT
        )
    ');

    $this->connection->table('posts')->insert([
        ['post_title' => 'First Post', 'post_status' => 'published', 'created_at' => '2024-01-01'],
        ['post_title' => 'Second Post', 'post_status' => 'draft', 'created_at' => '2024-01-02'],
    ]);

    // Use camelCase in finder, should convert to snake_case
    $post = $this->connection->table('posts')->findByPostStatus('published');
    expect($post['post_title'])->toBe('First Post');

    $posts = $this->connection->table('posts')->whereByPostStatus('draft')->get();
    expect($posts)->toHaveCount(1);
    expect($posts[0]['post_title'])->toBe('Second Post');
});

test('can register custom finder patterns', function () {
    // Register a custom finder for slug lookups
    Builder::registerFinder('/^getBySlug$/', function ($matches, $params) {
        $slug = $params[0] ?? null;

        return $this->where('slug', '=', $slug)->first();
    });

    // Register a finder for multiple conditions
    Builder::registerFinder('/^findActiveBy(.+)$/', function ($matches, $params) {
        $column = $this->camelToSnake($matches[1]);

        return $this->where('status', '=', 'active')
            ->where($column, '=', $params[0] ?? null)
            ->first();
    });

    // Test custom finders
    $user = $this->connection->table('users')->getBySlug('jane-smith');
    expect($user['name'])->toBe('Jane Smith');

    $activeUser = $this->connection->table('users')->findActiveByName('Bob Wilson');
    expect($activeUser['email'])->toBe('bob@example.com');
});

test('can chain multiple extensions', function () {
    // Register multiple extensions
    Builder::macro('active', function () {
        return $this->where('status', '=', 'active');
    });

    Builder::macro('recent', function ($days = 7) {
        $date = date('Y-m-d', strtotime("-{$days} days"));

        return $this->where('created_at', '>=', $date);
    });

    Builder::scope('ordered', function () {
        return $this->orderBy('created_at', 'desc');
    });

    // Chain everything together
    // Note: Bob Wilson was created on 2024-01-03, so we need a large enough window
    $users = $this->connection->table('users')
        ->active()
        ->recent(5000)  // Use a large number to include all test data
        ->withScope('ordered')
        ->whereByEmail('bob@example.com')
        ->get();

    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('Bob Wilson');
});

test('throws exception for undefined methods', function () {
    $this->connection->table('users')->undefinedMethod();
})->throws(\BadMethodCallException::class, 'Method Bob\Query\Builder::undefinedMethod does not exist');

test('maintains separate macro namespaces', function () {
    // Macros are static and shared across all Builder instances
    Builder::macro('testMacro', function () {
        return 'test';
    });

    $query1 = $this->connection->table('users');
    $query2 = $this->connection->table('posts');

    // Both instances should have access to the macro
    expect(Builder::hasMacro('testMacro'))->toBeTrue();

    // Clear macros affects all instances
    Builder::clearMacros();
    expect(Builder::hasMacro('testMacro'))->toBeFalse();
});

test('binds macros to correct context', function () {
    Builder::macro('getTableName', function () {
        return $this->from;
    });

    $query = $this->connection->table('users');
    $tableName = $query->getTableName();

    expect($tableName)->toBe('users');
});

test('can use global scopes', function () {
    // Register a global scope
    Builder::globalScope('activeOnly', function () {
        return $this->where('status', '=', 'active');
    });

    // Create a new query with global scopes
    $query = $this->connection->table('users')->withGlobalScopes();
    $users = $query->get();

    // Should only get active users
    expect($users)->toHaveCount(2);
    expect($users[0]['status'])->toBe('active');
    expect($users[1]['status'])->toBe('active');

    // Query without global scopes
    $allUsers = $this->connection->table('users')->get();
    expect($allUsers)->toHaveCount(3);
});

test('can remove global scopes', function () {
    Builder::globalScope('activeOnly', function () {
        return $this->where('status', '=', 'active');
    });

    // First, test with global scope applied
    $activeQuery = $this->connection->table('users')->withGlobalScopes();
    $activeUsers = $activeQuery->get();
    expect($activeUsers)->toHaveCount(2); // Only active users

    // Now test without applying the global scope
    // withoutGlobalScope should be called BEFORE withGlobalScopes to prevent it from being applied
    $allQuery = $this->connection->table('users')
        ->withoutGlobalScope('activeOnly')
        ->withGlobalScopes();

    $allUsers = $allQuery->get();
    expect($allUsers)->toHaveCount(3); // Should get all users
});
