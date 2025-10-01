<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;

/**
 * Test for Issue #17: NULL value scenario
 *
 * Testing if the parameter binding error occurs when parentKey value is NULL
 */
class NullTestPost extends Model
{
    protected string $table = 'posts';

    protected string $primaryKey = 'ID';

    public bool $timestamps = false;
}

class NullTestTerm extends Model
{
    protected string $table = 'terms';

    protected string $primaryKey = 'term_id';

    public bool $timestamps = false;

    public function posts()
    {
        return $this->belongsToMany(
            NullTestPost::class,
            'term_relationships',
            'term_taxonomy_id',    // Foreign key in pivot
            'object_id',           // Related key in pivot
            'term_taxonomy_id',    // Local key - This might be NULL!
            'ID'                   // Related key
        );
    }
}

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Simple structure
    $this->connection->unprepared('
        CREATE TABLE posts (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            post_title TEXT
        )
    ');

    $this->connection->unprepared('
        CREATE TABLE terms (
            term_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            term_taxonomy_id INTEGER
        )
    ');

    $this->connection->unprepared('
        CREATE TABLE term_relationships (
            object_id INTEGER NOT NULL,
            term_taxonomy_id INTEGER NOT NULL,
            PRIMARY KEY (object_id, term_taxonomy_id)
        )
    ');

    // Insert data
    $this->connection->table('posts')->insert([
        ['ID' => 1, 'post_title' => 'Post 1'],
        ['ID' => 2, 'post_title' => 'Post 2'],
    ]);

    // Insert terms - one with term_taxonomy_id, one without
    $this->connection->table('terms')->insert([
        ['term_id' => 1, 'name' => 'Has Taxonomy', 'term_taxonomy_id' => 1],
        ['term_id' => 2, 'name' => 'No Taxonomy', 'term_taxonomy_id' => null],
    ]);

    $this->connection->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 1],
        ['object_id' => 2, 'term_taxonomy_id' => 1],
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #17: BelongsToMany with NULL parent key value', function () {
    // Term without term_taxonomy_id
    $term = NullTestTerm::find(2);
    expect($term->name)->toBe('No Taxonomy');
    expect($term->term_taxonomy_id)->toBeNull();

    // This might cause issues if NULL is not handled properly
    $sql = $term->posts()->toSql();
    $bindings = $term->posts()->getBindings();

    // Check parameter counts
    $placeholderCount = substr_count($sql, '?');
    $bindingCount = count($bindings);

    expect($bindingCount)->toBe($placeholderCount);

    // Try to execute
    $posts = $term->posts()->get();
    expect($posts)->toHaveCount(0); // Should be empty since term_taxonomy_id is NULL
});

test('ISSUE #17: Check how WHERE clause handles NULL values', function () {
    $term = NullTestTerm::find(2);

    // Let's manually check what happens with NULL in WHERE
    $builder = $this->connection->table('posts');
    $builder->where('term_taxonomy_id', '=', null);

    $sql = $builder->toSql();
    $bindings = $builder->getBindings();

    // Laravel typically converts = NULL to IS NULL
    // Check that placeholders and bindings match
    $placeholderCount = substr_count($sql, '?');
    $bindingCount = count($bindings);

    expect($bindingCount)->toBe($placeholderCount);

    // Should convert = NULL to IS NULL
    expect($sql)->toContain('is null');
});
