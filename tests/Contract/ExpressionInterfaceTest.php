<?php

declare(strict_types=1);

use Bob\Contracts\ExpressionInterface;

it('implements ExpressionInterface', function () {
    $expression = Mockery::mock(ExpressionInterface::class);

    expect($expression)->toBeInstanceOf(ExpressionInterface::class);
});

it('has getValue method', function () {
    $expression = Mockery::mock(ExpressionInterface::class);

    expect(method_exists($expression, 'getValue'))->toBeTrue();
});

it('has __toString method', function () {
    $expression = Mockery::mock(ExpressionInterface::class);

    expect(method_exists($expression, '__toString'))->toBeTrue();
});

it('getValue returns mixed value', function () {
    $expression = Mockery::mock(ExpressionInterface::class);

    $expression->shouldReceive('getValue')->andReturn('COUNT(*)');

    expect($expression->getValue())->toBe('COUNT(*)');
});

it('__toString returns string representation', function () {
    $expression = Mockery::mock(ExpressionInterface::class);

    $expression->shouldReceive('__toString')->andReturn('COUNT(*)');

    expect($expression->__toString())->toBe('COUNT(*)');
    expect($expression->__toString())->toBeString();
});

it('can handle different value types', function () {
    $expression1 = Mockery::mock(ExpressionInterface::class);
    $expression2 = Mockery::mock(ExpressionInterface::class);
    $expression3 = Mockery::mock(ExpressionInterface::class);

    $expression1->shouldReceive('getValue')->andReturn('NOW()');
    $expression2->shouldReceive('getValue')->andReturn(42);
    $expression3->shouldReceive('getValue')->andReturn(['COALESCE(name, ?)', 'Unknown']);

    expect($expression1->getValue())->toBe('NOW()');
    expect($expression2->getValue())->toBe(42);
    expect($expression3->getValue())->toBeArray();
});

it('string conversion always returns string', function () {
    $expression1 = Mockery::mock(ExpressionInterface::class);
    $expression2 = Mockery::mock(ExpressionInterface::class);

    $expression1->shouldReceive('__toString')->andReturn('NOW()');
    $expression2->shouldReceive('__toString')->andReturn('42');

    expect($expression1->__toString())->toBeString();
    expect($expression2->__toString())->toBeString();
});
