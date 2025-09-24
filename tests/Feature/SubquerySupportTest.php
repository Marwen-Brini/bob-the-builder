<?php

use Bob\Database\Connection;
use Bob\Database\Model;

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
            status TEXT,
            author_id INTEGER
        )
    ');

    $this->connection->statement('
        CREATE TABLE post_meta (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER,
            meta_key TEXT,
            meta_value TEXT
        )
    ');

    // Insert test data
    $this->connection->table('posts')->insert([
        ['id' => 1, 'title' => 'Post 1', 'status' => 'published', 'author_id' => 1],
        ['id' => 2, 'title' => 'Post 2', 'status' => 'draft', 'author_id' => 1],
        ['id' => 3, 'title' => 'Post 3', 'status' => 'published', 'author_id' => 2],
        ['id' => 4, 'title' => 'Post 4', 'status' => 'published', 'author_id' => 1],
    ]);

    $this->connection->table('post_meta')->insert([
        ['post_id' => 1, 'meta_key' => 'featured', 'meta_value' => 'yes'],
        ['post_id' => 2, 'meta_key' => 'featured', 'meta_value' => 'no'],
        ['post_id' => 3, 'meta_key' => 'featured', 'meta_value' => 'yes'],
        ['post_id' => 4, 'meta_key' => 'views', 'meta_value' => '100'],
    ]);
});

afterEach(function () {
    Model::clearConnection();
});

test('whereIn with Builder subquery', function () {
    // Create a subquery to get featured post IDs
    $subquery = $this->connection->table('post_meta')
        ->select('post_id')
        ->where('meta_key', 'featured')
        ->where('meta_value', 'yes');

    // Use the subquery in whereIn
    $posts = $this->connection->table('posts')
        ->whereIn('id', $subquery)
        ->get();

    expect($posts)->toHaveCount(2);
    expect(array_column($posts, 'id'))->toBe([1, 3]);
});

test('whereIn with Closure subquery', function () {
    // Use a closure for the subquery
    $posts = $this->connection->table('posts')
        ->whereIn('id', function($query) {
            $query->from('post_meta')
                  ->select('post_id')
                  ->where('meta_key', 'featured')
                  ->where('meta_value', 'yes');
        })
        ->get();

    expect($posts)->toHaveCount(2);
    expect(array_column($posts, 'id'))->toBe([1, 3]);
});

test('whereNotIn with Builder subquery', function () {
    $subquery = $this->connection->table('post_meta')
        ->select('post_id')
        ->where('meta_key', 'featured')
        ->where('meta_value', 'yes');

    $posts = $this->connection->table('posts')
        ->whereNotIn('id', $subquery)
        ->get();

    expect($posts)->toHaveCount(2);
    expect(array_column($posts, 'id'))->toBe([2, 4]);
});

test('complex whereIn with multiple conditions in subquery', function () {
    // Add more meta data
    $this->connection->table('post_meta')->insert([
        ['post_id' => 1, 'meta_key' => 'priority', 'meta_value' => 'high'],
        ['post_id' => 3, 'meta_key' => 'priority', 'meta_value' => 'low'],
    ]);

    $subquery = $this->connection->table('post_meta')
        ->select('post_id')
        ->where('meta_key', 'featured')
        ->where('meta_value', 'yes')
        ->whereExists(function($q) {
            $q->from('post_meta as pm2')
              ->whereRaw('pm2.post_id = post_meta.post_id')
              ->where('pm2.meta_key', 'priority')
              ->where('pm2.meta_value', 'high');
        });

    $posts = $this->connection->table('posts')
        ->whereIn('id', $subquery)
        ->get();

    expect($posts)->toHaveCount(1);
    expect($posts[0]->id)->toBe(1);
});

test('orWhereIn with subquery', function () {
    $featuredSubquery = $this->connection->table('post_meta')
        ->select('post_id')
        ->where('meta_key', 'featured')
        ->where('meta_value', 'yes');

    $posts = $this->connection->table('posts')
        ->where('author_id', 2)
        ->orWhereIn('id', $featuredSubquery)
        ->orderBy('id')
        ->get();

    expect($posts)->toHaveCount(2);
    expect(array_column($posts, 'id'))->toBe([1, 3]);
});

test('whereIn generates correct SQL with subquery', function () {
    $subquery = $this->connection->table('post_meta')
        ->select('post_id')
        ->where('meta_key', 'featured');

    $sql = $this->connection->table('posts')
        ->whereIn('id', $subquery)
        ->toSql();

    expect($sql)->toContain('where "id" in (');
    expect($sql)->toContain('select "post_id" from "post_meta"');
    expect($sql)->toContain('where "meta_key" = ?');
});

class TestSubqueryPost extends Model {
    protected string $table = 'posts';
    protected bool $timestamps = false;
}

test('Model whereIn with subquery', function () {
    $subquery = $this->connection->table('post_meta')
        ->select('post_id')
        ->where('meta_key', 'featured')
        ->where('meta_value', 'yes');

    $posts = TestSubqueryPost::whereIn('id', $subquery)->get();

    expect($posts)->toHaveCount(2);
    expect($posts[0]->id)->toBe(1);
    expect($posts[1]->id)->toBe(3);
});