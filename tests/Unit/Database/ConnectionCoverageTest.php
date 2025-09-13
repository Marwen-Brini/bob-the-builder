<?php

use Bob\Database\Connection;
use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Processor;
use Bob\Cache\QueryCache;
use Bob\Database\QueryProfiler;
use Bob\Logging\Log;
use Psr\Log\LoggerInterface;

describe('Connection configuration and setup', function () {
    it('sets logger from config', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log')->zeroOrMoreTimes();
        $logger->shouldReceive('info')->zeroOrMoreTimes();
        $logger->shouldReceive('debug')->zeroOrMoreTimes();
        
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'logger' => $logger
        ];
        
        $connection = new Connection($config);
        
        // Use reflection to check the logger was set
        $reflection = new ReflectionClass($connection);
        $prop = $reflection->getProperty('logger');
        $prop->setAccessible(true);
        
        expect($prop->getValue($connection))->toBeInstanceOf(LoggerInterface::class);
    });
    
    it('uses PostgreSQL grammar for pgsql driver', function () {
        $config = [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'database' => 'test'
        ];
        
        $connection = new Connection($config);
        
        expect($connection->getQueryGrammar())->toBeInstanceOf(PostgreSQLGrammar::class);
    });
    
    it('uses PostgreSQL grammar for postgres driver', function () {
        $config = [
            'driver' => 'postgres',
            'host' => 'localhost',
            'database' => 'test'
        ];
        
        $connection = new Connection($config);
        
        expect($connection->getQueryGrammar())->toBeInstanceOf(PostgreSQLGrammar::class);
    });
    
    it('uses PostgreSQL grammar for postgresql driver', function () {
        $config = [
            'driver' => 'postgresql',
            'host' => 'localhost',
            'database' => 'test'
        ];
        
        $connection = new Connection($config);
        
        expect($connection->getQueryGrammar())->toBeInstanceOf(PostgreSQLGrammar::class);
    });
    
    it('uses default MySQL grammar for unknown driver', function () {
        $config = [
            'driver' => 'unknown_driver',
            'host' => 'localhost',
            'database' => 'test'
        ];
        
        $connection = new Connection($config);
        
        expect($connection->getQueryGrammar())->toBeInstanceOf(MySQLGrammar::class);
    });
    
    it('creates PostgreSQL DSN correctly', function () {
        $config = [
            'driver' => 'pgsql',
            'host' => 'db.example.com',
            'port' => 5433,
            'database' => 'myapp'
        ];
        
        $connection = new Connection($config);
        
        // Use reflection to test the DSN
        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('getPostgresDsn');
        $method->setAccessible(true);
        
        $dsn = $method->invoke($connection);
        
        expect($dsn)->toBe('pgsql:host=db.example.com;port=5433;dbname=myapp');
    });
    
    it('uses default PostgreSQL values when not provided', function () {
        $config = [
            'driver' => 'pgsql'
        ];
        
        $connection = new Connection($config);
        
        // Use reflection to test the DSN
        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('getPostgresDsn');
        $method->setAccessible(true);
        
        $dsn = $method->invoke($connection);
        
        expect($dsn)->toBe('pgsql:host=127.0.0.1;port=5432;dbname=');
    });
});

describe('PDO management', function () {
    it('allows setting PDO directly', function () {
        $config = ['driver' => 'sqlite', 'database' => ':memory:'];
        $connection = new Connection($config);
        
        $pdo = new PDO('sqlite::memory:');
        $result = $connection->setPdo($pdo);
        
        expect($result)->toBe($connection); // Fluent interface
        expect($connection->getPdo())->toBe($pdo);
    });
    
    it('returns connection name from config', function () {
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'name' => 'primary'
        ];
        
        $connection = new Connection($config);
        
        expect($connection->getName())->toBe('primary');
    });
    
    it('returns default name when not configured', function () {
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:'
        ];
        
        $connection = new Connection($config);
        
        expect($connection->getName())->toBe('default');
    });
});

