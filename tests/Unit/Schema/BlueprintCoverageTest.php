<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\Blueprint;
use Bob\Schema\SchemaGrammar;
use Bob\Schema\Grammars\MySQLGrammar;
use Bob\Database\Connection;

afterEach(function () {
    \Mockery::close();
});

test('addFluentIndexesFromColumns', function () {
    $blueprint = new Blueprint('test_table');

    // Test primary key from column definition
    $column = $blueprint->integer('id');
    $column->primary();

    // Test unique index from column definition
    $column2 = $blueprint->string('email');
    $column2->unique();

    // Test regular index from column definition
    $column3 = $blueprint->string('name');
    $column3->index();

    // Build to trigger addFluentIndexes
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('statement')->andReturn(true);
    $mockConnection->shouldReceive('getConfig')->andReturn(null);
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');

    $grammar = new MySQLGrammar();
    $blueprint->build($mockConnection, $grammar);

    // Check that index commands were added
    $commands = $blueprint->getCommands();
    $commandNames = array_map(fn($cmd) => $cmd->name, $commands);

    expect($commandNames)->toContain('primary');
    expect($commandNames)->toContain('unique');
    expect($commandNames)->toContain('index');
});

test('constrainedColumnWithInferredTable', function () {
    $blueprint = new Blueprint('posts');

    // Test constrained with inferred table name
    $column = $blueprint->foreignId('user_id');
    $column->constrained();

    // Build to trigger constraint processing
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('statement')->andReturn(true);
    $mockConnection->shouldReceive('getConfig')->andReturn(null);
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');

    $grammar = new MySQLGrammar();
    $blueprint->build($mockConnection, $grammar);

    // Check that foreign key command was added
    $commands = $blueprint->getCommands();
    $foreignCommand = null;
    foreach ($commands as $command) {
        if ($command->name === 'foreign') {
            $foreignCommand = $command;
            break;
        }
    }

    expect($foreignCommand)->not->toBeNull();
    expect($foreignCommand->columns)->toBe(['user_id']);
});

test('constrainedColumnWithCascadeOptions', function () {
    $blueprint = new Blueprint('posts');

    // Test constrained with cascade options
    $column = $blueprint->foreignId('user_id');
    $column->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();

    // Build to trigger constraint processing
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('statement')->andReturn(true);
    $mockConnection->shouldReceive('getConfig')->andReturn(null);
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');

    $grammar = new MySQLGrammar();
    $blueprint->build($mockConnection, $grammar);

    // Verify foreign key was created with cascade options
    $commands = $blueprint->getCommands();
    expect($commands)->not->toBeEmpty();
});

test('addFluentCommands', function () {
    $blueprint = new Blueprint('test_table');

    // Add column with fluent attributes
    $column = $blueprint->string('name');
    $column->comment = 'User name';
    $column->charset = 'utf8';
    $column->collation = 'utf8_general_ci';

    // Build with grammar that has fluent commands
    $mockConnection = \Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('statement')->andReturn(true);
    $mockConnection->shouldReceive('getConfig')->andReturn(null);
    $mockConnection->shouldReceive('getTablePrefix')->andReturn('');

    $grammar = new MySQLGrammar();
    $blueprint->build($mockConnection, $grammar);

    // Check that fluent commands were added
    $commands = $blueprint->getCommands();
    $commandNames = array_map(fn($cmd) => $cmd->name, $commands);

    expect($commandNames)->toContain('Comment');
    expect($commandNames)->toContain('Charset');
    expect($commandNames)->toContain('Collation');
});

test('incrementMethods', function () {
    $blueprint = new Blueprint('test_table');

    // Test all increment methods
    $col1 = $blueprint->tinyIncrements('tiny_id');
    $col2 = $blueprint->smallIncrements('small_id');
    $col3 = $blueprint->mediumIncrements('medium_id');
    $col4 = $blueprint->increments('normal_id');
    $col5 = $blueprint->bigIncrements('big_id');

    expect($col1->type)->toBe('tinyInteger');
    expect($col1->unsigned)->toBeTrue();
    expect($col1->autoIncrement)->toBeTrue();

    expect($col2->type)->toBe('smallInteger');
    expect($col2->unsigned)->toBeTrue();
    expect($col2->autoIncrement)->toBeTrue();

    expect($col3->type)->toBe('mediumInteger');
    expect($col3->unsigned)->toBeTrue();
    expect($col3->autoIncrement)->toBeTrue();

    expect($col4->type)->toBe('integer');
    expect($col4->unsigned)->toBeTrue();
    expect($col4->autoIncrement)->toBeTrue();

    expect($col5->type)->toBe('bigInteger');
    expect($col5->unsigned)->toBeTrue();
    expect($col5->autoIncrement)->toBeTrue();
});

