<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Query\Builder as BobBuilder;

/**
 * Test for Issue #10: Model Attribute Hydration from JOINed Tables
 *
 * The problem: When fields are SELECTed from JOINed tables via global scopes,
 * the model doesn't have these attributes accessible even though they're in the result.
 */
class CategoryWithJoinedFields extends Model
{
    protected string $table = 'terms';

    protected string $primaryKey = 'term_id';

    public bool $timestamps = false;

    protected static function booted(): void
    {
        // This global scope adds fields from joined table
        static::addGlobalScope('category_taxonomy', function (BobBuilder $builder) {
            $builder->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
                ->where('term_taxonomy.taxonomy', 'category')
                ->select('terms.*',
                    'term_taxonomy.parent',
                    'term_taxonomy.description as tax_description',
                    'term_taxonomy.count',
                    'term_taxonomy.term_taxonomy_id');
        });
    }
}

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create WordPress-like tables
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
            count INTEGER DEFAULT 0,
            description TEXT
        )
    ');

    // Insert test data
    $this->connection->table('terms')->insert([
        ['term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized'],
        ['term_id' => 2, 'name' => 'Technology', 'slug' => 'technology'],
        ['term_id' => 3, 'name' => 'PHP', 'slug' => 'php'],
    ]);

    $this->connection->table('term_taxonomy')->insert([
        ['term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'parent' => 0, 'count' => 5, 'description' => 'Default category'],
        ['term_taxonomy_id' => 2, 'term_id' => 2, 'taxonomy' => 'category', 'parent' => 0, 'count' => 10, 'description' => 'Tech posts'],
        ['term_taxonomy_id' => 3, 'term_id' => 3, 'taxonomy' => 'category', 'parent' => 2, 'count' => 3, 'description' => 'PHP posts'],
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #10: Model should have JOINed fields as attributes', function () {
    $category = CategoryWithJoinedFields::find(1);

    // These are from the 'terms' table - should work
    expect($category->term_id)->toBe(1);
    expect($category->name)->toBe('Uncategorized');
    expect($category->slug)->toBe('uncategorized');

    // These are from the JOINed 'term_taxonomy' table - SHOULD work but might not
    expect($category->parent)->toBe(0); // This is the issue!
    expect($category->count)->toBe(5);
    expect($category->tax_description)->toBe('Default category');
    expect($category->term_taxonomy_id)->toBe(1);
});

test('ISSUE #10: get() should hydrate all selected fields', function () {
    $categories = CategoryWithJoinedFields::get();

    expect($categories)->toHaveCount(3);

    // First category
    $first = $categories[0];
    expect($first->name)->toBe('Uncategorized');
    expect($first->parent)->toBe(0); // JOINed field
    expect($first->count)->toBe(5); // JOINed field

    // Second category
    $second = $categories[1];
    expect($second->name)->toBe('Technology');
    expect($second->parent)->toBe(0); // JOINed field
    expect($second->count)->toBe(10); // JOINed field

    // Third category (child)
    $third = $categories[2];
    expect($third->name)->toBe('PHP');
    expect($third->parent)->toBe(2); // Has parent!
    expect($third->count)->toBe(3);
});

test('ISSUE #10: Model attributes should include JOINed fields', function () {
    $category = CategoryWithJoinedFields::find(2);

    // Get all attributes
    $attributes = $category->getAttributes();

    // Should have fields from both tables
    expect($attributes)->toHaveKey('term_id');
    expect($attributes)->toHaveKey('name');
    expect($attributes)->toHaveKey('slug');
    expect($attributes)->toHaveKey('parent'); // From JOIN
    expect($attributes)->toHaveKey('count'); // From JOIN
    expect($attributes)->toHaveKey('tax_description'); // From JOIN
    expect($attributes)->toHaveKey('term_taxonomy_id'); // From JOIN
});

test('ISSUE #10: Model toArray should include JOINed fields', function () {
    $category = CategoryWithJoinedFields::find(1);

    $array = $category->toArray();

    // Should include all selected fields
    expect($array)->toMatchArray([
        'term_id' => 1,
        'name' => 'Uncategorized',
        'slug' => 'uncategorized',
        'parent' => 0,
        'count' => 5,
        'tax_description' => 'Default category',
        'term_taxonomy_id' => 1,
    ]);
});

test('Direct query builder returns all fields correctly', function () {
    // This is what the model query should produce
    $result = $this->connection->table('terms')
        ->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
        ->where('term_taxonomy.taxonomy', 'category')
        ->select('terms.*',
            'term_taxonomy.parent',
            'term_taxonomy.description as tax_description',
            'term_taxonomy.count',
            'term_taxonomy.term_taxonomy_id')
        ->where('terms.term_id', 1)
        ->first();

    // Direct query should have all fields
    expect($result->term_id)->toBe(1);
    expect($result->name)->toBe('Uncategorized');
    expect($result->parent)->toBe(0);
    expect($result->count)->toBe(5);
    expect($result->tax_description)->toBe('Default category');
});

test('ISSUE #10: Setting JOINed field values should work', function () {
    $category = CategoryWithJoinedFields::find(1);

    // Should be able to set values from JOINed fields
    $category->parent = 99;
    $category->count = 100;

    expect($category->parent)->toBe(99);
    expect($category->count)->toBe(100);

    // These should be tracked as dirty
    expect($category->isDirty('parent'))->toBeTrue();
    expect($category->isDirty('count'))->toBeTrue();
});
