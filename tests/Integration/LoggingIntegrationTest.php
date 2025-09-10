<?php

use Bob\Database\Connection;
use Bob\Logging\Log;
use Psr\Log\AbstractLogger;

beforeEach(function () {
    Log::reset();
    
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Create test table
    $this->connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    $this->connection->statement('INSERT INTO users (name, email) VALUES ("John", "john@example.com")');
    $this->connection->statement('INSERT INTO users (name, email) VALUES ("Jane", "jane@example.com")');
});

test('logs queries when globally enabled', function () {
    Log::enable();
    
    $this->connection->table('users')->where('id', 1)->get();
    
    $log = Log::getQueryLog();
    
    expect($log)->not->toBeEmpty();
    expect($log[0]['query'])->toContain('select * from "users"');
    expect($log[0]['bindings'])->toBe([1]);
});

test('does not log queries when globally disabled', function () {
    Log::disable();
    
    $this->connection->table('users')->get();
    
    $log = Log::getQueryLog();
    
    expect($log)->toBeEmpty();
});

test('logs all CRUD operations', function () {
    $this->connection->enableQueryLog();
    
    // SELECT
    $this->connection->table('users')->get();
    
    // INSERT
    $this->connection->table('users')->insert(['name' => 'Bob', 'email' => 'bob@example.com']);
    
    // UPDATE
    $this->connection->table('users')->where('id', 3)->update(['name' => 'Robert']);
    
    // DELETE
    $this->connection->table('users')->where('id', 3)->delete();
    
    $log = $this->connection->getQueryLog();
    
    expect($log)->toHaveCount(4);
    
    $queries = array_column($log, 'query');
    expect($queries[0])->toContain('select');
    expect($queries[1])->toContain('insert');
    expect($queries[2])->toContain('update');
    expect($queries[3])->toContain('delete');
});

test('logs transaction operations', function () {
    $this->connection->enableQueryLog();
    
    $this->connection->beginTransaction();
    $this->connection->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);
    $this->connection->commit();
    
    $log = $this->connection->getQueryLog();
    
    $messages = array_filter($log, fn($entry) => isset($entry['message']));
    
    expect($messages)->not->toBeEmpty();
    
    $transactionLogs = array_filter($messages, fn($entry) => str_contains($entry['message'], 'Transaction'));
    expect($transactionLogs)->toHaveCount(2);
});

test('logs rollback operations', function () {
    $this->connection->enableQueryLog();
    
    $this->connection->beginTransaction();
    $this->connection->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);
    $this->connection->rollBack();
    
    $log = $this->connection->getQueryLog();
    
    $messages = array_filter($log, fn($entry) => isset($entry['message']));
    $rollbackLogs = array_filter($messages, fn($entry) => str_contains($entry['message'], 'rolled back'));
    
    expect($rollbackLogs)->not->toBeEmpty();
});

test('logs savepoint operations', function () {
    // Skip for SQLite as it doesn't support savepoints
    if ($this->connection->getConfig('driver') === 'sqlite') {
        $this->markTestSkipped('SQLite does not support savepoints');
    }
    
    $this->connection->enableQueryLog();
    
    $this->connection->beginTransaction();
    $this->connection->beginTransaction(); // Creates savepoint
    $this->connection->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);
    $this->connection->rollBack(); // Rolls back to savepoint
    $this->connection->commit();
    
    $log = $this->connection->getQueryLog();
    
    $messages = array_filter($log, fn($entry) => isset($entry['message']));
    $savepointLogs = array_filter($messages, fn($entry) => str_contains($entry['message'], 'savepoint'));
    
    expect($savepointLogs)->not->toBeEmpty();
});

test('logs connection establishment', function () {
    Log::enable();
    
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'logging' => true,
    ]);
    
    // Force connection
    $connection->getPdo();
    
    $log = $connection->getQueryLog();
    
    $connectionLogs = array_filter($log, fn($entry) => 
        isset($entry['message']) && str_contains($entry['message'], 'connection established')
    );
    
    expect($connectionLogs)->not->toBeEmpty();
});

test('integrates with custom PSR-3 logger', function () {
    $customLogger = new class extends AbstractLogger {
        public array $logs = [];
        
        public function log($level, $message, array $context = []): void
        {
            $this->logs[] = [
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ];
        }
    };
    
    Log::setLogger($customLogger);
    Log::enable();
    
    $this->connection->table('users')->get();
    
    expect($customLogger->logs)->not->toBeEmpty();
    expect($customLogger->logs[0]['context'])->toHaveKey('query');
});

test('provides accurate query statistics', function () {
    Log::enable();
    
    // Run various queries
    $this->connection->table('users')->get();
    $this->connection->table('users')->where('id', 1)->get();
    $this->connection->table('users')->insert(['name' => 'New', 'email' => 'new@example.com']);
    $this->connection->table('users')->where('id', 3)->update(['name' => 'Updated']);
    $this->connection->table('users')->where('id', 3)->delete();
    
    $stats = Log::getStatistics();
    
    expect($stats['total_queries'])->toBe(5);
    expect($stats['queries_by_type'])->toMatchArray([
        'SELECT' => 2,
        'INSERT' => 1,
        'UPDATE' => 1,
        'DELETE' => 1,
    ]);
});

test('clears query log across all connections', function () {
    Log::enable();
    
    $connection1 = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $connection2 = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    
    $connection1->statement('CREATE TABLE test1 (id INTEGER)');
    $connection2->statement('CREATE TABLE test2 (id INTEGER)');
    
    expect(Log::getQueryLog())->not->toBeEmpty();
    
    Log::clearQueryLog();
    
    expect(Log::getQueryLog())->toBeEmpty();
    expect($connection1->getQueryLog())->toBeEmpty();
    expect($connection2->getQueryLog())->toBeEmpty();
});

test('pretend mode logs queries without executing', function () {
    $queries = $this->connection->pretend(function ($connection) {
        $connection->table('users')->insert(['name' => 'Test', 'email' => 'test@example.com']);
        $connection->table('users')->where('id', 999)->update(['name' => 'Updated']);
        $connection->table('users')->where('id', 999)->delete();
    });
    
    expect($queries)->toHaveCount(3);
    expect($queries[0]['query'])->toContain('insert');
    expect($queries[1]['query'])->toContain('update');
    expect($queries[2]['query'])->toContain('delete');
    
    // Verify data wasn't actually changed
    $count = $this->connection->table('users')->count();
    expect($count)->toBe(2);
});

test('logs execution time for queries', function () {
    $this->connection->enableQueryLog();
    
    $this->connection->table('users')->get();
    
    $log = $this->connection->getQueryLog();
    
    expect($log[0])->toHaveKey('time');
    expect($log[0]['time'])->toMatch('/\d+(\.\d+)?ms/');
});

test('connection automatically uses global logger when set', function () {
    $customLogger = new class extends AbstractLogger {
        public array $logs = [];
        
        public function log($level, $message, array $context = []): void
        {
            $this->logs[] = compact('level', 'message', 'context');
        }
    };
    
    Log::setLogger($customLogger);
    Log::enable();
    
    // Create new connection after setting global logger
    $newConnection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    $newConnection->statement('CREATE TABLE test (id INTEGER)');
    
    expect($customLogger->logs)->not->toBeEmpty();
});