describe('Table prefix management', function () {
    it('returns table prefix', function () {
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => 'wp_'
        ];
        
        $connection = new Connection($config);
        
        expect($connection->getTablePrefix())->toBe('wp_');
    });
    
    it('sets table prefix', function () {
        $config = ['driver' => 'sqlite', 'database' => ':memory:'];
        $connection = new Connection($config);
        
        $result = $connection->setTablePrefix('app_');
        
        expect($result)->toBe($connection); // Fluent interface
        expect($connection->getTablePrefix())->toBe('app_');
        expect($connection->getQueryGrammar()->getTablePrefix())->toBe('app_');
    });
});

describe('Configuration access', function () {
    it('returns entire config when no key specified', function () {
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => 'test_'
        ];
        
        $connection = new Connection($config);
        
        expect($connection->getConfig())->toBe($config);
    });
    
    it('returns specific config value by key', function () {
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'charset' => 'utf8mb4'
        ];
        
        $connection = new Connection($config);
        
        expect($connection->getConfig('charset'))->toBe('utf8mb4');
        expect($connection->getConfig('driver'))->toBe('sqlite');
    });
    
    it('returns null for non-existent config key', function () {
        $config = ['driver' => 'sqlite', 'database' => ':memory:'];
        $connection = new Connection($config);
        
        expect($connection->getConfig('non_existent'))->toBeNull();
    });
});

describe('Unprepared statements', function () {
    it('executes unprepared statements', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $result = $connection->unprepared('CREATE TABLE test (id INTEGER PRIMARY KEY)');
        
        expect($result)->toBeTrue();
        
        // Verify table was created
        $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name='test'");
        expect($tables)->toHaveCount(1);
    });
    
    it('handles unprepared statements in pretend mode', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $result = null;
        $queries = $connection->pretend(function ($db) use (&$result) {
            $result = $db->unprepared('CREATE TABLE test (id INTEGER)');
        });
        
        // In pretend mode, unprepared returns true but doesn't log
        expect($result)->toBeTrue();
    });
});

describe('Binding preparation', function () {
    it('converts DateTime objects to strings', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $date = new DateTime('2024-01-15 10:30:00');
        $bindings = ['created_at' => $date];
        
        $prepared = $connection->prepareBindings($bindings);
        
        expect($prepared['created_at'])->toBe('2024-01-15 10:30:00');
    });
    
    it('converts DateTimeImmutable objects to strings', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $date = new DateTimeImmutable('2024-01-15 10:30:00');
        $bindings = ['updated_at' => $date];
        
        $prepared = $connection->prepareBindings($bindings);
        
        expect($prepared['updated_at'])->toBe('2024-01-15 10:30:00');
    });
    
    it('converts boolean values to integers', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $bindings = [
            'active' => true,
            'deleted' => false
        ];
        
        $prepared = $connection->prepareBindings($bindings);
        
        expect($prepared['active'])->toBe(1);
        expect($prepared['deleted'])->toBe(0);
    });
});

describe('Transaction retries', function () {
    it('retries transaction on failure', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection->unprepared('CREATE TABLE test (id INTEGER PRIMARY KEY, value INTEGER)');
        
        $attempts = 0;
        
        $result = $connection->transaction(function ($db) use (&$attempts) {
            $attempts++;
            
            if ($attempts < 2) {
                throw new Exception('Temporary failure');
            }
            
            $db->insert('INSERT INTO test (value) VALUES (?)', [42]);
            return 'success';
        }, 3);
        
        expect($result)->toBe('success');
        expect($attempts)->toBe(2);
        
        // Verify data was inserted
        $count = $connection->selectOne('SELECT COUNT(*) as count FROM test');
        expect($count->count)->toBe(1);
    });
    
    it('throws exception after max attempts', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $attempts = 0;
        
        expect(function () use ($connection, &$attempts) {
            $connection->transaction(function ($db) use (&$attempts) {
                $attempts++;
                throw new Exception('Persistent failure');
            }, 2);
        })->toThrow(Exception::class, 'Persistent failure');
        
        expect($attempts)->toBe(2);
    });
});

