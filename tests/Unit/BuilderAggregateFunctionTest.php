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

test('handles aggregate function edge cases', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $builder = $connection->table('test');

    // Test various edge cases
    $builder->select([
        'COUNT(DISTINCT id)',          // Nested functions
        'Count(*)',                     // Mixed case
        'COUNT (*)',                    // Space before parenthesis
        'COUNT(*) AS total_count',      // Custom alias
        'account_number',               // Column containing function name
        'sum(amount)',                  // Lowercase
        'AVG  (  price  )',            // Multiple spaces
    ]);

    $columns = $builder->getColumns();

    // Nested function - should be Expression
    expect($columns[0])->toBeInstanceOf(Expression::class);
    expect($columns[0]->getValue())->toBe('COUNT(DISTINCT id)');

    // Mixed case - should be Expression
    expect($columns[1])->toBeInstanceOf(Expression::class);
    expect($columns[1]->getValue())->toBe('Count(*)');

    // Space before parenthesis - should be Expression
    expect($columns[2])->toBeInstanceOf(Expression::class);
    expect($columns[2]->getValue())->toBe('COUNT (*)');

    // Custom alias - should be Expression
    expect($columns[3])->toBeInstanceOf(Expression::class);
    expect($columns[3]->getValue())->toBe('COUNT(*) AS total_count');

    // Column containing function name - should NOT be Expression
    expect($columns[4])->toBe('account_number');

    // Lowercase - should be Expression
    expect($columns[5])->toBeInstanceOf(Expression::class);
    expect($columns[5]->getValue())->toBe('sum(amount)');

    // Multiple spaces - should be Expression
    expect($columns[6])->toBeInstanceOf(Expression::class);
    expect($columns[6]->getValue())->toBe('AVG  (  price  )');
});

test('does not treat non-aggregate functions as aggregates', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $builder = $connection->table('test');

    $builder->select([
        'username',
        'account_number',
        'summary',
        'minimum_value',
        'maximum_price',
        'count_of_items',  // Column name, not function
    ]);

    $columns = $builder->getColumns();

    // All should be regular strings, not Expression objects
    foreach ($columns as $column) {
        expect($column)->toBeString();
    }
});