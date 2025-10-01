<?php

declare(strict_types=1);

use Bob\Contracts\BuilderInterface;
use Bob\Database\Connection;
use Bob\Database\Migrations\DatabaseSeeder;
use Bob\Database\Migrations\Seeder;

afterEach(function () {
    \Mockery::close();
});
test('seeder can set and get connection', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);

    $seeder->setConnection($connection);

    // Use reflection to check the protected property
    $reflection = new \ReflectionClass($seeder);
    $connectionProperty = $reflection->getProperty('connection');
    $connectionProperty->setAccessible(true);

    expect($connectionProperty->getValue($seeder))->toBe($connection);
});

// CONVERTED TEST 2: testSetCommand
test('seeder can set command', function () {
    $seeder = new TestSeeder;
    $command = \Mockery::mock();
    $command->shouldReceive('info')->andReturn(null);

    $seeder->setCommand($command);

    // Use reflection to check the protected property
    $reflection = new \ReflectionClass($seeder);
    $commandProperty = $reflection->getProperty('command');
    $commandProperty->setAccessible(true);

    expect($commandProperty->getValue($seeder))->toBe($command);
});

// CONVERTED TEST 3: testDbWithExistingConnection
test('db method with existing connection', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);

    $seeder->setConnection($connection);

    expect($seeder->testDb())->toBe($connection);
});

// CONVERTED TEST 4: testDbWithoutConnection
test('db method without connection should get default', function () {
    $seeder = new TestSeeder;

    // Since Connection::getDefaultConnection() doesn't exist yet,
    // we test that it throws an error when no connection is set
    expect(fn () => $seeder->testDb())->toThrow(\Error::class);
});

// CONVERTED TEST 5: testTable
test('table method', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);
    $builder = \Mockery::mock(BuilderInterface::class);

    $connection->shouldReceive('table')->with('users')->andReturn($builder);
    $seeder->setConnection($connection);

    $result = $seeder->testTable('users');

    expect($result)->toBe($builder);
});

// CONVERTED TEST 6: testCallWithSingleClass
test('call method with single class', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);
    $command = \Mockery::mock();

    $command->shouldReceive('info')
        ->with('Seeding: AnotherTestSeeder')
        ->once();
    $command->shouldReceive('info')
        ->with(\Mockery::pattern('/Seeded:  AnotherTestSeeder \(\d+(\.\d+)?ms\)/'))
        ->once();

    $seeder->setConnection($connection);
    $seeder->setCommand($command);

    $seeder->call(AnotherTestSeeder::class);

    // Verify that AnotherTestSeeder was called
    expect(AnotherTestSeeder::$wasCalled)->toBeTrue();
});

// CONVERTED TEST 7: testCallWithArrayOfClasses
test('call method with array of classes', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);
    $command = \Mockery::mock();

    $command->shouldReceive('info')->times(4); // 2 seeders × 2 messages each

    $seeder->setConnection($connection);
    $seeder->setCommand($command);

    // Reset static flags
    AnotherTestSeeder::$wasCalled = false;
    ThirdTestSeeder::$wasCalled = false;

    $seeder->call([
        AnotherTestSeeder::class,
        ThirdTestSeeder::class,
    ]);

    expect(AnotherTestSeeder::$wasCalled)->toBeTrue();
    expect(ThirdTestSeeder::$wasCalled)->toBeTrue();
});

// CONVERTED TEST 8: testCallSilently
test('call method silently no command output', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);
    $command = \Mockery::mock();

    // Command should NOT receive any info calls
    $command->shouldNotReceive('info');

    $seeder->setConnection($connection);
    $seeder->setCommand($command);

    // Reset static flag
    AnotherTestSeeder::$wasCalled = false;

    $seeder->call(AnotherTestSeeder::class, true);

    expect(AnotherTestSeeder::$wasCalled)->toBeTrue();
});

