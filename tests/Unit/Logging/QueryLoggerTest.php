<?php

declare(strict_types=1);

use Bob\Logging\QueryLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

it('handles statistics with null query entries', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockLogger->shouldReceive('log')->andReturn(true);
    
    $queryLogger = new QueryLogger($mockLogger);
    
    // Add a query log entry with null query
    $reflection = new ReflectionClass($queryLogger);
    $prop = $reflection->getProperty('queryLog');
    $prop->setAccessible(true);
    $prop->setValue($queryLogger, [
        ['query' => null, 'time' => '10ms'],
        ['query' => 'SELECT * FROM users', 'time' => '5ms'],
        ['query' => 'INSERT INTO users VALUES (1)', 'time' => '3ms'],
    ]);
    
    // Get statistics - should handle null query gracefully
    $stats = $queryLogger->getStatistics();
    
    expect($stats)->toBeArray();
    expect($stats['total_queries'])->toBe(3);
    expect($stats['queries_by_type'])->toHaveKey('SELECT');
    expect($stats['queries_by_type'])->toHaveKey('INSERT');
});

it('handles various log entry formats in statistics', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockLogger->shouldReceive('log')->andReturn(true);
    
    $queryLogger = new QueryLogger($mockLogger);
    
    // Add various log entry formats
    $reflection = new ReflectionClass($queryLogger);
    $prop = $reflection->getProperty('queryLog');
    $prop->setAccessible(true);
    $prop->setValue($queryLogger, [
        ['query' => 'SELECT * FROM users', 'time' => '10ms'],
        ['message' => 'Connection established'], // No query field
        ['query' => null, 'time' => '5ms'], // Null query
        ['query' => '', 'time' => '2ms'], // Empty query
        ['query' => 'UPDATE users SET name = ?', 'time' => '8ms'],
    ]);
    
    // Get statistics - should handle all formats gracefully
    $stats = $queryLogger->getStatistics();
    
    expect($stats)->toBeArray();
    expect($stats['total_queries'])->toBe(5);
    expect((float)$stats['total_time'])->toBe(25.0); // 10 + 5 + 2 + 8
});

it('returns enabled status', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $queryLogger = new QueryLogger($mockLogger);
    
    // Should be enabled by default
    expect($queryLogger->isEnabled())->toBeTrue();
    
    $queryLogger->disable();
    expect($queryLogger->isEnabled())->toBeFalse();
    
    $queryLogger->enable();
    expect($queryLogger->isEnabled())->toBeTrue();
});

it('does not log queries when disabled', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    // Should not receive any log calls
    $mockLogger->shouldNotReceive('log');
    
    $queryLogger = new QueryLogger($mockLogger);
    $queryLogger->disable();
    
    // Try to log a query - should be skipped
    $queryLogger->logQuery('SELECT * FROM users', [], 10.5);
    
    // Query log should be empty
    expect($queryLogger->getQueryLog())->toBe([]);
});

it('logs slow queries with warning level', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    
    // Expect a warning level log for slow query
    $mockLogger->shouldReceive('log')
        ->once()
        ->with(LogLevel::WARNING, Mockery::any(), Mockery::type('array'))
        ->andReturn(true);
    
    $queryLogger = new QueryLogger($mockLogger);
    $queryLogger->setSlowQueryThreshold(100); // 100ms threshold
    
    // Log a slow query (500ms)
    $queryLogger->logQuery('SELECT * FROM large_table', [], 500.0);
    
    $log = $queryLogger->getQueryLog();
    expect($log)->toHaveCount(1);
});

it('logs query errors when enabled', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    
    $mockLogger->shouldReceive('error')
        ->once()
        ->with('Query execution failed', Mockery::type('array'))
        ->andReturn(true);
    
    $queryLogger = new QueryLogger($mockLogger);
    $queryLogger->enable();
    
    $exception = new \Exception('Table not found', 1146);
    $queryLogger->logQueryError('SELECT * FROM missing', [], $exception);
});

it('does not log query errors when disabled', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    
    // Should not receive any error calls
    $mockLogger->shouldNotReceive('error');
    
    $queryLogger = new QueryLogger($mockLogger);
    $queryLogger->disable();
    
    $exception = new \Exception('Table not found');
    $queryLogger->logQueryError('SELECT * FROM missing', [], $exception);
});

