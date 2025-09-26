<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Query\Builder;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    Model::setConnection($this->connection);

    // Create test tables
    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            user_id INTEGER,
            category_id INTEGER,
            status TEXT
        )
    ');

    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT
        )
    ');

    // Insert test data
    $this->connection->table('users')->insert([
        ['name' => 'Alice'],
        ['name' => 'Bob'],
    ]);

    $this->connection->table('posts')->insert([
        ['title' => 'Post 1', 'user_id' => 1, 'category_id' => 1, 'status' => 'published'],
        ['title' => 'Post 2', 'user_id' => 1, 'category_id' => 2, 'status' => 'draft'],
        ['title' => 'Post 3', 'user_id' => 2, 'category_id' => 1, 'status' => 'published'],
    ]);
});

afterEach(function () {
    Model::clearConnection();
});

class TestBindingPost extends Model {
    protected string $table = 'posts';
    protected bool $timestamps = false;
}

class TestBindingPostWithScopes extends Model {
    protected string $table = 'posts';
    protected bool $timestamps = false;

    protected static function boot(): void {
        parent::boot();
        // This could add bindings that shouldn't be in delete
        static::addGlobalScope('published', function (Builder $builder) {
            $builder->where('status', 'published');
        });
    }
}

test('delete with no extra bindings works', function () {
    $post = TestBindingPost::find(1);

    $this->connection->enableQueryLog();

    $result = $post->delete();

    expect($result)->toBeTrue();

    $log = $this->connection->getQueryLog();
    $lastQuery = end($log);

    expect($lastQuery['query'])->toContain('delete from');
    expect($lastQuery['bindings'])->toHaveCount(1);
    expect($lastQuery['bindings'][0])->toBe(1);
});

test('delete with global scopes that add bindings', function () {
    // This might expose the binding issue
    $post = TestBindingPostWithScopes::find(3); // Will apply the 'published' scope

    expect($post)->toBeInstanceOf(TestBindingPostWithScopes::class);

    $this->connection->enableQueryLog();

    // This might fail if bindings are mixed up
    $result = $post->delete();

    expect($result)->toBeTrue();

    $log = $this->connection->getQueryLog();
    $queries = array_slice($log, -1); // Get last query only
    $deleteQuery = end($queries);

    expect($deleteQuery['query'])->toContain('delete from');
    // Should only have the ID binding for delete
    expect($deleteQuery['bindings'])->toHaveCount(1);
    expect($deleteQuery['bindings'][0])->toBe(3);
});

test('complex query then delete - bindings isolation', function () {
    // Build a complex query with multiple bindings
    $query = TestBindingPost::query()
        ->join('users', 'posts.user_id', '=', 'users.id')
        ->where('users.name', 'Alice')
        ->where('posts.status', 'published')
        ->orderBy('posts.title');

    $post = $query->first();
    expect($post)->toBeInstanceOf(TestBindingPost::class);
    expect($post->id)->toBe(1);

    // Now delete - should only use WHERE bindings for the ID
    $this->connection->enableQueryLog();

    $result = $post->delete();

    expect($result)->toBeTrue();

    $log = $this->connection->getQueryLog();
    $deleteQuery = end($log);

    // The delete should have clean bindings, just the ID
    expect($deleteQuery['bindings'])->toHaveCount(1);
    expect($deleteQuery['bindings'][0])->toBe(1);
});

test('manual query builder delete with extra bindings', function () {
    // This simulates what might be happening
    $builder = $this->connection->table('posts');

    // Add some bindings that shouldn't be in delete
    $builder->addBinding([1, 2], 'select');
    $builder->addBinding([3], 'join');

    // Add the actual where
    $builder->where('id', 4);

    // Check what bindings we have before delete
    $allBindings = $builder->getBindings();
    $whereBindings = $builder->getBindings('where');

    expect($allBindings)->toBe([1, 2, 3, 4]); // All bindings flattened
    expect($whereBindings)->toBe([4]); // Just where binding

    $this->connection->enableQueryLog();

    // Delete should only use WHERE bindings
    $result = $builder->delete();

    // Verify it actually deleted something (or 0 if no matching record)
    expect($result)->toBeInt();

    $log = $this->connection->getQueryLog();
    $deleteQuery = end($log);

    // Should only have WHERE binding
    expect($deleteQuery['bindings'])->toHaveCount(1);
    expect($deleteQuery['bindings'][0])->toBe(4);
});

test('compare getBindings vs getBindings where', function () {
    $builder = $this->connection->table('posts');

    // Add various bindings
    $builder->addBinding([1], 'select');
    $builder->addBinding([2], 'join');
    $builder->where('id', 3);
    $builder->addBinding([4], 'having');

    $allBindings = $builder->getBindings();
    $whereBindings = $builder->getBindings('where');

    expect($allBindings)->toBe([1, 2, 3, 4]); // Flattened all
    expect($whereBindings)->toBe([3]); // Just where

    // For delete, we should only use WHERE bindings!
});