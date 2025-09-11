<?php

declare(strict_types=1);

use Bob\Exceptions\ConnectionException;

it('creates connection failed exception', function () {
    $config = [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
    ];

    $exception = ConnectionException::connectionFailed($config);

    expect($exception->getMessage())->toContain('mysql');
    expect($exception->getMessage())->toContain('test_db');
    expect($exception->getMessage())->toContain('localhost');
    expect($exception->getConfig())->toBe($config);
});

it('creates connection failed exception with previous exception', function () {
    $config = [
        'driver' => 'pgsql',
        'host' => '192.168.1.1',
        'database' => 'app_db',
    ];
    $previous = new Exception('Connection refused');

    $exception = ConnectionException::connectionFailed($config, $previous);

    expect($exception->getMessage())->toContain('Connection refused');
    expect($exception->getPrevious())->toBe($previous);
});

it('creates unsupported driver exception', function () {
    $exception = ConnectionException::unsupportedDriver('mongodb');

    expect($exception->getMessage())->toContain('mongodb');
    expect($exception->getMessage())->toContain('mysql, pgsql, sqlite');
    expect($exception->getConfig())->toBe(['driver' => 'mongodb']);
});

it('creates missing configuration exception', function () {
    $exception = ConnectionException::missingConfiguration('password');

    expect($exception->getMessage())->toContain('password');
    expect($exception->getMessage())->toContain('missing required key');
    expect($exception->getConfig())->toBe(['missing_key' => 'password']);
});

it('creates invalid configuration exception', function () {
    $exception = ConnectionException::invalidConfiguration('port', 'must be a number');

    expect($exception->getMessage())->toContain('port');
    expect($exception->getMessage())->toContain('must be a number');
    expect($exception->getConfig())->toBe([
        'invalid_key' => 'port',
        'reason' => 'must be a number',
    ]);
});

it('creates transaction error exception', function () {
    $exception = ConnectionException::transactionError('rollback');

    expect($exception->getMessage())->toContain('Transaction rollback failed');
    expect($exception->getConfig())->toBe(['operation' => 'rollback']);
});

it('creates transaction error with previous exception', function () {
    $previous = new Exception('Deadlock detected');
    $exception = ConnectionException::transactionError('commit', $previous);

    expect($exception->getMessage())->toContain('Transaction commit failed');
    expect($exception->getMessage())->toContain('Deadlock detected');
    expect($exception->getPrevious())->toBe($previous);
});

it('handles missing config values gracefully', function () {
    $config = ['driver' => 'sqlite'];

    $exception = ConnectionException::connectionFailed($config);

    expect($exception->getMessage())->toContain('sqlite');
    expect($exception->getMessage())->toContain('unknown');
});
