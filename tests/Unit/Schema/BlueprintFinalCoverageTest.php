<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\Blueprint;
use Bob\Schema\ColumnDefinition;

test('spatial index fluent (covers lines 141-142)', function () {
    $blueprint = new Blueprint('test_table');

    // Create a column with spatialIndex fluent method
    $column = $blueprint->geometry('location');
    $column->spatialIndex();

    // Check that the fluent method worked
    expect($column->spatialIndex ?? false)->toBeTrue();

    // Check that the column was added
    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(1);
    expect($columns[0]['name'])->toBe('location');
    expect($columns[0]['type'])->toBe('geometry');
});

test('fulltext index fluent (covers line 140)', function () {
    $blueprint = new Blueprint('test_table');

    // Create a column with fulltext fluent method
    $column = $blueprint->text('content');
    $column->fulltext();

    // Check that the fluent method worked
    expect($column->fulltext ?? false)->toBeTrue();

    // Check that the column was added
    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(1);
    expect($columns[0]['name'])->toBe('content');
    expect($columns[0]['type'])->toBe('text');
});

test('unsigned float and double (covers lines 416-424)', function () {
    $blueprint = new Blueprint('test_table');

    // Test unsignedFloat (line 416)
    $floatColumn = $blueprint->unsignedFloat('price', 8, 2);
    expect($floatColumn)->toBeInstanceOf(ColumnDefinition::class);

    // Test unsignedDouble (line 424)
    $doubleColumn = $blueprint->unsignedDouble('amount', 10, 4);
    expect($doubleColumn)->toBeInstanceOf(ColumnDefinition::class);

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(2);

    // Check float column
    expect($columns[0]['name'])->toBe('price');
    expect($columns[0]['type'])->toBe('float');
    expect($columns[0]['precision'])->toBe(8);
    expect($columns[0]['scale'])->toBe(2);
    expect($columns[0]['unsigned'])->toBeTrue();

    // Check double column
    expect($columns[1]['name'])->toBe('amount');
    expect($columns[1]['type'])->toBe('double');
    expect($columns[1]['precision'])->toBe(10);
    expect($columns[1]['scale'])->toBe(4);
    expect($columns[1]['unsigned'])->toBeTrue();
});

test('softDeletesTz (covers line 562)', function () {
    $blueprint = new Blueprint('test_table');

    // Test softDeletesTz method
    $result = $blueprint->softDeletesTz();
    expect($result)->toBeInstanceOf(ColumnDefinition::class);

    // Test with custom column name and precision
    $blueprint->softDeletesTz('archived_at', 3);

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(2);

    // Check default softDeletesTz
    expect($columns[0]['name'])->toBe('deleted_at');
    expect($columns[0]['type'])->toBe('timestampTz');
    expect($columns[0]['nullable'])->toBeTrue();

    // Check custom softDeletesTz
    expect($columns[1]['name'])->toBe('archived_at');
    expect($columns[1]['type'])->toBe('timestampTz');
    expect($columns[1]['precision'])->toBe(3);
    expect($columns[1]['nullable'])->toBeTrue();
});

test('foreignUuid (covers line 602)', function () {
    $blueprint = new Blueprint('test_table');

    // Test foreignUuid method
    $result = $blueprint->foreignUuid('user_id');
    expect($result)->toBeInstanceOf(ColumnDefinition::class);

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(1);

    expect($columns[0]['name'])->toBe('user_id');
    expect($columns[0]['type'])->toBe('uuid');
});

test('removeColumn (covers lines 717-721)', function () {
    $blueprint = new Blueprint('test_table');

    // Add some columns first
    $blueprint->string('name');
    $blueprint->string('email');
    $blueprint->integer('age');

    expect($blueprint->getColumns())->toHaveCount(3);

    // Remove a column (covers lines 717-721)
    $result = $blueprint->removeColumn('email');
    expect($result)->toBeInstanceOf(Blueprint::class);

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(2);

    // Verify the correct column was removed
    $columnNames = array_column($columns, 'name');
    expect($columnNames)->toContain('name');
    expect($columnNames)->toContain('age');
    expect($columnNames)->not->toContain('email');

    // Try to remove a non-existent column (should not cause error)
    $blueprint->removeColumn('non_existent');
    expect($blueprint->getColumns())->toHaveCount(2);
});

test('spatial index command (covers line 785)', function () {
    $blueprint = new Blueprint('test_table');

    // Test spatialIndex method directly
    $result = $blueprint->spatialIndex('location');
    expect($result)->not->toBeNull();

    // Test with array of columns
    $blueprint->spatialIndex(['lat', 'lng'], 'location_spatial');

    $commands = $blueprint->getCommands();
    $spatialCommands = array_filter($commands, fn($cmd) => $cmd['name'] === 'spatialIndex');
    expect($spatialCommands)->toHaveCount(2);

    $spatialCommandsArray = array_values($spatialCommands);

    // Check first spatial index
    expect($spatialCommandsArray[0]['columns'])->toBe(['location']);

    // Check second spatial index
    expect($spatialCommandsArray[1]['columns'])->toBe(['lat', 'lng']);
    expect($spatialCommandsArray[1]['index'])->toBe('location_spatial');
});

test('fluent column index methods', function () {
    $blueprint = new Blueprint('test_table');

    // Create columns with various fluent index methods
    $desc = $blueprint->text('description');
    $desc->fulltext();

    $geom = $blueprint->geometry('coordinates');
    $geom->spatialIndex();

    $title = $blueprint->string('title');
    $title->index();

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(3);

    // Check that fluent properties were set
    expect($desc->fulltext ?? false)->toBeTrue();
    expect($geom->spatialIndex ?? false)->toBeTrue();
    expect($title->index ?? false)->toBeTrue();
});

test('removeColumn edge cases', function () {
    $blueprint = new Blueprint('test_table');

    // Add columns
    $nameCol = $blueprint->string('name');
    $emailCol = $blueprint->string('email');

    expect($blueprint->getColumns())->toHaveCount(2);

    // Remove column that exists
    $blueprint->removeColumn('name');
    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(1);

    // Find the remaining column
    $remainingColumn = null;
    foreach ($columns as $column) {
        if ($column->name === 'email') {
            $remainingColumn = $column;
            break;
        }
    }
    expect($remainingColumn)->not->toBeNull();
    expect($remainingColumn->name)->toBe('email');

    // Remove the same column again (should be no-op)
    $blueprint->removeColumn('name');
    expect($blueprint->getColumns())->toHaveCount(1);

    // Remove last column
    $blueprint->removeColumn('email');
    expect($blueprint->getColumns())->toHaveCount(0);

    // Remove from empty blueprint
    $blueprint->removeColumn('anything');
    expect($blueprint->getColumns())->toHaveCount(0);
});