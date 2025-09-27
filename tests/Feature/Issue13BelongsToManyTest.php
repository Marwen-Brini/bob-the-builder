<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Query\Builder as BobBuilder;

/**
 * Test for Issue #13: BelongsToMany Relationship Issues
 *
 * The problem: BelongsToMany relationships are not working correctly,
 * especially in WordPress context with term_relationships pivot table.
 */

class Post extends Model
{
    protected string $table = 'posts';
    protected string $primaryKey = 'ID';
    public bool $timestamps = false;
}

class Category extends Model
{
    protected string $table = 'terms';
    protected string $primaryKey = 'term_id';
    public bool $timestamps = false;

    public function posts()
    {
        // WordPress-style relationship
        return $this->belongsToMany(
            Post::class,
            'term_relationships',  // pivot table
            'term_taxonomy_id',    // foreign key in pivot (references this model)
            'object_id',           // related key in pivot (references Post)
            'term_taxonomy_id',    // local key (this model's key)
            'ID'                   // related model's key
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
            post_title TEXT NOT NULL,
            post_content TEXT
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
        ['ID' => 1, 'post_title' => 'First Post', 'post_content' => 'Content 1'],
        ['ID' => 2, 'post_title' => 'Second Post', 'post_content' => 'Content 2'],
        ['ID' => 3, 'post_title' => 'Third Post', 'post_content' => 'Content 3'],
    ]);

    $this->connection->table('terms')->insert([
        ['term_id' => 1, 'name' => 'Technology', 'slug' => 'technology'],
        ['term_id' => 2, 'name' => 'PHP', 'slug' => 'php'],
    ]);

    $this->connection->table('term_taxonomy')->insert([
        ['term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'count' => 2],
        ['term_taxonomy_id' => 2, 'term_id' => 2, 'taxonomy' => 'category', 'count' => 1],
    ]);

    // Posts 1 and 2 are in Technology category
    // Post 1 is also in PHP category
    $this->connection->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 1], // Post 1 -> Technology
        ['object_id' => 1, 'term_taxonomy_id' => 2], // Post 1 -> PHP
        ['object_id' => 2, 'term_taxonomy_id' => 1], // Post 2 -> Technology
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #13: BelongsToMany should work with WordPress-style relationships', function () {
    $category = Category::find(1); // Technology category

    // Get posts in this category
    $posts = $category->posts()->get();

    // Should have 2 posts (Post 1 and Post 2)
    expect($posts)->toHaveCount(2);
    expect($posts[0]->ID)->toBeIn([1, 2]);
    expect($posts[1]->ID)->toBeIn([1, 2]);
});

test('ISSUE #13: BelongsToMany should generate correct SQL', function () {
    $category = Category::find(1);

    // Get the SQL that will be generated
    $sql = $category->posts()->toSql();

    // Should join the pivot table and filter by term_taxonomy_id
    expect($sql)->toContain('term_relationships');
    expect($sql)->toContain('posts.ID');
    expect($sql)->toContain('term_taxonomy_id');
});

test('ISSUE #13: BelongsToMany should handle empty relationships', function () {
    // Create a category with no posts
    $this->connection->table('terms')->insert([
        'term_id' => 3,
        'name' => 'Empty Category',
        'slug' => 'empty'
    ]);
    $this->connection->table('term_taxonomy')->insert([
        'term_taxonomy_id' => 3,
        'term_id' => 3,
        'taxonomy' => 'category',
        'count' => 0
    ]);

    $category = Category::find(3);
    $posts = $category->posts()->get();

    // Should return empty collection
    expect($posts)->toBeEmpty();
});

test('ISSUE #13: BelongsToMany should work with eager loading', function () {
    $categories = Category::with('posts')->get();

    // All categories should have their posts loaded
    expect($categories)->toHaveCount(2);

    $techCategory = $categories->firstWhere('term_id', 1);
    expect($techCategory->posts)->toHaveCount(2);

    $phpCategory = $categories->firstWhere('term_id', 2);
    expect($phpCategory->posts)->toHaveCount(1);
});