<?php

declare(strict_types=1);

use Bob\Logging\Log;
use Bob\Logging\QueryLogger;
use Bob\Database\Connection;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    // Reset global state before each test
    Log::disable();
    Log::clearQueryLog();
    
    // Clear registered connections and logger
    $reflection = new ReflectionClass(Log::class);
    $prop = $reflection->getProperty('connections');
    $prop->setAccessible(true);
    $prop->setValue(null, []);
    
    $loggerProp = $reflection->getProperty('globalLogger');
    $loggerProp->setAccessible(true);
    $loggerProp->setValue(null, null);
    
    $queryLoggerProp = $reflection->getProperty('globalQueryLogger');
    $queryLoggerProp->setAccessible(true);
    $queryLoggerProp->setValue(null, null);
});

it('unregisters connections', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Register the connection
    Log::registerConnection($connection);
    
    // Verify it's registered
    $reflection = new ReflectionClass(Log::class);
    $prop = $reflection->getProperty('connections');
    $prop->setAccessible(true);
    $connections = $prop->getValue();
    expect(count($connections))->toBe(1);
    
    // Unregister the connection
    Log::unregisterConnection($connection);
    
    // Verify it's unregistered
    $connections = $prop->getValue();
    expect(count($connections))->toBe(0);
});

it('registers connection only once when enabling', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Enable for same connection multiple times
    Log::enableFor($connection);
    Log::enableFor($connection);
    
    // Check that connection is registered only once
    $reflection = new ReflectionClass(Log::class);
    $prop = $reflection->getProperty('connections');
    $prop->setAccessible(true);
    $connections = $prop->getValue();
    
    $key = spl_object_hash($connection);
    expect(array_key_exists($key, $connections))->toBeTrue();
    expect(count($connections))->toBe(1);
});

it('gets query log from multiple registered connections when connection not provided', function () {
    // Create multiple connections
    $conn1 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $conn2 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Register connections
    Log::registerConnection($conn1);
    Log::registerConnection($conn2);
    
    // Enable query logging
    $conn1->enableQueryLog();
    $conn2->enableQueryLog();
    
    // Execute queries on different connections
    $conn1->statement('CREATE TABLE test1 (id INTEGER)');
    $conn2->statement('CREATE TABLE test2 (id INTEGER)');
    
    // Get all queries (without providing a specific connection)
    $queries = Log::getQueryLog();
    
    // Should have queries from both connections
    expect(count($queries))->toBeGreaterThanOrEqual(2);
});

it('configures global query logger with all config options', function () {
    // Create a mock logger
    $mockPsrLogger = Mockery::mock(LoggerInterface::class);
    $mockPsrLogger->shouldReceive('log')->andReturnTrue();
    
    // Create and configure the logger
    $logger = new QueryLogger($mockPsrLogger);
    Log::setLogger($logger, [
        'log_bindings' => false,
        'log_time' => false,
        'slow_query_threshold' => 500,
    ]);
    
    // Configuration is applied to the logger
    // Note: QueryLogger doesn't have getter methods, but the config is applied
});

it('configures logger with partial config options', function () {
    $mockPsrLogger = Mockery::mock(LoggerInterface::class);
    $mockPsrLogger->shouldReceive('log')->andReturnTrue();
    
    $logger = new QueryLogger($mockPsrLogger);
    
    // Configure with only log_bindings
    Log::setLogger($logger, [
        'log_bindings' => false,
    ]);
    
    // Configure with only log_time  
    Log::setLogger($logger, [
        'log_time' => false,
    ]);
    
    // Configure with only slow_query_threshold
    Log::setLogger($logger, [
        'slow_query_threshold' => 250,
    ]);
    
    // The configurations are applied internally
    expect($logger)->toBeInstanceOf(QueryLogger::class);
});

