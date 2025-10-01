<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Fluent;
use Bob\Schema\Grammars\PostgreSQLGrammar;

beforeEach(function () {
    $this->grammar = new PostgreSQLGrammar;

    $this->connection = \Mockery::mock(Connection::class);
    $this->connection->shouldReceive('getTablePrefix')->andReturn('');
    $this->connection->shouldReceive('getConfig')->andReturn(null);
});

afterEach(function () {
    \Mockery::close();
});

/**
 * Helper method to call protected methods using reflection
 */
function callProtectedMethodPostgreSQL($object, $method, ...$args)
{
    $reflection = new ReflectionClass($object);
    $reflectionMethod = $reflection->getMethod($method);
    $reflectionMethod->setAccessible(true);

    return $reflectionMethod->invoke($object, ...$args);
}

test('compile change column with nullable (covers lines 83-85)', function () {
    $blueprint = new Blueprint('test_table');

    // Create a column that is marked for change with nullable = true (to trigger 'drop not null')
    $column = new Fluent(['name' => 'test_col', 'nullable' => true, 'type' => 'string', 'change' => true]);

    // Use reflection to access protected addColumn method
    $reflection = new ReflectionClass($blueprint);
    $addColumnMethod = $reflection->getMethod('addColumn');
    $addColumnMethod->setAccessible(true);
    $addColumnMethod->invoke($blueprint, 'string', 'test_col', $column->toArray());

    $change = $this->grammar->compileChange($blueprint, new Fluent([
        'name' => 'change',
    ]), $this->connection);

    expect($change)->toContain('drop not null');
});

test('compile change column with default (covers lines 89-94)', function () {
    $blueprint = new Blueprint('test_table');

    // Create a column change with default value
    $column = new Fluent([
        'name' => 'test_col',
        'type' => 'string',
        'default' => "'test_default'",
        'change' => true,
    ]);

    // Use reflection to access protected addColumn method
    $reflection = new ReflectionClass($blueprint);
    $addColumnMethod = $reflection->getMethod('addColumn');
    $addColumnMethod->setAccessible(true);
    $addColumnMethod->invoke($blueprint, 'string', 'test_col', $column->toArray());

    $change = $this->grammar->compileChange($blueprint, new Fluent([
        'name' => 'change',
    ]), $this->connection);

    expect($change)->toContain('set default');
    expect($change)->toContain('test_default');
});

test('type char without length (covers line 221)', function () {
    $column = new Fluent(['type' => 'char']);
    $result = callProtectedMethodPostgreSQL($this->grammar, 'typeChar', $column);

    expect($result)->toBe('char');
});

test('type string without length (covers line 233)', function () {
    $column = new Fluent(['type' => 'string']);
    $result = callProtectedMethodPostgreSQL($this->grammar, 'typeString', $column);

    expect($result)->toBe('varchar');
});

test('type set (covers line 358)', function () {
    $column = new Fluent(['type' => 'set']);
    $result = callProtectedMethodPostgreSQL($this->grammar, 'typeSet', $column);

    expect($result)->toBe('text[]');
});

test('type timestampTz with useCurrent (covers line 443)', function () {
    $column = new Fluent([
        'type' => 'timestampTz',
        'useCurrent' => true,
        'precision' => 6,
    ]);

    $result = callProtectedMethodPostgreSQL($this->grammar, 'typeTimestampTz', $column);

    expect($result)->toContain('default CURRENT_TIMESTAMP');
    expect($result)->toContain('(6)');
});

test('type computed (covers line 558)', function () {
    expect(function () {
        $column = new Fluent(['type' => 'computed']);
        callProtectedMethodPostgreSQL($this->grammar, 'typeComputed', $column);
    })->toThrow(\RuntimeException::class, 'Computed columns are not supported by PostgreSQL.');
});

test('format postGis type with geography (covers lines 567-570)', function () {
    $column = new Fluent([
        'isGeography' => true,
        'srid' => 3857,
    ]);

    $result = callProtectedMethodPostgreSQL($this->grammar, 'formatPostGisType', 'point', $column);

    expect($result)->toBe('geography(POINT, 3857)');
});

test('format postGis type with geography default srid (covers line 571)', function () {
    $column = new Fluent([
        'isGeography' => true,
        // No srid specified, should use default 4326
    ]);

    $result = callProtectedMethodPostgreSQL($this->grammar, 'formatPostGisType', 'linestring', $column);

    expect($result)->toBe('geography(LINESTRING, 4326)');
});