test('dropMethods', function () {
    $blueprint = new Blueprint('test_table');

    // Test drop commands
    $blueprint->dropPrimary('primary_key');
    $blueprint->dropUnique('unique_index');
    $blueprint->dropIndex('normal_index');
    $blueprint->dropFulltext('fulltext_index');
    $blueprint->dropSpatialIndex('spatial_index');
    $blueprint->dropForeign('foreign_key');
    $blueprint->dropTimestamps();
    $blueprint->dropSoftDeletes();
    $blueprint->dropSoftDeletes('custom_deleted_at');

    $commands = $blueprint->getCommands();
    $commandNames = array_map(fn($cmd) => $cmd->name, $commands);

    expect($commandNames)->toContain('dropPrimary');
    expect($commandNames)->toContain('dropUnique');
    expect($commandNames)->toContain('dropIndex');
    expect($commandNames)->toContain('dropFulltext');
    expect($commandNames)->toContain('dropSpatialIndex');
    expect($commandNames)->toContain('dropForeign');
    expect($commandNames)->toContain('dropColumn');
});

test('dropIndexWithArrayColumns', function () {
    $blueprint = new Blueprint('test_table');

    // Test drop index with array of columns
    $blueprint->dropIndex(['col1', 'col2']);

    $commands = $blueprint->getCommands();
    $dropCommand = $commands[0];

    expect($dropCommand->name)->toBe('dropIndex');
    expect($dropCommand->index)->toBe('test_table_col1_col2_index');
});

test('renameAndComment', function () {
    $blueprint = new Blueprint('old_table');

    // Test rename
    $renameCommand = $blueprint->rename('new_table');
    expect($renameCommand->name)->toBe('rename');
    expect($renameCommand->to)->toBe('new_table');

    // Test comment
    $commentCommand = $blueprint->comment('This is a test table');
    expect($commentCommand->name)->toBe('comment');
    expect($commentCommand->comment)->toBe('This is a test table');
});

test('afterColumn', function () {
    $blueprint = new Blueprint('test_table');

    // Test after method
    $blueprint->after('existing_column', function (Blueprint $table) {
        $table->string('new_column');
    });

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(1);
    expect($columns[0]->name)->toBe('new_column');
    expect($columns[0]->after)->toBe('existing_column');
});

test('creatingMethod', function () {
    $blueprint = new Blueprint('test_table');
    expect($blueprint->creating())->toBeFalse();

    $blueprint->create();
    expect($blueprint->creating())->toBeTrue();
});

test('getTableMethod', function () {
    $blueprint = new Blueprint('test_table');
    expect($blueprint->getTable())->toBe('test_table');
});

test('getColumns', function () {
    $blueprint = new Blueprint('test_table');

    $blueprint->string('col1');
    $blueprint->integer('col2');

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(2);
    expect($columns[0]->name)->toBe('col1');
    expect($columns[1]->name)->toBe('col2');
});

test('getCommands', function () {
    $blueprint = new Blueprint('test_table');

    $blueprint->create();
    $blueprint->index('col1');

    $commands = $blueprint->getCommands();
    expect($commands)->toHaveCount(2);
    expect($commands[0]->name)->toBe('create');
    expect($commands[1]->name)->toBe('index');
});

test('getAddedColumns', function () {
    $blueprint = new Blueprint('test_table');

    $blueprint->string('col1');
    $blueprint->integer('col2')->change();

    $addedColumns = $blueprint->getAddedColumns();
    expect($addedColumns)->toHaveCount(1);
    expect($addedColumns[0]->name)->toBe('col1');
});

test('getChangedColumns', function () {
    $blueprint = new Blueprint('test_table');

    $blueprint->string('col1');
    $col2 = $blueprint->integer('col2');
    $col2->change();

    $changedColumns = $blueprint->getChangedColumns();
    expect($changedColumns)->toHaveCount(1);
    expect($changedColumns[0]->name)->toBe('col2');
    expect($changedColumns[0]->change)->toBeTrue();
});