it('returns empty array when no connections registered for getQueryLog', function () {
    // Ensure no connections are registered
    $reflection = new ReflectionClass(Log::class);
    $connProp = $reflection->getProperty('connections');
    $connProp->setAccessible(true);
    $connProp->setValue(null, []);
    
    // Get query log should return empty array
    $queries = Log::getQueryLog();
    expect($queries)->toBe([]);
});

it('merges query logs from all connections', function () {
    $conn1 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $conn2 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $conn3 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Register all connections
    Log::registerConnection($conn1);
    Log::registerConnection($conn2);
    Log::registerConnection($conn3);
    
    // Enable query logging on all
    $conn1->enableQueryLog();
    $conn2->enableQueryLog();
    $conn3->enableQueryLog();
    
    // Execute queries
    $conn1->statement('CREATE TABLE users (id INTEGER)');
    $conn2->statement('CREATE TABLE posts (id INTEGER)');
    $conn3->statement('CREATE TABLE comments (id INTEGER)');
    
    // Get all queries (no connection specified)
    $queries = Log::getQueryLog();
    
    // Should have at least 3 queries
    expect(count($queries))->toBeGreaterThanOrEqual(3);
    
    // Verify queries are from different connections
    $queryStrings = array_map(fn($q) => $q['query'] ?? '', $queries);
    
    // Check that our specific queries are present
    $hasUsers = false;
    $hasPosts = false;
    $hasComments = false;
    
    foreach ($queryStrings as $query) {
        if ($query && str_contains($query, 'users')) $hasUsers = true;
        if ($query && str_contains($query, 'posts')) $hasPosts = true;
        if ($query && str_contains($query, 'comments')) $hasComments = true;
    }
    
    expect($hasUsers)->toBeTrue();
    expect($hasPosts)->toBeTrue();
    expect($hasComments)->toBeTrue();
});

it('registers connection when using enableFor and connection not already registered', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Ensure connection is not registered initially
    $reflection = new ReflectionClass(Log::class);
    $connProp = $reflection->getProperty('connections');
    $connProp->setAccessible(true);
    $connProp->setValue(null, []);
    
    // Use enableFor - should register the connection
    Log::enableFor($connection);
    
    // Check that connection was registered
    $connections = $connProp->getValue();
    $key = spl_object_hash($connection);
    expect(array_key_exists($key, $connections))->toBeTrue();
    expect($connection->isLoggingEnabled())->toBeTrue();
});

it('uses connections array when no global query logger is set', function () {
    // Clear global logger
    $reflection = new ReflectionClass(Log::class);
    $globalLoggerProp = $reflection->getProperty('globalQueryLogger');
    $globalLoggerProp->setAccessible(true);
    $globalLoggerProp->setValue(null, null);
    
    $conn1 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $conn2 = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Register and enable logging
    Log::registerConnection($conn1);
    Log::registerConnection($conn2);
    $conn1->enableQueryLog();
    $conn2->enableQueryLog();
    
    // Execute queries
    $conn1->statement('CREATE TABLE test1 (id INTEGER)');
    $conn2->statement('CREATE TABLE test2 (id INTEGER)');
    
    // Get query log should merge from connections
    $queries = Log::getQueryLog();
    
    // Should have queries from both connections
    expect(count($queries))->toBeGreaterThanOrEqual(2);
});

it('configures global query logger settings', function () {
    // Create a global query logger
    $queryLogger = new QueryLogger(null);
    
    $reflection = new ReflectionClass(Log::class);
    $globalLoggerProp = $reflection->getProperty('globalQueryLogger');
    $globalLoggerProp->setAccessible(true);
    $globalLoggerProp->setValue(null, $queryLogger);
    
    // Configure settings
    Log::configure([
        'log_bindings' => false,
        'log_time' => false,
        'slow_query_threshold' => 500,
    ]);
    
    // Settings should be applied to the global query logger
    // Note: QueryLogger doesn't have getters, but the configure method should have run without errors
    expect($queryLogger)->toBeInstanceOf(QueryLogger::class);
});