<?php

declare(strict_types=1);

use Bob\Exceptions\QueryException;

it('creates exception with SQL and bindings', function () {
    $sql = 'SELECT * FROM users WHERE id = ?';
    $bindings = [1];
    
    $exception = new QueryException('Test error', $sql, $bindings);
    
    expect($exception->getMessage())->toBe('Test error');
    expect($exception->getSql())->toBe($sql);
    expect($exception->getBindings())->toBe($bindings);
});

it('formats message with SQL and bindings', function () {
    $sql = 'SELECT * FROM users WHERE id = ? AND name = ?';
    $bindings = [1, 'John'];
    
    $message = QueryException::formatMessage($sql, $bindings);
    
    expect($message)->toContain('Database query error');
    expect($message)->toContain("id = 1");
    expect($message)->toContain("name = 'John'");
});

it('creates exception from SQL and bindings', function () {
    $sql = 'INSERT INTO users (name, email) VALUES (?, ?)';
    $bindings = ['John', 'john@example.com'];
    
    $exception = QueryException::fromSqlAndBindings($sql, $bindings);
    
    expect($exception->getMessage())->toContain("VALUES ('John', 'john@example.com')");
    expect($exception->getSql())->toBe($sql);
    expect($exception->getBindings())->toBe($bindings);
});

it('formats SQL with string bindings', function () {
    $sql = 'SELECT * FROM users WHERE name = ? AND email = ?';
    $bindings = ['John Doe', 'john@example.com'];
    
    $exception = QueryException::fromSqlAndBindings($sql, $bindings);
    
    expect($exception->getMessage())->toContain("'John Doe'");
    expect($exception->getMessage())->toContain("'john@example.com'");
});

it('formats SQL with numeric bindings', function () {
    $sql = 'SELECT * FROM users WHERE id = ? AND age > ?';
    $bindings = [123, 18];
    
    $exception = QueryException::fromSqlAndBindings($sql, $bindings);
    
    expect($exception->getMessage())->toContain('123');
    expect($exception->getMessage())->toContain('18');
});

it('handles empty bindings', function () {
    $sql = 'SELECT * FROM users';
    $bindings = [];
    
    $exception = QueryException::fromSqlAndBindings($sql, $bindings);
    
    expect($exception->getMessage())->toContain($sql);
    expect($exception->getBindings())->toBe([]);
});

it('preserves previous exception', function () {
    $previous = new Exception('Database connection lost');
    $sql = 'SELECT * FROM users';
    $bindings = [];
    
    $exception = QueryException::fromSqlAndBindings($sql, $bindings, $previous);
    
    expect($exception->getMessage())->toContain('Database connection lost');
    expect($exception->getPrevious())->toBe($previous);
});