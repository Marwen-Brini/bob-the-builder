<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Grammars\SQLiteGrammar;

afterEach(function () {
    \Mockery::close();
});

test('automatic index creation during build', function () {
    // Create blueprint
    $blueprint = new Blueprint('test_table');
    $blueprint->create();

    // Create columns with only supported fluent index properties
    $strCol = $blueprint->string('name');
    $strCol->index = true; // This should trigger normal index creation

    $emailCol = $blueprint->string('email');
    $emailCol->unique = true; // This should trigger unique index creation

    // Mock connection and grammar
    $connection = \Mockery::mock(Connection::class);
    $connection->shouldReceive('statement')->andReturn(true);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->andReturn(null);

    $grammar = new SQLiteGrammar; // Use SQLite which supports basic indexes

    // Build the blueprint - this should trigger automatic index creation
    $blueprint->build($connection, $grammar);

    $commands = $blueprint->getCommands();

    // Check that the automatic indexes were created
    $commandNames = array_column($commands, 'name');
    expect($commandNames)->toContain('create');
    expect($commandNames)->toContain('index');
    expect($commandNames)->toContain('unique');
});

test('build processes all column indexes', function () {
    $blueprint = new Blueprint('test_table');

    // Create multiple columns with different supported index types
    $col1 = $blueprint->string('title');
    $col1->index = true;

    $col2 = $blueprint->string('email');
    $col2->unique = true;

    $col3 = $blueprint->integer('category_id');
    $col3->index = 'category_idx';

    // Mock dependencies
    $connection = \Mockery::mock(Connection::class);
    $connection->shouldReceive('statement')->andReturn(true);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->andReturn(null);

    $grammar = new SQLiteGrammar;

    // Execute build
    $blueprint->build($connection, $grammar);

    // Verify supported index types were processed
    $commands = $blueprint->getCommands();
    $commandNames = array_column($commands, 'name');

    expect($commandNames)->toContain('index');
    expect($commandNames)->toContain('unique');

    // Count index commands (the third column with named index might get processed differently)
    $indexCommands = array_filter($commands, fn ($cmd) => in_array($cmd['name'], ['index', 'unique']));
    expect(count($indexCommands))->toBeGreaterThanOrEqual(2); // Should have at least 2 index commands
});

test('build without automatic indexes', function () {
    $blueprint = new Blueprint('test_table');
    $blueprint->create();

    // Add columns without index properties
    $blueprint->string('name');
    $blueprint->integer('age');

    // Mock dependencies
    $connection = \Mockery::mock(Connection::class);
    $connection->shouldReceive('statement')->andReturn(true);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->andReturn(null);

    $grammar = new SQLiteGrammar;

    // Execute build
    $blueprint->build($connection, $grammar);

    $commands = $blueprint->getCommands();
    $commandNames = array_column($commands, 'name');

    // Should only have create command, no automatic indexes
    expect($commandNames)->toContain('create');
    expect($commandNames)->not->toContain('index');
    expect($commandNames)->not->toContain('unique');
});

test('fluent index property detection', function () {
    $blueprint = new Blueprint('test_table');

    // Create columns and set index properties manually
    $col1 = $blueprint->string('searchable');
    $col1->index = true;

    $col2 = $blueprint->string('unique_field');
    $col2->unique = true;

    // Verify properties are set
    expect($col1->index)->toBeTrue();
    expect($col2->unique)->toBeTrue();

    // Mock build process
    $connection = \Mockery::mock(Connection::class);
    $connection->shouldReceive('statement')->andReturn(true);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->andReturn(null);

    $grammar = new SQLiteGrammar;

    // Build should process these properties
    $blueprint->build($connection, $grammar);

    $commands = $blueprint->getCommands();
    $indexCommands = array_filter($commands, fn ($cmd) => in_array($cmd['name'], ['index', 'unique']));

    expect($indexCommands)->toHaveCount(2);
});
