<?php

use Bob\Logging\QueryLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

test('can enable and disable logging', function () {
    $logger = new QueryLogger();
    
    expect($logger->isEnabled())->toBeTrue();
    
    $logger->setEnabled(false);
    expect($logger->isEnabled())->toBeFalse();
    
    $logger->setEnabled(true);
    expect($logger->isEnabled())->toBeTrue();
});

test('can set underlying PSR-3 logger', function () {
    $psrLogger = Mockery::mock(LoggerInterface::class);
    $queryLogger = new QueryLogger();
    
    $queryLogger->setLogger($psrLogger);
    
    $psrLogger->shouldReceive('log')
        ->once()
        ->with(LogLevel::DEBUG, 'Query executed', Mockery::type('array'));
    
    $queryLogger->logQuery('SELECT * FROM users', [], 10.5);
});

test('logs queries with bindings when enabled', function () {
    $logger = new QueryLogger();
    $logger->setLogBindings(true);
    
    $logger->logQuery('SELECT * FROM users WHERE id = ?', [1], 10.5);
    
    $log = $logger->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0])->toHaveKeys(['query', 'bindings', 'time']);
    expect($log[0]['bindings'])->toBe([1]);
});

test('logs queries without bindings when disabled', function () {
    $logger = new QueryLogger();
    $logger->setLogBindings(false);
    
    $logger->logQuery('SELECT * FROM users WHERE id = ?', [1], 10.5);
    
    $log = $logger->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0])->not->toHaveKey('bindings');
});

test('logs execution time when enabled', function () {
    $logger = new QueryLogger();
    $logger->setLogTime(true);
    
    $logger->logQuery('SELECT * FROM users', [], 10.5);
    
    $log = $logger->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0])->toHaveKey('time');
    expect($log[0]['time'])->toBe('10.5ms');
});

test('does not log execution time when disabled', function () {
    $logger = new QueryLogger();
    $logger->setLogTime(false);
    
    $logger->logQuery('SELECT * FROM users', [], 10.5);
    
    $log = $logger->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0])->not->toHaveKey('time');
});

test('identifies slow queries', function () {
    $psrLogger = Mockery::mock(LoggerInterface::class);
    $queryLogger = new QueryLogger($psrLogger);
    $queryLogger->setSlowQueryThreshold(100);
    
    $psrLogger->shouldReceive('log')
        ->once()
        ->with(LogLevel::WARNING, 'Slow query detected', Mockery::on(function ($context) {
            return $context['slow_query'] === true;
        }));
    
    $queryLogger->logQuery('SELECT * FROM large_table', [], 150);
});

test('logs query errors', function () {
    $logger = new QueryLogger();
    $exception = new Exception('Table not found');
    
    $logger->logQueryError('SELECT * FROM invalid_table', [], $exception);
    
    $log = $logger->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0])->toHaveKeys(['level', 'message', 'context']);
    expect($log[0]['level'])->toBe('error');
    expect($log[0]['context']['error'])->toBe('Table not found');
});

test('logs transaction events', function () {
    $logger = new QueryLogger();
    
    $logger->logTransaction('started');
    $logger->logTransaction('savepoint', 'sp1');
    $logger->logTransaction('committed');
    
    $log = $logger->getQueryLog();
    expect($log)->toHaveCount(3);
    expect($log[0]['message'])->toContain('Transaction started');
    expect($log[1]['context']['savepoint'])->toBe('sp1');
    expect($log[2]['message'])->toContain('Transaction committed');
});

test('logs connection events without password', function () {
    $logger = new QueryLogger();
    
    $config = [
        'driver' => 'mysql',
        'host' => 'localhost',
        'username' => 'root',
        'password' => 'secret',
        'database' => 'test',
    ];
    
    $logger->logConnection('established', $config);
    
    $log = $logger->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0]['context']['config'])->not->toHaveKey('password');
    expect($log[0]['context']['config']['username'])->toBe('root');
});

test('respects maximum query log size', function () {
    $logger = new QueryLogger();
    
    // Set max to 2
    $reflection = new ReflectionClass($logger);
    $property = $reflection->getProperty('maxQueryLog');
    $property->setAccessible(true);
    $property->setValue($logger, 2);
    
    $logger->logQuery('Query 1', [], 1);
    $logger->logQuery('Query 2', [], 2);
    $logger->logQuery('Query 3', [], 3);
    
    $log = $logger->getQueryLog();
    expect($log)->toHaveCount(2);
    expect($log[0]['query'])->toBe('Query 2');
    expect($log[1]['query'])->toBe('Query 3');
});