// CONVERTED TEST 9: testCallSilentMethod
test('callSilent method wrapper for call with silent true', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);
    $command = \Mockery::mock();

    // Command should NOT receive any info calls
    $command->shouldNotReceive('info');

    $seeder->setConnection($connection);
    $seeder->setCommand($command);

    // Reset static flag
    AnotherTestSeeder::$wasCalled = false;

    $seeder->callSilent(AnotherTestSeeder::class);

    expect(AnotherTestSeeder::$wasCalled)->toBeTrue();
});

// CONVERTED TEST 10: testCallWithoutCommand
test('call without command no output', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);

    $seeder->setConnection($connection);
    // No command set

    // Reset static flag
    AnotherTestSeeder::$wasCalled = false;

    $seeder->call(AnotherTestSeeder::class);

    expect(AnotherTestSeeder::$wasCalled)->toBeTrue();
});

// CONVERTED TEST 11: testResolveValidClass
test('resolve method with valid class', function () {
    $seeder = new TestSeeder;

    $resolved = $seeder->testResolve(AnotherTestSeeder::class);

    expect($resolved)->toBeInstanceOf(AnotherTestSeeder::class);
});

// CONVERTED TEST 12: testResolveInvalidClass
test('resolve method with invalid class', function () {
    $seeder = new TestSeeder;

    expect(fn () => $seeder->testResolve('NonExistentSeeder'))
        ->toThrow(\InvalidArgumentException::class, 'Seeder class [NonExistentSeeder] does not exist.');
});

// CONVERTED TEST 13: testDatabaseSeederRun
test('DatabaseSeeder run method', function () {
    $seeder = new DatabaseSeeder;

    // Should not throw any exceptions (empty implementation)
    $seeder->run();

    expect(true)->toBeTrue(); // If we get here, the test passed
});

// CONVERTED TEST 14: testSeederPropagatesConnectionAndCommand
test('seeder propagates connection and command to called seeders', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);
    $command = \Mockery::mock();

    $command->shouldReceive('info')->times(2);

    $seeder->setConnection($connection);
    $seeder->setCommand($command);

    // Reset static flag
    AnotherTestSeeder::$wasCalled = false;
    AnotherTestSeeder::$receivedConnection = null;
    AnotherTestSeeder::$receivedCommand = null;

    $seeder->call(AnotherTestSeeder::class);

    expect(AnotherTestSeeder::$wasCalled)->toBeTrue();
    expect(AnotherTestSeeder::$receivedConnection)->toBe($connection);
    expect(AnotherTestSeeder::$receivedCommand)->toBe($command);
});

// CONVERTED TEST 15: testCallTimingFunctionality
test('call timing functionality', function () {
    $seeder = new TestSeeder;
    $connection = \Mockery::mock(Connection::class);
    $command = \Mockery::mock();

    $command->shouldReceive('info')
        ->with('Seeding: SlowTestSeeder')
        ->once();
    $command->shouldReceive('info')
        ->with(\Mockery::pattern('/Seeded:  SlowTestSeeder \(\d+(\.\d+)?ms\)/'))
        ->once();

    $seeder->setConnection($connection);
    $seeder->setCommand($command);

    $seeder->call(SlowTestSeeder::class);

    expect(SlowTestSeeder::$wasCalled)->toBeTrue();
});

// PHPUNIT CLASS COMMENTED OUT - ALL TESTS CONVERTED TO PEST
// class SeederTest extends TestCase
// {
//     protected function tearDown(): void
//     {
//         \Mockery::close();
//         parent::tearDown();
//     }

// /**
//  * Test seeder can set and get connection
//  */
// public function testSetConnection()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);

//     $seeder->setConnection($connection);

//     // Use reflection to check the protected property
//     $reflection = new \ReflectionClass($seeder);
//     $connectionProperty = $reflection->getProperty('connection');
//     $connectionProperty->setAccessible(true);

//     $this->assertSame($connection, $connectionProperty->getValue($seeder));
// }

// /**
//  * Test seeder can set command
//  */
// public function testSetCommand()
// {
//     $seeder = new TestSeeder();
//     $command = \Mockery::mock();
//     $command->shouldReceive('info')->andReturn(null);

//     $seeder->setCommand($command);

