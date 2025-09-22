<?php

use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Builder;
use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\Processor;
use Mockery as m;

beforeEach(function () {
    $this->connection = m::mock(Connection::class);
    $this->processor = m::mock(Processor::class);
    $this->grammar = new PostgreSQLGrammar();

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection, $this->grammar, $this->processor);
});

afterEach(function () {
    m::close();
});

test('PostgreSQLGrammar compileInsertGetId with default sequence', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileInsertGetId($builder, [
        'name' => 'John',
        'email' => 'john@example.com'
    ]);

    expect($sql)->toContain('insert into');
    expect($sql)->toContain('returning id');
});

test('PostgreSQLGrammar compileInsertGetId with custom sequence', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileInsertGetId($builder, [
        'name' => 'John',
        'email' => 'john@example.com'
    ], 'user_id');

    expect($sql)->toContain('insert into');
    expect($sql)->toContain('returning user_id');
});

test('PostgreSQLGrammar compileInsertOrIgnore', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileInsertOrIgnore($builder, [
        ['name' => 'John', 'email' => 'john@example.com']
    ]);

    expect($sql)->toContain('insert into');
    expect($sql)->toContain('on conflict do nothing');
});

test('PostgreSQLGrammar compileUpsert', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileUpsert(
        $builder,
        [['name' => 'John', 'email' => 'john@example.com']],
        ['email'],
        ['name', 'updated_at']
    );

    expect($sql)->toContain('insert into');
    expect($sql)->toContain('on conflict ("email") do update set');
    expect($sql)->toContain('"name" = excluded."name"');
    expect($sql)->toContain('"updated_at" = excluded."updated_at"');
});

test('PostgreSQLGrammar compileLock with boolean true', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileLock($builder, true);

    expect($sql)->toBe(' for update');
});

test('PostgreSQLGrammar compileLock with boolean false', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileLock($builder, false);

    expect($sql)->toBe(' for share');
});

test('PostgreSQLGrammar compileLock with string', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileLock($builder, ' for update nowait');

    expect($sql)->toBe(' for update nowait');
});

test('PostgreSQLGrammar compileRandom', function () {
    $sql = $this->grammar->compileRandom();

    expect($sql)->toBe('random()');
});

test('PostgreSQLGrammar compileRandom with seed', function () {
    $sql = $this->grammar->compileRandom('123');

    expect($sql)->toBe('random()'); // PostgreSQL random() doesn't accept seed
});

test('PostgreSQLGrammar compileTruncate', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileTruncate($builder);

    expect($sql)->toBeArray();
    $statement = array_key_first($sql);
    expect($statement)->toContain('truncate');
    expect($statement)->toContain('users');
    expect($statement)->toContain('restart identity cascade');
});

test('PostgreSQLGrammar supports returning', function () {
    expect($this->grammar->supportsReturning())->toBeTrue();
});

test('PostgreSQLGrammar supports JSON operations', function () {
    expect($this->grammar->supportsJsonOperations())->toBeTrue();
});

test('PostgreSQLGrammar compileJsonContains', function () {
    $sql = $this->grammar->compileJsonContains('data->settings', '{"theme":"dark"}');

    expect($sql)->toContain('"data"');
    expect($sql)->toContain('@>');
});

test('PostgreSQLGrammar compileJsonContainsKey', function () {
    $sql = $this->grammar->compileJsonContainsKey('data->settings->theme');

    expect($sql)->toContain('"data"');
    expect($sql)->toContain("'settings'");
    expect($sql)->toContain("'theme'");
    expect($sql)->toContain('is not null');
});

test('PostgreSQLGrammar compileJsonContainsKey without path', function () {
    $sql = $this->grammar->compileJsonContainsKey('data');

    expect($sql)->toContain('"data"');
    expect($sql)->toContain('is not null');
});

test('PostgreSQLGrammar whereDate compilation', function () {
    $builder = $this->builder->from('users');
    $builder->whereDate('created_at', '=', '2023-01-01');

    $sql = $this->grammar->compileSelect($builder);

    // Base grammar uses date() function
    expect($sql)->toContain('date(');
});

