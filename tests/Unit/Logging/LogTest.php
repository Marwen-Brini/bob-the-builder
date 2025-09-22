<?php

use Bob\Logging\Log;
use Bob\Logging\QueryLogger;
use Bob\Database\Connection;
use Psr\Log\LoggerInterface;
use Mockery as m;

describe('Log Tests', function () {

    beforeEach(function () {
        // Reset the Log state before each test
        Log::reset();
    });

    afterEach(function () {
        m::close();
        Log::reset(); // Clean up after each test
    });

    test('Log starts with disabled state', function () {
        expect(Log::isEnabled())->toBeFalse();
    });

    test('enable method enables logging globally', function () {
        Log::enable();
        expect(Log::isEnabled())->toBeTrue();
    });

    test('disable method disables logging globally', function () {
        Log::enable();
        Log::disable();
        expect(Log::isEnabled())->toBeFalse();
    });

    test('setLogger sets global PSR-3 logger', function () {
        $logger = m::mock(LoggerInterface::class);

        Log::setLogger($logger);

        expect(Log::getLogger())->toBe($logger);
    });

    test('clearLogger removes global logger', function () {
        $logger = m::mock(LoggerInterface::class);

        Log::setLogger($logger);
        Log::clearLogger();

        expect(Log::getLogger())->toBeNull();
    });

    test('getQueryLogger returns QueryLogger instance', function () {
        $queryLogger = Log::getQueryLogger();

        expect($queryLogger)->toBeInstanceOf(QueryLogger::class);
    });

    test('getQueryLogger returns same instance on multiple calls', function () {
        $queryLogger1 = Log::getQueryLogger();
        $queryLogger2 = Log::getQueryLogger();

        expect($queryLogger1)->toBe($queryLogger2);
    });

    test('registerConnection adds connection to registry', function () {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('setQueryLogger')->once();

        Log::registerConnection($connection);

        // Enable logging should affect registered connections
        $connection->shouldReceive('enableQueryLog')->once();
        Log::enable();
    });

    test('registerConnection enables logging if globally enabled', function () {
        $connection = m::mock(Connection::class);

        Log::enable(); // Enable first

        $connection->shouldReceive('enableQueryLog')->once();
        $connection->shouldReceive('setQueryLogger')->once();

        Log::registerConnection($connection);
    });

    test('registerConnection sets logger if global logger exists', function () {
        $logger = m::mock(LoggerInterface::class);
        $connection = m::mock(Connection::class);

        Log::setLogger($logger);

        $connection->shouldReceive('setLogger')->with($logger)->once();
        $connection->shouldReceive('setQueryLogger')->once();

        Log::registerConnection($connection);
    });

    test('unregisterConnection removes connection from registry', function () {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('setQueryLogger')->once();

        Log::registerConnection($connection);
        Log::unregisterConnection($connection);

        // After unregistering, enable should not affect the connection
        Log::enable();
        // No expectations on connection means it won't be called
    });

    test('enableFor enables logging for specific connection', function () {
        $connection = m::mock(Connection::class);

        $connection->shouldReceive('enableQueryLog')->once();
        $connection->shouldReceive('setQueryLogger')->once();

        Log::enableFor($connection);
    });

    test('enableFor registers connection if not already registered', function () {
        $connection = m::mock(Connection::class);

        $connection->shouldReceive('enableQueryLog')->twice(); // Once in enableFor, once in enable
        $connection->shouldReceive('setQueryLogger')->once();

        Log::enableFor($connection);
        Log::enable(); // This should affect the registered connection
    });

    test('disableFor disables logging for specific connection', function () {
        $connection = m::mock(Connection::class);

        $connection->shouldReceive('disableQueryLog')->once();

        Log::disableFor($connection);
    });

    test('getQueryLog returns empty array when no logger', function () {
        $result = Log::getQueryLog();

        expect($result)->toBe([]);
    });

    test('getQueryLog returns logs from global query logger', function () {
        $queryLogger = Log::getQueryLogger();

        // Mock the query log
        $mockLogs = [['query' => 'SELECT * FROM users']];

        // Use reflection to set the query log
        $reflection = new ReflectionClass($queryLogger);
        $property = $reflection->getProperty('queryLog');
        $property->setAccessible(true);
        $property->setValue($queryLogger, $mockLogs);

        $result = Log::getQueryLog();

        expect($result)->toBe($mockLogs);
    });

    test('clearQueryLog clears all logs', function () {
        $queryLogger = Log::getQueryLogger();

        // Add some logs
        $reflection = new ReflectionClass($queryLogger);
        $property = $reflection->getProperty('queryLog');
        $property->setAccessible(true);
        $property->setValue($queryLogger, [['query' => 'test']]);

        Log::clearQueryLog();

        expect($property->getValue($queryLogger))->toBe([]);
    });

    test('clearQueryLog clears logs from registered connections', function () {
        $connection = m::mock(Connection::class);

        $connection->shouldReceive('setQueryLogger')->once();
        $connection->shouldReceive('clearQueryLog')->once();

        Log::registerConnection($connection);
        Log::clearQueryLog();
    });

    test('getStatistics returns statistics', function () {
        $stats = Log::getStatistics();

        expect($stats)->toHaveKeys(['total_queries', 'total_time', 'average_time', 'slow_queries', 'queries_by_type', 'connections']);
        expect($stats['total_queries'])->toBe(0);
        expect($stats['connections'])->toBe(0);
    });

    test('getStatistics merges statistics from query logger', function () {
        Log::enable();

        // Log some queries to generate statistics
        Log::logQuery('SELECT * FROM users', [], 20);
        Log::logQuery('SELECT * FROM posts', [], 30);
        Log::logQuery('INSERT INTO logs', [], 50);

        $stats = Log::getStatistics();

        expect($stats['total_queries'])->toBe(3);
        expect($stats['total_time'])->toBe('100ms');
        expect($stats['average_time'])->toBe('33.33ms');
        expect($stats['queries_by_type'])->toHaveKey('SELECT');
    });

    test('configure updates global configuration', function () {
        Log::configure([
            'log_bindings' => false,
            'log_time' => false,
            'slow_query_threshold' => 500
        ]);

        $queryLogger = Log::getQueryLogger();

        // Check that configuration is applied
        $reflection = new ReflectionClass($queryLogger);

        $logBindings = $reflection->getProperty('logBindings');
        $logBindings->setAccessible(true);
        expect($logBindings->getValue($queryLogger))->toBeFalse();

        $logTime = $reflection->getProperty('logTime');
        $logTime->setAccessible(true);
        expect($logTime->getValue($queryLogger))->toBeFalse();

        $threshold = $reflection->getProperty('slowQueryThreshold');
        $threshold->setAccessible(true);
        expect($threshold->getValue($queryLogger))->toBe(500.0);
    });

    test('configure applies to existing query logger', function () {
        $queryLogger = Log::getQueryLogger(); // Create logger first

        Log::configure([
            'log_bindings' => false,
            'log_time' => false,
            'slow_query_threshold' => 200
        ]);

        $reflection = new ReflectionClass($queryLogger);

        $threshold = $reflection->getProperty('slowQueryThreshold');
        $threshold->setAccessible(true);
        expect($threshold->getValue($queryLogger))->toBe(200.0);
    });

    test('logQuery logs when enabled', function () {
        Log::enable();

        Log::logQuery('SELECT * FROM users', ['id' => 1], 10.5);

        $logs = Log::getQueryLog();
        expect($logs)->toHaveCount(1);
        expect($logs[0]['query'])->toBe('SELECT * FROM users');
        expect($logs[0]['bindings'])->toBe(['id' => 1]);
        expect($logs[0]['time'])->toBe(10.5);
    });

    test('logQuery does not log when disabled', function () {
        Log::disable();

        Log::logQuery('SELECT * FROM users', ['id' => 1], 10.5);

        $logs = Log::getQueryLog();
        expect($logs)->toHaveCount(0);
    });

    test('logError logs error when enabled', function () {
        $logger = m::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->with('Test error', ['context' => 'test'])->once();

        Log::setLogger($logger);
        Log::enable();

        Log::logError('Test error', ['context' => 'test']);
    });

    test('logError does not log when disabled', function () {
        $logger = m::mock(LoggerInterface::class);
        $logger->shouldNotReceive('error');

        Log::setLogger($logger);
        Log::disable();

        Log::logError('Test error', ['context' => 'test']);
    });

    test('logInfo logs info when enabled', function () {
        $logger = m::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('Test info', ['context' => 'test'])->once();

        Log::setLogger($logger);
        Log::enable();

        Log::logInfo('Test info', ['context' => 'test']);
    });

    test('logInfo does not log when disabled', function () {
        $logger = m::mock(LoggerInterface::class);
        $logger->shouldNotReceive('info');

        Log::setLogger($logger);
        Log::disable();

        Log::logInfo('Test info', ['context' => 'test']);
    });

    test('reset clears all state', function () {
        $logger = m::mock(LoggerInterface::class);
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('setQueryLogger')->once();
        $connection->shouldReceive('setLogger')->once();
        $connection->shouldReceive('enableQueryLog')->once(); // Called when registering after global enable

        Log::enable();
        Log::setLogger($logger);
        Log::registerConnection($connection);

        Log::reset();

        expect(Log::isEnabled())->toBeFalse();
        expect(Log::getLogger())->toBeNull();

        // Connections should be cleared - enable should not affect the previously registered connection
        Log::enable(); // No expectations means connection won't be called
    });

    test('mergeStatistics merges statistics correctly', function () {
        // Test protected method via reflection
        $reflection = new ReflectionClass(Log::class);
        $method = $reflection->getMethod('mergeStatistics');
        $method->setAccessible(true);

        $stats1 = [
            'total_queries' => 5,
            'total_time' => 100,
            'slow_queries' => 1,
            'queries_by_type' => ['select' => 3, 'insert' => 2]
        ];

        $stats2 = [
            'total_queries' => 3,
            'total_time' => '50ms',
            'slow_queries' => 2,
            'queries_by_type' => ['select' => 1, 'update' => 2]
        ];

        $merged = $method->invoke(null, $stats1, $stats2);

        expect($merged['total_queries'])->toBe(8);
        expect($merged['total_time'])->toBe(150.0);
        expect($merged['slow_queries'])->toBe(3);
        expect($merged['queries_by_type'])->toBe(['select' => 4, 'insert' => 2, 'update' => 2]);
    });

    test('setLogger updates existing query logger', function () {
        $logger1 = m::mock(LoggerInterface::class);
        $logger2 = m::mock(LoggerInterface::class);

        Log::setLogger($logger1);
        $queryLogger = Log::getQueryLogger(); // Create query logger with first logger

        Log::setLogger($logger2);

        // Check that query logger was updated
        $reflection = new ReflectionClass($queryLogger);
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);

        expect($property->getValue($queryLogger))->toBe($logger2);
    });

    test('enable updates existing query logger state', function () {
        Log::disable();
        $queryLogger = Log::getQueryLogger(); // Create while disabled

        Log::enable();

        // Check that query logger was enabled
        $reflection = new ReflectionClass($queryLogger);
        $property = $reflection->getProperty('enabled');
        $property->setAccessible(true);

        expect($property->getValue($queryLogger))->toBeTrue();
    });

    test('disable updates existing query logger state', function () {
        Log::enable();
        $queryLogger = Log::getQueryLogger(); // Create while enabled

        Log::disable();

        // Check that query logger was disabled
        $reflection = new ReflectionClass($queryLogger);
        $property = $reflection->getProperty('enabled');
        $property->setAccessible(true);

        expect($property->getValue($queryLogger))->toBeFalse();
    });

});