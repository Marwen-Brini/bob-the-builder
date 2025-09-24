<?php

use Bob\Database\Connection;
use Bob\Database\Model;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    Model::setConnection($this->connection);

    // Create test table
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');

    // Insert test data
    $this->connection->table('users')->insert([
        ['name' => 'John Doe', 'email' => 'john@example.com'],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ['name' => 'Bob Johnson', 'email' => 'bob@example.com'],
    ]);
});

afterEach(function () {
    Model::clearConnection();
});

// Create a User model for testing
class TestDeleteUser extends Model {
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected bool $timestamps = false; // SQLite doesn't auto-update timestamps
}

test('can delete a model instance', function () {
    $user = TestDeleteUser::find(1);
    expect($user)->toBeInstanceOf(TestDeleteUser::class);
    expect($user->name)->toBe('John Doe');

    // This is what's reportedly failing
    $result = $user->delete();

    expect($result)->toBeTrue();

    // Verify the user is deleted
    $deletedUser = TestDeleteUser::find(1);
    expect($deletedTestDeleteUser)->toBeNull();

    // Other users should still exist
    expect(TestDeleteUser::query()->count())->toBe(2);
});

test('can delete multiple model instances', function () {
    $user1 = TestDeleteUser::find(1);
    $user2 = TestDeleteUser::find(2);

    $result1 = $user1->delete();
    $result2 = $user2->delete();

    expect($result1)->toBeTrue();
    expect($result2)->toBeTrue();

    expect(TestDeleteUser::query()->count())->toBe(1);
});

test('cannot delete non-existent model', function () {
    $user = new TestDeleteUser();
    $user->id = 999; // Non-existent ID

    $result = $user->delete();

    expect($result)->toBeFalse(); // Should return false as model doesn't exist
});

test('delete with custom primary key', function () {
    // Create table with custom primary key
    $this->connection->statement('
        CREATE TABLE posts (
            post_id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            content TEXT
        )
    ');

    $this->connection->table('posts')->insert([
        ['title' => 'First Post', 'content' => 'Content 1'],
        ['title' => 'Second Post', 'content' => 'Content 2'],
    ]);

    class Post extends Model {
        protected string $table = 'posts';
        protected string $primaryKey = 'post_id';
        protected bool $timestamps = false;
    }

    $post = Post::query()->where('post_id', 1)->first();
    expect($post)->toBeInstanceOf(Post::class);
    expect($post->title)->toBe('First Post');

    $result = $post->delete();
    expect($result)->toBeTrue();

    expect(Post::query()->count())->toBe(1);
});

test('delete generates correct SQL', function () {
    $user = TestDeleteUser::find(2);

    // Capture the SQL that would be executed
    $this->connection->enableQueryLog();

    $user->delete();

    $queryLog = $this->connection->getQueryLog();
    $lastQuery = end($queryLog);

    expect($lastQuery['query'])->toContain('delete from');
    expect($lastQuery['query'])->toContain('where');
    expect($lastQuery['bindings'])->toHaveCount(1);
    expect($lastQuery['bindings'][0])->toBe(2);
});

test('multiple deletes in transaction', function () {
    $this->connection->beginTransaction();

    $user1 = TestDeleteUser::find(1);
    $user2 = TestDeleteUser::find(2);

    $user1->delete();
    $user2->delete();

    // Before commit, in another connection they would still exist
    // But in same transaction they should be gone
    expect(TestDeleteUser::query()->count())->toBe(1);

    $this->connection->commit();

    expect(TestDeleteUser::query()->count())->toBe(1);
});

test('workaround comparison - both methods should work the same', function () {
    $user1 = TestDeleteUser::find(1);
    $user2 = TestDeleteUser::find(2);

    // Original method (reportedly broken)
    $result1 = $user1->delete();

    // Workaround method
    $result2 = TestDeleteUser::query()
        ->where($user2->getPrimaryKey(), $user2->getKey())
        ->delete();

    expect($result1)->toBeTrue();
    expect($result2)->toBe(1); // delete() returns affected rows

    expect(TestDeleteUser::query()->count())->toBe(1);
});