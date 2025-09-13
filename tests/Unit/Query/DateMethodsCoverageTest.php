<?php

use Bob\Database\Connection;
use Bob\Query\Grammars\MySQLGrammar;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test',
        'username' => 'root',
        'password' => '',
    ]);

    // Set MySQL grammar for proper date function compilation
    $this->connection->setQueryGrammar(new MySQLGrammar());
});

describe('Date methods coverage for MySQL', function () {
    it('compiles whereDate', function () {
        $builder = $this->connection->table('posts');
        $builder->whereDate('created_at', '=', '2024-01-01');

        $sql = $builder->toSql();
        expect($sql)->toContain('date(');
        expect($builder->getBindings())->toBe(['2024-01-01']);
    });

    it('compiles whereTime', function () {
        $builder = $this->connection->table('posts');
        $builder->whereTime('created_at', '>', '12:00:00');

        $sql = $builder->toSql();
        expect($sql)->toContain('time(');
        expect($builder->getBindings())->toBe(['12:00:00']);
    });

    it('compiles whereDay', function () {
        $builder = $this->connection->table('posts');
        $builder->whereDay('created_at', '=', 15);

        $sql = $builder->toSql();
        expect($sql)->toContain('day(');
        expect($builder->getBindings())->toBe([15]);
    });

    it('compiles whereMonth', function () {
        $builder = $this->connection->table('posts');
        $builder->whereMonth('created_at', '=', 6);

        $sql = $builder->toSql();
        expect($sql)->toContain('month(');
        expect($builder->getBindings())->toBe([6]);
    });

    it('compiles whereYear', function () {
        $builder = $this->connection->table('posts');
        $builder->whereYear('created_at', '=', 2024);

        $sql = $builder->toSql();
        expect($sql)->toContain('year(');
        expect($builder->getBindings())->toBe([2024]);
    });
});

describe('Grammar date method compilation', function () {
    it('compiles whereDate using grammar directly', function () {
        $grammar = new MySQLGrammar();
        $builder = $this->connection->table('users');

        $where = [
            'type' => 'Date',
            'column' => 'created_at',
            'operator' => '=',
            'value' => '2024-01-01',
            'boolean' => 'and'
        ];

        $method = new ReflectionMethod($grammar, 'whereDate');
        $method->setAccessible(true);
        $result = $method->invoke($grammar, $builder, $where);

        expect($result)->toContain('date(');
    });

    it('compiles whereTime using grammar directly', function () {
        $grammar = new MySQLGrammar();
        $builder = $this->connection->table('users');

        $where = [
            'type' => 'Time',
            'column' => 'created_at',
            'operator' => '>',
            'value' => '12:00:00',
            'boolean' => 'and'
        ];

        $method = new ReflectionMethod($grammar, 'whereTime');
        $method->setAccessible(true);
        $result = $method->invoke($grammar, $builder, $where);

        expect($result)->toContain('time(');
    });

    it('compiles whereDay using grammar directly', function () {
        $grammar = new MySQLGrammar();
        $builder = $this->connection->table('users');

        $where = [
            'type' => 'Day',
            'column' => 'created_at',
            'operator' => '=',
            'value' => 15,
            'boolean' => 'and'
        ];

        $method = new ReflectionMethod($grammar, 'whereDay');
        $method->setAccessible(true);
        $result = $method->invoke($grammar, $builder, $where);

        expect($result)->toContain('day(');
    });

    it('compiles whereMonth using grammar directly', function () {
        $grammar = new MySQLGrammar();
        $builder = $this->connection->table('users');

        $where = [
            'type' => 'Month',
            'column' => 'created_at',
            'operator' => '=',
            'value' => 6,
            'boolean' => 'and'
        ];

        $method = new ReflectionMethod($grammar, 'whereMonth');
        $method->setAccessible(true);
        $result = $method->invoke($grammar, $builder, $where);

        expect($result)->toContain('month(');
    });

    it('compiles whereYear using grammar directly', function () {
        $grammar = new MySQLGrammar();
        $builder = $this->connection->table('users');

        $where = [
            'type' => 'Year',
            'column' => 'created_at',
            'operator' => '=',
            'value' => 2024,
            'boolean' => 'and'
        ];

        $method = new ReflectionMethod($grammar, 'whereYear');
        $method->setAccessible(true);
        $result = $method->invoke($grammar, $builder, $where);

        expect($result)->toContain('year(');
    });
});