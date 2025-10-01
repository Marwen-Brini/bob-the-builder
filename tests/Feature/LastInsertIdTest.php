<?php

namespace Tests\Feature;

use Bob\Database\Connection;

beforeEach(function () {
    // Create SQLite in-memory database for testing
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create test table
    $this->connection->unprepared('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT
        )
    ');

    // Create another test table for testing different scenarios
    $this->connection->unprepared('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT,
            user_id INTEGER
        )
    ');
});

test('lastInsertId returns the ID of the last inserted row', function () {
    // Insert a record using raw SQL
    $this->connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['John Doe', 'john@example.com']);

    $id = $this->connection->lastInsertId();

    expect($id)->toBe('1');

    // Insert another record
    $this->connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Jane Doe', 'jane@example.com']);

    $id = $this->connection->lastInsertId();

    expect($id)->toBe('2');
});

test('lastInsertId works with query builder insert', function () {
    // Insert using query builder
    $this->connection->table('users')->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $id = $this->connection->lastInsertId();

    expect($id)->toBe('1');
});

test('lastInsertId returns 0 when no insert has occurred', function () {
    $id = $this->connection->lastInsertId();

    // SQLite returns '0' when no insert has occurred
    expect($id)->toBe('0');
});

test('lastInsertId persists across multiple tables', function () {
    // Insert into users table
    $this->connection->table('users')->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $userId = $this->connection->lastInsertId();
    expect($userId)->toBe('1');

    // Insert into posts table
    $this->connection->table('posts')->insert([
        'title' => 'First Post',
        'content' => 'Hello World',
        'user_id' => $userId,
    ]);

    $postId = $this->connection->lastInsertId();
    expect($postId)->toBe('1'); // First post ID
});

test('insertGetId returns the ID directly', function () {
    $id = $this->connection->insertGetId('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($id)->toBe('1');

    // Verify the record was inserted
    $user = $this->connection->table('users')->find($id);
    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@example.com');
});

test('insertGetId works with multiple inserts', function () {
    $id1 = $this->connection->insertGetId('users', [
        'name' => 'User 1',
        'email' => 'user1@example.com',
    ]);

    $id2 = $this->connection->insertGetId('users', [
        'name' => 'User 2',
        'email' => 'user2@example.com',
    ]);

    $id3 = $this->connection->insertGetId('users', [
        'name' => 'User 3',
        'email' => 'user3@example.com',
    ]);

    expect($id1)->toBe('1');
    expect($id2)->toBe('2');
    expect($id3)->toBe('3');

    // Verify all records exist
    $count = $this->connection->table('users')->count();
    expect($count)->toBe(3);
});

test('insertGetId works with different tables', function () {
    $userId = $this->connection->insertGetId('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $postId = $this->connection->insertGetId('posts', [
        'title' => 'My Post',
        'content' => 'Post content',
        'user_id' => $userId,
    ]);

    expect($userId)->toBe('1');
    expect($postId)->toBe('1'); // First ID in posts table

    // Verify the relationship
    $post = $this->connection->table('posts')->find($postId);
    expect((string) $post->user_id)->toBe($userId);
});

test('lastInsertId works after bulk insert', function () {
    // Note: Bulk insert behavior varies by database
    // SQLite only returns the last ID from a multi-row insert
    $this->connection->insert(
        'INSERT INTO users (name, email) VALUES (?, ?), (?, ?), (?, ?)',
        ['User1', 'user1@example.com', 'User2', 'user2@example.com', 'User3', 'user3@example.com']
    );

    $id = $this->connection->lastInsertId();

    // Should return the ID of the last inserted row (3)
    expect($id)->toBe('3');
});

test('insertGetId with null values', function () {
    $id = $this->connection->insertGetId('posts', [
        'title' => 'Post without content',
        'content' => null,
        'user_id' => null,
    ]);

    expect($id)->toBe('1');

    $post = $this->connection->table('posts')->find($id);
    expect($post->title)->toBe('Post without content');
    expect($post->content)->toBeNull();
    expect($post->user_id)->toBeNull();
});

test('lastInsertId is connection specific', function () {
    // Create a second connection
    $connection2 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection2->unprepared('
        CREATE TABLE items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )
    ');

    // Insert in first connection
    $this->connection->insertGetId('users', [
        'name' => 'User 1',
        'email' => 'user1@example.com',
    ]);

    // Insert in second connection
    $connection2->insertGetId('items', [
        'name' => 'Item 1',
    ]);

    // Each connection should have its own lastInsertId
    expect($this->connection->lastInsertId())->toBe('1');
    expect($connection2->lastInsertId())->toBe('1');
});