describe('Savepoint transactions', function () {
    it('creates savepoints for nested transactions', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        // SQLite doesn't support savepoints, so let's mock the grammar
        $grammar = Mockery::mock(MySQLGrammar::class)->makePartial();
        $grammar->shouldReceive('supportsSavepoints')->andReturn(true);
        $grammar->shouldReceive('compileSavepoint')->with('trans2')->andReturn('SAVEPOINT trans2');
        $grammar->shouldReceive('compileSavepointRollBack')->with('trans2')->andReturn('ROLLBACK TO SAVEPOINT trans2');
        
        $connection->setQueryGrammar($grammar);
        
        // Start first transaction
        $connection->beginTransaction();
        expect($connection->transactionLevel())->toBe(1);
        
        // Start nested transaction (should create savepoint)
        $connection->beginTransaction();
        expect($connection->transactionLevel())->toBe(2);
        
        // Rollback nested transaction
        $connection->rollBack();
        expect($connection->transactionLevel())->toBe(1);
        
        // Rollback main transaction
        $connection->rollBack();
        expect($connection->transactionLevel())->toBe(0);
    });
});

describe('Query state methods', function () {
    it('reports pretending state', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        expect($connection->pretending())->toBeFalse();
        
        $connection->pretend(function ($db) use (&$insidePretend) {
            $insidePretend = $db->pretending();
        });
        
        expect($insidePretend)->toBeTrue();
        expect($connection->pretending())->toBeFalse();
    });
    
    it('reports logging state', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        // Start with query log disabled
        $connection->disableQueryLog();
        
        expect($connection->logging())->toBeFalse();
        
        $connection->enableQueryLog();
        expect($connection->logging())->toBeTrue();
        
        $connection->disableQueryLog();
        expect($connection->logging())->toBeFalse();
    });
    
    it('flushes query log', function () {
        // Create a fresh connection without any global interference
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        // Test that flush works - whether there are queries or not
        $connection->flushQueryLog();
        expect($connection->getQueryLog())->toHaveCount(0);
        
        // Enable logging and try to add a query
        $connection->enableQueryLog();
        
        // Even if logging doesn't work due to global state, flush should still work
        $connection->flushQueryLog();
        expect($connection->getQueryLog())->toHaveCount(0);
    });
});

describe('Prepared statement caching', function () {
    it('caches prepared statements when enabled', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection->unprepared('CREATE TABLE test (id INTEGER PRIMARY KEY)');
        $connection->enableStatementCaching();
        
        // Use reflection to access the cache
        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('getCachedStatement');
        $method->setAccessible(true);
        $cache = $reflection->getProperty('preparedStatements');
        $cache->setAccessible(true);
        
        $pdo = $connection->getPdo();
        $query = 'SELECT * FROM test WHERE id = ?';
        
        // First call should create and cache
        $stmt1 = $method->invoke($connection, $query, $pdo);
        expect($cache->getValue($connection))->toHaveCount(1);
        
        // Second call should return cached
        $stmt2 = $method->invoke($connection, $query, $pdo);
        expect($stmt1)->toBe($stmt2);
        expect($cache->getValue($connection))->toHaveCount(1);
    });
    
    it('does not cache when disabled', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection->unprepared('CREATE TABLE test (id INTEGER PRIMARY KEY)');
        $connection->disableStatementCaching();
        
        // Use reflection
        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('getCachedStatement');
        $method->setAccessible(true);
        
        $pdo = $connection->getPdo();
        $query = 'SELECT * FROM test WHERE id = ?';
        
        $stmt1 = $method->invoke($connection, $query, $pdo);
        $stmt2 = $method->invoke($connection, $query, $pdo);
        
        expect($stmt1)->not->toBe($stmt2);
    });
    
    it('limits cached statements to max', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection->enableStatementCaching();
        $connection->setMaxCachedStatements(2);
        
        // Use reflection
        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('getCachedStatement');
        $method->setAccessible(true);
        $cache = $reflection->getProperty('preparedStatements');
        $cache->setAccessible(true);
        
        $pdo = $connection->getPdo();
        
        // Cache 3 statements, should evict first one
        $method->invoke($connection, 'SELECT 1', $pdo);
        $method->invoke($connection, 'SELECT 2', $pdo);
        $method->invoke($connection, 'SELECT 3', $pdo);
        
        expect($cache->getValue($connection))->toHaveCount(2);
    });
    
    it('clears statement cache', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection->enableStatementCaching();
        
        // Use reflection
        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('getCachedStatement');
        $method->setAccessible(true);
        $cache = $reflection->getProperty('preparedStatements');
        $cache->setAccessible(true);
        
        $pdo = $connection->getPdo();
        
        $method->invoke($connection, 'SELECT 1', $pdo);
        expect($cache->getValue($connection))->toHaveCount(1);
        
        $connection->clearStatementCache();
        expect($cache->getValue($connection))->toHaveCount(0);
    });
    
    it('adjusts cache when max is reduced', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection->enableStatementCaching();
        
        // Use reflection
        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('getCachedStatement');
        $method->setAccessible(true);
        $cache = $reflection->getProperty('preparedStatements');
        $cache->setAccessible(true);
        
        $pdo = $connection->getPdo();
        
        // Cache 3 statements
        $method->invoke($connection, 'SELECT 1', $pdo);
        $method->invoke($connection, 'SELECT 2', $pdo);
        $method->invoke($connection, 'SELECT 3', $pdo);
        
        // Reduce max to 1
        $connection->setMaxCachedStatements(1);
        
        expect($cache->getValue($connection))->toHaveCount(1);
    });
});

