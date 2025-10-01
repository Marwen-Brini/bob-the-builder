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

    // Create test table
    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            status TEXT,
            author_id INTEGER,
            published_at DATETIME,
            created_at DATETIME,
            updated_at DATETIME
        )
    ');

    // Insert test data
    $this->connection->table('posts')->insert([
        ['title' => 'Post 1', 'status' => 'published', 'author_id' => 1, 'published_at' => '2024-01-01 10:00:00'],
        ['title' => 'Post 2', 'status' => 'draft', 'author_id' => 1, 'published_at' => null],
        ['title' => 'Post 3', 'status' => 'published', 'author_id' => 2, 'published_at' => '2024-01-02 10:00:00'],
        ['title' => 'Post 4', 'status' => 'published', 'author_id' => 1, 'published_at' => '2024-01-03 10:00:00'],
    ]);
});

afterEach(function () {
    Model::clearConnection();
});

class TestScopePost extends Model
{
    protected string $table = 'posts';

    protected bool $timestamps = false;

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeByAuthor(Builder $query, int $authorId): Builder
    {
        return $query->where('author_id', $authorId);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        $date = date('Y-m-d', strtotime("-$days days"));

        return $query->where('published_at', '>=', $date);
    }
}

test('can chain single scope method', function () {
    // This should work
    $posts = TestScopePost::published()->get();

    expect($posts)->toHaveCount(3);
    // Check that all posts have 'published' status
    foreach ($posts as $post) {
        expect($post->status)->toBe('published');
    }
});

test('can chain multiple scope methods', function () {
    // This should work: Post::published()->byAuthor(1)->get()
    $posts = TestScopePost::published()->byAuthor(1)->get();

    expect($posts)->toHaveCount(2);
    $titles = array_map(fn ($p) => $p->title, $posts);
    expect($titles)->toBe(['Post 1', 'Post 4']);
});

test('can chain three scope methods', function () {
    // Update dates to be recent
    $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
    $lastWeek = date('Y-m-d H:i:s', strtotime('-7 days'));

    TestScopePost::query()->where('id', 1)->update(['published_at' => $yesterday]);
    TestScopePost::query()->where('id', 4)->update(['published_at' => $lastWeek]);

    $posts = TestScopePost::published()->byAuthor(1)->recent(10)->get();

    expect($posts)->toHaveCount(2);
});

test('scope methods work with other query builder methods', function () {
    $posts = TestScopePost::published()
        ->orderBy('published_at', 'desc')
        ->limit(2)
        ->get();

    expect($posts)->toHaveCount(2);
    expect($posts[0]->title)->toBe('Post 4');
});

test('scope methods work with where conditions', function () {
    $posts = TestScopePost::published()
        ->where('title', 'like', '%3%')
        ->get();

    expect($posts)->toHaveCount(1);
    expect($posts[0]->title)->toBe('Post 3');
});

test('scope with parameters works correctly', function () {
    $posts = TestScopePost::byAuthor(2)->get();

    expect($posts)->toHaveCount(1);
    expect($posts[0]->author_id)->toBe(2);
});

test('multiple parameterized scopes work', function () {
    // Create a post with today's date
    $today = date('Y-m-d H:i:s');
    TestScopePost::query()->where('id', 4)->update(['published_at' => $today]);

    $posts = TestScopePost::byAuthor(1)->recent(1)->get();

    expect($posts)->toHaveCount(1);
    expect($posts[0]->id)->toBe(4);
});
