<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;

/**
 * Test for Issue #17: REAL WordPress Structure
 *
 * This test replicates the ACTUAL WordPress database structure
 * where terms and term_taxonomy are separate tables
 */
class RealWpPost extends Model
{
    protected string $table = 'posts';

    protected string $primaryKey = 'ID';

    public bool $timestamps = false;
}

class RealWpTerm extends Model
{
    protected string $table = 'terms';

    protected string $primaryKey = 'term_id';

    public bool $timestamps = false;

    // Add a global scope to join with term_taxonomy table
    protected static function booted(): void
    {
        static::addGlobalScope('taxonomy', function ($builder) {
            $builder->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
                ->select('terms.*', 'term_taxonomy.term_taxonomy_id', 'term_taxonomy.taxonomy', 'term_taxonomy.parent', 'term_taxonomy.count');
        });
    }

    public function posts()
    {
        // The REAL issue: term_taxonomy_id is NOT on the terms table
        // It's on the term_taxonomy table, but we're using it as the local key
        return $this->belongsToMany(
            RealWpPost::class,
            'term_relationships',  // Pivot table
            'term_taxonomy_id',    // Foreign key in pivot
            'object_id',          // Related key in pivot
            'term_taxonomy_id',    // Local key - BUT THIS ISN'T ON TERMS TABLE!
            'ID'                  // Related key on post
        );
    }
}

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create EXACT WordPress table structure
    $this->connection->unprepared('
        CREATE TABLE posts (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            post_title TEXT,
            post_content TEXT,
            post_status TEXT
        )
    ');

    // Terms table - NOTE: NO term_taxonomy_id column!
    $this->connection->unprepared('
        CREATE TABLE terms (
            term_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL
        )
    ');

    // Term taxonomy table - THIS has term_taxonomy_id
    $this->connection->unprepared('
        CREATE TABLE term_taxonomy (
            term_taxonomy_id INTEGER PRIMARY KEY AUTOINCREMENT,
            term_id INTEGER NOT NULL,
            taxonomy TEXT NOT NULL,
            parent INTEGER DEFAULT 0,
            count INTEGER DEFAULT 0
        )
    ');

    // Pivot table references term_taxonomy_id, not term_id
    $this->connection->unprepared('
        CREATE TABLE term_relationships (
            object_id INTEGER NOT NULL,
            term_taxonomy_id INTEGER NOT NULL,
            term_order INTEGER DEFAULT 0,
            PRIMARY KEY (object_id, term_taxonomy_id)
        )
    ');

    // Insert test data
    $this->connection->table('posts')->insert([
        ['ID' => 1, 'post_title' => 'First Post', 'post_content' => 'Content 1', 'post_status' => 'publish'],
        ['ID' => 2, 'post_title' => 'Second Post', 'post_content' => 'Content 2', 'post_status' => 'publish'],
    ]);

    $this->connection->table('terms')->insert([
        ['term_id' => 1, 'name' => 'Technology', 'slug' => 'technology'],
        ['term_id' => 2, 'name' => 'PHP', 'slug' => 'php'],
    ]);

    $this->connection->table('term_taxonomy')->insert([
        ['term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'parent' => 0, 'count' => 2],
        ['term_taxonomy_id' => 2, 'term_id' => 2, 'taxonomy' => 'category', 'parent' => 0, 'count' => 1],
    ]);

    // Create relationships using term_taxonomy_id
    $this->connection->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 1], // Post 1 -> Technology (via taxonomy 1)
        ['object_id' => 1, 'term_taxonomy_id' => 2], // Post 1 -> PHP (via taxonomy 2)
        ['object_id' => 2, 'term_taxonomy_id' => 1], // Post 2 -> Technology
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #17: Real WordPress structure - term_taxonomy_id from JOINed table', function () {
    // Get a term with the global scope applied
    $term = RealWpTerm::find(1);

    // The term should have term_taxonomy_id from the JOINed table
    expect($term)->not->toBeNull();
    expect($term->term_id)->toBe(1);
    expect($term->name)->toBe('Technology');

    // This is from the JOINed term_taxonomy table
    expect($term->term_taxonomy_id)->toBe(1);

    // Now try to get posts - this was previously causing parameter binding errors
    $posts = $term->posts()->get();

    // Should have 2 posts
    expect($posts)->toHaveCount(2);
});

test('ISSUE #17: Debug SQL generation with JOINed field as local key', function () {
    $term = RealWpTerm::find(1);

    // The term should have term_taxonomy_id from JOIN
    expect($term->term_taxonomy_id)->toBe(1);

    // Get the relationship
    $relation = $term->posts();

    // Check the query generation
    $sql = $relation->toSql();
    $bindings = $relation->getBindings();

    // Check if parameter counts match
    $placeholderCount = substr_count($sql, '?');
    $bindingCount = count($bindings);

    expect($bindingCount)->toBe($placeholderCount);
});

test('ISSUE #17: The real problem - getAttribute returns null for JOINed fields in relationship context', function () {
    $term = RealWpTerm::find(1);

    // Verify that getAttribute works correctly for JOINed fields
    expect($term->getAttribute('term_id'))->toBe(1);
    expect($term->getAttribute('term_taxonomy_id'))->toBe(1);

    // Verify direct property access also works
    expect($term->term_taxonomy_id)->toBe(1);

    // Ensure the relationship can access the JOINed field correctly
    $relation = $term->posts();

    expect($term->term_taxonomy_id)->toBe(1);
});