it('implements all PSR-3 log levels', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    
    // Set up expectations for all log levels
    $mockLogger->shouldReceive('emergency')->once()->andReturn(true);
    $mockLogger->shouldReceive('alert')->once()->andReturn(true);
    $mockLogger->shouldReceive('critical')->once()->andReturn(true);
    $mockLogger->shouldReceive('error')->once()->andReturn(true);
    $mockLogger->shouldReceive('warning')->once()->andReturn(true);
    $mockLogger->shouldReceive('notice')->once()->andReturn(true);
    $mockLogger->shouldReceive('info')->once()->andReturn(true);
    $mockLogger->shouldReceive('debug')->once()->andReturn(true);
    
    $queryLogger = new QueryLogger($mockLogger);
    $queryLogger->enable();
    
    // Call all log levels
    $queryLogger->emergency('Emergency message', []);
    $queryLogger->alert('Alert message', []);
    $queryLogger->critical('Critical message', []);
    $queryLogger->error('Error message', []);
    $queryLogger->warning('Warning message', []);
    $queryLogger->notice('Notice message', []);
    $queryLogger->info('Info message', []);
    $queryLogger->debug('Debug message', []);
});

it('does not forward logs to PSR logger when disabled', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    
    // Should not receive any log calls
    $mockLogger->shouldNotReceive('emergency');
    $mockLogger->shouldNotReceive('alert');
    $mockLogger->shouldNotReceive('critical');
    
    $queryLogger = new QueryLogger($mockLogger);
    $queryLogger->disable();
    
    // Try to log - should be skipped
    $queryLogger->emergency('Emergency', []);
    $queryLogger->alert('Alert', []);
    $queryLogger->critical('Critical', []);
});

it('logs to internal query log when no PSR logger is set', function () {
    // Create QueryLogger without PSR logger
    $queryLogger = new QueryLogger(null);
    $queryLogger->enable();
    
    // Log messages
    $queryLogger->info('Test info message', ['data' => 'value']);
    $queryLogger->error('Test error message', ['code' => 500]);
    
    // Should be stored in query log
    $log = $queryLogger->getQueryLog();
    expect($log)->toHaveCount(2);
    expect($log[0]['level'])->toBe('info');
    expect($log[0]['message'])->toBe('Test info message');
    expect($log[1]['level'])->toBe('error');
    expect($log[1]['message'])->toBe('Test error message');
});

it('handles log transaction events', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockLogger->shouldReceive('info')->times(3)->andReturn(true);
    
    $queryLogger = new QueryLogger($mockLogger);
    
    $queryLogger->logTransaction('begin');
    $queryLogger->logTransaction('commit');
    $queryLogger->logTransaction('rollback', 'savepoint_1');
    
    $log = $queryLogger->getQueryLog();
    expect($log)->toHaveCount(3);
});

it('handles log connection events', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockLogger->shouldReceive('info')->twice()->andReturn(true);
    
    $queryLogger = new QueryLogger($mockLogger);
    
    $queryLogger->logConnection('connected', ['driver' => 'mysql']);
    $queryLogger->logConnection('disconnected');
    
    $log = $queryLogger->getQueryLog();
    expect($log)->toHaveCount(2);
});

it('filters password from connection config', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockLogger->shouldReceive('info')->once()->andReturn(true);
    
    $queryLogger = new QueryLogger($mockLogger);
    
    // Config with password should be filtered
    $queryLogger->logConnection('connected', [
        'driver' => 'mysql',
        'username' => 'root',
        'password' => 'secret123',
        'host' => 'localhost'
    ]);
    
    $log = $queryLogger->getQueryLog();
    expect($log[0]['config'])->not->toHaveKey('password');
    expect($log[0]['config'])->toHaveKey('username');
    expect($log[0]['config'])->toHaveKey('driver');
});

it('handles max query log limit', function () {
    $queryLogger = new QueryLogger(null);
    
    // Use reflection to set a small limit
    $reflection = new ReflectionClass($queryLogger);
    $prop = $reflection->getProperty('maxQueryLog');
    $prop->setAccessible(true);
    $prop->setValue($queryLogger, 3);
    
    // Add 5 queries - should only keep last 3
    $queryLogger->logQuery('SELECT 1', [], 1);
    $queryLogger->logQuery('SELECT 2', [], 2);
    $queryLogger->logQuery('SELECT 3', [], 3);
    $queryLogger->logQuery('SELECT 4', [], 4);
    $queryLogger->logQuery('SELECT 5', [], 5);
    
    $log = $queryLogger->getQueryLog();
    expect($log)->toHaveCount(3);
    expect($log[0]['query'])->toBe('SELECT 3'); // First query should be the 3rd one added
    expect($log[2]['query'])->toBe('SELECT 5'); // Last query should be the 5th one
});

