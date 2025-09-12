<?php

use Bob\Query\Builder;
use Bob\Database\Connection;
use Bob\Query\Grammars\MySQLGrammar;

beforeEach(function () {
    // Clear macros before each test
    Builder::clearMacros();
});

afterEach(function () {
    // Clean up after tests
    Builder::clearMacros();
});

it('can get all registered macros', function () {
    Builder::macro('testMethod', fn() => 'test');
    Builder::macro('anotherMethod', fn() => 'another');
    
    $macros = Builder::getMacros();
    
    expect($macros)->toHaveCount(2);
    expect($macros)->toHaveKey('testMethod');
    expect($macros)->toHaveKey('anotherMethod');
});

it('can clear all macros', function () {
    Builder::macro('method1', fn() => 'one');
    Builder::macro('method2', fn() => 'two');
    
    expect(Builder::hasMacro('method1'))->toBeTrue();
    expect(Builder::hasMacro('method2'))->toBeTrue();
    
    Builder::clearMacros();
    
    expect(Builder::hasMacro('method1'))->toBeFalse();
    expect(Builder::hasMacro('method2'))->toBeFalse();
    expect(Builder::getMacros())->toBeEmpty();
});

it('handles static macro calls', function () {
    Builder::macro('staticTest', function() {
        return 'static result';
    });
    
    $result = Builder::staticTest();
    expect($result)->toBe('static result');
});

it('throws exception for undefined static methods', function () {
    expect(fn() => Builder::undefinedStaticMethod())
        ->toThrow(BadMethodCallException::class, 'Method Bob\Query\Builder::undefinedStaticMethod does not exist.');
});

it('binds closures to correct context for instance calls', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getQueryGrammar')->andReturn(new MySQLGrammar());
    $connection->shouldReceive('getPostProcessor')->andReturn(new \Bob\Query\Processor());
    
    $builder = new Builder($connection);
    
    Builder::macro('getBuilderTable', function() {
        return $this->from;
    });
    
    $builder->from('users');
    expect($builder->getBuilderTable())->toBe('users');
});

it('binds closures to null for static calls', function () {
    Builder::macro('staticMacro', function() {
        // In static context, the closure is bound to null
        // but can still access static properties
        return 'static result';
    });
    
    $result = Builder::staticMacro();
    expect($result)->toBe('static result');
});

it('handles closure macros properly', function () {
    // Test that closure macros are properly bound
    Builder::macro('customTest', function($value) {
        return "custom: $value";
    });
    
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getQueryGrammar')->andReturn(new MySQLGrammar());
    $connection->shouldReceive('getPostProcessor')->andReturn(new \Bob\Query\Processor());
    
    $builder = new Builder($connection);
    $result = $builder->customTest('test');
    expect($result)->toBe('custom: test');
});

it('properly binds static macros to class context', function () {
    Builder::macro('getClassName', function() {
        return static::class;
    });
    
    // When called statically, should have access to static::class
    $result = Builder::getClassName();
    expect($result)->toBe(Builder::class);
});

it('maintains separate macro namespaces per class', function () {
    // Test that macros are specific to each class using the trait
    $testClass1 = new class {
        use \Bob\Query\Macroable;
    };
    
    $testClass2 = new class {
        use \Bob\Query\Macroable;
    };
    
    $class1Name = get_class($testClass1);
    $class2Name = get_class($testClass2);
    
    $class1Name::macro('method1', fn() => 'class1');
    $class2Name::macro('method2', fn() => 'class2');
    
    expect($class1Name::hasMacro('method1'))->toBeTrue();
    expect($class1Name::hasMacro('method2'))->toBeFalse();
    expect($class2Name::hasMacro('method1'))->toBeFalse();
    expect($class2Name::hasMacro('method2'))->toBeTrue();
});

it('passes parameters correctly to macros', function () {
    Builder::macro('concatenate', function($a, $b, $c = 'default') {
        return "$a-$b-$c";
    });
    
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getQueryGrammar')->andReturn(new MySQLGrammar());
    $connection->shouldReceive('getPostProcessor')->andReturn(new \Bob\Query\Processor());
    
    $builder = new Builder($connection);
    
    expect($builder->concatenate('one', 'two'))->toBe('one-two-default');
    expect($builder->concatenate('one', 'two', 'three'))->toBe('one-two-three');
});

it('can chain macros with fluent interface', function () {
    Builder::macro('customWhere', function($column, $value) {
        $this->where($column, $value);
        return $this;
    });
    
    Builder::macro('customLimit', function($limit) {
        $this->limit($limit);
        return $this;
    });
    
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getQueryGrammar')->andReturn(new MySQLGrammar());
    $connection->shouldReceive('getPostProcessor')->andReturn(new \Bob\Query\Processor());
    
    $builder = new Builder($connection);
    
    $result = $builder
        ->from('users')
        ->customWhere('active', true)
        ->customLimit(10);
    
    expect($result)->toBeInstanceOf(Builder::class);
    expect($result->toSql())->toContain('where');
    expect($result->toSql())->toContain('limit');
});