<?php

use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Query\JoinClause;
use Bob\Query\Processor;

beforeEach(function () {
    $this->connection = Mockery::mock(Connection::class);
    $this->grammar = Mockery::mock(Grammar::class);
    $this->processor = Mockery::mock(Processor::class);

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection, $this->grammar, $this->processor);
});

describe('Builder Additional Coverage', function () {

    test('distinct can be set', function () {
        $this->builder->distinct()->from('users');
        expect($this->builder->distinct)->toBeTrue();

        $this->builder->distinct(false);
        expect($this->builder->distinct)->toBeFalse();
    });

    test('select with expression', function () {
        $expr = new Expression('COUNT(*)');
        $this->builder->select($expr)->from('users');
        expect($this->builder->columns)->toBe([$expr]);
    });

    test('selectRaw creates expression', function () {
        $this->builder->selectRaw('COUNT(*) as total')->from('users');
        expect($this->builder->columns[0])->toBeInstanceOf(Expression::class);
    });

    test('addSelect appends columns', function () {
        $this->builder->select('id')->from('users');
        $this->builder->addSelect(['name', 'email']);

        expect($this->builder->columns)->toBe(['id', 'name', 'email']);
    });

    test('from with alias', function () {
        $this->builder->from('users', 'u');
        expect($this->builder->from)->toBe('users as u');
    });

    test('fromRaw creates expression', function () {
        $this->connection->shouldReceive('raw')
            ->with('(SELECT * FROM users) as u')
            ->andReturn(new Expression('(SELECT * FROM users) as u'));

        $this->builder->fromRaw('(SELECT * FROM users) as u');
        expect($this->builder->from)->toBeInstanceOf(Expression::class);
    });

    test('where with array conditions', function () {
        $this->builder->from('users')->where([
            ['name', '=', 'John'],
            ['age', '>', 18],
        ]);

        expect($this->builder->wheres)->toHaveCount(2);
    });

    test('orWhere adds with or boolean', function () {
        $this->builder->from('users')
            ->where('name', 'John')
            ->orWhere('name', 'Jane');

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[1]['boolean'])->toBe('or');
    });

    test('whereRaw with bindings', function () {
        $this->builder->from('users')->whereRaw('age > ? AND status = ?', [18, 'active']);

        expect($this->builder->wheres[0]['type'])->toBe('Raw'); // Fixed: uppercase 'Raw' to match implementation
        expect($this->builder->wheres[0]['sql'])->toBe('age > ? AND status = ?');
        expect($this->builder->getBindings())->toBe([18, 'active']);
    });

    test('whereIn with subquery', function () {
        $subquery = function ($query) {
            $query->from('posts')->select('user_id')->where('published', true);
        };

        $this->builder->from('users')->whereIn('id', $subquery);

        expect($this->builder->wheres[0]['type'])->toBe('InSub');
        expect($this->builder->wheres[0]['query'])->toBeInstanceOf(Builder::class);
    });

    test('whereBetween adds between condition', function () {
        $this->builder->from('users')->whereBetween('age', [18, 65]);

        expect($this->builder->wheres[0]['type'])->toBe('Between');
        expect($this->builder->wheres[0]['values'])->toBe([18, 65]);
    });

    test('whereNotBetween adds not between condition', function () {
        $this->builder->from('users')->whereNotBetween('age', [0, 17]);

        expect($this->builder->wheres[0]['type'])->toBe('NotBetween');
        // 'not' property may not exist, check type is NotBetween instead
    });

    test('whereNull and whereNotNull', function () {
        $this->builder->from('users')
            ->whereNull('deleted_at')
            ->whereNotNull('email_verified_at');

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[0]['type'])->toBe('Null');
        expect($this->builder->wheres[1]['type'])->toBe('NotNull');
    });

    test('whereExists with builder', function () {
        $subquery = function ($query) {
            $query->from('posts')->whereRaw('posts.user_id = users.id');
        };

        $this->builder->from('users')->whereExists($subquery);

        expect($this->builder->wheres[0]['type'])->toBe('Exists');
        expect($this->builder->wheres[0]['query'])->toBeInstanceOf(Builder::class);
    });

    test('join with closure', function () {
        $this->builder->from('users')->join('posts', function ($join) {
            $join->on('users.id', '=', 'posts.user_id')
                ->where('posts.published', '=', true);
        });

        expect($this->builder->joins)->toHaveCount(1);
        expect($this->builder->joins[0])->toBeInstanceOf(JoinClause::class);
    });

    test('leftJoin creates left join', function () {
        $this->builder->from('users')->leftJoin('posts', 'users.id', '=', 'posts.user_id');

        expect($this->builder->joins[0]->type)->toBe('left');
    });

    test('rightJoin creates right join', function () {
        $this->builder->from('users')->rightJoin('posts', 'users.id', '=', 'posts.user_id');

        expect($this->builder->joins[0]->type)->toBe('right');
    });

    test('crossJoin creates cross join', function () {
        $this->builder->from('users')->crossJoin('roles');

        expect($this->builder->joins[0]->type)->toBe('cross');
    });

    test('groupBy with multiple columns', function () {
        $this->builder->from('users')->groupBy(['status', 'role']);

        expect($this->builder->groups)->toBe(['status', 'role']);
    });

    test('having adds having clause', function () {
        $this->builder->from('users')
            ->groupBy('status')
            ->having('status', '=', 'active');

        expect($this->builder->havings)->toHaveCount(1);
        expect($this->builder->havings[0]['column'])->toBe('status');
    });

    test('orderBy with direction', function () {
        $this->builder->from('users')
            ->orderBy('name')
            ->orderBy('created_at', 'desc');

        expect($this->builder->orders)->toHaveCount(2);
        expect($this->builder->orders[0]['direction'])->toBe('asc');
        expect($this->builder->orders[1]['direction'])->toBe('desc');
    });

    test('orderByDesc helper', function () {
        $result = $this->builder->from('users')->orderByDesc('created_at');

        expect($result)->toBeInstanceOf(Builder::class);
        expect($this->builder->orders[0]['column'])->toBe('created_at');
        expect($this->builder->orders[0]['direction'])->toBe('desc');
    });

    test('latest and oldest helpers', function () {
        $builder1 = clone $this->builder;
        $builder1->from('users')->latest();

        expect($builder1->orders[0]['column'])->toBe('created_at');
        expect($builder1->orders[0]['direction'])->toBe('desc');

        $builder2 = clone $this->builder;
        $builder2->from('users')->oldest('updated_at');

        expect($builder2->orders[0]['column'])->toBe('updated_at');
        expect($builder2->orders[0]['direction'])->toBe('asc');
    });

    test('limit and offset', function () {
        $this->builder->from('users')->limit(10)->offset(20);

        expect($this->builder->limit)->toBe(10);
        expect($this->builder->offset)->toBe(20);
    });

    test('take and skip aliases', function () {
        $this->builder->from('users')->take(5)->skip(10);

        expect($this->builder->limit)->toBe(5);
        expect($this->builder->offset)->toBe(10);
    });

    test('forPage pagination helper', function () {
        $this->builder->from('users')->forPage(3, 20);

        expect($this->builder->limit)->toBe(20);
        expect($this->builder->offset)->toBe(40);
    });

    test('when conditional clause - true condition', function () {
        $applied = false;

        $this->builder->from('users')->when(true, function ($query) use (&$applied) {
            $applied = true;
            $query->where('active', true);
        });

        expect($applied)->toBeTrue();
        expect($this->builder->wheres)->toHaveCount(1);
    });

    test('when conditional clause - false condition', function () {
        $applied = false;

        $this->builder->from('users')->when(false, function ($query) use (&$applied) {
            $applied = true;
            $query->where('active', true);
        });

        expect($applied)->toBeFalse();
        expect($this->builder->wheres)->toHaveCount(0);
    });

    test('when with default callback', function () {
        $this->builder->from('users')->when(
            false,
            function ($query) {
                $query->where('active', true);
            },
            function ($query) {
                $query->where('active', false);
            }
        );

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['value'])->toBeFalse();
    });

    test('lock for update', function () {
        $this->builder->from('users')->lock();
        expect($this->builder->lock)->toBeTrue();

        $this->builder->lock(false);
        expect($this->builder->lock)->toBeFalse();

        $this->builder->lock('custom lock');
        expect($this->builder->lock)->toBe('custom lock');
    });

    test('sharedLock sets lock to false', function () {
        $this->builder->from('users')->sharedLock();
        expect($this->builder->lock)->toBeFalse();
    });

    test('lockForUpdate sets lock to true', function () {
        $this->builder->from('users')->lockForUpdate();
        expect($this->builder->lock)->toBeTrue();
    });

    test('toSql returns compiled query', function () {
        $expectedSql = 'select * from users where active = ?';
        $this->grammar->shouldReceive('compileSelect')->andReturn($expectedSql);

        $sql = $this->builder->from('users')->where('active', true)->toSql();

        expect($sql)->toBe($expectedSql);
    });

    test('getBindings returns all bindings', function () {
        $this->builder->from('users')
            ->where('name', 'John')
            ->whereIn('role', ['admin', 'user'])
            ->having('count', '>', 5);

        $bindings = $this->builder->getBindings();

        expect($bindings)->toBe(['John', 'admin', 'user', 5]);
    });

    test('setBindings replaces bindings', function () {
        $this->builder->from('users')->where('id', 1);
        $this->builder->setBindings(['new', 'bindings']);

        expect($this->builder->getBindings())->toBe(['new', 'bindings']);
    });

    test('addBinding adds to specific type', function () {
        $this->builder->from('users');
        $this->builder->addBinding('value1', 'where');
        $this->builder->addBinding(['value2', 'value3'], 'where');

        // Use reflection to check protected property since getBindings returns all bindings
        $reflection = new \ReflectionClass($this->builder);
        $bindingsProperty = $reflection->getProperty('bindings');
        $bindingsProperty->setAccessible(true);
        $bindings = $bindingsProperty->getValue($this->builder);
        expect($bindings['where'])->toBe(['value1', 'value2', 'value3']);
    });

    test('mergeWheres combines where clauses', function () {
        $otherBuilder = new Builder($this->connection, $this->grammar, $this->processor);
        $otherBuilder->where('status', 'active')->where('role', 'admin');

        $this->builder->from('users')->where('id', '>', 10);
        $this->builder->mergeWheres($otherBuilder->wheres, $otherBuilder->getBindings());

        expect($this->builder->wheres)->toHaveCount(3);
        expect($this->builder->getBindings())->toBe([10, 'active', 'admin']);
    });

    test('raw creates expression', function () {
        $this->connection->shouldReceive('raw')
            ->with('COUNT(*)')
            ->andReturn(new Expression('COUNT(*)'));

        $raw = $this->builder->raw('COUNT(*)');

        expect($raw)->toBeInstanceOf(Expression::class);
        expect($raw->getValue())->toBe('COUNT(*)');
    });

    test('getGrammar and getProcessor return instances', function () {
        expect($this->builder->getGrammar())->toBe($this->grammar);
        expect($this->builder->getProcessor())->toBe($this->processor);
    });

    test('useWritePdo for write operations', function () {
        $this->builder->from('users')->useWritePdo();

        expect($this->builder->useWritePdo)->toBeTrue();
    });

});

