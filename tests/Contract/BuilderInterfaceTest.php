<?php

declare(strict_types=1);

use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ExpressionInterface;

it('implements BuilderInterface', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect($builder)->toBeInstanceOf(BuilderInterface::class);
});

it('has select methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'select'))->toBeTrue();
    expect(method_exists($builder, 'addSelect'))->toBeTrue();
    expect(method_exists($builder, 'distinct'))->toBeTrue();
});

it('has from method', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'from'))->toBeTrue();
});

it('has where clause methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'where'))->toBeTrue();
    expect(method_exists($builder, 'orWhere'))->toBeTrue();
    expect(method_exists($builder, 'whereIn'))->toBeTrue();
    expect(method_exists($builder, 'whereNotIn'))->toBeTrue();
    expect(method_exists($builder, 'whereBetween'))->toBeTrue();
    expect(method_exists($builder, 'whereNull'))->toBeTrue();
    expect(method_exists($builder, 'whereNotNull'))->toBeTrue();
    expect(method_exists($builder, 'whereExists'))->toBeTrue();
});

it('has join methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'join'))->toBeTrue();
    expect(method_exists($builder, 'leftJoin'))->toBeTrue();
    expect(method_exists($builder, 'rightJoin'))->toBeTrue();
    expect(method_exists($builder, 'crossJoin'))->toBeTrue();
});

it('has grouping and ordering methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'groupBy'))->toBeTrue();
    expect(method_exists($builder, 'having'))->toBeTrue();
    expect(method_exists($builder, 'orderBy'))->toBeTrue();
    expect(method_exists($builder, 'orderByDesc'))->toBeTrue();
    expect(method_exists($builder, 'oldest'))->toBeTrue();
    expect(method_exists($builder, 'latest'))->toBeTrue();
    expect(method_exists($builder, 'inRandomOrder'))->toBeTrue();
});

it('has limit and pagination methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'limit'))->toBeTrue();
    expect(method_exists($builder, 'take'))->toBeTrue();
    expect(method_exists($builder, 'offset'))->toBeTrue();
    expect(method_exists($builder, 'skip'))->toBeTrue();
    expect(method_exists($builder, 'page'))->toBeTrue();
});

it('has execution methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'get'))->toBeTrue();
    expect(method_exists($builder, 'first'))->toBeTrue();
    expect(method_exists($builder, 'find'))->toBeTrue();
    expect(method_exists($builder, 'value'))->toBeTrue();
    expect(method_exists($builder, 'pluck'))->toBeTrue();
    expect(method_exists($builder, 'chunk'))->toBeTrue();
    expect(method_exists($builder, 'cursor'))->toBeTrue();
});

it('has aggregate methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'count'))->toBeTrue();
    expect(method_exists($builder, 'sum'))->toBeTrue();
    expect(method_exists($builder, 'avg'))->toBeTrue();
    expect(method_exists($builder, 'min'))->toBeTrue();
    expect(method_exists($builder, 'max'))->toBeTrue();
});

it('has mutation methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'insert'))->toBeTrue();
    expect(method_exists($builder, 'insertGetId'))->toBeTrue();
    expect(method_exists($builder, 'update'))->toBeTrue();
    expect(method_exists($builder, 'delete'))->toBeTrue();
    expect(method_exists($builder, 'truncate'))->toBeTrue();
});

it('has utility methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    expect(method_exists($builder, 'toSql'))->toBeTrue();
    expect(method_exists($builder, 'getBindings'))->toBeTrue();
    expect(method_exists($builder, 'clone'))->toBeTrue();
    expect(method_exists($builder, 'raw'))->toBeTrue();
});

it('returns self for fluent methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    $builder->shouldReceive('select')->andReturnSelf();
    $builder->shouldReceive('from')->andReturnSelf();
    $builder->shouldReceive('where')->andReturnSelf();
    $builder->shouldReceive('orderBy')->andReturnSelf();
    $builder->shouldReceive('limit')->andReturnSelf();

    expect($builder->select(['*']))->toBe($builder);
    expect($builder->from('users'))->toBe($builder);
    expect($builder->where('id', 1))->toBe($builder);
    expect($builder->orderBy('name'))->toBe($builder);
    expect($builder->limit(10))->toBe($builder);
});

it('returns correct types from execution methods', function () {
    $builder = Mockery::mock(BuilderInterface::class);

    $builder->shouldReceive('get')->andReturn([]);
    $builder->shouldReceive('first')->andReturn(null);
    $builder->shouldReceive('count')->andReturn(0);
    $builder->shouldReceive('toSql')->andReturn('');
    $builder->shouldReceive('getBindings')->andReturn([]);

    expect($builder->get())->toBeArray();
    expect($builder->first())->toBeNull();
    expect($builder->count())->toBeInt();
    expect($builder->toSql())->toBeString();
    expect($builder->getBindings())->toBeArray();
});

it('raw method returns ExpressionInterface', function () {
    $builder = Mockery::mock(BuilderInterface::class);
    $expression = Mockery::mock(ExpressionInterface::class);

    $builder->shouldReceive('raw')->andReturn($expression);

    expect($builder->raw('COUNT(*)'))->toBeInstanceOf(ExpressionInterface::class);
});