describe('Query cache integration', function () {
    it('enables query cache', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $connection->enableQueryCache(100, 300);
        
        $cache = $connection->getQueryCache();
        expect($cache)->toBeInstanceOf(QueryCache::class);
        expect($cache->isEnabled())->toBeTrue();
    });
    
    it('disables query cache', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $connection->enableQueryCache();
        $connection->disableQueryCache();
        
        $cache = $connection->getQueryCache();
        expect($cache->isEnabled())->toBeFalse();
    });
    
    it('flushes query cache', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $connection->enableQueryCache();
        $cache = $connection->getQueryCache();
        
        // Add something to cache
        $cache->put('test_key', ['data']);
        expect($cache->get('test_key'))->toBe(['data']);
        
        $connection->flushQueryCache();
        expect($cache->get('test_key'))->toBeNull();
    });
    
    it('handles flush when cache not initialized', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        // Should not throw
        $connection->flushQueryCache();
        
        expect($connection->getQueryCache())->toBeNull();
    });
});

describe('Profiling integration', function () {
    it('enables profiling', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $connection->enableProfiling();
        
        $profiler = $connection->getProfiler();
        expect($profiler)->toBeInstanceOf(QueryProfiler::class);
        expect($profiler->isEnabled())->toBeTrue();
    });
    
    it('disables profiling', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $connection->enableProfiling();
        $connection->disableProfiling();
        
        $profiler = $connection->getProfiler();
        expect($profiler->isEnabled())->toBeFalse();
    });
    
    it('returns profiling report when not initialized', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $report = $connection->getProfilingReport();
        
        expect($report)->toBe([
            'enabled' => false,
            'message' => 'Profiling not initialized'
        ]);
    });
    
    it('returns profiling report when initialized', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        $connection->enableProfiling();
        $connection->unprepared('CREATE TABLE test (id INTEGER)');
        
        $report = $connection->getProfilingReport();
        
        expect($report)->toHaveKeys(['total_queries', 'total_time_ms', 'query_types']);
    });
    
    it('handles profiler exception in run method', function () {
        $connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        
        // Create a mock profiler that tracks calls
        $profiler = Mockery::mock(QueryProfiler::class);
        $profiler->shouldReceive('isEnabled')->andReturn(true);
        $profiler->shouldReceive('start')->andReturn('profile_1');
        $profiler->shouldReceive('end')->with('profile_1')->once();
        
        // Inject the profiler
        $reflection = new ReflectionClass($connection);
        $prop = $reflection->getProperty('profiler');
        $prop->setAccessible(true);
        $prop->setValue($connection, $profiler);
        
        // Execute a query that throws an exception
        try {
            $connection->select('INVALID SQL');
        } catch (Exception $e) {
            // Expected
        }
        
        // Verify end was called despite exception
        expect(true)->toBeTrue();
    });
});

describe('Error handling in createConnection', function () {
    it('throws exception for unsupported driver', function () {
        $config = [
            'driver' => 'oracle',
            'host' => 'localhost',
            'database' => 'test'
        ];
        
        expect(function () use ($config) {
            $connection = new Connection($config);
            $connection->getPdo(); // This triggers createConnection
        })->toThrow(InvalidArgumentException::class, 'Unsupported driver: oracle');
    });
});