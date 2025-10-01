<?php

use Bob\Logging\QueryLogger;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

describe('QueryLogger Tests', function () {

    beforeEach(function () {
        $this->psrLogger = m::mock(LoggerInterface::class);
        $this->logger = new QueryLogger($this->psrLogger);
    });

    afterEach(function () {
        m::close();
    });

    test('constructor sets logger', function () {
        $logger = new QueryLogger($this->psrLogger);

        // Use reflection to verify the logger was set
        $reflection = new ReflectionClass($logger);
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);

        expect($property->getValue($logger))->toBe($this->psrLogger);
    });

    test('constructor works without logger', function () {
        $logger = new QueryLogger;

        $reflection = new ReflectionClass($logger);
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);

        expect($property->getValue($logger))->toBeNull();
    });

    test('setLogger sets underlying logger', function () {
        $newLogger = m::mock(LoggerInterface::class);
        $this->logger->setLogger($newLogger);

        $reflection = new ReflectionClass($this->logger);
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);

        expect($property->getValue($this->logger))->toBe($newLogger);
    });

    test('enable and disable methods work', function () {
        $this->logger->disable();
        expect($this->logger->isEnabled())->toBeFalse();

        $this->logger->enable();
        expect($this->logger->isEnabled())->toBeTrue();
    });

    test('setEnabled method works', function () {
        $this->logger->setEnabled(false);
        expect($this->logger->isEnabled())->toBeFalse();

        $this->logger->setEnabled(true);
        expect($this->logger->isEnabled())->toBeTrue();
    });

    test('setLogBindings updates binding logging', function () {
        $this->logger->setLogBindings(false);

        $reflection = new ReflectionClass($this->logger);
        $property = $reflection->getProperty('logBindings');
        $property->setAccessible(true);

        expect($property->getValue($this->logger))->toBeFalse();

        $this->logger->setLogBindings(true);
        expect($property->getValue($this->logger))->toBeTrue();
    });

    test('setLogTime updates time logging', function () {
        $this->logger->setLogTime(false);

        $reflection = new ReflectionClass($this->logger);
        $property = $reflection->getProperty('logTime');
        $property->setAccessible(true);

        expect($property->getValue($this->logger))->toBeFalse();

        $this->logger->setLogTime(true);
        expect($property->getValue($this->logger))->toBeTrue();
    });

    test('setSlowQueryThreshold updates threshold', function () {
        $this->logger->setSlowQueryThreshold(500);

        $reflection = new ReflectionClass($this->logger);
        $property = $reflection->getProperty('slowQueryThreshold');
        $property->setAccessible(true);

        expect($property->getValue($this->logger))->toBe(500.0);
    });

    test('logQuery logs normal query with PSR logger', function () {
        $this->psrLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::DEBUG, 'Query executed', [
                'query' => 'SELECT * FROM users',
                'bindings' => ['id' => 1],
                'time' => '50ms',
            ]);

        $this->logger->logQuery('SELECT * FROM users', ['id' => 1], 50);

        $logs = $this->logger->getQueryLog();
        expect($logs)->toHaveCount(1);
        expect($logs[0]['query'])->toBe('SELECT * FROM users');
        expect($logs[0]['bindings'])->toBe(['id' => 1]);
        expect($logs[0]['time'])->toBe(50.0);
    });

    test('logQuery logs slow query with warning level', function () {
        $this->logger->setSlowQueryThreshold(100);

        $this->psrLogger->shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $context) {
                return $level === LogLevel::WARNING &&
                       $message === 'Slow query detected' &&
                       $context['query'] === 'SELECT * FROM large_table' &&
                       empty($context['bindings']) &&
                       $context['time'] === '150ms' &&
                       $context['slow_query'] === true;
            });

        $this->logger->logQuery('SELECT * FROM large_table', [], 150);

        $logs = $this->logger->getQueryLog();
        expect($logs[0]['slow_query'])->toBeTrue();
    });

    test('logQuery respects logBindings setting', function () {
        $this->logger->setLogBindings(false);

        $this->psrLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::DEBUG, 'Query executed', [
                'query' => 'SELECT * FROM users',
                'time' => '50ms',
            ]);

        $this->logger->logQuery('SELECT * FROM users', ['id' => 1], 50);

        $logs = $this->logger->getQueryLog();
        expect($logs[0])->not->toHaveKey('bindings');
    });

    test('logQuery respects logTime setting', function () {
        $this->logger->setLogTime(false);

        $this->psrLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::DEBUG, 'Query executed', [
                'query' => 'SELECT * FROM users',
                'bindings' => ['id' => 1],
            ]);

        $this->logger->logQuery('SELECT * FROM users', ['id' => 1], 50);

        $logs = $this->logger->getQueryLog();
        expect($logs[0])->not->toHaveKey('time');
    });

    test('logQuery stores in internal log without PSR logger', function () {
        $logger = new QueryLogger;

        $logger->logQuery('SELECT * FROM users', ['id' => 1], 50);

        $logs = $logger->getQueryLog();
        expect($logs)->toHaveCount(1);
        expect($logs[0]['query'])->toBe('SELECT * FROM users');
    });

    test('logQueryError logs failed query', function () {
        $exception = new Exception('Syntax error', 42);

        $this->psrLogger->shouldReceive('error')
            ->once()
            ->with('Query execution failed', [
                'query' => 'SELECT * FROM invalid',
                'bindings' => [],
                'error' => 'Syntax error',
                'code' => 42,
            ]);

        $this->logger->logQueryError('SELECT * FROM invalid', [], $exception);
    });

    test('logQueryError stores in internal log without PSR logger', function () {
        $logger = new QueryLogger;
        $exception = new Exception('Syntax error', 42);

        $logger->logQueryError('SELECT * FROM invalid', [], $exception);

        $logs = $logger->getQueryLog();
        expect($logs)->toHaveCount(1);
        expect($logs[0]['level'])->toBe('error');
        expect($logs[0]['message'])->toBe('Query execution failed');
    });

    test('logTransaction logs transaction event', function () {
        $this->psrLogger->shouldReceive('info')
            ->once()
            ->with('Transaction begin', ['event' => 'begin']);

        $this->logger->logTransaction('begin');

        $logs = $this->logger->getQueryLog();
        expect($logs[0]['type'])->toBe('transaction');
        expect($logs[0]['event'])->toBe('begin');
        expect($logs[0]['savepoint'])->toBeNull();
    });

    test('logTransaction logs savepoint', function () {
        $this->psrLogger->shouldReceive('info')
            ->once()
            ->with('Transaction savepoint', ['event' => 'savepoint', 'savepoint' => 'sp1']);

        $this->logger->logTransaction('savepoint', 'sp1');

        $logs = $this->logger->getQueryLog();
        expect($logs[0]['savepoint'])->toBe('sp1');
    });

    test('logConnection logs connection event', function () {
        $config = [
            'host' => 'localhost',
            'database' => 'test',
            'password' => 'secret',
        ];

        $this->psrLogger->shouldReceive('info')
            ->once()
            ->with('Database connection connect', [
                'event' => 'connect',
                'config' => [
                    'host' => 'localhost',
                    'database' => 'test',
                ],
            ]);

        $this->logger->logConnection('connect', $config);

        $logs = $this->logger->getQueryLog();
        expect($logs[0]['type'])->toBe('connection');
        expect($logs[0]['config'])->not->toHaveKey('password');
    });

    test('addToQueryLog respects maxQueryLog limit', function () {
        $reflection = new ReflectionClass($this->logger);
        $property = $reflection->getProperty('maxQueryLog');
        $property->setAccessible(true);
        $property->setValue($this->logger, 3);

        $this->psrLogger->shouldReceive('log')->times(4);

        $this->logger->logQuery('Query 1', [], 10);
        $this->logger->logQuery('Query 2', [], 20);
        $this->logger->logQuery('Query 3', [], 30);
        $this->logger->logQuery('Query 4', [], 40);

        $logs = $this->logger->getQueryLog();
        expect($logs)->toHaveCount(3);
        expect($logs[0]['query'])->toBe('Query 2'); // First query was shifted out
    });

    test('clearQueryLog clears all logs', function () {
        $this->psrLogger->shouldReceive('log')->once();

        $this->logger->logQuery('SELECT * FROM users');
        expect($this->logger->getQueryLog())->toHaveCount(1);

        $this->logger->clearQueryLog();
        expect($this->logger->getQueryLog())->toHaveCount(0);
    });

    test('getStatistics returns correct stats', function () {
        $this->psrLogger->shouldReceive('log')->times(4);
        $this->psrLogger->shouldReceive('info')->once();

        $this->logger->logQuery('SELECT * FROM users', [], 50);
        $this->logger->logQuery('INSERT INTO logs', [], 30);
        $this->logger->logQuery('UPDATE users SET name = ?', [], 20);
        $this->logger->logQuery('DELETE FROM cache', [], 100);
        $this->logger->logTransaction('begin');

        $stats = $this->logger->getStatistics();

        expect($stats['total_queries'])->toBe(5);
        expect($stats['total_time'])->toBe('200ms');
        expect($stats['average_time'])->toBe('40ms'); // (50+30+20+100) / 5 (includes transaction)
        expect($stats['slow_queries'])->toBe(0);
        expect($stats['queries_by_type']['SELECT'])->toBe(1);
        expect($stats['queries_by_type']['INSERT'])->toBe(1);
        expect($stats['queries_by_type']['UPDATE'])->toBe(1);
        expect($stats['queries_by_type']['DELETE'])->toBe(1);
    });

    test('getStatistics handles slow queries', function () {
        $this->logger->setSlowQueryThreshold(50);

        $this->psrLogger->shouldReceive('log')->times(2);

        $this->logger->logQuery('SELECT * FROM users', [], 30);
        $this->logger->logQuery('SELECT * FROM large_table', [], 100);

        $stats = $this->logger->getStatistics();

        expect($stats['slow_queries'])->toBe(1);
    });

    test('getStatistics handles empty log', function () {
        $stats = $this->logger->getStatistics();

        expect($stats['total_queries'])->toBe(0);
        expect($stats['total_time'])->toBe('0ms');
        expect($stats['average_time'])->toBe('0ms');
        expect($stats['queries_by_type'])->toBe([]);
    });

    test('getQueryType identifies query types correctly', function () {
        $reflection = new ReflectionClass($this->logger);
        $method = $reflection->getMethod('getQueryType');
        $method->setAccessible(true);

        expect($method->invoke($this->logger, 'SELECT * FROM users'))->toBe('SELECT');
        expect($method->invoke($this->logger, 'INSERT INTO logs'))->toBe('INSERT');
        expect($method->invoke($this->logger, 'UPDATE users SET'))->toBe('UPDATE');
        expect($method->invoke($this->logger, 'DELETE FROM cache'))->toBe('DELETE');
        expect($method->invoke($this->logger, 'CREATE TABLE test'))->toBe('CREATE');
        expect($method->invoke($this->logger, 'DROP TABLE old'))->toBe('DROP');
        expect($method->invoke($this->logger, 'ALTER TABLE users'))->toBe('ALTER');
        expect($method->invoke($this->logger, 'TRUNCATE TABLE logs'))->toBe('OTHER');
    });

    test('PSR-3 emergency method forwards to logger', function () {
        $this->psrLogger->shouldReceive('emergency')
            ->once()
            ->with('Emergency message', ['data' => 'test']);

        $this->logger->emergency('Emergency message', ['data' => 'test']);
    });

    test('PSR-3 alert method forwards to logger', function () {
        $this->psrLogger->shouldReceive('alert')
            ->once()
            ->with('Alert message', ['data' => 'test']);

        $this->logger->alert('Alert message', ['data' => 'test']);
    });

    test('PSR-3 critical method forwards to logger', function () {
        $this->psrLogger->shouldReceive('critical')
            ->once()
            ->with('Critical message', ['data' => 'test']);

        $this->logger->critical('Critical message', ['data' => 'test']);
    });

    test('PSR-3 error method forwards to logger', function () {
        $this->psrLogger->shouldReceive('error')
            ->once()
            ->with('Error message', ['data' => 'test']);

        $this->logger->error('Error message', ['data' => 'test']);
    });

    test('PSR-3 warning method forwards to logger', function () {
        $this->psrLogger->shouldReceive('warning')
            ->once()
            ->with('Warning message', ['data' => 'test']);

        $this->logger->warning('Warning message', ['data' => 'test']);
    });

    test('PSR-3 notice method forwards to logger', function () {
        $this->psrLogger->shouldReceive('notice')
            ->once()
            ->with('Notice message', ['data' => 'test']);

        $this->logger->notice('Notice message', ['data' => 'test']);
    });

    test('PSR-3 info method forwards to logger', function () {
        $this->psrLogger->shouldReceive('info')
            ->once()
            ->with('Info message', ['data' => 'test']);

        $this->logger->info('Info message', ['data' => 'test']);
    });

    test('PSR-3 debug method forwards to logger', function () {
        $this->psrLogger->shouldReceive('debug')
            ->once()
            ->with('Debug message', ['data' => 'test']);

        $this->logger->debug('Debug message', ['data' => 'test']);
    });

    test('PSR-3 log method forwards to logger', function () {
        $this->psrLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'Log message', ['data' => 'test']);

        $this->logger->log(LogLevel::INFO, 'Log message', ['data' => 'test']);
    });

    test('PSR-3 methods store in internal log without PSR logger', function () {
        $logger = new QueryLogger;

        $logger->error('Error message', ['data' => 'test']);
        $logger->warning('Warning message', ['data' => 'test']);
        $logger->info('Info message', ['data' => 'test']);
        $logger->debug('Debug message', ['data' => 'test']);
        $logger->log(LogLevel::NOTICE, 'Notice message', ['data' => 'test']);

        $logs = $logger->getQueryLog();
        expect($logs)->toHaveCount(5);
        expect($logs[0]['level'])->toBe('error');
        expect($logs[1]['level'])->toBe('warning');
        expect($logs[2]['level'])->toBe('info');
        expect($logs[3]['level'])->toBe('debug');
        expect($logs[4]['level'])->toBe(LogLevel::NOTICE);
    });

    test('PSR-3 methods respect enabled flag', function () {
        $this->psrLogger->shouldNotReceive('emergency');
        $this->psrLogger->shouldNotReceive('alert');
        $this->psrLogger->shouldNotReceive('critical');
        $this->psrLogger->shouldNotReceive('error');
        $this->psrLogger->shouldNotReceive('warning');
        $this->psrLogger->shouldNotReceive('notice');
        $this->psrLogger->shouldNotReceive('info');
        $this->psrLogger->shouldNotReceive('debug');
        $this->psrLogger->shouldNotReceive('log');

        $this->logger->disable();

        $this->logger->emergency('Emergency');
        $this->logger->alert('Alert');
        $this->logger->critical('Critical');
        $this->logger->error('Error');
        $this->logger->warning('Warning');
        $this->logger->notice('Notice');
        $this->logger->info('Info');
        $this->logger->debug('Debug');
        $this->logger->log(LogLevel::INFO, 'Log');

        expect($this->logger->getQueryLog())->toHaveCount(0);
    });

    test('getStatistics handles time as string with ms suffix', function () {
        // Manually add entries to the query log to test the string handling
        $reflection = new ReflectionClass($this->logger);
        $property = $reflection->getProperty('queryLog');
        $property->setAccessible(true);

        $property->setValue($this->logger, [
            ['query' => 'SELECT * FROM users', 'time' => '50ms'],
            ['query' => 'SELECT * FROM posts', 'time' => '75.5ms'],
        ]);

        $stats = $this->logger->getStatistics();

        expect($stats['total_time'])->toBe('125.5ms');
        expect($stats['average_time'])->toBe('62.75ms');
    });

});
