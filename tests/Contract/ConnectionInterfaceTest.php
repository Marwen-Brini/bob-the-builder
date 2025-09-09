<?php

declare(strict_types=1);

use Bob\Contracts\ConnectionInterface;
use Bob\Contracts\BuilderInterface;
use Bob\Contracts\GrammarInterface;
use Bob\Contracts\ProcessorInterface;
use Bob\Contracts\ExpressionInterface;

it('implements ConnectionInterface', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    expect($connection)->toBeInstanceOf(ConnectionInterface::class);
});

it('has PDO management methods', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    expect(method_exists($connection, 'getPdo'))->toBeTrue();
    expect(method_exists($connection, 'getName'))->toBeTrue();
    expect(method_exists($connection, 'getDatabaseName'))->toBeTrue();
    expect(method_exists($connection, 'reconnect'))->toBeTrue();
    expect(method_exists($connection, 'disconnect'))->toBeTrue();
});

it('has grammar and processor methods', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    expect(method_exists($connection, 'getQueryGrammar'))->toBeTrue();
    expect(method_exists($connection, 'setQueryGrammar'))->toBeTrue();
    expect(method_exists($connection, 'getPostProcessor'))->toBeTrue();
    expect(method_exists($connection, 'setPostProcessor'))->toBeTrue();
});

it('has table prefix methods', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    expect(method_exists($connection, 'getTablePrefix'))->toBeTrue();
    expect(method_exists($connection, 'setTablePrefix'))->toBeTrue();
});

it('has query execution methods', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    expect(method_exists($connection, 'select'))->toBeTrue();
    expect(method_exists($connection, 'insert'))->toBeTrue();
    expect(method_exists($connection, 'update'))->toBeTrue();
    expect(method_exists($connection, 'delete'))->toBeTrue();
    expect(method_exists($connection, 'statement'))->toBeTrue();
    expect(method_exists($connection, 'affectingStatement'))->toBeTrue();
    expect(method_exists($connection, 'prepareBindings'))->toBeTrue();
});

it('has transaction methods', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    expect(method_exists($connection, 'transaction'))->toBeTrue();
    expect(method_exists($connection, 'beginTransaction'))->toBeTrue();
    expect(method_exists($connection, 'commit'))->toBeTrue();
    expect(method_exists($connection, 'rollBack'))->toBeTrue();
    expect(method_exists($connection, 'transactionLevel'))->toBeTrue();
});

it('has query logging methods', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    expect(method_exists($connection, 'logQuery'))->toBeTrue();
    expect(method_exists($connection, 'enableQueryLog'))->toBeTrue();
    expect(method_exists($connection, 'disableQueryLog'))->toBeTrue();
    expect(method_exists($connection, 'logging'))->toBeTrue();
    expect(method_exists($connection, 'getQueryLog'))->toBeTrue();
    expect(method_exists($connection, 'flushQueryLog'))->toBeTrue();
});

it('has pretend method for dry run', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    expect(method_exists($connection, 'pretend'))->toBeTrue();
});

it('has table and raw methods', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    expect(method_exists($connection, 'table'))->toBeTrue();
    expect(method_exists($connection, 'raw'))->toBeTrue();
});

it('returns correct types', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    $pdo = Mockery::mock(PDO::class);
    $grammar = Mockery::mock(GrammarInterface::class);
    $processor = Mockery::mock(ProcessorInterface::class);
    $builder = Mockery::mock(BuilderInterface::class);
    $expression = Mockery::mock(ExpressionInterface::class);
    
    $connection->shouldReceive('getPdo')->andReturn($pdo);
    $connection->shouldReceive('getName')->andReturn('default');
    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn($processor);
    $connection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $connection->shouldReceive('getTablePrefix')->andReturn('wp_');
    $connection->shouldReceive('select')->andReturn([]);
    $connection->shouldReceive('insert')->andReturn(true);
    $connection->shouldReceive('update')->andReturn(5);
    $connection->shouldReceive('delete')->andReturn(3);
    $connection->shouldReceive('statement')->andReturn(true);
    $connection->shouldReceive('affectingStatement')->andReturn(10);
    $connection->shouldReceive('transactionLevel')->andReturn(0);
    $connection->shouldReceive('logging')->andReturn(false);
    $connection->shouldReceive('getQueryLog')->andReturn([]);
    $connection->shouldReceive('table')->andReturn($builder);
    $connection->shouldReceive('raw')->andReturn($expression);
    $connection->shouldReceive('prepareBindings')->andReturn([]);
    $connection->shouldReceive('pretend')->andReturn([]);
    
    expect($connection->getPdo())->toBeInstanceOf(PDO::class);
    expect($connection->getName())->toBeString();
    expect($connection->getQueryGrammar())->toBeInstanceOf(GrammarInterface::class);
    expect($connection->getPostProcessor())->toBeInstanceOf(ProcessorInterface::class);
    expect($connection->getDatabaseName())->toBeString();
    expect($connection->getTablePrefix())->toBeString();
    expect($connection->select('SELECT * FROM users'))->toBeArray();
    expect($connection->insert('INSERT INTO users VALUES (1)'))->toBeBool();
    expect($connection->update('UPDATE users SET name = ?'))->toBeInt();
    expect($connection->delete('DELETE FROM users'))->toBeInt();
    expect($connection->statement('CREATE TABLE test'))->toBeBool();
    expect($connection->affectingStatement('UPDATE users SET active = 1'))->toBeInt();
    expect($connection->transactionLevel())->toBeInt();
    expect($connection->logging())->toBeBool();
    expect($connection->getQueryLog())->toBeArray();
    expect($connection->table('users'))->toBeInstanceOf(BuilderInterface::class);
    expect($connection->raw('COUNT(*)'))->toBeInstanceOf(ExpressionInterface::class);
    expect($connection->prepareBindings([1, 'test']))->toBeArray();
    expect($connection->pretend(function() {}))->toBeArray();
});

it('transaction accepts closure and attempts', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    
    $connection->shouldReceive('transaction')
        ->with(Mockery::type(Closure::class), 1)
        ->andReturn('result');
    
    $result = $connection->transaction(function() {
        return 'result';
    }, 1);
    
    expect($result)->toBe('result');
});