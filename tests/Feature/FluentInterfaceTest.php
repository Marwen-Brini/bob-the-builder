<?php

use Bob\Query\Builder;
use Bob\Database\Connection;
use Bob\Query\Grammar;
use Bob\Query\Processor;

afterEach(function () {
    Mockery::close();
});

test('builder methods return builder instance for chaining', function () {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class)->makePartial();
    $processor = Mockery::mock(Processor::class);

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);

    $builder = new Builder($connection);

    expect($builder->select('*'))->toBe($builder);
    expect($builder->from('users'))->toBe($builder);
    expect($builder->where('id', 1))->toBe($builder);
    expect($builder->orderBy('name'))->toBe($builder);
    expect($builder->limit(10))->toBe($builder);
});