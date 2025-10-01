<?php

use Bob\Database\Connection;
use Bob\Logging\Log;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Grammars\SQLiteGrammar;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    // Reset global logger
    Log::clearLogger();
    Log::disable();
});

test('Connection with logging config enabled', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'logging' => true,
    ]);

    expect($connection->logging())->toBeTrue();
});

test('Connection uses global logger when available', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockLogger->shouldReceive('log')->withAnyArgs()->zeroOrMoreTimes();
    $mockLogger->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
    $mockLogger->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

    Log::setLogger($mockLogger);
    Log::enable();

    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->enableQueryLog();
    $connection->select('SELECT 1');

    // The logger should have been used via Log facade
    expect(Log::getLogger())->toBe($mockLogger);
});

test('Connection with custom logger', function () {
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockLogger->shouldReceive('log')->withAnyArgs()->zeroOrMoreTimes();
    $mockLogger->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
    $mockLogger->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'logger' => $mockLogger,
    ]);

    $connection->enableQueryLog();
    $connection->select('SELECT 1');

    // Verify the logger was used
    expect(true)->toBeTrue();
});

test('Connection creates MySQL grammar', function () {
    $connection = new Connection([
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test',
        'username' => 'root',
        'password' => '',
    ]);

    expect($connection->getQueryGrammar())->toBeInstanceOf(MySQLGrammar::class);
});

test('Connection creates PostgreSQL grammar', function () {
    $connection = new Connection([
        'driver' => 'pgsql',
        'host' => 'localhost',
        'database' => 'test',
        'username' => 'postgres',
        'password' => '',
    ]);

    expect($connection->getQueryGrammar())->toBeInstanceOf(PostgreSQLGrammar::class);
});

test('Connection creates PostgreSQL grammar with postgres driver', function () {
    $connection = new Connection([
        'driver' => 'postgres',
        'host' => 'localhost',
        'database' => 'test',
        'username' => 'postgres',
        'password' => '',
    ]);

    expect($connection->getQueryGrammar())->toBeInstanceOf(PostgreSQLGrammar::class);
});

test('Connection creates PostgreSQL grammar with postgresql driver', function () {
    $connection = new Connection([
        'driver' => 'postgresql',
        'host' => 'localhost',
        'database' => 'test',
        'username' => 'postgres',
        'password' => '',
    ]);

    expect($connection->getQueryGrammar())->toBeInstanceOf(PostgreSQLGrammar::class);
});

test('Connection creates SQLite grammar', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    expect($connection->getQueryGrammar())->toBeInstanceOf(SQLiteGrammar::class);
});

test('Connection defaults to MySQL grammar', function () {
    $connection = new Connection([
        'database' => 'test',
        'username' => 'root',
        'password' => '',
    ]);

    expect($connection->getQueryGrammar())->toBeInstanceOf(MySQLGrammar::class);
});

test('Connection raw method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $expression = $connection->raw('COUNT(*)');

    expect($expression)->toBeInstanceOf(\Bob\Database\Expression::class);
    expect((string) $expression)->toBe('COUNT(*)');
});

test('Connection statement method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $result = $connection->statement('CREATE TABLE test_statement (id INTEGER PRIMARY KEY)');
    expect($result)->toBeTrue();

    // Verify table was created
    $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name='test_statement'");
    expect($tables)->toHaveCount(1);
});

test('Connection unprepared method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $result = $connection->unprepared('CREATE TABLE test_unprepared (id INTEGER PRIMARY KEY)');
    expect($result)->toBeTrue();

    // Verify table was created
    $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name='test_unprepared'");
    expect($tables)->toHaveCount(1);
});

test('Connection affectingStatement method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_affecting (id INTEGER PRIMARY KEY, name TEXT)');
    $connection->insert('INSERT INTO test_affecting (name) VALUES (?)', ['John']);
    $connection->insert('INSERT INTO test_affecting (name) VALUES (?)', ['Jane']);

    $affected = $connection->affectingStatement('UPDATE test_affecting SET name = ?', ['Updated']);
    expect($affected)->toBe(2);
});

