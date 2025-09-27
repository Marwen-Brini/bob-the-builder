<?php

namespace Tests\Integration;

use Bob\Database\Connection;
use PDO;

uses()->group('mysql');

beforeAll(function () {
    // Detect if we're in GitHub Actions CI environment
    $isGitHubCI = isset($_SERVER['GITHUB_ACTIONS']) && $_SERVER['GITHUB_ACTIONS'] === 'true';

    if ($isGitHubCI) {
        // GitHub Actions CI environment - use CI MySQL service
        // These match the settings in .github/workflows/tests.yml
        $_SERVER['MYSQL_TEST_HOST'] = $_ENV['MYSQL_HOST'] ?? '127.0.0.1';
        $_SERVER['MYSQL_TEST_USERNAME'] = $_ENV['MYSQL_USERNAME'] ?? 'root';
        $_SERVER['MYSQL_TEST_PASSWORD'] = $_ENV['MYSQL_PASSWORD'] ?? 'password';
        $_SERVER['MYSQL_TEST_DATABASE'] = $_ENV['MYSQL_DATABASE'] ?? 'bob_test';
        $_SERVER['MYSQL_TEST_PORT'] = $_ENV['MYSQL_PORT'] ?? 3306;
    } elseif (!isset($_SERVER['MYSQL_TEST_HOST'])) {
        // Local environment - try to load from config file
        $configFile = __DIR__ . '/../../tests/config/database.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $_SERVER['MYSQL_TEST_HOST'] = $config['host'] ?? null;
            $_SERVER['MYSQL_TEST_USERNAME'] = $config['username'] ?? null;
            $_SERVER['MYSQL_TEST_PASSWORD'] = $config['password'] ?? null;
            $_SERVER['MYSQL_TEST_DATABASE'] = $config['database'] ?? null;
            $_SERVER['MYSQL_TEST_PORT'] = $config['port'] ?? 3306;
        }
    }
});

beforeEach(function () {
    // Skip if MySQL is not configured
    if (!isset($_SERVER['MYSQL_TEST_HOST'])) {
        $this->markTestSkipped('MySQL test database not configured');
    }

    try {
        $this->connection = new Connection([
            'driver' => 'mysql',
            'host' => $_SERVER['MYSQL_TEST_HOST'],
            'port' => $_SERVER['MYSQL_TEST_PORT'] ?? 3306,
            'database' => $_SERVER['MYSQL_TEST_DATABASE'],
            'username' => $_SERVER['MYSQL_TEST_USERNAME'],
            'password' => $_SERVER['MYSQL_TEST_PASSWORD'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        // Drop and recreate test tables
        $this->connection->unprepared('DROP TABLE IF EXISTS users');
        $this->connection->unprepared('DROP TABLE IF EXISTS posts');

        $this->connection->unprepared('
            CREATE TABLE users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL
            )
        ');

        $this->connection->unprepared('
            CREATE TABLE posts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                user_id BIGINT UNSIGNED
            )
        ');
    } catch (\Exception $e) {
        $this->markTestSkipped('Could not connect to MySQL: ' . $e->getMessage());
    }
});

afterEach(function () {
    if (isset($this->connection)) {
        // Clean up tables
        $this->connection->unprepared('DROP TABLE IF EXISTS posts');
        $this->connection->unprepared('DROP TABLE IF EXISTS users');
    }
});

test('MySQL lastInsertId returns the ID of the last inserted row', function () {
    $this->connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['John Doe', 'john@example.com']);

    $id = $this->connection->lastInsertId();

    expect($id)->toBe('1');

    // Insert another record
    $this->connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Jane Doe', 'jane@example.com']);

    $id = $this->connection->lastInsertId();

    expect($id)->toBe('2');
});

test('MySQL insertGetId works correctly', function () {
    $id = $this->connection->insertGetId('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);

    expect($id)->toBe('1');

    // Verify the record
    $user = $this->connection->table('users')->where('id', $id)->first();
    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@example.com');
});

test('MySQL lastInsertId works with large auto-increment values', function () {
    // Set auto-increment to a large value
    $this->connection->unprepared('ALTER TABLE users AUTO_INCREMENT = 1000000');

    $id = $this->connection->insertGetId('users', [
        'name' => 'User with large ID',
        'email' => 'large@example.com'
    ]);

    expect($id)->toBe('1000000');

    $nextId = $this->connection->insertGetId('users', [
        'name' => 'Next User',
        'email' => 'next@example.com'
    ]);

    expect($nextId)->toBe('1000001');
});

test('MySQL lastInsertId with transactions', function () {
    $this->connection->beginTransaction();

    $id1 = $this->connection->insertGetId('users', [
        'name' => 'Transaction User 1',
        'email' => 'trans1@example.com'
    ]);

    expect($id1)->toBe('1');

    $id2 = $this->connection->insertGetId('users', [
        'name' => 'Transaction User 2',
        'email' => 'trans2@example.com'
    ]);

    expect($id2)->toBe('2');

    $this->connection->commit();

    // Verify records exist after commit
    $count = $this->connection->table('users')->count();
    expect($count)->toBe(2);
});

test('MySQL lastInsertId behavior with rollback', function () {
    // Insert a record outside transaction
    $id1 = $this->connection->insertGetId('users', [
        'name' => 'User 1',
        'email' => 'user1@example.com'
    ]);

    expect($id1)->toBe('1');

    $this->connection->beginTransaction();

    $id2 = $this->connection->insertGetId('users', [
        'name' => 'User 2',
        'email' => 'user2@example.com'
    ]);

    // The lastInsertId should be available before rollback
    expect($id2)->toBe('2');
    expect($this->connection->lastInsertId())->toBe('2');

    $this->connection->rollBack();

    // After rollback, MySQL resets lastInsertId to 0
    // This is actual MySQL behavior - lastInsertId is reset after rollback
    expect($this->connection->lastInsertId())->toBe('0');

    // But the first record should still exist
    $count = $this->connection->table('users')->count();
    expect($count)->toBe(1); // Only the first user exists

    // Verify only the first user exists
    $users = $this->connection->table('users')->get();
    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('User 1');
});

test('MySQL insertGetId with multiple tables', function () {
    $userId = $this->connection->insertGetId('users', [
        'name' => 'Blog Author',
        'email' => 'author@example.com'
    ]);

    $postId1 = $this->connection->insertGetId('posts', [
        'title' => 'First Post',
        'content' => 'Content 1',
        'user_id' => $userId
    ]);

    $postId2 = $this->connection->insertGetId('posts', [
        'title' => 'Second Post',
        'content' => 'Content 2',
        'user_id' => $userId
    ]);

    expect($userId)->toBe('1');
    expect($postId1)->toBe('1');
    expect($postId2)->toBe('2');

    // Verify relationships
    $posts = $this->connection->table('posts')->where('user_id', $userId)->get();
    expect($posts)->toHaveCount(2);
});