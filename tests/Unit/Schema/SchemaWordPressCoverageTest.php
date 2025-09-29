<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;
use Bob\Schema\WordPressBlueprint;
use Bob\Schema\Grammars\MySQLGrammar;
use Bob\Schema\Grammars\SQLiteGrammar;

beforeEach(function () {
    // Set up a mock connection
    $connection = \Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->with('charset')->andReturn(null);
    $connection->shouldReceive('getConfig')->with('collation')->andReturn(null);
    $connection->shouldReceive('statement')->andReturn(true);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new MySQLGrammar());

    Schema::setConnection($connection);
});

afterEach(function () {
    \Mockery::close();
});

test('createWordPress method (covers lines 63-67)', function () {
    $statementCalled = false;

    $connection = \Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->with('charset')->andReturn(null);
    $connection->shouldReceive('getConfig')->with('collation')->andReturn(null);
    $connection->shouldReceive('statement')
        ->once()
        ->withArgs(function($sql) use (&$statementCalled) {
            $statementCalled = true;
            // Check that SQL contains WordPress-specific features
            expect(strtolower($sql))->toContain('create table');
            return true;
        })
        ->andReturn(true);

    $grammar = new MySQLGrammar();
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);

    Schema::setConnection($connection);

    Schema::createWordPress('wp_posts', function (WordPressBlueprint $table) {
        $table->wpId();
        $table->wpTitle();
        $table->wpContent();
    });

    expect($statementCalled)->toBeTrue();
});

test('tableWordPress method (covers lines 86-89)', function () {
    $connection = \Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getConfig')->with('charset')->andReturn(null);
    $connection->shouldReceive('getConfig')->with('collation')->andReturn(null);
    $connection->shouldReceive('statement')
        ->atLeast()->once()
        ->andReturn(true);

    $grammar = new MySQLGrammar();
    $connection->shouldReceive('getSchemaGrammar')->andReturn($grammar);

    Schema::setConnection($connection);

    // This should work without errors and call statement
    Schema::tableWordPress('wp_posts', function (WordPressBlueprint $table) {
        $table->string('new_field');
        $table->index('new_field');
    });

    // If we reach here, the method was called successfully
    expect(true)->toBeTrue();
});

test('dropAllTables method (covers line 306)', function () {
    expect(fn() => Schema::dropAllTables())
        ->toThrow(\RuntimeException::class, 'Drop all tables is not yet implemented');
});