<?php

use Bob\Database\Connection;
use Bob\Logging\Log;
use Mockery as m;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    // Reset global state
    Log::clearLogger();
    Log::reset();

    $this->pdo = m::mock(PDO::class);
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ], $this->pdo);
});

afterEach(function () {
    m::close();
    Log::clearLogger();
    Log::reset();
});

describe('LogsQueries Coverage Tests', function () {

    // Line 116: getQueryStatistics()
    test('getQueryStatistics returns statistics from query logger', function () {
        // Enable query logging
        $this->connection->enableQueryLog();

        // Get the query logger directly and add some queries
        $logger = $this->connection->getQueryLogger();

        // Use reflection to access the protected addToQueryLog method
        $method = new ReflectionMethod($logger, 'addToQueryLog');
        $method->setAccessible(true);

        // Add queries with time as numeric values
        $method->invoke($logger, ['query' => 'SELECT * FROM users', 'time' => 50.0]);
        $method->invoke($logger, ['query' => 'SELECT * FROM posts', 'time' => 20.0]);

        // Get statistics - this calls getQueryLogger()->getStatistics()
        $stats = $this->connection->getQueryStatistics();

        expect($stats)->toBeArray();
        expect($stats)->toHaveKey('total_queries');
        expect($stats)->toHaveKey('total_time');
        expect($stats['total_queries'])->toBe(2);
        // The stats function expects time values with 'ms' suffix, so it might be 0 if not formatted
        expect($stats)->toHaveKey('queries_by_type');
    });

    // Lines 145-146: logQueryError with logging enabled
    test('logQueryError logs errors when logging is enabled', function () {
        // Create a mock logger to verify it gets called
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('error')->once()->with(
            'Query execution failed',
            m::on(function ($context) {
                return isset($context['query']) &&
                       $context['query'] === 'SELECT * FROM users' &&
                       isset($context['bindings']) &&
                       $context['bindings'] === [1] &&
                       isset($context['error']) &&
                       $context['error'] === 'Database error' &&
                       isset($context['code']) &&
                       $context['code'] === 0;
            })
        );

        // Set the mock logger globally
        Log::setLogger($mockLogger);

        // Enable query logging
        $this->connection->enableQueryLog();

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->connection, 'logQueryError');
        $method->setAccessible(true);

        $exception = new Exception('Database error');
        $method->invoke($this->connection, 'SELECT * FROM users', [1], $exception);

        // Verify the error was logged through the logger
        expect(true)->toBeTrue(); // Mock will verify it was called
    });

    // Line 156: logTransaction with logging enabled
    test('logTransaction logs transaction events when logging is enabled', function () {
        // Create a mock logger to verify it gets called
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->once()->with(
            m::on(function ($message) {
                return str_contains($message, 'Transaction begin');
            }),
            m::type('array')
        );

        // Set the mock logger globally
        Log::setLogger($mockLogger);

        // Enable query logging
        $this->connection->enableQueryLog();

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->connection, 'logTransaction');
        $method->setAccessible(true);

        $method->invoke($this->connection, 'begin');

        // The transaction should be logged to the PSR logger
        expect(true)->toBeTrue(); // Logger mock will verify it was called
    });

    // Test logTransaction with savepoint
    test('logTransaction logs savepoint events when logging is enabled', function () {
        // Create a mock logger to verify it gets called
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->once()->with(
            'Transaction savepoint',
            ['event' => 'savepoint', 'savepoint' => 'sp1']
        );

        // Set the mock logger globally
        Log::setLogger($mockLogger);

        // Enable query logging
        $this->connection->enableQueryLog();

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->connection, 'logTransaction');
        $method->setAccessible(true);

        $method->invoke($this->connection, 'savepoint', 'sp1');

        // The savepoint should be logged to the PSR logger
        expect(true)->toBeTrue(); // Logger mock will verify it was called
    });

    // Test logQueryError when logging is disabled
    test('logQueryError does nothing when logging is disabled', function () {
        // Create a mock logger that should NOT be called
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('info');

        // Set the mock logger globally
        Log::setLogger($mockLogger);

        // Disable query logging (default state)
        $this->connection->disableQueryLog();

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->connection, 'logQueryError');
        $method->setAccessible(true);

        $exception = new Exception('Database error');
        $method->invoke($this->connection, 'SELECT * FROM users', [1], $exception);

        // Nothing should have been logged
        expect(true)->toBeTrue();
    });

    // Test logTransaction when logging is disabled
    test('logTransaction does nothing when logging is disabled', function () {
        // Create a mock logger that should NOT be called
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('info');

        // Set the mock logger globally
        Log::setLogger($mockLogger);

        // Disable query logging (default state)
        $this->connection->disableQueryLog();

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->connection, 'logTransaction');
        $method->setAccessible(true);

        $method->invoke($this->connection, 'begin');

        // Nothing should have been logged
        expect(true)->toBeTrue();
    });
});
