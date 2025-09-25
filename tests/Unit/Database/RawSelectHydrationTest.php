<?php

namespace Tests\Unit\Database;

use Bob\Database\Model;
use Bob\Database\Connection;
use Bob\Query\Builder;
use Mockery as m;

class TestRawSelectModel extends Model
{
    protected string $table = 'posts';
    protected string $primaryKey = 'id';
    protected array $fillable = ['id', 'title', 'content'];
}

beforeEach(function () {
    // Create a real SQLite connection for integration testing
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $this->connection = new Connection(['driver' => 'sqlite'], null, $pdo);

    // Create test table
    $this->connection->statement('CREATE TABLE posts (
        id INTEGER PRIMARY KEY,
        title TEXT,
        content TEXT,
        status TEXT,
        created_at TIMESTAMP
    )');

    // Insert test data
    $this->connection->statement('INSERT INTO posts (id, title, content, status) VALUES
        (1, "First Post", "Content 1", "published"),
        (2, "Second Post", "Content 2", "published"),
        (3, "Third Post", "Content 3", "draft"),
        (4, "Fourth Post", "Content 4", "published")');

    Model::setConnection($this->connection);
});

afterEach(function () {
    Model::clearConnection();
});

test('selectRaw with aggregate functions hydrates model attributes', function () {
    // Test case from the bug report
    $result = TestRawSelectModel::query()
        ->selectRaw('COUNT(*) as total')
        ->whereRaw('id = ?', [1])
        ->first();

    // This should work - the 'total' attribute should be accessible
    expect($result)->toBeInstanceOf(TestRawSelectModel::class);
    expect($result->total)->toBe(1);
    expect($result->getAttribute('total'))->toBe(1);
});

test('selectRaw with multiple aggregates hydrates all attributes', function () {
    $result = TestRawSelectModel::query()
        ->selectRaw('COUNT(*) as count, MAX(id) as max_id, MIN(id) as min_id')
        ->where('status', 'published')
        ->first();

    expect($result->count)->toBe(3);
    expect($result->max_id)->toBe(4);
    expect($result->min_id)->toBe(1);
});

test('selectRaw with calculated fields hydrates properly', function () {
    $result = TestRawSelectModel::query()
        ->selectRaw('id, title, LENGTH(content) as content_length')
        ->where('id', 1)
        ->first();

    expect($result->id)->toBe(1);
    expect($result->title)->toBe('First Post');
    expect($result->content_length)->toBe(9); // "Content 1" has 9 characters
});

test('selectRaw with CASE statements hydrates properly', function () {
    $results = TestRawSelectModel::query()
        ->selectRaw('id, title, CASE WHEN status = "published" THEN 1 ELSE 0 END as is_published')
        ->get();

    expect($results)->toHaveCount(4);
    expect($results[0]->is_published)->toBe(1); // First post is published
    expect($results[2]->is_published)->toBe(0); // Third post is draft
});

test('mixed select and selectRaw hydrates all columns', function () {
    $result = TestRawSelectModel::query()
        ->select('id', 'title')
        ->selectRaw('UPPER(status) as status_upper')
        ->where('id', 1)
        ->first();

    expect($result->id)->toBe(1);
    expect($result->title)->toBe('First Post');
    expect($result->status_upper)->toBe('PUBLISHED');
});

test('selectRaw with subquery hydrates properly', function () {
    $result = TestRawSelectModel::query()
        ->selectRaw('(SELECT COUNT(*) FROM posts WHERE status = "published") as published_count')
        ->first();

    expect($result->published_count)->toBe(3);
});

test('get() method also hydrates raw select properly', function () {
    $results = TestRawSelectModel::query()
        ->selectRaw('COUNT(*) as total, status')
        ->groupBy('status')
        ->get();

    expect($results)->toHaveCount(2);

    // Find specific results
    $published = null;
    $draft = null;
    foreach ($results as $result) {
        if ($result->status === 'published') {
            $published = $result;
        } elseif ($result->status === 'draft') {
            $draft = $result;
        }
    }

    expect($published)->not->toBeNull();
    expect($draft)->not->toBeNull();
    expect($published->total)->toBe(3);
    expect($draft->total)->toBe(1);
});

test('hydration works with complex raw SQL', function () {
    $result = TestRawSelectModel::query()
        ->selectRaw('
            id,
            title,
            CASE
                WHEN id <= 2 THEN "old"
                WHEN id > 2 AND id <= 3 THEN "medium"
                ELSE "new"
            END as age_category,
            LENGTH(title) as title_length
        ')
        ->where('id', 1)
        ->first();

    expect($result->id)->toBe(1);
    expect($result->title)->toBe('First Post');
    expect($result->age_category)->toBe('old');
    expect($result->title_length)->toBe(10); // "First Post" has 10 characters
});

test('model attributes are accessible via property and getAttribute after raw select', function () {
    $result = TestRawSelectModel::query()
        ->selectRaw('COUNT(*) as total')
        ->first();

    // Property access
    expect($result->total)->toBe(4);

    // getAttribute method
    expect($result->getAttribute('total'))->toBe(4);

    // Check if attribute exists
    $attributes = $result->getAttributes();
    expect(array_key_exists('total', $attributes))->toBeTrue();
});

test('toArray includes raw selected attributes', function () {
    $result = TestRawSelectModel::query()
        ->selectRaw('id, COUNT(*) OVER() as total_count')
        ->where('id', 1)
        ->first();

    $array = $result->toArray();

    expect($array)->toHaveKey('id');
    expect($array)->toHaveKey('total_count');
    expect($array['id'])->toBe(1);
    expect($array['total_count'])->toBe(1);
});

test('raw select without table columns still creates model instance', function () {
    $result = TestRawSelectModel::query()
        ->selectRaw('1 + 1 as calculation, "test" as test_string')
        ->first();

    expect($result)->toBeInstanceOf(TestRawSelectModel::class);
    expect($result->calculation)->toBe(2);
    expect($result->test_string)->toBe('test');
});

test('null values from raw select are properly handled', function () {
    $result = TestRawSelectModel::query()
        ->selectRaw('id, NULL as null_field, "" as empty_string')
        ->where('id', 1)
        ->first();

    expect($result->id)->toBe(1);
    expect($result->null_field)->toBeNull();
    expect($result->empty_string)->toBe('');
});