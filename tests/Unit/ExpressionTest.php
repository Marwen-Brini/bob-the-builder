<?php

declare(strict_types=1);

use Bob\Database\Expression;

test('creates expression with value', function() {
    $expression = new Expression('count(*)');
    
    expect($expression)->toBeInstanceOf(Expression::class);
    expect($expression->getValue())->toBe('count(*)');
});

test('returns value on toString', function() {
    $expression = new Expression('sum(amount)');
    
    expect((string) $expression)->toBe('sum(amount)');
});

test('can handle complex SQL expressions', function() {
    $expression = new Expression('DATE_FORMAT(created_at, "%Y-%m-%d")');
    
    expect($expression->getValue())->toBe('DATE_FORMAT(created_at, "%Y-%m-%d")');
    expect((string) $expression)->toBe('DATE_FORMAT(created_at, "%Y-%m-%d")');
});

test('can handle mathematical expressions', function() {
    $expression = new Expression('price * quantity');
    
    expect($expression->getValue())->toBe('price * quantity');
});

test('can handle case statements', function() {
    $sql = 'CASE WHEN status = "active" THEN 1 ELSE 0 END';
    $expression = new Expression($sql);
    
    expect($expression->getValue())->toBe($sql);
});

test('can handle subqueries', function() {
    $sql = '(SELECT COUNT(*) FROM posts WHERE user_id = users.id)';
    $expression = new Expression($sql);
    
    expect($expression->getValue())->toBe($sql);
});

test('preserves whitespace and formatting', function() {
    $sql = "SELECT   *\n  FROM   users\n WHERE  active = 1";
    $expression = new Expression($sql);
    
    expect($expression->getValue())->toBe($sql);
});

test('handles empty expressions', function() {
    $expression = new Expression('');
    
    expect($expression->getValue())->toBe('');
    expect((string) $expression)->toBe('');
});

test('handles null coalescing expressions', function() {
    $expression = new Expression('COALESCE(nickname, name, "Anonymous")');
    
    expect($expression->getValue())->toBe('COALESCE(nickname, name, "Anonymous")');
});

test('handles JSON operations', function() {
    $expression = new Expression('JSON_EXTRACT(metadata, "$.email")');
    
    expect($expression->getValue())->toBe('JSON_EXTRACT(metadata, "$.email")');
});

test('can be used for column aliases', function() {
    $expression = new Expression('count(*) as total');
    
    expect($expression->getValue())->toBe('count(*) as total');
});

test('can be used for table names with database prefix', function() {
    $expression = new Expression('database.schema.table');
    
    expect($expression->getValue())->toBe('database.schema.table');
});

test('handles window functions', function() {
    $expression = new Expression('ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC)');
    
    expect($expression->getValue())->toBe('ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC)');
});

test('handles concatenation', function() {
    $expression = new Expression('CONCAT(first_name, " ", last_name)');
    
    expect($expression->getValue())->toBe('CONCAT(first_name, " ", last_name)');
});

test('handles type casting', function() {
    $expression = new Expression('CAST(price AS DECIMAL(10, 2))');
    
    expect($expression->getValue())->toBe('CAST(price AS DECIMAL(10, 2))');
});

test('handles database specific functions', function() {
    // MySQL
    $mysqlExpression = new Expression('GROUP_CONCAT(name SEPARATOR ", ")');
    expect($mysqlExpression->getValue())->toBe('GROUP_CONCAT(name SEPARATOR ", ")');
    
    // PostgreSQL
    $pgExpression = new Expression('string_agg(name, ", ")');
    expect($pgExpression->getValue())->toBe('string_agg(name, ", ")');
    
    // SQLite
    $sqliteExpression = new Expression('group_concat(name, ", ")');
    expect($sqliteExpression->getValue())->toBe('group_concat(name, ", ")');
});

test('handles special characters', function() {
    $expression = new Expression('title LIKE "%50\% off%"');
    
    expect($expression->getValue())->toBe('title LIKE "%50\% off%"');
});

test('can represent binary operations', function() {
    $expression = new Expression('flags & 4 = 4');
    
    expect($expression->getValue())->toBe('flags & 4 = 4');
});

test('can represent interval expressions', function() {
    $expression = new Expression("created_at > NOW() - INTERVAL '30 days'");
    
    expect($expression->getValue())->toBe("created_at > NOW() - INTERVAL '30 days'");
});

test('works with increment operations', function() {
    $expression = new Expression('views + 1');
    
    expect($expression->getValue())->toBe('views + 1');
});

test('works with decrement operations', function() {
    $expression = new Expression('stock - 1');
    
    expect($expression->getValue())->toBe('stock - 1');
});

test('can handle raw bindings placeholder', function() {
    $expression = new Expression('status = ? AND active = ?');
    
    expect($expression->getValue())->toBe('status = ? AND active = ?');
});

test('maintains immutability', function() {
    $originalValue = 'count(*)';
    $expression = new Expression($originalValue);
    
    // getValue should return the same value each time
    expect($expression->getValue())->toBe($originalValue);
    expect($expression->getValue())->toBe($originalValue);
    
    // toString should also be consistent
    expect((string) $expression)->toBe($originalValue);
    expect((string) $expression)->toBe($originalValue);
});