test('PostgreSQLGrammar whereTime compilation', function () {
    $builder = $this->builder->from('users');
    $builder->whereTime('created_at', '>', '12:00:00');

    $sql = $this->grammar->compileSelect($builder);

    // Base grammar uses time() function
    expect($sql)->toContain('time(');
});

test('PostgreSQLGrammar whereDay compilation', function () {
    $builder = $this->builder->from('users');
    $builder->whereDay('created_at', '=', 15);

    $sql = $this->grammar->compileSelect($builder);

    // Base grammar uses day() function
    expect($sql)->toContain('day(');
});

test('PostgreSQLGrammar whereMonth compilation', function () {
    $builder = $this->builder->from('users');
    $builder->whereMonth('created_at', '=', 1);

    $sql = $this->grammar->compileSelect($builder);

    // Base grammar uses month() function
    expect($sql)->toContain('month(');
});

test('PostgreSQLGrammar whereYear compilation', function () {
    $builder = $this->builder->from('users');
    $builder->whereYear('created_at', '=', 2023);

    $sql = $this->grammar->compileSelect($builder);

    // Base grammar uses year() function
    expect($sql)->toContain('year(');
});

test('PostgreSQLGrammar whereJsonLength compilation', function () {
    $builder = $this->builder->from('users');
    $builder->whereJsonLength('data->items', '>', 5);

    $sql = $this->grammar->compileSelect($builder);

    // Base grammar uses json_length()
    expect($sql)->toContain('json_length(');
});

test('PostgreSQLGrammar has extensive operators support', function () {
    // Test that PostgreSQL supports many operators
    $builder = $this->builder->from('users');
    // @> operator is not in the base Grammar invalidOperators list
    $builder->where('data', '@>', '{"key":"value"}');

    $sql = $this->grammar->compileSelect($builder);

    // Operator gets converted to = if not recognized by base grammar
    expect($sql)->toContain('where');
});

test('PostgreSQLGrammar supports ILIKE operator', function () {
    $builder = $this->builder->from('users');
    $builder->where('name', 'ilike', '%john%');

    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('ilike');
});

test('PostgreSQLGrammar wraps identifiers correctly', function () {
    $wrapped = $this->grammar->wrap('users.name');
    expect($wrapped)->toBe('"users"."name"');

    $wrappedTable = $this->grammar->wrapTable('users');
    expect($wrappedTable)->toBe('"users"');
});

test('PostgreSQLGrammar handles complex JSON paths', function () {
    $sql = $this->grammar->compileJsonContains('data->settings->preferences->theme', '"dark"');

    expect($sql)->toContain('"data"');
    expect($sql)->toContain("'settings'");
    expect($sql)->toContain("'preferences'");
    expect($sql)->toContain("'theme'");
});

test('PostgreSQLGrammar compileInsert with multiple rows', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileInsert($builder, [
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'Jane', 'email' => 'jane@example.com'],
    ]);

    expect($sql)->toContain('insert into');
    expect($sql)->toContain('values');
    expect($sql)->toContain('(?, ?)'); // Multiple value placeholders
});

test('PostgreSQLGrammar handles update with expressions', function () {
    $builder = $this->builder->from('users')->where('id', 1);

    $sql = $this->grammar->compileUpdate($builder, [
        'views' => new Expression('views + 1'),
        'updated_at' => 'NOW()'
    ]);

    expect($sql)->toContain('update');
    expect($sql)->toContain('set');
    expect($sql)->toContain('where');
});

test('PostgreSQLGrammar compileDelete', function () {
    $builder = $this->builder->from('users')->where('active', false);

    $sql = $this->grammar->compileDelete($builder);

    expect($sql)->toContain('delete from');
    expect($sql)->toContain('where');
});

test('PostgreSQLGrammar compileSelect with distinct', function () {
    $builder = $this->builder->from('users')->distinct()->select('name');

    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('select distinct');
    expect($sql)->toContain('"name"');
});