//     // Use reflection to check the protected property
//     $reflection = new \ReflectionClass($seeder);
//     $commandProperty = $reflection->getProperty('command');
//     $commandProperty->setAccessible(true);

//     $this->assertSame($command, $commandProperty->getValue($seeder));
// }

// /**
//  * Test db() method with existing connection
//  */
// public function testDbWithExistingConnection()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);

//     $seeder->setConnection($connection);

//     $this->assertSame($connection, $seeder->testDb());
// }

// /**
//  * Test db() method without connection (should get default)
//  */
// public function testDbWithoutConnection()
// {
//     $seeder = new TestSeeder();

//     // Since Connection::getDefaultConnection() doesn't exist yet,
//     // we test that it throws an error when no connection is set
//     $this->expectException(\Error::class);
//     $seeder->testDb();
// }

// /**
//  * Test table() method
//  */
// public function testTable()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);
//     $builder = \Mockery::mock(BuilderInterface::class);

//     $connection->shouldReceive('table')->with('users')->andReturn($builder);
//     $seeder->setConnection($connection);

//     $result = $seeder->testTable('users');

//     $this->assertSame($builder, $result);
// }

// /**
//  * Test call() method with single class
//  */
// public function testCallWithSingleClass()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);
//     $command = \Mockery::mock();

//     $command->shouldReceive('info')
//         ->with('Seeding: Tests\Unit\Database\Migrations\AnotherTestSeeder')
//         ->once();
//     $command->shouldReceive('info')
//         ->with(\Mockery::pattern('/Seeded:  Tests\\\\Unit\\\\Database\\\\Migrations\\\\AnotherTestSeeder \(\d+(\.\d+)?ms\)/'))
//         ->once();

//     $seeder->setConnection($connection);
//     $seeder->setCommand($command);

//     $seeder->call(AnotherTestSeeder::class);

//     // Verify that AnotherTestSeeder was called
//     $this->assertTrue(AnotherTestSeeder::$wasCalled);
// }

// /**
//  * Test call() method with array of classes
//  */
// public function testCallWithArrayOfClasses()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);
//     $command = \Mockery::mock();

//     $command->shouldReceive('info')->times(4); // 2 seeders × 2 messages each

//     $seeder->setConnection($connection);
//     $seeder->setCommand($command);

//     // Reset static flags
//     AnotherTestSeeder::$wasCalled = false;
//     ThirdTestSeeder::$wasCalled = false;

//     $seeder->call([
//         AnotherTestSeeder::class,
//         ThirdTestSeeder::class
//     ]);

//     $this->assertTrue(AnotherTestSeeder::$wasCalled);
//     $this->assertTrue(ThirdTestSeeder::$wasCalled);
// }

// /**
//  * Test call() method silently (no command output)
//  */
// public function testCallSilently()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);
//     $command = \Mockery::mock();

//     // Command should NOT receive any info calls
//     $command->shouldNotReceive('info');

//     $seeder->setConnection($connection);
//     $seeder->setCommand($command);

//     // Reset static flag
//     AnotherTestSeeder::$wasCalled = false;

//     $seeder->call(AnotherTestSeeder::class, true);

//     $this->assertTrue(AnotherTestSeeder::$wasCalled);
// }

// /**
//  * Test callSilent() method (wrapper for call with silent=true)
//  */
// public function testCallSilentMethod()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);
//     $command = \Mockery::mock();

//     // Command should NOT receive any info calls
//     $command->shouldNotReceive('info');

//     $seeder->setConnection($connection);
//     $seeder->setCommand($command);

//     // Reset static flag
//     AnotherTestSeeder::$wasCalled = false;

//     $seeder->callSilent(AnotherTestSeeder::class);

//     $this->assertTrue(AnotherTestSeeder::$wasCalled);
// }

// /**
//  * Test call() without command (no output)
//  */
// public function testCallWithoutCommand()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);

//     $seeder->setConnection($connection);
//     // No command set

//     // Reset static flag
//     AnotherTestSeeder::$wasCalled = false;

