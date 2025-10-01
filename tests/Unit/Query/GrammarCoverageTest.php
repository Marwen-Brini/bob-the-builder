<?php

use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\Builder;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Processor;
use Mockery as m;

beforeEach(function () {
    $this->connection = m::mock(Connection::class);
    $this->processor = m::mock(Processor::class);
    $this->grammar = new MySQLGrammar;  // Use concrete implementation

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection, $this->grammar, $this->processor);
});

afterEach(function () {
    m::close();
});

test('Grammar compileSelect with all components', function () {
    $builder = $this->builder->from('users')
        ->select(['id', 'name'])
        ->where('active', 1)
        ->orderBy('name')
        ->limit(10)
        ->offset(5);

    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('select');
    expect($sql)->toContain('from');
    expect($sql)->toContain('where');
    expect($sql)->toContain('order by');
    expect($sql)->toContain('limit');
});

test('Grammar compileInsert', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileInsert($builder, [
        ['name' => 'John', 'email' => 'john@example.com'],
    ]);

    expect($sql)->toContain('insert into');
    expect($sql)->toContain('users');
    expect($sql)->toContain('values');
});

test('Grammar compileInsertGetId', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileInsertGetId($builder, [
        'name' => 'John',
        'email' => 'john@example.com',
    ], 'id');

    expect($sql)->toContain('insert into');
});

test('Grammar compileUpdate', function () {
    $builder = $this->builder->from('users')->where('id', 1);

    $sql = $this->grammar->compileUpdate($builder, [
        'name' => 'Jane',
        'email' => 'jane@example.com',
    ]);

    expect($sql)->toContain('update');
    expect($sql)->toContain('set');
});

test('Grammar compileDelete', function () {
    $builder = $this->builder->from('users')->where('id', 1);

    $sql = $this->grammar->compileDelete($builder);

    expect($sql)->toContain('delete from');
    expect($sql)->toContain('where');
});

test('Grammar compileTruncate', function () {
    $builder = $this->builder->from('users');

    $sql = $this->grammar->compileTruncate($builder);

    // compileTruncate returns an array with SQL as key
    expect($sql)->toBeArray();
    $sqlStatement = array_key_first($sql);
    expect($sqlStatement)->toContain('truncate');
    expect($sqlStatement)->toContain('users');
});

test('Grammar compileExists', function () {
    $builder = $this->builder->from('users')->where('active', 1);

    $sql = $this->grammar->compileExists($builder);

    expect($sql)->toContain('select exists');
});

test('Grammar parameter and parameterize', function () {
    $param = $this->grammar->parameter('test');
    expect($param)->toBe('?');

    $params = $this->grammar->parameterize(['a', 'b', 'c']);
    expect($params)->toBe('?, ?, ?');
});

test('Grammar wrap and wrapTable', function () {
    $wrapped = $this->grammar->wrap('users.name');
    // MySQL uses backticks
    expect($wrapped)->toBe('`users`.`name`');

    $wrappedTable = $this->grammar->wrapTable('users');
    expect($wrappedTable)->toBe('`users`');
});

test('Grammar columnize', function () {
    $columns = $this->grammar->columnize(['id', 'name', 'email']);
    // MySQL uses backticks
    expect($columns)->toBe('`id`, `name`, `email`');
});

test('Grammar compileWheres with various types', function () {
    $builder = $this->builder->from('users');

    // Basic where
    $builder->where('name', 'John');
    $builder->whereIn('id', [1, 2, 3]);
    $builder->whereNull('deleted_at');
    $builder->whereBetween('age', [18, 65]);

    // Use compileSelect to test the where compilation
    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('where');
    expect($sql)->toContain('in');
    expect($sql)->toContain('is null');
    expect($sql)->toContain('between');
});

test('Grammar compileJoins', function () {
    $builder = $this->builder->from('users');
    $builder->join('posts', 'users.id', '=', 'posts.user_id');

    // Use compileSelect to test the join compilation
    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('inner join');
});

test('Grammar compileGroups', function () {
    $builder = $this->builder->from('users')
        ->groupBy('status', 'type');

    // Use compileSelect to test the group compilation
    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('group by');
});

test('Grammar compileHavings', function () {
    $builder = $this->builder->from('users')
        ->groupBy('status')
        ->having('count', '>', 10);

    // Use compileSelect to test the having compilation
    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('having');
});

test('Grammar compileOrders', function () {
    $builder = $this->builder->from('users')
        ->orderBy('name')
        ->orderBy('email', 'desc');

    // Use compileSelect to test the order compilation
    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('order by');
});

test('Grammar compileLimit and compileOffset', function () {
    $builder = $this->builder->from('users')->limit(10)->offset(20);

    // Use compileSelect to test the limit and offset compilation
    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('limit 10');
    expect($sql)->toContain('offset 20');
});

test('Grammar compileSavepoint and compileSavepointRollBack', function () {
    $savepoint = $this->grammar->compileSavepoint('trans1');
    expect($savepoint)->toBe('SAVEPOINT trans1');

    $rollback = $this->grammar->compileSavepointRollBack('trans1');
    expect($rollback)->toBe('ROLLBACK TO SAVEPOINT trans1');
});

test('Grammar supportsSavepoints', function () {
    expect($this->grammar->supportsSavepoints())->toBeTrue();
});

test('Grammar getValue with Expression', function () {
    $expression = new Expression('NOW()');
    $value = $this->grammar->getValue($expression);

    expect($value)->toBe('NOW()');
});

test('Grammar getDateFormat', function () {
    $format = $this->grammar->getDateFormat();
    expect($format)->toBe('Y-m-d H:i:s');
});

test('Grammar quoteString', function () {
    // quoteString method doesn't exist, test wrap instead
    $wrapped = $this->grammar->wrap("O'Brien");
    expect($wrapped)->toContain('Brien');
});

test('Grammar isExpression', function () {
    $expression = new Expression('NOW()');
    expect($this->grammar->isExpression($expression))->toBeTrue();

    expect($this->grammar->isExpression('string'))->toBeFalse();
});

test('Grammar compileUnions', function () {
    $builder = $this->builder->from('users');
    $unionBuilder = new Builder($this->connection, $this->grammar, $this->processor);
    $unionBuilder->from('admins');

    $builder->union($unionBuilder);

    // Use compileSelect to test the union compilation
    $sql = $this->grammar->compileSelect($builder);

    expect($sql)->toContain('union');
});
