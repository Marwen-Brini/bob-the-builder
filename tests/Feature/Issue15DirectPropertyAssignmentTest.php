<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;

/**
 * Test for Issue #15: Direct Property Assignment Not Persisted
 *
 * The problem: When you assign a property directly and call save(),
 * save() returns true but the value is not actually persisted to the database.
 */

class CategoryForIssue15 extends Model
{
    protected string $table = 'categories';
    protected string $primaryKey = 'id';
    public bool $timestamps = false;

    // Test with different fillable configurations
    protected array $fillable = ['name', 'slug']; // 'parent' is NOT fillable
}

class CategoryFillableParent extends Model
{
    protected string $table = 'categories';
    protected string $primaryKey = 'id';
    public bool $timestamps = false;

    protected array $fillable = ['name', 'slug', 'parent']; // 'parent' IS fillable
}

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create test table
    $this->connection->unprepared('
        CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            parent INTEGER DEFAULT 0,
            description TEXT
        )
    ');

    // Insert test data
    $this->connection->table('categories')->insert([
        ['id' => 1, 'name' => 'Technology', 'slug' => 'technology', 'parent' => 0],
        ['id' => 2, 'name' => 'PHP', 'slug' => 'php', 'parent' => 0],
    ]);

    Model::setConnection($this->connection);
});

test('ISSUE #15: Direct property assignment should persist when field is fillable', function () {
    $category = CategoryFillableParent::find(1);

    // Verify initial state
    expect($category->parent)->toBe(0);

    // Assign new value
    $category->parent = 5;

    // Check that the model detects the change
    expect($category->isDirty())->toBeTrue();
    expect($category->isDirty('parent'))->toBeTrue();
    expect($category->getDirty())->toHaveKey('parent');
    expect($category->getDirty()['parent'])->toBe(5);

    // Save should return true AND actually persist
    $result = $category->save();
    expect($result)->toBeTrue();

    // Verify persistence by reloading
    $reloaded = CategoryFillableParent::find(1);
    expect($reloaded->parent)->toBe(5);
});

test('ISSUE #15: Direct property assignment fails silently when field is not fillable', function () {
    $category = CategoryForIssue15::find(1);

    // Verify initial state
    expect($category->parent)->toBe(0);

    // Assign new value to non-fillable field
    $category->parent = 5;

    // Debug: Check if the assignment worked in memory

    // THIS IS THE BUG: save() returns true but doesn't persist
    $result = $category->save();

    // The problem: save() returns true but value is not saved
    expect($result)->toBeTrue(); // This passes (save() lies)

    // The value SHOULD be persisted now (bug is fixed!)
    $reloaded = CategoryForIssue15::find(1);
    expect($reloaded->parent)->toBe(5); // The new value is saved!

    // Bug is FIXED - direct assignment now works correctly
});

test('ISSUE #15: Debug - Check Model dirty tracking behavior', function () {
    $category = CategoryForIssue15::find(1);


    // Assign value
    $category->parent = 5;


    // Test save
    $result = $category->save();

    // Check what was actually saved
    $rawData = $this->connection->table('categories')->where('id', 1)->first();
});

test('ISSUE #15: Direct property assignment should throw exception or provide clear feedback', function () {
    $category = CategoryForIssue15::find(1);

    // Current behavior: silent failure
    $category->parent = 5;
    $result = $category->save();

    // What should happen:
    // Option 1: Throw exception during assignment
    // Option 2: Throw exception during save()
    // Option 3: Return false from save() with clear error
    // Option 4: Always save direct assignments (ignore fillable for direct access)

    // Fixed behavior:
    expect($result)->toBeTrue(); // save() works correctly now

    $reloaded = CategoryForIssue15::find(1);
    expect($reloaded->parent)->toBe(5); // Value IS saved now!

    // This test now documents the FIXED behavior
    // Direct property assignment works correctly
});