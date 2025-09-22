<?php

use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Query\Processor;
use Bob\Database\Connection;
use Bob\Database\Expression;

beforeEach(function () {
    $this->connection = Mockery::mock(Connection::class);
    $this->grammar = Mockery::mock(Grammar::class)->makePartial();
    $this->processor = Mockery::mock(Processor::class);
    
    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
    
    $this->builder = new Builder($this->connection);
});

afterEach(function () {
    Mockery::close();
});

describe('Builder', function () {
    
    test('implements BuilderInterface', function () {
        expect($this->builder)->toBeInstanceOf(\Bob\Contracts\BuilderInterface::class);
    });
    
    test('select columns', function () {
        $this->builder->select('id', 'name');
        expect($this->builder->columns)->toBe(['id', 'name']);
        
        $this->builder->select(['email', 'created_at']);
        expect($this->builder->columns)->toBe(['email', 'created_at']);
    });
    
    test('from table', function () {
        $this->builder->from('users');
        expect($this->builder->from)->toBe('users');
        
        $this->builder->from('users', 'u');
        expect($this->builder->from)->toBe('users as u');
    });
    
    test('where conditions', function () {
        $this->builder->where('id', 1);
        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0])->toBe([
            'type' => 'Basic',
            'column' => 'id',
            'operator' => '=',
            'value' => 1,
            'boolean' => 'and'
        ]);
        
        $this->builder->where('name', 'like', '%john%');
        expect($this->builder->wheres)->toHaveCount(2);
    });
    
    test('orWhere conditions', function () {
        $this->builder->where('id', 1)->orWhere('email', 'test@example.com');
        expect($this->builder->wheres[1]['boolean'])->toBe('or');
    });
    
    test('whereIn conditions', function () {
        $this->builder->whereIn('id', [1, 2, 3]);
        expect($this->builder->wheres[0]['type'])->toBe('In');
        expect($this->builder->wheres[0]['values'])->toBe([1, 2, 3]);
    });
    
    test('whereNull conditions', function () {
        $this->builder->whereNull('deleted_at');
        expect($this->builder->wheres[0]['type'])->toBe('Null');
    });
    
    test('whereBetween conditions', function () {
        $this->builder->whereBetween('age', [18, 65]);
        expect($this->builder->wheres[0]['type'])->toBe('Between');
        expect($this->builder->wheres[0]['values'])->toBe([18, 65]);
    });
    
    test('joins', function () {
        $this->builder->join('posts', 'users.id', '=', 'posts.user_id');
        expect($this->builder->joins)->toHaveCount(1);
        expect($this->builder->joins[0]->type)->toBe('inner');
        expect($this->builder->joins[0]->table)->toBe('posts');
    });
    
    test('leftJoin', function () {
        $this->builder->leftJoin('posts', 'users.id', '=', 'posts.user_id');
        expect($this->builder->joins[0]->type)->toBe('left');
    });
    
    test('orderBy', function () {
        $this->builder->orderBy('created_at', 'desc');
        expect($this->builder->orders)->toBe([[
            'column' => 'created_at',
            'direction' => 'desc'
        ]]);
    });
    
    test('groupBy', function () {
        $this->builder->groupBy('category', 'status');
        expect($this->builder->groups)->toBe(['category', 'status']);
    });
    
    test('having', function () {
        $this->builder->having('count', '>', 10);
        expect($this->builder->havings)->toHaveCount(1);
    });
    
    test('limit and offset', function () {
        $this->builder->limit(10)->offset(20);
        expect($this->builder->limit)->toBe(10);
        expect($this->builder->offset)->toBe(20);
    });
    
    test('raw expressions', function () {
        $expression = new Expression('COUNT(*)');
        $this->connection->shouldReceive('raw')->with('COUNT(*)')->andReturn($expression);

        $raw = $this->builder->raw('COUNT(*)');
        expect($raw)->toBeInstanceOf(Expression::class);
        expect($raw->getValue())->toBe('COUNT(*)');
    });
    
    test('aggregate functions', function () {
        $this->connection->shouldReceive('select')->andReturn([]);
        $this->processor->shouldReceive('processSelect')->andReturn([]);
        
        $this->builder->from('users')->count();
        $this->builder->from('users')->max('id');
        $this->builder->from('users')->min('id');
        $this->builder->from('users')->sum('amount');
        $this->builder->from('users')->avg('score');
        
        expect(true)->toBeTrue();
    });
    
    test('insert', function () {
        $this->connection->shouldReceive('insert')->andReturn(true);
        
        $result = $this->builder->from('users')->insert(['name' => 'John']);
        expect($result)->toBeTrue();
    });
    
    test('update', function () {
        $this->connection->shouldReceive('update')->andReturn(1);
        
        $result = $this->builder->from('users')->where('id', 1)->update(['name' => 'Jane']);
        expect($result)->toBe(1);
    });
    
    test('delete', function () {
        $this->connection->shouldReceive('delete')->andReturn(1);
        
        $result = $this->builder->from('users')->where('id', 1)->delete();
        expect($result)->toBe(1);
    });
    
    test('toSql', function () {
        $this->grammar->shouldReceive('compileSelect')->andReturn('select * from users');
        
        $sql = $this->builder->from('users')->toSql();
        expect($sql)->toBe('select * from users');
    });
    
    test('getBindings', function () {
        $this->builder->where('id', 1)->where('name', 'John');
        expect($this->builder->getBindings())->toBe([1, 'John']);
    });
    
    test('clone', function () {
        $this->builder->from('users')->where('id', 1);
        $clone = clone $this->builder;
        
        $clone->where('name', 'John');
        
        expect($this->builder->wheres)->toHaveCount(1);
        expect($clone->wheres)->toHaveCount(2);
    });
});