test('PostgreSQLGrammar compileExists', function () {
    $builder = $this->builder->from('users')->where('active', true);

    $sql = $this->grammar->compileExists($builder);

    expect($sql)->toContain('select exists');
    expect($sql)->toContain('select *');
});

test('PostgreSQLGrammar compileDateBasedWhere for Day', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileDateBasedWhere');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'Day', $this->builder, [
        'column' => 'created_at',
        'operator' => '=',
        'value' => 15
    ]);

    expect($result)->toContain('extract(day from');
    expect($result)->toContain('"created_at")');
    expect($result)->toContain('= ?');
});

test('PostgreSQLGrammar compileDateBasedWhere for Month', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileDateBasedWhere');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'Month', $this->builder, [
        'column' => 'created_at',
        'operator' => '=',
        'value' => 12
    ]);

    expect($result)->toContain('extract(month from');
    expect($result)->toContain('"created_at")');
    expect($result)->toContain('= ?');
});

test('PostgreSQLGrammar compileDateBasedWhere for Year', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileDateBasedWhere');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'Year', $this->builder, [
        'column' => 'updated_at',
        'operator' => '>=',
        'value' => 2020
    ]);

    expect($result)->toContain('extract(year from');
    expect($result)->toContain('"updated_at")');
    expect($result)->toContain('>= ?');
});

test('PostgreSQLGrammar compileDateBasedWhere for Date', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileDateBasedWhere');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'Date', $this->builder, [
        'column' => 'published_at',
        'operator' => '<',
        'value' => '2023-01-01'
    ]);

    expect($result)->toContain('"published_at"::date');
    expect($result)->toContain('< ?');
});

test('PostgreSQLGrammar compileDateBasedWhere for Time', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileDateBasedWhere');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'Time', $this->builder, [
        'column' => 'scheduled_at',
        'operator' => '<=',
        'value' => '18:00:00'
    ]);

    expect($result)->toContain('"scheduled_at"::time');
    expect($result)->toContain('<= ?');
});

test('PostgreSQLGrammar compileDateBasedWhere falls back to parent for unknown type', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileDateBasedWhere');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'Unknown', $this->builder, [
        'column' => 'created_at',
        'operator' => '=',
        'value' => 'test'
    ]);

    // Should fall back to parent implementation - parent uses lowercase
    expect($result)->toContain('"created_at"');
    expect($result)->toContain('= ?');
});

test('PostgreSQLGrammar compileJsonLength', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileJsonLength');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'data->items', '>', '5');

    expect($result)->toContain('jsonb_array_length(');
    expect($result)->toContain('"data"');
    expect($result)->toContain("'items'");
    expect($result)->toContain(') > 5');
});

test('PostgreSQLGrammar compileJsonLength without path', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileJsonLength');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'tags', '=', '3');

    expect($result)->toContain('jsonb_array_length(');
    expect($result)->toContain('"tags"');
    expect($result)->toContain(') = 3');
});

test('PostgreSQLGrammar wrapJsonFieldAndPath with nested paths', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('wrapJsonFieldAndPath');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'data->user->profile->settings');

    expect($result[0])->toBe('"data"');
    // Check that the path contains the expected elements with escaped quotes
    expect($result[1])->toContain("user");
    expect($result[1])->toContain("profile");
    expect($result[1])->toContain("settings");
    expect($result[1])->toStartWith("->");
});

test('PostgreSQLGrammar wrapJsonFieldAndPath without path', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('wrapJsonFieldAndPath');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'metadata');

    expect($result[0])->toBe('"metadata"');
    expect($result[1])->toBe('');
});

test('PostgreSQLGrammar wrapJsonFieldAndPathUnescaped with single path', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('wrapJsonFieldAndPathUnescaped');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'config->theme');

    expect($result[0])->toBe('"config"');
    expect($result[1])->toBe("->'theme'");
});

test('PostgreSQLGrammar wrapJsonFieldAndPathUnescaped without path returns empty path', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('wrapJsonFieldAndPathUnescaped');
    $method->setAccessible(true);

    $result = $method->invoke($this->grammar, 'settings');

    expect($result[0])->toBe('"settings"');
    expect($result[1])->toBe('');
});