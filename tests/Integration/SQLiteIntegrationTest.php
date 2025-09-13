<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Query\Builder;

beforeEach(function () {
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('PDO SQLite extension is not available.');
    }

    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    // Create test table
    $this->connection->statement('DROP TABLE IF EXISTS users');
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            age INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
});

afterEach(function () {
    $this->connection->disconnect();
});

it('can insert and select data', function () {
    $builder = $this->connection->table('users');

    $builder->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    $users = $builder->get();

    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('John Doe');
    expect($users[0]->email)->toBe('john@example.com');
    expect($users[0]->age)->toBe(30);
});

it('can update data', function () {
    $builder = $this->connection->table('users');

    $builder->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    $affected = $builder->where('name', 'John Doe')->update(['age' => 31]);

    expect($affected)->toBe(1);

    $user = $builder->first();
    expect($user->age)->toBe(31);
});

it('can delete data', function () {
    $builder = $this->connection->table('users');

    $builder->insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
        ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25],
    ]);

    $deleted = $builder->where('name', 'John Doe')->delete();

    expect($deleted)->toBe(1);

    // Get a fresh builder instance for the next query
    $users = $this->connection->table('users')->get();
    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('Jane Doe');
});

it('can use where clauses', function () {
    $builder = $this->connection->table('users');

    $builder->insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
        ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25],
        ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'age' => 35],
    ]);

    $users = $this->connection->table('users')->where('age', '>', 25)->get();
    expect($users)->toHaveCount(2);

    $users = $this->connection->table('users')->whereBetween('age', [25, 30])->get();
    expect($users)->toHaveCount(2);

    $users = $this->connection->table('users')->whereIn('name', ['John Doe', 'Jane Doe'])->get();
    expect($users)->toHaveCount(2);
});

it('can use aggregate functions', function () {
    $builder = $this->connection->table('users');

    $builder->insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
        ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25],
        ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'age' => 35],
    ]);

    expect($this->connection->table('users')->count())->toBe(3);
    expect($this->connection->table('users')->sum('age'))->toBe(90);
    expect($this->connection->table('users')->avg('age'))->toBe(30.0);
    expect($this->connection->table('users')->min('age'))->toBe(25);
    expect($this->connection->table('users')->max('age'))->toBe(35);
});

it('can use joins', function () {
    $this->connection->statement('DROP TABLE IF EXISTS posts');
    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            title TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ');

    $userId = $this->connection->table('users')->insertGetId([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    $this->connection->table('posts')->insert([
        ['user_id' => $userId, 'title' => 'First Post'],
        ['user_id' => $userId, 'title' => 'Second Post'],
    ]);

    $results = $this->connection->table('users')
        ->select('users.name', 'posts.title')
        ->join('posts', 'users.id', '=', 'posts.user_id')
        ->get();

    expect($results)->toHaveCount(2);
    expect($results[0]->name)->toBe('John Doe');
    expect($results[0]->title)->toBeIn(['First Post', 'Second Post']);

    $this->connection->statement('DROP TABLE posts');
});

it('can handle transactions', function () {
    $this->connection->beginTransaction();

    $this->connection->table('users')->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    expect($this->connection->table('users')->count())->toBe(1);

    $this->connection->rollBack();

    expect($this->connection->table('users')->count())->toBe(0);

    $this->connection->beginTransaction();

    $this->connection->table('users')->insert([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'age' => 25,
    ]);

    $this->connection->commit();

    expect($this->connection->table('users')->count())->toBe(1);
});

it('handles SQLite specific features', function () {
    $builder = $this->connection->table('users');

    // Test last insert ID
    $id = $builder->insertGetId([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    expect($id)->toBe(1);

    $id = $builder->insertGetId([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'age' => 25,
    ]);

    expect($id)->toBe(2);
});