test('format postGis type with geometry srid (covers line 575)', function () {
    $column = new Fluent([
        'isGeography' => false,
        'srid' => 2154,
    ]);

    $result = callProtectedMethodPostgreSQL($this->grammar, 'formatPostGisType', 'polygon', $column);

    expect($result)->toBe('geometry(POLYGON, 2154)');
});

test('modify nullable with false (covers line 603)', function () {
    $blueprint = new Blueprint('test_table');
    $column = new Fluent([
        'nullable' => false,
    ]);

    $result = callProtectedMethodPostgreSQL($this->grammar, 'modifyNullable', $blueprint, $column);

    expect($result)->toBe(' not null');
});

test('column types via blueprint', function () {
    $blueprint = new Blueprint('test_table');
    $blueprint->create();

    // Test char column without length - should use line 221
    $blueprint->char('char_col');

    // Test string column without length - should use line 233
    $blueprint->string('string_col');

    // Test set column - should use line 358
    $reflection = new ReflectionClass($blueprint);
    $addColumnMethod = $reflection->getMethod('addColumn');
    $addColumnMethod->setAccessible(true);
    $addColumnMethod->invoke($blueprint, 'set', 'set_col', []);

    // Test timestampTz with useCurrent - should use line 443
    $timestampCol = $blueprint->timestampTz('timestamp_col');
    $timestampCol->useCurrent();

    $sql = $blueprint->toSql($this->connection, $this->grammar);
    $fullSql = implode(' ', $sql);

    // Verify the SQL contains our expected types
    expect($fullSql)->toContain('char');
    expect($fullSql)->toContain('varchar');
    expect($fullSql)->toContain('text[]');
    expect($fullSql)->toContain('CURRENT_TIMESTAMP');
});

test('postGis type variations', function () {
    // Test geometry without SRID (should return just 'geometry')
    $geometryColumn = new Fluent([
        'isGeography' => false,
        // No srid property
    ]);
    $geometryResult = callProtectedMethodPostgreSQL($this->grammar, 'formatPostGisType', 'point', $geometryColumn);
    expect($geometryResult)->toBe('geometry');

    // Test geography with custom SRID
    $geographyColumn = new Fluent([
        'isGeography' => true,
        'srid' => 4269,
    ]);
    $geographyResult = callProtectedMethodPostgreSQL($this->grammar, 'formatPostGisType', 'multipoint', $geographyColumn);
    expect($geographyResult)->toBe('geography(MULTIPOINT, 4269)');
});

test('nullable modifier variations', function () {
    $blueprint = new Blueprint('test_table');

    // Test nullable = false
    $notNullColumn = new Fluent(['nullable' => false]);
    $notNullResult = callProtectedMethodPostgreSQL($this->grammar, 'modifyNullable', $blueprint, $notNullColumn);
    expect($notNullResult)->toBe(' not null');

    // Test nullable = true (should return ' null' per PostgreSQL Grammar implementation)
    $nullableColumn = new Fluent(['nullable' => true]);
    $nullableResult = callProtectedMethodPostgreSQL($this->grammar, 'modifyNullable', $blueprint, $nullableColumn);
    expect($nullableResult)->toBe(' null');

    // Test nullable not set (should return ' not null' since nullable defaults to false)
    $defaultColumn = new Fluent([]);
    $defaultResult = callProtectedMethodPostgreSQL($this->grammar, 'modifyNullable', $blueprint, $defaultColumn);
    expect($defaultResult)->toBe(' not null');
});

test('complete change column scenario', function () {
    $blueprint = new Blueprint('test_table');

    // Create a column that requires changing with both nullable and default
    $column = new Fluent([
        'name' => 'complex_col',
        'type' => 'string',
        'nullable' => true,  // Set to true to trigger 'drop not null'
        'default' => "'complex_default'",
        'change' => true,
    ]);

    // Use reflection to access protected addColumn method
    $reflection = new ReflectionClass($blueprint);
    $addColumnMethod = $reflection->getMethod('addColumn');
    $addColumnMethod->setAccessible(true);
    $addColumnMethod->invoke($blueprint, 'string', 'complex_col', $column->toArray());

    $sql = $this->grammar->compileChange($blueprint, new Fluent([
        'name' => 'change',
    ]), $this->connection);

    // Should contain both nullable and default modifications
    expect($sql)->toContain('drop not null');
    expect($sql)->toContain('set default');
    expect($sql)->toContain('complex_default');
});
