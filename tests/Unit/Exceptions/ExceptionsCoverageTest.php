<?php

use Bob\Exceptions\QueryException;
use Bob\Exceptions\ConnectionException;
use Bob\Exceptions\GrammarException;

test('QueryException formats message correctly', function () {
    $exception = new QueryException(
        'SQLSTATE[42S02]: Base table or view not found',
        'select * from users where id = ?',
        [1]
    );

    expect($exception->getMessage())->toContain('SQLSTATE[42S02]');
    expect($exception->getSql())->toBe('select * from users where id = ?');
    expect($exception->getBindings())->toBe([1]);
});

test('QueryException fromSqlAndBindings', function () {
    $exception = QueryException::fromSqlAndBindings(
        'select * from users where id = ?',
        [1],
        new Exception('Original error')
    );

    expect($exception)->toBeInstanceOf(QueryException::class);
    expect($exception->getMessage())->toContain('Original error');
    expect($exception->getMessage())->toContain('SQL: select * from users where id = 1');
    expect($exception->getSql())->toBe('select * from users where id = ?');
});

test('QueryException static formatMessage', function () {
    $message = QueryException::formatMessage(
        'select * from users where id = ?',
        [1],
        new Exception('Original error')
    );

    expect($message)->toContain('Original error');
    expect($message)->toContain('SQL: select * from users where id = 1');
});

test('QueryException formatMessage with string binding', function () {
    $message = QueryException::formatMessage(
        'select * from users where name = ?',
        ['John'],
        null
    );

    expect($message)->toContain("SQL: select * from users where name = 'John'");
});

test('ConnectionException static methods', function () {
    // Test connectionFailed
    $exception = ConnectionException::connectionFailed([
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db'
    ]);

    expect($exception)->toBeInstanceOf(ConnectionException::class);
    expect($exception->getMessage())->toContain('Failed to connect');
    expect($exception->getMessage())->toContain('mysql');
    expect($exception->getMessage())->toContain('localhost');

    // Test unsupportedDriver
    $exception = ConnectionException::unsupportedDriver('oracle');
    expect($exception->getMessage())->toContain('Unsupported database driver: oracle');

    // Test missingConfiguration
    $exception = ConnectionException::missingConfiguration('password');
    expect($exception->getMessage())->toContain('Database configuration missing required key: password');

    // Test invalidConfiguration
    $exception = ConnectionException::invalidConfiguration('port', 'must be numeric');
    expect($exception->getMessage())->toContain('Invalid database configuration');
    expect($exception->getMessage())->toContain('port');

    // Test transactionError
    $exception = ConnectionException::transactionError('commit');
    expect($exception->getMessage())->toContain('Transaction commit failed');

    // Test transactionError with previous exception
    $previous = new Exception('Deadlock detected');
    $exception = ConnectionException::transactionError('rollback', $previous);
    expect($exception->getMessage())->toContain('Transaction rollback failed');
    expect($exception->getMessage())->toContain('Deadlock detected');
});

test('ConnectionException getConfig', function () {
    $config = ['driver' => 'mysql', 'host' => 'localhost'];
    $exception = new ConnectionException('Test error', $config);

    expect($exception->getConfig())->toBe($config);
});

test('GrammarException constructor', function () {
    $exception = new GrammarException('Test grammar error');
    expect($exception)->toBeInstanceOf(GrammarException::class);
    expect($exception->getMessage())->toBe('Test grammar error');
});

test('QueryException with empty bindings', function () {
    $message = QueryException::formatMessage(
        'select * from users',
        []
    );

    expect($message)->toContain('SQL: select * from users');
    expect($message)->not->toContain('?');
});

test('QueryException with multiple bindings', function () {
    $exception = QueryException::fromSqlAndBindings(
        'select * from users where name = ? and age > ?',
        ['John', 18]
    );

    expect($exception->getMessage())->toContain("name = 'John'");
    expect($exception->getMessage())->toContain('age > 18');
});

test('ConnectionException connectionFailed with previous exception', function () {
    $previous = new Exception('Access denied');
    $exception = ConnectionException::connectionFailed(
        ['driver' => 'mysql', 'host' => 'localhost', 'database' => 'test'],
        $previous
    );

    expect($exception->getMessage())->toContain('Failed to connect');
    expect($exception->getMessage())->toContain('Access denied');
    expect($exception->getPrevious())->toBe($previous);
});

test('ConnectionException with unknown config values', function () {
    $exception = ConnectionException::connectionFailed([]);

    expect($exception->getMessage())->toContain('unknown');
    expect($exception->getMessage())->toContain("database 'unknown'");
});