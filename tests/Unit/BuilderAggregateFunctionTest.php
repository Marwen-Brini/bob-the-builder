<?php

use Bob\Database\Connection;
use Bob\Database\Expression;

test('addSelect converts aggregate functions to Expression', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $builder = $connection->table('test');

    // Start with a regular column
    $builder->select('name');

    // Add aggregate functions using addSelect
    $builder->addSelect('COUNT(*) as total');
    $builder->addSelect(['SUM(amount) as sum_amount', 'AVG(price) as avg_price']);

    $columns = $builder->getColumns();

    // First column should be regular string
    expect($columns[0])->toBe('name');

    // Aggregate functions should be converted to Expression objects
    expect($columns[1])->toBeInstanceOf(Expression::class);
    expect($columns[1]->getValue())->toBe('COUNT(*) as total');

    expect($columns[2])->toBeInstanceOf(Expression::class);
    expect($columns[2]->getValue())->toBe('SUM(amount) as sum_amount');

    expect($columns[3])->toBeInstanceOf(Expression::class);
    expect($columns[3]->getValue())->toBe('AVG(price) as avg_price');
});

test('addSelect with multiple arguments converts aggregates', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $builder = $connection->table('test');

    // Use multiple arguments (not array)
    $builder->addSelect('category', 'MIN(date) as first_date', 'MAX(date) as last_date');

    $columns = $builder->getColumns();

    expect($columns[0])->toBe('category');
    expect($columns[1])->toBeInstanceOf(Expression::class);
    expect($columns[1]->getValue())->toBe('MIN(date) as first_date');
    expect($columns[2])->toBeInstanceOf(Expression::class);
    expect($columns[2]->getValue())->toBe('MAX(date) as last_date');
});