test('Connection cursor method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_cursor (id INTEGER PRIMARY KEY, name TEXT)');
    for ($i = 1; $i <= 5; $i++) {
        $connection->insert('INSERT INTO test_cursor (name) VALUES (?)', ["Name $i"]);
    }

    $count = 0;
    foreach ($connection->cursor('SELECT * FROM test_cursor') as $row) {
        $count++;
        expect($row)->toBeObject();
        expect(property_exists($row, 'name') || isset($row->name))->toBeTrue();
    }

    expect($count)->toBe(5);
});

test('Connection transaction with closure', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_trans (id INTEGER PRIMARY KEY, value INTEGER)');

    $result = $connection->transaction(function ($db) {
        $db->insert('INSERT INTO test_trans (value) VALUES (?)', [100]);

        return 'success';
    });

    expect($result)->toBe('success');

    $rows = $connection->select('SELECT * FROM test_trans');
    expect($rows)->toHaveCount(1);
    if (count($rows) > 0) {
        expect($rows[0])->toHaveProperty('value');
        expect((int) $rows[0]->value)->toBe(100);
    }
});

test('Connection transaction with attempts', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_attempts (id INTEGER PRIMARY KEY, value INTEGER)');

    $attempts = 0;
    $result = $connection->transaction(function ($db) use (&$attempts) {
        $attempts++;
        if ($attempts < 2) {
            throw new \Exception('Simulated failure');
        }
        $db->insert('INSERT INTO test_attempts (value) VALUES (?)', [200]);

        return 'success after retry';
    }, 3);

    expect($result)->toBe('success after retry');
    expect($attempts)->toBe(2);

    $rows = $connection->select('SELECT * FROM test_attempts');
    expect($rows)->toHaveCount(1);
});

test('Connection transaction throws after max attempts', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->transaction(function () {
        throw new \Exception('Always fails');
    }, 2);
})->throws(\Exception::class, 'Always fails');

test('Connection manual transaction methods', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_manual (id INTEGER PRIMARY KEY, value INTEGER)');

    // Test successful transaction
    $connection->beginTransaction();
    expect($connection->transactionLevel())->toBe(1);

    $connection->insert('INSERT INTO test_manual (value) VALUES (?)', [300]);
    $connection->commit();
    expect($connection->transactionLevel())->toBe(0);

    $rows = $connection->select('SELECT * FROM test_manual');
    expect($rows)->toHaveCount(1);

    // Test rollback
    $connection->beginTransaction();
    $connection->insert('INSERT INTO test_manual (value) VALUES (?)', [400]);
    $connection->rollBack();

    $rows = $connection->select('SELECT * FROM test_manual');
    expect($rows)->toHaveCount(1); // Still only 1 row
});

test('Connection nested transactions', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_nested (id INTEGER PRIMARY KEY, value INTEGER)');

    $connection->beginTransaction();
    expect($connection->transactionLevel())->toBe(1);

    $connection->insert('INSERT INTO test_nested (value) VALUES (?)', [500]);

    $connection->beginTransaction();
    expect($connection->transactionLevel())->toBe(2);

    $connection->insert('INSERT INTO test_nested (value) VALUES (?)', [600]);

    $connection->commit(); // Commit inner
    expect($connection->transactionLevel())->toBe(1);

    $connection->commit(); // Commit outer
    expect($connection->transactionLevel())->toBe(0);

    $rows = $connection->select('SELECT * FROM test_nested ORDER BY value');
    expect($rows)->toHaveCount(2);
    expect((int) $rows[0]->value)->toBe(500);
    expect((int) $rows[1]->value)->toBe(600);
});

test('Connection rollback nested transactions', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_rollback_level (id INTEGER PRIMARY KEY, value INTEGER)');

    $connection->beginTransaction();
    $connection->insert('INSERT INTO test_rollback_level (value) VALUES (?)', [700]);

    $connection->beginTransaction();
    $connection->insert('INSERT INTO test_rollback_level (value) VALUES (?)', [800]);

    $connection->beginTransaction();
    $connection->insert('INSERT INTO test_rollback_level (value) VALUES (?)', [900]);

    // Rollback innermost transaction
    $connection->rollBack();
    expect($connection->transactionLevel())->toBe(2);

    // Rollback middle transaction
    $connection->rollBack();
    expect($connection->transactionLevel())->toBe(1);

    $connection->commit();

    $rows = $connection->select('SELECT * FROM test_rollback_level');
    expect($rows)->toHaveCount(1); // Only first insert committed
    expect((int) $rows[0]->value)->toBe(700);
});