test('can clear query log', function () {
    $logger = new QueryLogger();
    
    $logger->logQuery('SELECT * FROM users', [], 10);
    expect($logger->getQueryLog())->toHaveCount(1);
    
    $logger->clearQueryLog();
    expect($logger->getQueryLog())->toBeEmpty();
});

test('provides query statistics', function () {
    $logger = new QueryLogger();
    $logger->setSlowQueryThreshold(50);
    
    $logger->logQuery('SELECT * FROM users', [], 10);
    $logger->logQuery('INSERT INTO posts', [], 20);
    $logger->logQuery('UPDATE users SET active = 1', [], 60);
    $logger->logQuery('DELETE FROM sessions', [], 5);
    $logger->logQuery('SELECT * FROM large_table', [], 100);
    
    $stats = $logger->getStatistics();
    
    expect($stats['total_queries'])->toBe(5);
    expect($stats['total_time'])->toBe('195ms');
    expect($stats['average_time'])->toBe('39ms');
    expect($stats['slow_queries'])->toBe(2);
    expect($stats['queries_by_type'])->toBe([
        'SELECT' => 2,
        'INSERT' => 1,
        'UPDATE' => 1,
        'DELETE' => 1,
    ]);
});

test('identifies query types correctly', function () {
    $logger = new QueryLogger();
    
    $queries = [
        'SELECT * FROM users' => 'SELECT',
        'INSERT INTO posts VALUES (1)' => 'INSERT',
        'UPDATE users SET name = "John"' => 'UPDATE',
        'DELETE FROM sessions' => 'DELETE',
        'CREATE TABLE test (id INT)' => 'CREATE',
        'DROP TABLE old_data' => 'DROP',
        'ALTER TABLE users ADD COLUMN age INT' => 'ALTER',
        'TRUNCATE TABLE logs' => 'OTHER',
    ];
    
    foreach ($queries as $query => $expectedType) {
        $logger->logQuery($query, [], 1);
    }
    
    $stats = $logger->getStatistics();
    
    expect($stats['queries_by_type'])->toMatchArray([
        'SELECT' => 1,
        'INSERT' => 1,
        'UPDATE' => 1,
        'DELETE' => 1,
        'CREATE' => 1,
        'DROP' => 1,
        'ALTER' => 1,
        'OTHER' => 1,
    ]);
});

test('implements PSR-3 logger interface methods', function () {
    $psrLogger = Mockery::mock(LoggerInterface::class);
    $queryLogger = new QueryLogger($psrLogger);
    
    $methods = [
        'emergency' => LogLevel::EMERGENCY,
        'alert' => LogLevel::ALERT,
        'critical' => LogLevel::CRITICAL,
        'error' => LogLevel::ERROR,
        'warning' => LogLevel::WARNING,
        'notice' => LogLevel::NOTICE,
        'info' => LogLevel::INFO,
        'debug' => LogLevel::DEBUG,
    ];
    
    foreach ($methods as $method => $level) {
        $psrLogger->shouldReceive($method)
            ->once()
            ->with('Test message', ['context' => 'data']);
        
        $queryLogger->$method('Test message', ['context' => 'data']);
    }
});

test('stores logs in memory when no PSR-3 logger provided', function () {
    $logger = new QueryLogger();
    
    $logger->error('Error message', ['code' => 500]);
    $logger->warning('Warning message', ['threshold' => 100]);
    $logger->info('Info message', ['status' => 'ok']);
    $logger->debug('Debug message', ['data' => 'test']);
    
    $log = $logger->getQueryLog();
    
    expect($log)->toHaveCount(4);
    expect($log[0]['level'])->toBe('error');
    expect($log[1]['level'])->toBe('warning');
    expect($log[2]['level'])->toBe('info');
    expect($log[3]['level'])->toBe('debug');
});

test('does not log when disabled', function () {
    $logger = new QueryLogger();
    $logger->setEnabled(false);
    
    $logger->logQuery('SELECT * FROM users', [], 10);
    $logger->logQueryError('SELECT * FROM invalid', [], new Exception());
    $logger->logTransaction('started');
    $logger->logConnection('established', []);
    
    expect($logger->getQueryLog())->toBeEmpty();
});