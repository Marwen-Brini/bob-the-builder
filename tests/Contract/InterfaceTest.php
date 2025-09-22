<?php

use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ConnectionInterface;
use Bob\Contracts\ExpressionInterface;
use Bob\Contracts\GrammarInterface;
use Bob\Contracts\ProcessorInterface;
use Bob\Query\Builder;
use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\Grammar;
use Bob\Query\Processor;

test('Builder implements BuilderInterface', function () {
    expect(Builder::class)->toImplement(BuilderInterface::class);
});

test('Connection implements ConnectionInterface', function () {
    expect(Connection::class)->toImplement(ConnectionInterface::class);
});

test('Expression implements ExpressionInterface', function () {
    expect(Expression::class)->toImplement(ExpressionInterface::class);
});

test('Grammar implements GrammarInterface', function () {
    expect(Grammar::class)->toImplement(GrammarInterface::class);
});

test('Processor implements ProcessorInterface', function () {
    expect(Processor::class)->toImplement(ProcessorInterface::class);
});