it('handles notice level logging when logger is set', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockLogger->shouldReceive('notice')->once()->andReturn(true);
    
    $queryLogger = new QueryLogger($mockLogger);
    $queryLogger->notice('Notice message', ['key' => 'value']);
});

it('handles debug level logging when logger is not set', function () {
    $queryLogger = new QueryLogger(null);
    
    $queryLogger->debug('Debug message', ['key' => 'value']);
    
    $log = $queryLogger->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0]['level'])->toBe('debug');
    expect($log[0]['message'])->toBe('Debug message');
});

it('handles log method when logger is not set', function () {
    $queryLogger = new QueryLogger(null);
    
    $queryLogger->log('custom', 'Custom message', ['data' => 'value']);
    
    $log = $queryLogger->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0]['level'])->toBe('custom');
    expect($log[0]['message'])->toBe('Custom message');
});

it('handles warning level logging when logger is not set', function () {
    $queryLogger = new QueryLogger(null);
    
    $queryLogger->warning('Warning message', ['severity' => 'high']);
    
    $log = $queryLogger->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0]['level'])->toBe('warning');
    expect($log[0]['message'])->toBe('Warning message');
    expect($log[0]['context'])->toHaveKey('severity');
});

it('can set and use different configuration options', function () {
    $queryLogger = new QueryLogger(null);
    
    // Test setting log bindings
    $queryLogger->setLogBindings(false);
    $queryLogger->logQuery('SELECT * FROM users WHERE id = ?', [1], 10);
    
    $log = $queryLogger->getQueryLog();
    expect($log[0])->not->toHaveKey('bindings');
    
    $queryLogger->clearQueryLog();
    $queryLogger->setLogBindings(true);
    $queryLogger->logQuery('SELECT * FROM users WHERE id = ?', [1], 10);
    
    $log = $queryLogger->getQueryLog();
    expect($log[0])->toHaveKey('bindings');
});

it('can set and use log time option', function () {
    $queryLogger = new QueryLogger(null);
    
    // Test setting log time
    $queryLogger->setLogTime(false);
    $queryLogger->logQuery('SELECT * FROM users', [], 10);
    
    $log = $queryLogger->getQueryLog();
    expect($log[0])->not->toHaveKey('time');
    
    $queryLogger->clearQueryLog();
    $queryLogger->setLogTime(true);
    $queryLogger->logQuery('SELECT * FROM users', [], 10);
    
    $log = $queryLogger->getQueryLog();
    expect($log[0])->toHaveKey('time');
});

it('can determine all SQL query types', function () {
    $queryLogger = new QueryLogger(null);
    $reflection = new ReflectionClass($queryLogger);
    $method = $reflection->getMethod('getQueryType');
    $method->setAccessible(true);
    
    expect($method->invoke($queryLogger, 'SELECT * FROM users'))->toBe('SELECT');
    expect($method->invoke($queryLogger, 'INSERT INTO users VALUES (1)'))->toBe('INSERT');
    expect($method->invoke($queryLogger, 'UPDATE users SET name = "test"'))->toBe('UPDATE');
    expect($method->invoke($queryLogger, 'DELETE FROM users'))->toBe('DELETE');
    expect($method->invoke($queryLogger, 'CREATE TABLE test (id INT)'))->toBe('CREATE');
    expect($method->invoke($queryLogger, 'DROP TABLE test'))->toBe('DROP');
    expect($method->invoke($queryLogger, 'ALTER TABLE users ADD column'))->toBe('ALTER');
    expect($method->invoke($queryLogger, 'SHOW TABLES'))->toBe('OTHER');
});

it('handles setLogger method', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $queryLogger = new QueryLogger(null);
    
    // Initially no logger
    $queryLogger->info('Test message', []);
    
    // Set logger
    $queryLogger->setLogger($mockLogger);
    
    // Now should use the logger
    $mockLogger->shouldReceive('info')->once()->andReturn(true);
    $queryLogger->info('Test message', []);
});

it('handles statistics time calculation with integer values', function () {
    $queryLogger = new QueryLogger(null);
    
    // Add queries with integer time values instead of strings
    $reflection = new ReflectionClass($queryLogger);
    $prop = $reflection->getProperty('queryLog');
    $prop->setAccessible(true);
    $prop->setValue($queryLogger, [
        ['query' => 'SELECT 1', 'time' => '10'], // String time
        ['query' => 'SELECT 2', 'time' => 20], // Integer time without 'ms'
        ['query' => 'SELECT 3', 'time' => '30ms'], // Properly formatted
    ]);
    
    $stats = $queryLogger->getStatistics();
    expect($stats['total_time'])->toBe('60ms');
});