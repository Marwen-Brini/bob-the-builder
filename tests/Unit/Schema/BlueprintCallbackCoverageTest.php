<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\Blueprint;

test('blueprint constructor with callback (covers line 74)', function () {
    $callbackCalled = false;
    $callbackBlueprint = null;

    $blueprint = new Blueprint('test_table', function (Blueprint $table) use (&$callbackCalled, &$callbackBlueprint) {
        $callbackCalled = true;
        $callbackBlueprint = $table;

        // Add some columns to ensure the callback is working
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    expect($callbackCalled)->toBeTrue();
    expect($callbackBlueprint)->toBe($blueprint);
    expect($blueprint->getTable())->toBe('test_table');

    // Verify columns were added
    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(4); // id, name, created_at, updated_at
});

test('blueprint constructor without callback (covers null path)', function () {
    $blueprint = new Blueprint('test_table');

    expect($blueprint->getTable())->toBe('test_table');
    expect($blueprint->getColumns())->toBeEmpty();

    // Should not throw any errors with null callback
    $blueprint2 = new Blueprint('test_table2', null);
    expect($blueprint2->getTable())->toBe('test_table2');
});

test('blueprint constructor with prefix', function () {
    $blueprint = new Blueprint('posts', null, 'wp_');

    expect($blueprint->getTable())->toBe('posts');
    expect($blueprint)->not->toBeNull();
});

test('unique index with expression (covers lines 141-142)', function () {
    $blueprint = new Blueprint('test_table');

    // Test unique with columns parameter
    $result = $blueprint->unique(['email', 'deleted_at']);

    expect($result)->not->toBeNull();
    $commands = $blueprint->getCommands();
    $lastCommand = end($commands);

    expect($lastCommand['name'])->toBe('unique');
    expect($lastCommand['columns'])->toBe(['email', 'deleted_at']);
});

test('blueprint drop operations (covers lines 416-424)', function () {
    $blueprint = new Blueprint('test_table');

    // Test dropColumn with multiple columns
    $blueprint->dropColumn(['col1', 'col2']);

    // Test dropColumn again
    $blueprint->dropColumn(['col3', 'col4']);

    // Test dropSoftDeletes
    $blueprint->dropSoftDeletes();

    // Test dropSoftDeletes with custom column
    $blueprint->dropSoftDeletes('archived_at');

    // Test dropTimestamps
    $blueprint->dropTimestamps();

    $commands = $blueprint->getCommands();

    // Verify all drop commands were added
    expect($commands)->toHaveCount(5);

    // Check specific commands
    expect($commands[0]['name'])->toBe('dropColumn');
    expect($commands[0]['columns'])->toBe(['col1', 'col2']);

    expect($commands[1]['name'])->toBe('dropColumn');
    expect($commands[1]['columns'])->toBe(['col3', 'col4']);

    expect($commands[2]['name'])->toBe('dropColumn');
    expect($commands[2]['columns'])->toBe(['deleted_at']);

    expect($commands[3]['name'])->toBe('dropColumn');
    expect($commands[3]['columns'])->toBe(['archived_at']);

    expect($commands[4]['name'])->toBe('dropColumn');
    expect($commands[4]['columns'])->toBe(['created_at', 'updated_at']);
});

test('blueprint increments operations (covers lines 545-546)', function () {
    $blueprint = new Blueprint('test_table');

    // Test increments
    $result = $blueprint->increments('id');
    expect($result)->toBeInstanceOf(\Bob\Schema\ColumnDefinition::class);

    // Test bigIncrements
    $blueprint->bigIncrements('big_id');

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(2);

    // Check increments column
    expect($columns[0]['name'])->toBe('id');
    expect($columns[0]['type'])->toBe('integer');
    expect($columns[0]['autoIncrement'])->toBeTrue();
    expect($columns[0]['unsigned'])->toBeTrue();

    // Check bigIncrements column
    expect($columns[1]['name'])->toBe('big_id');
    expect($columns[1]['type'])->toBe('bigInteger');
    expect($columns[1]['autoIncrement'])->toBeTrue();
    expect($columns[1]['unsigned'])->toBeTrue();
});

test('blueprint timestamps with timezone (covers line 562)', function () {
    $blueprint = new Blueprint('test_table');

    // Test timestampsTz
    $blueprint->timestampsTz();

    $columns = $blueprint->getColumns();

    // timestampsTz creates two columns: created_at and updated_at with timezone
    expect($columns)->toHaveCount(2);

    expect($columns[0]['name'])->toBe('created_at');
    expect($columns[0]['type'])->toBe('timestampTz');
    expect($columns[0]['nullable'] ?? false)->toBeTrue();

    expect($columns[1]['name'])->toBe('updated_at');
    expect($columns[1]['type'])->toBe('timestampTz');
    expect($columns[1]['nullable'] ?? false)->toBeTrue();
});

test('blueprint softDeletes (covers line 602)', function () {
    $blueprint = new Blueprint('users');

    // Test softDeletes
    $result = $blueprint->softDeletes();

    expect($result)->toBeInstanceOf(\Bob\Schema\ColumnDefinition::class);

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(1);

    expect($columns[0]['name'])->toBe('deleted_at');
    expect($columns[0]['type'])->toBe('timestamp');
    expect($columns[0]['nullable'])->toBeTrue();
});

test('blueprint indexes and constraints (covers lines 717-721, 785-793)', function () {
    $blueprint = new Blueprint('test_table');

    // Test dropForeign with array
    $blueprint->dropForeign(['user_id']);

    // Test dropForeign with string
    $blueprint->dropForeign('posts_user_id_foreign');

    // Test rawIndex
    $result = $blueprint->rawIndex('(JSON_EXTRACT(data, "$.email"))', 'email_index');
    expect($result)->not->toBeNull();

    // Test id with custom name
    $result = $blueprint->id('custom_id');
    expect($result)->toBeInstanceOf(\Bob\Schema\ColumnDefinition::class);

    $commands = $blueprint->getCommands();

    // Check dropForeign commands
    $dropForeignCommands = array_filter($commands, fn($cmd) => $cmd['name'] === 'dropForeign');
    expect(count($dropForeignCommands))->toBe(2);

    // rawIndex adds to commands
    expect(count($commands))->toBeGreaterThanOrEqual(3);
});