<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;

/**
 * Test for Issue #17: BelongsToMany Parameter Binding Error
 *
 * CRITICAL: BelongsToMany relationships fail with parameter binding error
 * This makes WordPress taxonomy system completely unusable
 */
class WpPost extends Model
{
    protected string $table = 'posts';

    protected string $primaryKey = 'ID';

    public bool $timestamps = false;
}

class WpTerm extends Model
{
    protected string $table = 'terms';

    protected string $primaryKey = 'term_id';

    public bool $timestamps = false;

    public function posts()
    {
        // WordPress-style relationship where term_taxonomy_id appears as both foreign and local key
        return $this->belongsToMany(
            WpPost::class,
            'term_relationships',  // Pivot table
            'term_taxonomy_id',    // Foreign key in pivot (references term_taxonomy.term_taxonomy_id)
            'object_id',          // Related key in pivot (references posts.ID)
            'term_taxonomy_id',    // Local key on term (same as foreign key!)
            'ID'                  // Related key on post
        );
    }
}

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create WordPress-like tables
    $this->connection->unprepared('
        CREATE TABLE posts (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            post_title TEXT,
            post_content TEXT,
            post_status TEXT
        )
    ');

    $this->connection->unprepared('
        CREATE TABLE terms (
            term_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL
        )
    ');

    $this->connection->unprepared('
        CREATE TABLE term_taxonomy (
            term_taxonomy_id INTEGER PRIMARY KEY AUTOINCREMENT,
            term_id INTEGER NOT NULL,
            taxonomy TEXT NOT NULL,
            parent INTEGER DEFAULT 0,
            count INTEGER DEFAULT 0
        )
    ');

    // Pivot table
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
        ['ID' => 3, 'post_title' => 'Third Post', 'post_content' => 'Content 3', 'post_status' => 'draft'],
    ]);

    $this->connection->table('terms')->insert([
        ['term_id' => 1, 'name' => 'Technology', 'slug' => 'technology'],
        ['term_id' => 2, 'name' => 'PHP', 'slug' => 'php'],
    ]);

    $this->connection->table('term_taxonomy')->insert([
        ['term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'parent' => 0, 'count' => 2],
        ['term_taxonomy_id' => 2, 'term_id' => 2, 'taxonomy' => 'category', 'parent' => 0, 'count' => 1],
    ]);

    // Create relationships
    $this->connection->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 1], // Post 1 -> Technology
        ['object_id' => 1, 'term_taxonomy_id' => 2], // Post 1 -> PHP
        ['object_id' => 2, 'term_taxonomy_id' => 1], // Post 2 -> Technology
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #17: BelongsToMany should work with WordPress-style relationships without parameter binding errors', function () {
    // This is the CRITICAL test case that's failing
    $term = WpTerm::find(1);
    expect($term)->not->toBeNull();
    expect($term->term_id)->toBe(1);
    expect($term->name)->toBe('Technology');

    // Add term_taxonomy_id to the term model (as WordPress does)
    $term->term_taxonomy_id = 1; // This is the key issue - the term needs to know its taxonomy ID

    // This should NOT throw a parameter binding error
    $posts = $term->posts()->get();

    // Should have 2 posts (Post 1 and Post 2)
    expect($posts)->toHaveCount(2);
    expect($posts[0]->ID)->toBeIn([1, 2]);
    expect($posts[1]->ID)->toBeIn([1, 2]);
});

test('ISSUE #17: BelongsToMany should generate correct SQL with proper bindings', function () {
    $term = WpTerm::find(1);
    $term->term_taxonomy_id = 1; // Set the taxonomy ID

    // Get the SQL and bindings
    $sql = $term->posts()->toSql();
    $bindings = $term->posts()->getBindings();

    // Count placeholders in SQL
    $placeholderCount = substr_count($sql, '?');
    $bindingCount = count($bindings);

    // The counts MUST match - this is the critical issue
    expect($bindingCount)->toBe($placeholderCount,
        "Parameter binding mismatch: SQL has {$placeholderCount} placeholders but {$bindingCount} bindings"
    );

    // Should contain the necessary SQL parts
    expect($sql)->toContain('term_relationships');
    expect($sql)->toContain('posts');
    expect($sql)->toContain('term_taxonomy_id');
});

test('ISSUE #17: BelongsToMany should work with additional WHERE constraints', function () {
    $term = WpTerm::find(1);
    $term->term_taxonomy_id = 1;

    // Add additional constraints
    $publishedPosts = $term->posts()
        ->where('post_status', 'publish')
        ->get();

    // Should only get published posts
    expect($publishedPosts)->toHaveCount(2);
    foreach ($publishedPosts as $post) {
        expect($post->post_status)->toBe('publish');
    }
});

test('ISSUE #17: BelongsToMany should work with eager loading', function () {
    // Add the term_taxonomy_id attribute to terms
    $terms = WpTerm::all();
    foreach ($terms as $index => $term) {
        $term->term_taxonomy_id = $index + 1; // term 1 -> taxonomy 1, term 2 -> taxonomy 2
    }

    // Test eager loading with modified terms
    $categories = [];
    foreach ($terms as $term) {
        $term->load('posts');
        $categories[] = $term;
    }

    expect($categories)->toHaveCount(2);

    // Technology category should have 2 posts
    expect($categories[0]->posts)->toHaveCount(2);

    // PHP category should have 1 post
    expect($categories[1]->posts)->toHaveCount(1);
});

test('ISSUE #17: Debug the actual parameter binding issue', function () {
    $term = WpTerm::find(1);
    $term->term_taxonomy_id = 1;

    // Get the relationship
    $relation = $term->posts();

    // Get the underlying query
    $query = $relation->getQuery();

    // Now try to execute
    $posts = $relation->get();
    expect($posts)->toHaveCount(2);
});
