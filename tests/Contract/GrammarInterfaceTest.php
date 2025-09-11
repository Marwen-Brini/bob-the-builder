<?php

declare(strict_types=1);

use Bob\Contracts\BuilderInterface;
use Bob\Contracts\GrammarInterface;

it('implements GrammarInterface', function () {
    $grammar = Mockery::mock(GrammarInterface::class);

    expect($grammar)->toBeInstanceOf(GrammarInterface::class);
});

it('has compile methods', function () {
    $grammar = Mockery::mock(GrammarInterface::class);

    expect(method_exists($grammar, 'compileSelect'))->toBeTrue();
    expect(method_exists($grammar, 'compileInsert'))->toBeTrue();
    expect(method_exists($grammar, 'compileInsertGetId'))->toBeTrue();
    expect(method_exists($grammar, 'compileUpdate'))->toBeTrue();
    expect(method_exists($grammar, 'compileDelete'))->toBeTrue();
    expect(method_exists($grammar, 'compileTruncate'))->toBeTrue();
    expect(method_exists($grammar, 'compileLock'))->toBeTrue();
    expect(method_exists($grammar, 'compileExists'))->toBeTrue();
});

it('has wrap methods', function () {
    $grammar = Mockery::mock(GrammarInterface::class);

    expect(method_exists($grammar, 'wrap'))->toBeTrue();
    expect(method_exists($grammar, 'wrapTable'))->toBeTrue();
    expect(method_exists($grammar, 'wrapArray'))->toBeTrue();
});

it('has utility methods', function () {
    $grammar = Mockery::mock(GrammarInterface::class);

    expect(method_exists($grammar, 'getDateFormat'))->toBeTrue();
    expect(method_exists($grammar, 'getTablePrefix'))->toBeTrue();
    expect(method_exists($grammar, 'setTablePrefix'))->toBeTrue();
    expect(method_exists($grammar, 'parameter'))->toBeTrue();
    expect(method_exists($grammar, 'parameterize'))->toBeTrue();
    expect(method_exists($grammar, 'columnize'))->toBeTrue();
});

it('has feature support methods', function () {
    $grammar = Mockery::mock(GrammarInterface::class);

    expect(method_exists($grammar, 'supportsReturning'))->toBeTrue();
    expect(method_exists($grammar, 'supportsJsonOperations'))->toBeTrue();
    expect(method_exists($grammar, 'getOperators'))->toBeTrue();
});

it('returns correct types', function () {
    $grammar = Mockery::mock(GrammarInterface::class);
    $builder = Mockery::mock(BuilderInterface::class);

    $grammar->shouldReceive('compileSelect')->andReturn('SELECT * FROM users');
    $grammar->shouldReceive('compileInsert')->andReturn('INSERT INTO users VALUES (?)');
    $grammar->shouldReceive('compileInsertGetId')->andReturn('INSERT INTO users VALUES (?)');
    $grammar->shouldReceive('compileUpdate')->andReturn('UPDATE users SET name = ?');
    $grammar->shouldReceive('compileDelete')->andReturn('DELETE FROM users');
    $grammar->shouldReceive('compileTruncate')->andReturn(['TRUNCATE TABLE users' => []]);
    $grammar->shouldReceive('compileLock')->andReturn(' FOR UPDATE');
    $grammar->shouldReceive('compileExists')->andReturn('SELECT EXISTS(SELECT * FROM users)');
    $grammar->shouldReceive('wrap')->andReturn('`column`');
    $grammar->shouldReceive('wrapTable')->andReturn('`users`');
    $grammar->shouldReceive('wrapArray')->andReturn(['`col1`', '`col2`']);
    $grammar->shouldReceive('getDateFormat')->andReturn('Y-m-d H:i:s');
    $grammar->shouldReceive('getTablePrefix')->andReturn('wp_');
    $grammar->shouldReceive('parameter')->andReturn('?');
    $grammar->shouldReceive('parameterize')->andReturn('?, ?');
    $grammar->shouldReceive('columnize')->andReturn('`col1`, `col2`');
    $grammar->shouldReceive('supportsReturning')->andReturn(false);
    $grammar->shouldReceive('supportsJsonOperations')->andReturn(true);
    $grammar->shouldReceive('getOperators')->andReturn(['=', '!=', '<', '>', '<=', '>=']);

    expect($grammar->compileSelect($builder))->toBeString();
    expect($grammar->compileInsert($builder, []))->toBeString();
    expect($grammar->compileInsertGetId($builder, [], 'id'))->toBeString();
    expect($grammar->compileUpdate($builder, []))->toBeString();
    expect($grammar->compileDelete($builder))->toBeString();
    expect($grammar->compileTruncate($builder))->toBeArray();
    expect($grammar->compileLock($builder, true))->toBeString();
    expect($grammar->compileExists($builder))->toBeString();
    expect($grammar->wrap('column'))->toBeString();
    expect($grammar->wrapTable('users'))->toBeString();
    expect($grammar->wrapArray(['col1', 'col2']))->toBeArray();
    expect($grammar->getDateFormat())->toBeString();
    expect($grammar->getTablePrefix())->toBeString();
    expect($grammar->parameter('value'))->toBeString();
    expect($grammar->parameterize(['val1', 'val2']))->toBeString();
    expect($grammar->columnize(['col1', 'col2']))->toBeString();
    expect($grammar->supportsReturning())->toBeBool();
    expect($grammar->supportsJsonOperations())->toBeBool();
    expect($grammar->getOperators())->toBeArray();
});

it('compile methods accept BuilderInterface', function () {
    $grammar = Mockery::mock(GrammarInterface::class);
    $builder = Mockery::mock(BuilderInterface::class);

    $grammar->shouldReceive('compileSelect')
        ->with(Mockery::type(BuilderInterface::class))
        ->andReturn('SELECT * FROM users');

    $grammar->shouldReceive('compileInsert')
        ->with(Mockery::type(BuilderInterface::class), Mockery::type('array'))
        ->andReturn('INSERT INTO users VALUES (?)');

    $result = $grammar->compileSelect($builder);
    expect($result)->toBeString();

    $result = $grammar->compileInsert($builder, ['name' => 'John']);
    expect($result)->toBeString();
});
