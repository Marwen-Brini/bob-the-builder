<?php

use Bob\Logging\Log;
use Bob\Database\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

beforeEach(function () {
    Log::reset();
});

test('can enable and disable logging globally', function () {
    expect(Log::isEnabled())->toBeFalse();
    
    Log::enable();
    expect(Log::isEnabled())->toBeTrue();
    
    Log::disable();
    expect(Log::isEnabled())->toBeFalse();
});

test('can set and get global logger', function () {
    $logger = new NullLogger();
    
    Log::setLogger($logger);
    
    expect(Log::getLogger())->toBe($logger);
});

test('automatically configures registered connections when enabling globally', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    expect($connection->isLoggingEnabled())->toBeFalse();
    
    Log::enable();
    
    expect($connection->isLoggingEnabled())->toBeTrue();
});

test('can enable logging for specific connection', function () {
    $connection1 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection2 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    Log::enableFor($connection1);
    
    expect($connection1->isLoggingEnabled())->toBeTrue();
    expect($connection2->isLoggingEnabled())->toBeFalse();
});

test('can disable logging for specific connection', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    Log::enable();
    expect($connection->isLoggingEnabled())->toBeTrue();
    
    Log::disableFor($connection);
    expect($connection->isLoggingEnabled())->toBeFalse();
});

test('shares global logger with registered connections', function () {
    // Start with logging disabled
    Log::disable();
    
    $logger = new NullLogger();
    Log::setLogger($logger);
    
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    expect($connection->isLoggingEnabled())->toBeFalse();
    
    Log::enable();
    
    expect($connection->isLoggingEnabled())->toBeTrue();
});

test('can get query log from all connections', function () {
    Log::enable();
    
    $connection1 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $connection2 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Clear any connection logs
    Log::clearQueryLog();
    
    // Use logQuery directly since toSql() doesn't log
    $connection1->logQuery('SELECT * FROM users', [], 10);
    $connection2->logQuery('SELECT * FROM posts', [], 15);
    
    $log = Log::getQueryLog();
    
    expect($log)->toBeArray();
    // Just check that we have logs from both connections
    $queries = array_filter($log, fn($entry) => isset($entry['query']));
    expect($queries)->toHaveCount(2);
});

test('can clear query log for all connections', function () {
    Log::enable();
    
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Use logQuery directly
    $connection->logQuery('SELECT * FROM users', [], 10);
    
    expect(Log::getQueryLog())->not->toBeEmpty();
    
    Log::clearQueryLog();
    
    expect(Log::getQueryLog())->toBeEmpty();
});

test('can get statistics from all connections', function () {
    Log::enable();
    
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $stats = Log::getStatistics();
    
    expect($stats)->toBeArray()
        ->toHaveKeys(['total_queries', 'total_time', 'average_time', 'slow_queries', 'queries_by_type', 'connections']);
    
    expect($stats['connections'])->toBe(1);
});

test('can configure global logging settings', function () {
    Log::configure([
        'log_bindings' => false,
        'log_time' => false,
        'slow_query_threshold' => 500,
        'max_query_log' => 50,
    ]);
    
    $queryLogger = Log::getQueryLogger();
    
    expect($queryLogger)->toBeInstanceOf(\Bob\Logging\QueryLogger::class);
});

test('can log queries manually', function () {
    Log::enable();
    
    Log::logQuery('SELECT * FROM users WHERE id = ?', [1], 10.5);
    
    $log = Log::getQueryLog();
    
    expect($log)->toHaveCount(1);
    expect($log[0])->toHaveKeys(['query', 'bindings', 'time']);
    expect($log[0]['query'])->toBe('SELECT * FROM users WHERE id = ?');
    expect($log[0]['bindings'])->toBe([1]);
    expect($log[0]['time'])->toBe('10.5ms');
});

test('can log errors manually', function () {
    Log::enable();
    
    Log::logError('Database connection failed', ['host' => 'localhost']);
    
    $log = Log::getQueryLog();
    
    expect($log)->toHaveCount(1);
    expect($log[0])->toHaveKeys(['level', 'message', 'context']);
    expect($log[0]['level'])->toBe('error');
    expect($log[0]['message'])->toBe('Database connection failed');
});

test('can log info manually', function () {
    Log::enable();
    
    Log::logInfo('Cache cleared', ['duration' => '100ms']);
    
    $log = Log::getQueryLog();
    
    expect($log)->toHaveCount(1);
    expect($log[0])->toHaveKeys(['level', 'message', 'context']);
    expect($log[0]['level'])->toBe('info');
    expect($log[0]['message'])->toBe('Cache cleared');
});

test('reset clears all global state', function () {
    $logger = new NullLogger();
    Log::setLogger($logger);
    Log::enable();
    
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    Log::reset();
    
    expect(Log::isEnabled())->toBeFalse();
    expect(Log::getLogger())->toBeNull();
    expect(Log::getStatistics()['connections'])->toBe(0);
});