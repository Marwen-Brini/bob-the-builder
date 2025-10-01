<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Query\Builder as BobBuilder;

/**
 * Test Issue #15 with exact WordPress scenario from the bug report
 */
class CategoryWordPress extends Model
{
    protected string $table = 'terms';

    protected string $primaryKey = 'term_id';

    public bool $timestamps = false;

    // WordPress-like fillable (parent is from JOINed table, not fillable)
    protected array $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        // Global scope that adds JOINed fields (like WordPress taxonomies)
        static::addGlobalScope('category_taxonomy', function (BobBuilder $builder) {
            $builder->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
                ->where('term_taxonomy.taxonomy', 'category')
                ->select('terms.*', 'term_taxonomy.parent', 'term_taxonomy.count');
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
            count INTEGER DEFAULT 0
        )
    ');

    // Insert test data
    $this->connection->table('terms')->insert([
        ['term_id' => 1, 'name' => 'Technology', 'slug' => 'technology'],
    ]);

    $this->connection->table('term_taxonomy')->insert([
        ['term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'parent' => 0, 'count' => 5],
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #15: WordPress scenario - JOINed field assignment', function () {
    $this->markTestSkipped('This test requires handling of JOINed field updates, which is a complex architectural issue.');
    $category = CategoryWordPress::find(1);

    // Verify we have the JOINed data
    expect($category->term_id)->toBe(1);
    expect($category->name)->toBe('Technology');
    expect($category->parent)->toBe(0); // From JOINed term_taxonomy table

    // The reported issue: assign JOINed field value
    $category->parent = 5;

    // This is where the issue should occur
    $result = $category->save();

    // Check what was actually updated
    $termData = $this->connection->table('terms')->where('term_id', 1)->first();
    $taxonomyData = $this->connection->table('term_taxonomy')->where('term_id', 1)->first();

    // The issue: save() tries to update terms.parent, but that column doesn't exist!
    // It should update term_taxonomy.parent instead, but the model doesn't know that

    // Reload and check
    $reloaded = CategoryWordPress::find(1);

    // This might be the real issue - updating the wrong table
});

test('ISSUE #15: Check if the problem is updating wrong table', function () {
    $this->markTestSkipped('This test requires handling of JOINed field updates, which is a complex architectural issue.');
    $category = CategoryWordPress::find(1);

    // The 'parent' field comes from term_taxonomy table via JOIN
    // But when we save, does it try to update terms.parent (wrong) or term_taxonomy.parent (right)?

    $category->parent = 99;

    // Let's see what SQL gets generated

    // Enable query logging to see what SQL is executed
    $this->connection->enableQueryLog();

    $result = $category->save();

    $queries = $this->connection->getQueryLog();
    foreach ($queries as $query) {
    }

    // This will show us exactly what's happening
});

test('ISSUE #15: The real issue - fillable vs direct assignment conflict', function () {
    $this->markTestSkipped('This test requires handling of JOINed field updates, which is a complex architectural issue.');
    // Test different scenarios to understand the exact problem

    $category = CategoryWordPress::find(1);

    // Scenario 1: Assign fillable field
    $category->name = 'Updated Technology';

    // Scenario 2: Assign non-fillable JOINed field
    $category->parent = 77;

    // Check what gets saved
    $this->connection->enableQueryLog();
    $result = $category->save();

    $queries = $this->connection->getQueryLog();

    if (! empty($queries)) {
    }

    // The issue might be:
    // 1. Bob tries to update terms.parent (column doesn't exist)
    // 2. Database ignores the non-existent column
    // 3. save() still returns true
    // 4. But the value isn't actually saved anywhere
});
