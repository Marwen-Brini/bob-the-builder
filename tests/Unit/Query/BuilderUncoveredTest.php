<?php

use Bob\Contracts\BuilderInterface;
use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Query\JoinClause;
use Bob\Query\Processor;
use Mockery as m;

beforeEach(function () {
    $this->connection = m::mock(Connection::class);
    $this->grammar = m::mock(Grammar::class);
    $this->processor = m::mock(Processor::class);

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
});

afterEach(function () {
    m::close();
});

describe('Builder Uncovered Lines Tests', function () {

    test('where with closure calls whereSub (line 192)', function () {
        $builder = new Builder($this->connection);

        $result = $builder->where('status', '=', function ($query) {
            $query->select('status')->from('statuses')->where('active', true);
        });

        expect($result)->toBe($builder);
        expect($builder->wheres)->toHaveCount(1);
        expect($builder->wheres[0]['type'])->toBe('Sub');
    });

    test('addArrayOfWheres with nested arrays (lines 212-215)', function () {
        $builder = new Builder($this->connection);

        $builder->where([
            ['status', '=', 'active'],
            ['role', 'admin'],
            'name' => 'John',
        ]);

        expect($builder->wheres)->toHaveCount(3);
    });

    test('whereSub method with callback (lines 268-275)', function () {
        $builder = new Builder($this->connection);

        $reflection = new ReflectionClass($builder);
        $method = $reflection->getMethod('whereSub');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'column', '=', function ($query) {
            $query->where('test', 1);
        }, 'and');

        expect($result)->toBe($builder);
        expect($builder->wheres)->toHaveCount(1);
        expect($builder->wheres[0]['type'])->toBe('Sub');
    });

    test('whereIn with BuilderInterface (lines 296-300)', function () {
        $builder = new Builder($this->connection);
        $subQuery = m::mock(BuilderInterface::class);

        // getBindings() without parameters returns a flat array
        $subQuery->shouldReceive('getBindings')->andReturn([1, 2, 3]);

        $result = $builder->whereIn('id', $subQuery);

        expect($result)->toBe($builder);
        expect($builder->wheres[0]['type'])->toBe('InSub');
        expect($builder->getRawBindings()['where'])->toBe([1, 2, 3]);
    });

    test('whereNotIn with BuilderInterface (lines 296-300)', function () {
        $builder = new Builder($this->connection);
        $subQuery = m::mock(BuilderInterface::class);

        // getBindings() without parameters returns a flat array
        $subQuery->shouldReceive('getBindings')->andReturn([4, 5, 6]);

        $result = $builder->whereNotIn('id', $subQuery);

        expect($result)->toBe($builder);
        expect($builder->wheres[0]['type'])->toBe('NotInSub');
    });

    test('whereInSub method (lines 368-373)', function () {
        $builder = new Builder($this->connection);

        $result = $builder->whereIn('id', function ($query) {
            $query->select('user_id')->from('posts')->where('published', true);
        });

        expect($result)->toBe($builder);
        expect($builder->wheres[0]['type'])->toBe('InSub');
    });

    test('whereColumn with array (lines 606-609)', function () {
        $builder = new Builder($this->connection);

        $result = $builder->whereColumn([
            ['first_name', '=', 'last_name'],
            ['updated_at', '>', 'created_at'],
        ]);

        expect($result)->toBe($builder);
        expect($builder->wheres)->toHaveCount(2);
        expect($builder->wheres[0]['type'])->toBe('Column');
    });

    test('having method (line 664)', function () {
        $builder = new Builder($this->connection);

        $result = $builder->having('total', '>', 100)
            ->having('count', '<', 50);

        expect($result)->toBe($builder);
        expect($builder->havings)->toHaveCount(2);
    });

    test('havingRaw method (lines 738-742)', function () {
        $builder = new Builder($this->connection);

        $result = $builder->havingRaw('SUM(amount) > ?', [100], 'and');

        expect($result)->toBe($builder);
        expect($builder->havings[0]['type'])->toBe('Raw');
        expect($builder->havings[0]['sql'])->toBe('SUM(amount) > ?');
        expect($builder->getRawBindings()['having'])->toBe([100]);
    });

    test('orHavingRaw method (line 740)', function () {
        $builder = new Builder($this->connection);

        $result = $builder->orHavingRaw('COUNT(*) > 10');

        expect($result)->toBe($builder);
        expect($builder->havings[0]['boolean'])->toBe('or');
    });

    test('union with closure (line 910)', function () {
        $builder = new Builder($this->connection);

        $result = $builder->union(function ($query) {
            $query->select('*')->from('archived_users');
        });

        expect($result)->toBe($builder);
        expect($builder->unions)->toHaveCount(1);
    });

    test('lock method variations (lines 963, 968, 977)', function () {
        $builder = new Builder($this->connection);

        // Test lockForUpdate
        $builder->lockForUpdate();
        expect($builder->lock)->toBeTrue();

        // Test sharedLock
        $builder2 = new Builder($this->connection);
        $builder2->sharedLock();
        expect($builder2->lock)->toBeFalse();

        // Test lock(false)
        $builder3 = new Builder($this->connection);
        $builder3->lock(false);
        expect($builder3->lock)->toBeFalse();
    });

    test('find method (line 1082)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $this->grammar->shouldReceive('compileSelect')->andReturn('SELECT * FROM users WHERE id = ? LIMIT 1');
        $this->processor->shouldReceive('processSelect')->andReturn([
            (object) ['id' => 1, 'name' => 'John'],
        ]);
        $this->connection->shouldReceive('selectOne')->andReturn(
            (object) ['id' => 1, 'name' => 'John']
        );

        $result = $builder->find(1);

        expect($result)->toBeObject();
        expect($result->id)->toBe(1);
    });

    test('value method (line 1102)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $this->grammar->shouldReceive('compileSelect')->andReturn('SELECT name FROM users LIMIT 1');
        $this->processor->shouldReceive('processSelect')->andReturn([
            (object) ['name' => 'John'],
        ]);
        $this->connection->shouldReceive('selectOne')->andReturn(
            (object) ['name' => 'John']
        );

        $result = $builder->value('name');

        expect($result)->toBe('John');
    });

    test('insertOrIgnore with empty values (lines 1175-1177)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $result = $builder->insertOrIgnore([]);

        expect($result)->toBe(0);
    });

    test('insertOrIgnore with single row (lines 1179-1181)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $this->grammar->shouldReceive('compileInsertOrIgnore')
            ->once()
            ->andReturn('INSERT IGNORE INTO users');

        $this->connection->shouldReceive('affectingStatement')
            ->once()
            ->andReturn(1);

        $result = $builder->insertOrIgnore(['name' => 'John', 'email' => 'john@example.com']);

        expect($result)->toBe(1);
    });

    test('insertOrIgnore with multiple rows (lines 1183-1190)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $values = [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['email' => 'jane@example.com', 'name' => 'Jane'], // Different order
        ];

        $this->grammar->shouldReceive('compileInsertOrIgnore')
            ->once()
            ->andReturn('INSERT IGNORE INTO users');

        $this->connection->shouldReceive('affectingStatement')
            ->once()
            ->andReturn(2);

        $result = $builder->insertOrIgnore($values);

        expect($result)->toBe(2);
    });

    test('updateOrInsert when record does not exist (lines 1205-1207)', function () {
        $builder = m::mock(Builder::class)->makePartial();
        $builder->shouldAllowMockingProtectedMethods();
        $builder->__construct($this->connection);
        $builder->from('users');

        $builder->shouldReceive('where')->with(['email' => 'new@example.com'])->andReturnSelf();
        $builder->shouldReceive('exists')->andReturn(false);
        $builder->shouldReceive('insert')->with(['email' => 'new@example.com', 'name' => 'New User'])->andReturn(true);

        $result = $builder->updateOrInsert(['email' => 'new@example.com'], ['name' => 'New User']);

        expect($result)->toBeTrue();
    });

    test('updateOrInsert when record exists with empty values (lines 1209-1211)', function () {
        $builder = m::mock(Builder::class)->makePartial();
        $builder->shouldAllowMockingProtectedMethods();
        $builder->__construct($this->connection);
        $builder->from('users');

        $builder->shouldReceive('where')->with(['email' => 'existing@example.com'])->andReturnSelf();
        $builder->shouldReceive('exists')->andReturn(true);

        $result = $builder->updateOrInsert(['email' => 'existing@example.com'], []);

        expect($result)->toBeTrue();
    });

    test('updateOrInsert when record exists with values (line 1213)', function () {
        $builder = m::mock(Builder::class)->makePartial();
        $builder->shouldAllowMockingProtectedMethods();
        $builder->__construct($this->connection);
        $builder->from('users');

        $builder->shouldReceive('where')->with(['email' => 'existing@example.com'])->andReturnSelf();
        $builder->shouldReceive('exists')->andReturn(true);
        $builder->shouldReceive('limit')->with(1)->andReturnSelf();
        $builder->shouldReceive('update')->with(['name' => 'Updated User'])->andReturn(1);

        $result = $builder->updateOrInsert(['email' => 'existing@example.com'], ['name' => 'Updated User']);

        expect($result)->toBeTrue();
    });

    test('upsert method (lines 1237, 1257, 1274)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $values = [
            ['email' => 'john@example.com', 'name' => 'John'],
            ['email' => 'jane@example.com', 'name' => 'Jane'],
        ];

        $this->grammar->shouldReceive('compileUpsert')
            ->once()
            ->andReturn('INSERT INTO users ... ON DUPLICATE KEY UPDATE');

        $this->connection->shouldReceive('affectingStatement')
            ->once()
            ->andReturn(2);

        $result = $builder->upsert($values, ['email'], ['name']);

        expect($result)->toBe(2);
    });

    test('increment and decrement with extra columns (lines 1295-1299, 1312)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $this->grammar->shouldReceive('wrap')->with('votes')->andReturn('`votes`');
        $this->grammar->shouldReceive('compileUpdate')->andReturn('UPDATE users SET');
        $this->connection->shouldReceive('raw')->with('`votes` + 5')->andReturn(new Expression('`votes` + 5'));
        $this->connection->shouldReceive('update')->andReturn(1);

        $result = $builder->increment('votes', 5, ['last_voted_at' => '2025-01-01']);

        expect($result)->toBe(1);

        // Test decrement
        $builder2 = new Builder($this->connection);
        $builder2->from('posts');

        $this->grammar->shouldReceive('wrap')->with('likes')->andReturn('`likes`');
        $this->grammar->shouldReceive('compileUpdate')->andReturn('UPDATE posts SET');
        $this->connection->shouldReceive('raw')->with('`likes` - 1')->andReturn(new Expression('`likes` - 1'));
        $this->connection->shouldReceive('update')->andReturn(1);

        $result2 = $builder2->decrement('likes', 1, ['updated_at' => '2025-01-01']);

        expect($result2)->toBe(1);
    });

    test('pluck with key (lines 1416-1447)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $this->grammar->shouldReceive('compileSelect')->andReturn('SELECT name, id FROM users');
        $this->processor->shouldReceive('processSelect')->andReturn([
            (object) ['id' => 1, 'name' => 'John'],
            (object) ['id' => 2, 'name' => 'Jane'],
        ]);
        $this->connection->shouldReceive('select')->andReturn([
            (object) ['id' => 1, 'name' => 'John'],
            (object) ['id' => 2, 'name' => 'Jane'],
        ]);

        $result = $builder->pluck('name', 'id');

        expect($result)->toBe([1 => 'John', 2 => 'Jane']);
    });

    test('implode method (line 1470)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $this->grammar->shouldReceive('compileSelect')->andReturn('SELECT name FROM users');
        $this->processor->shouldReceive('processSelect')->andReturn([
            (object) ['name' => 'John'],
            (object) ['name' => 'Jane'],
            (object) ['name' => 'Bob'],
        ]);
        $this->connection->shouldReceive('select')->andReturn([
            (object) ['name' => 'John'],
            (object) ['name' => 'Jane'],
            (object) ['name' => 'Bob'],
        ]);

        $result = $builder->implode('name', ', ');

        expect($result)->toBe('John, Jane, Bob');
    });

    test('exists and doesntExist methods (lines 1528, 1593)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $this->grammar->shouldReceive('compileExists')->andReturn('SELECT EXISTS(...) as exists');
        $this->processor->shouldReceive('processSelect')->andReturn([
            (object) ['exists' => 1],
        ]);
        $this->connection->shouldReceive('select')->andReturn([
            (object) ['exists' => 1],
        ]);

        expect($builder->exists())->toBeTrue();
        expect($builder->doesntExist())->toBeFalse();
    });

    test('existsOr method with callback (line 1602)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $this->grammar->shouldReceive('compileExists')->andReturn('SELECT EXISTS(...) as exists');
        $this->processor->shouldReceive('processSelect')->andReturn([]);
        $this->connection->shouldReceive('select')->andReturn([]);

        $called = false;
        $builder->existsOr(function () use (&$called) {
            $called = true;
        });

        expect($called)->toBeTrue();
    });

    test('doesntExistOr method with callback (line 1627)', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        $this->grammar->shouldReceive('compileExists')->andReturn('SELECT EXISTS(...) as exists');
        $this->processor->shouldReceive('processSelect')->andReturn([
            (object) ['exists' => 1],
        ]);
        $this->connection->shouldReceive('select')->andReturn([
            (object) ['exists' => 1],
        ]);

        $called = false;
        $builder->doesntExistOr(function () use (&$called) {
            $called = true;
        });

        expect($called)->toBeTrue();
    });

    test('crossJoinSub method (lines 1662-1690)', function () {
        $builder = new Builder($this->connection);

        $this->grammar->shouldReceive('compileSelect')->andReturn('SELECT * FROM departments');
        $this->grammar->shouldReceive('wrap')->with('dept')->andReturn('`dept`');

        $result = $builder->crossJoinSub(function ($query) {
            $query->select('*')->from('departments');
        }, 'dept');

        expect($result)->toBe($builder);
        expect($builder->joins)->toHaveCount(1);
        expect($builder->joins[0]->type)->toBe('cross');
    });

    test('newJoinClause method (lines 1700-1720)', function () {
        $builder = new Builder($this->connection);

        $reflection = new ReflectionClass($builder);
        $method = $reflection->getMethod('newJoinClause');
        $method->setAccessible(true);

        $joinClause = $method->invoke($builder, 'inner', 'users');

        expect($joinClause)->toBeInstanceOf(JoinClause::class);
        expect($joinClause->type)->toBe('inner');
        expect($joinClause->table)->toBe('users');
    });

    test('forSubQuery method (line 1727)', function () {
        $builder = new Builder($this->connection);

        $result = $builder->forSubQuery();

        expect($result)->toBeInstanceOf(Builder::class);
        expect($result)->not->toBe($builder);
    });

    test('mergeBindings method (lines 1771-1772, 1780-1784)', function () {
        $builder = new Builder($this->connection);
        $otherBuilder = new Builder($this->connection);

        $otherBuilder->addBinding([1, 2, 3], 'where');
        $otherBuilder->addBinding([4, 5], 'having');

        $builder->mergeBindings($otherBuilder);

        expect($builder->getRawBindings()['where'])->toBe([1, 2, 3]);
        expect($builder->getRawBindings()['having'])->toBe([4, 5]);
    });

    test('getGrammar and getProcessor methods (lines 1806-1809, 1825)', function () {
        $builder = new Builder($this->connection);

        expect($builder->getGrammar())->toBe($this->grammar);
        expect($builder->getProcessor())->toBe($this->processor);
    });

    test('raw method (line 1840)', function () {
        $builder = new Builder($this->connection);

        $this->connection->shouldReceive('raw')->with('COUNT(*)')->andReturn(new Expression('COUNT(*)'));

        $result = $builder->raw('COUNT(*)');

        expect($result)->toBeInstanceOf(Expression::class);
    });

    test('getBindings with specific type (lines 1934-1972)', function () {
        $builder = new Builder($this->connection);

        $builder->addBinding([1, 2], 'where');
        $builder->addBinding([3], 'having');
        $builder->addBinding([4, 5], 'order');

        expect($builder->getRawBindings())->toHaveKey('where');
        expect($builder->getRawBindings()['where'])->toBe([1, 2]);
        expect($builder->getRawBindings()['having'])->toBe([3]);
    });

    test('getRawBindings method (line 2036)', function () {
        $builder = new Builder($this->connection);

        $builder->addBinding([1, 2], 'where');
        $builder->addBinding([3], 'having');

        $raw = $builder->getRawBindings();

        expect($raw)->toHaveKey('select');
        expect($raw)->toHaveKey('where');
        expect($raw)->toHaveKey('having');
        expect($raw['where'])->toBe([1, 2]);
    });

    test('whereColumn with nested arrays (line 227)', function () {
        $builder = new Builder($this->connection);

        $builder->whereColumn([
            ['first_name', '=', 'last_name'],
            ['updated_at', '>', 'created_at'],
        ]);

        expect($builder->wheres)->toHaveCount(2);
        expect($builder->wheres[0]['type'])->toBe('Column');
        expect($builder->wheres[1]['type'])->toBe('Column');
    });

    test('whereNested with boolean (line 1646)', function () {
        $builder = new Builder($this->connection);

        $builder->whereNested(function ($query) {
            $query->where('name', 'John')->orWhere('name', 'Jane');
        }, 'or');

        expect($builder->wheres)->toHaveCount(1);
        expect($builder->wheres[0]['type'])->toBe('Nested');
        expect($builder->wheres[0]['boolean'])->toBe('or');
    });

    test('forPageBeforeId and forPageAfterId methods', function () {
        $builder = new Builder($this->connection);
        $builder->from('users');

        // Test forPageBeforeId
        $result = $builder->forPageBeforeId(15, 100, 'id');
        expect($result)->toBe($builder);
        expect($builder->wheres)->toHaveCount(1);
        expect($builder->limit)->toBe(15);

        // Test forPageAfterId
        $builder2 = new Builder($this->connection);
        $builder2->from('posts');

        $result2 = $builder2->forPageAfterId(10, 50);
        expect($result2)->toBe($builder2);
        expect($builder2->wheres)->toHaveCount(1);
        expect($builder2->limit)->toBe(10);
    });
});
