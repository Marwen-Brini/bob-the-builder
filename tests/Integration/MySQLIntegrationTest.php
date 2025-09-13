<?php

declare(strict_types=1);

use Bob\Database\Connection;

beforeEach(function () {
    if (! extension_loaded('pdo_mysql')) {
        $this->markTestSkipped('PDO MySQL extension is not available.');
    }

    // Use environment variables (for CI/CD)
    $dbConfig = [
        'driver' => 'mysql',
        'host' => $_ENV['MYSQL_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['MYSQL_PORT'] ?? 3306,
        'database' => $_ENV['MYSQL_DATABASE'] ?? 'bob_test',
        'username' => $_ENV['MYSQL_USERNAME'] ?? 'root',
        'password' => $_ENV['MYSQL_PASSWORD'] ?? 'password',  // CI uses 'password'
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ];

    $this->connection = new Connection($dbConfig);

    // Create test table
    $this->connection->statement('DROP TABLE IF EXISTS users');
    $this->connection->statement('
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255),
            email VARCHAR(255),
            age INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
});

afterEach(function () {
    // Drop tables in correct order to avoid foreign key constraints
    $this->connection->statement('SET FOREIGN_KEY_CHECKS = 0');
    $this->connection->statement('DROP TABLE IF EXISTS posts');
    $this->connection->statement('DROP TABLE IF EXISTS users');
    $this->connection->statement('SET FOREIGN_KEY_CHECKS = 1');
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
})->group('mysql', 'integration');

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
})->group('mysql', 'integration');

it('can delete data', function () {
    $builder = $this->connection->table('users');

    $builder->insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
        ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25],
    ]);

    $deleted = $this->connection->table('users')->where('name', 'John Doe')->delete();

    expect($deleted)->toBe(1);

    $users = $this->connection->table('users')->get();
    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('Jane Doe');
})->group('mysql', 'integration');

it('can use where clauses', function () {
    $this->connection->table('users')->insert([
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
})->group('mysql', 'integration');

it('can use aggregate functions', function () {
    $builder = $this->connection->table('users');

    $builder->insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
        ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'age' => 25],
        ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'age' => 35],
    ]);

    expect($builder->count())->toBe(3);
    expect((int) $builder->sum('age'))->toBe(90);
    expect((float) $builder->avg('age'))->toBe(30.0);
    expect((int) $builder->min('age'))->toBe(25);
    expect((int) $builder->max('age'))->toBe(35);
})->group('mysql', 'integration');

it('can use joins', function () {
    $this->connection->statement('DROP TABLE IF EXISTS posts');
    $this->connection->statement('
        CREATE TABLE posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ');

    $builder = $this->connection->table('users');

    $userId = $builder->insertGetId([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    $this->connection->table('posts')->insert([
        ['user_id' => $userId, 'title' => 'First Post'],
        ['user_id' => $userId, 'title' => 'Second Post'],
    ]);

    $results = $builder
        ->select('users.name', 'posts.title')
        ->join('posts', 'users.id', '=', 'posts.user_id')
        ->get();

    expect($results)->toHaveCount(2);
    expect($results[0]->name)->toBe('John Doe');
    expect($results[0]->title)->toBeIn(['First Post', 'Second Post']);

    $this->connection->statement('DROP TABLE posts');
})->group('mysql', 'integration');

it('can handle transactions', function () {
    $builder = $this->connection->table('users');

    $this->connection->beginTransaction();

    $builder->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    expect($builder->count())->toBe(1);

    $this->connection->rollBack();

    expect($builder->count())->toBe(0);

    $this->connection->beginTransaction();

    $builder->insert([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'age' => 25,
    ]);

    $this->connection->commit();

    expect($builder->count())->toBe(1);
})->group('mysql', 'integration');