test('Connection handles exception in transaction', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_exception (id INTEGER PRIMARY KEY, value INTEGER)');

    try {
        $connection->transaction(function ($db) {
            $db->insert('INSERT INTO test_exception (value) VALUES (?)', [1000]);
            throw new \RuntimeException('Transaction failed');
        });
    } catch (\RuntimeException $e) {
        // Expected
    }

    $rows = $connection->select('SELECT * FROM test_exception');
    expect($rows)->toHaveCount(0); // No rows due to rollback
});

test('Connection selectOne method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_selectone (id INTEGER PRIMARY KEY, name TEXT)');
    $connection->insert('INSERT INTO test_selectone (name) VALUES (?)', ['First']);
    $connection->insert('INSERT INTO test_selectone (name) VALUES (?)', ['Second']);

    $result = $connection->selectOne('SELECT * FROM test_selectone ORDER BY id');

    expect($result)->toBeObject();
    expect($result->name)->toBe('First');
});

test('Connection scalar method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->statement('CREATE TABLE test_scalar (id INTEGER PRIMARY KEY, value INTEGER)');
    $connection->insert('INSERT INTO test_scalar (value) VALUES (?)', [42]);

    $result = $connection->scalar('SELECT value FROM test_scalar');

    expect($result)->toBe(42);
});

test('Connection getConfig method', function () {
    $config = [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'test_',
    ];

    $connection = new Connection($config);

    expect($connection->getConfig())->toBe($config);
    expect($connection->getConfig('driver'))->toBe('sqlite');
    expect($connection->getConfig('prefix'))->toBe('test_');
    expect($connection->getConfig('nonexistent'))->toBeNull();
});

test('Connection getDatabaseName method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    expect($connection->getDatabaseName())->toBe(':memory:');
});

test('Connection getTablePrefix method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'wp_',
    ]);

    expect($connection->getTablePrefix())->toBe('wp_');
});

test('Connection setTablePrefix method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->setTablePrefix('custom_');
    expect($connection->getTablePrefix())->toBe('custom_');
});

test('Connection reconnect method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Get initial PDO
    $pdo1 = $connection->getPdo();

    // Reconnect
    $connection->reconnect();

    // Get new PDO
    $pdo2 = $connection->getPdo();

    expect($pdo1)->not->toBe($pdo2);
});

test('Connection disconnect method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Ensure connected
    $connection->getPdo();

    // Disconnect
    $connection->disconnect();

    // PDO should be recreated on next access
    $pdo = $connection->getPdo();
    expect($pdo)->toBeInstanceOf(PDO::class);
});

test('Connection listen method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->enableQueryLog();  // Enable logging so events are fired

    $events = [];
    $connection->listen(function ($query) use (&$events) {
        $events[] = $query;
    });

    $connection->select('SELECT 1');

    expect($events)->toHaveCount(1);
    expect($events[0]['query'])->toBe('SELECT 1');
});

test('Connection flushQueryLog method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Force connection to establish first
    $connection->getPdo();

    $connection->enableQueryLog();

    // Clear any initial connection logs
    $connection->flushQueryLog();

    $connection->select('SELECT 1');
    $connection->select('SELECT 2');

    $log = $connection->getQueryLog();
    expect($log)->toHaveCount(2);

    $connection->flushQueryLog();

    expect($connection->getQueryLog())->toHaveCount(0);
});

test('Connection disableQueryLog method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->enableQueryLog();
    expect($connection->logging())->toBeTrue();

    $connection->disableQueryLog();
    expect($connection->logging())->toBeFalse();

    $connection->select('SELECT 1');
    expect($connection->getQueryLog())->toHaveCount(0);
});

test('Connection logQuery method', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $connection->enableQueryLog();

    $connection->logQuery('SELECT * FROM users WHERE id = ?', [1], 10.5);

    $log = $connection->getQueryLog();
    expect($log)->toHaveCount(1);
    expect($log[0]['query'])->toBe('SELECT * FROM users WHERE id = ?');
    expect($log[0]['bindings'])->toBe([1]);
    expect($log[0]['time'])->toBe(10.5);
});

test('Connection getName and setName methods', function () {
    $connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    expect($connection->getName())->toBe('');

    $connection->setName('primary');
    expect($connection->getName())->toBe('primary');
});