//     $seeder->call(AnotherTestSeeder::class);

//     $this->assertTrue(AnotherTestSeeder::$wasCalled);
// }

// /**
//  * Test resolve() method with valid class
//  */
// public function testResolveValidClass()
// {
//     $seeder = new TestSeeder();

//     $resolved = $seeder->testResolve(AnotherTestSeeder::class);

//     $this->assertInstanceOf(AnotherTestSeeder::class, $resolved);
// }

// /**
//  * Test resolve() method with invalid class
//  */
// public function testResolveInvalidClass()
// {
//     $this->expectException(InvalidArgumentException::class);
//     $this->expectExceptionMessage('Seeder class [NonExistentSeeder] does not exist.');

//     $seeder = new TestSeeder();
//     $seeder->testResolve('NonExistentSeeder');
// }

// /**
//  * Test DatabaseSeeder run method
//  */
// public function testDatabaseSeederRun()
// {
//     $seeder = new DatabaseSeeder();

//     // Should not throw any exceptions (empty implementation)
//     $seeder->run();

//     $this->assertTrue(true); // If we get here, the test passed
// }

// /**
//  * Test seeder propagates connection and command to called seeders
//  */
// public function testSeederPropagatesConnectionAndCommand()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);
//     $command = \Mockery::mock();

//     $command->shouldReceive('info')->times(2);

//     $seeder->setConnection($connection);
//     $seeder->setCommand($command);

//     // Reset static flag
//     AnotherTestSeeder::$wasCalled = false;
//     AnotherTestSeeder::$receivedConnection = null;
//     AnotherTestSeeder::$receivedCommand = null;

//     $seeder->call(AnotherTestSeeder::class);

//     $this->assertTrue(AnotherTestSeeder::$wasCalled);
//     $this->assertSame($connection, AnotherTestSeeder::$receivedConnection);
//     $this->assertSame($command, AnotherTestSeeder::$receivedCommand);
// }

// /**
//  * Test timing functionality in call method
//  */
// public function testCallTimingFunctionality()
// {
//     $seeder = new TestSeeder();
//     $connection = \Mockery::mock(Connection::class);
//     $command = \Mockery::mock();

//     $command->shouldReceive('info')
//         ->with('Seeding: Tests\Unit\Database\Migrations\SlowTestSeeder')
//         ->once();
//     $command->shouldReceive('info')
//         ->with(\Mockery::pattern('/Seeded:  Tests\\\\Unit\\\\Database\\\\Migrations\\\\SlowTestSeeder \(\d+(\.\d+)?ms\)/'))
//         ->once();

//     $seeder->setConnection($connection);
//     $seeder->setCommand($command);

//     $seeder->call(SlowTestSeeder::class);

//     $this->assertTrue(SlowTestSeeder::$wasCalled);
// }
// }

/**
 * Test seeder implementation
 */
class TestSeeder extends Seeder
{
    public function run(): void
    {
        // Test implementation
    }

    // Expose protected methods for testing
    public function testDb(): Connection
    {
        return $this->db();
    }

    public function testTable(string $table)
    {
        return $this->table($table);
    }

    public function testResolve(string $class): Seeder
    {
        return $this->resolve($class);
    }
}

/**
 * Another test seeder for call testing
 */
class AnotherTestSeeder extends Seeder
{
    public static bool $wasCalled = false;

    public static ?Connection $receivedConnection = null;

    public static mixed $receivedCommand = null;

    public function run(): void
    {
        self::$wasCalled = true;
        self::$receivedConnection = $this->connection;
        self::$receivedCommand = $this->command;
    }
}

/**
 * Third test seeder for array testing
 */
class ThirdTestSeeder extends Seeder
{
    public static bool $wasCalled = false;

    public function run(): void
    {
        self::$wasCalled = true;
    }
}

/**
 * Slow test seeder for timing testing
 */
class SlowTestSeeder extends Seeder
{
    public static bool $wasCalled = false;

    public function run(): void
    {
        // Simulate some work
        usleep(1000); // 1ms
        self::$wasCalled = true;
    }
}
