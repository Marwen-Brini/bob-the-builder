<?php

use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Builder;
use Bob\Database\Connection;
use Bob\Database\Expression;

beforeEach(function () {
    $this->grammar = new MySQLGrammar();
    $this->processor = Mockery::mock(\Bob\Query\Processor::class);
    $this->connection = Mockery::mock(Connection::class);
    $this->connection->shouldReceive('getTablePrefix')->andReturn('');
    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
    $this->builder = new Builder($this->connection);
});

it('wraps values with backticks', function () {
    $this->builder->from('users');
    $sql = $this->grammar->compileSelect($this->builder);
    expect($sql)->toBe('select * from `users`');
});

it('handles special characters in identifiers', function () {
    $this->builder->from('table`with`backticks');
    $sql = $this->grammar->compileSelect($this->builder);
    expect($sql)->toBe('select * from `table``with``backticks`');
});

it('compiles insert or ignore', function () {
    $this->builder->from('users');
    $sql = $this->grammar->compileInsertOrIgnore($this->builder, [
        ['name' => 'John', 'email' => 'john@example.com']
    ]);
    expect($sql)->toBe('insert ignore into `users` (`name`, `email`) values (?, ?)');
});

it('compiles json length operator', function () {
    $this->builder->from('users')->whereRaw('json_length(`data`->\'$.items\') > ?', [5]);
    $sql = $this->grammar->compileSelect($this->builder);
    expect($sql)->toContain('json_length');
});

it('wraps json field and path correctly', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('wrapJsonFieldAndPath');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->grammar, 'data->items');
    expect($result)->toBe(['`data`', ', \'$.items\'']);
    
    $result = $method->invoke($this->grammar, 'simple_column');
    expect($result)->toBe(['`simple_column`', '']);
});

it('wraps json path correctly', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('wrapJsonPath');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->grammar, 'items->0->name');
    expect($result)->toBe('\'$.items.0.name\'');
});

it('compiles json length in where clause', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('compileJsonLength');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->grammar, 'data->items', '>', '5');
    expect($result)->toBe('json_length(`data`, \'$.items\') > 5');
});

it('compiles upsert with on duplicate key update', function () {
    $this->builder->from('users');
    $values = [
        ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']
    ];
    $uniqueBy = ['id'];
    $update = ['name' => 'name', 'email' => 'email'];
    
    $sql = $this->grammar->compileUpsert($this->builder, $values, $uniqueBy, $update);
    
    expect($sql)->toContain('insert into `users`');
    expect($sql)->toContain('on duplicate key update');
    expect($sql)->toContain('`name` = values(`name`)');
    expect($sql)->toContain('`email` = values(`email`)');
});

it('compiles upsert with custom update columns', function () {
    $this->builder->from('products');
    $values = [
        ['sku' => 'ABC123', 'price' => 100, 'quantity' => 50]
    ];
    $uniqueBy = ['sku'];
    $update = ['price' => 150, 'quantity' => 75];
    
    $sql = $this->grammar->compileUpsert($this->builder, $values, $uniqueBy, $update);
    
    expect($sql)->toContain('on duplicate key update');
    expect($sql)->toContain('`price` = values(`price`)');
    expect($sql)->toContain('`quantity` = values(`quantity`)');
});

it('compiles lock for update', function () {
    $result = $this->grammar->compileLock($this->builder, true);
    expect($result)->toBe(' for update');
});

it('compiles lock in share mode', function () {
    $result = $this->grammar->compileLock($this->builder, false);
    expect($result)->toBe(' lock in share mode');
});

it('compiles custom lock string', function () {
    $customLock = ' for update skip locked';
    $result = $this->grammar->compileLock($this->builder, $customLock);
    expect($result)->toBe($customLock);
});

it('compiles random without seed', function () {
    $result = $this->grammar->compileRandom();
    expect($result)->toBe('RAND()');
});

it('compiles random with seed', function () {
    $result = $this->grammar->compileRandom('123');
    expect($result)->toBe('RAND(123)');
});

it('preserves asterisk without wrapping', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('wrapValue');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->grammar, '*');
    expect($result)->toBe('*');
});

it('supports MySQL specific operators', function () {
    $reflection = new ReflectionClass($this->grammar);
    $property = $reflection->getProperty('operators');
    $property->setAccessible(true);
    $operators = $property->getValue($this->grammar);
    
    expect($operators)->toContain('<=>')
        ->toContain('regexp')
        ->toContain('not regexp')
        ->toContain('rlike')
        ->toContain('not rlike')
        ->toContain('&')
        ->toContain('|')
        ->toContain('^')
        ->toContain('<<')
        ->toContain('>>');
});

it('compiles whereRaw with MySQL specific functions', function () {
    $this->builder->from('users')
        ->whereRaw('MATCH(name, bio) AGAINST(? IN BOOLEAN MODE)', ['search term']);
    
    $sql = $this->grammar->compileSelect($this->builder);
    expect($sql)->toContain('MATCH(name, bio) AGAINST(? IN BOOLEAN MODE)');
});

it('handles multi-level json paths', function () {
    $reflection = new ReflectionClass($this->grammar);
    $method = $reflection->getMethod('wrapJsonFieldAndPath');
    $method->setAccessible(true);
    
    $result = $method->invoke($this->grammar, 'data->deeply->nested->value');
    expect($result[0])->toBe('`data`');
    expect($result[1])->toBe(', \'$.deeply.nested.value\'');
});

it('compiles insert with multiple rows', function () {
    $this->builder->from('users');
    $values = [
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'Jane', 'email' => 'jane@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com']
    ];
    
    $sql = $this->grammar->compileInsert($this->builder, $values);
    expect($sql)->toBe('insert into `users` (`name`, `email`) values (?, ?), (?, ?), (?, ?)');
});

it('handles empty update array in upsert', function () {
    $this->builder->from('users');
    $values = [['id' => 1, 'name' => 'John']];
    $uniqueBy = ['id'];
    $update = [];
    
    $sql = $this->grammar->compileUpsert($this->builder, $values, $uniqueBy, $update);
    expect($sql)->toContain('on duplicate key update');
});