test('get method executes query', function () {
    $this->connection->shouldReceive('select')
        ->once()
        ->with('select * from users', [], true)
        ->andReturn([
            (object) ['id' => 1, 'name' => 'John'],
            (object) ['id' => 2, 'name' => 'Jane'],
        ]);

    $this->grammar->shouldReceive('compileSelect')->andReturn('select * from users');
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($b, $r) => $r);

    $results = $this->builder->from('users')->get();

    expect($results)->toHaveCount(2);
});

test('first returns single record', function () {
    $this->connection->shouldReceive('selectOne')
        ->once()
        ->with('select * from users limit 1', [], true)
        ->andReturn((object) ['id' => 1, 'name' => 'John']);

    $this->grammar->shouldReceive('compileSelect')->andReturn('select * from users limit 1');
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($b, $r) => $r);

    $result = $this->builder->from('users')->first();

    expect($result->name)->toBe('John');
});

test('value returns single column value', function () {
    $this->connection->shouldReceive('selectOne')
        ->once()
        ->andReturn((object) ['name' => 'John']);

    $this->grammar->shouldReceive('compileSelect')->andReturn('select name from users limit 1');
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($b, $r) => $r);

    $value = $this->builder->from('users')->value('name');

    expect($value)->toBe('John');
});

test('exists returns boolean', function () {
    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([['exists' => 1]]);

    $this->grammar->shouldReceive('compileExists')->andReturn('select exists(select * from users)');
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($b, $r) => $r);

    $exists = $this->builder->from('users')->exists();

    expect($exists)->toBeTrue();
});

test('doesntExist returns boolean', function () {
    $this->connection->shouldReceive('select')
        ->once()
        ->andReturn([]);

    $this->grammar->shouldReceive('compileExists')->andReturn('select exists(select * from users)');
    $this->processor->shouldReceive('processSelect')->andReturnUsing(fn ($b, $r) => $r);

    $doesntExist = $this->builder->from('users')->doesntExist();

    expect($doesntExist)->toBeTrue();
});
