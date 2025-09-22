<?php

use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Query\Processor;
use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\JoinClause;

beforeEach(function () {
    $this->connection = Mockery::mock(Connection::class);
    $this->grammar = Mockery::mock(Grammar::class);
    $this->processor = Mockery::mock(Processor::class);

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection, $this->grammar, $this->processor);
});

describe('Builder Extended Coverage', function () {

    test('distinct queries', function () {
        $this->builder->distinct()->from('users');

        expect($this->builder->distinct)->toBeTrue();
    });

    test('select with array columns', function () {
        $this->builder->select(['name', 'email'])->from('users');

        expect($this->builder->columns)->toBe(['name', 'email']);
    });

    test('selectRaw', function () {
        $this->builder->selectRaw('count(*) as user_count')->from('users');

        expect($this->builder->columns[0])->toBeInstanceOf(Expression::class);
    });

    test('addSelect', function () {
        $this->builder->select('name')->addSelect('email')->from('users');

        expect($this->builder->columns)->toHaveCount(2);
        expect($this->builder->columns)->toBe(['name', 'email']);
    });

    test('selectSub with closure', function () {
        // Mock the wrap method for the alias
        $this->grammar->shouldReceive('wrap')->with('orders_count')->andReturn('`orders_count`');
        // Mock the compileSelect method for the subquery
        $this->grammar->shouldReceive('compileSelect')->andReturn('select count(*) from orders where user_id = users.id');

        $this->builder->selectSub(function ($query) {
            $query->from('orders')->selectRaw('count(*)')->whereColumn('user_id', 'users.id');
        }, 'orders_count')->from('users');

        expect($this->builder->columns)->toHaveCount(1);
    });

    test('whereColumn', function () {
        $this->builder->from('users')->whereColumn('first_name', 'last_name');

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('Column');
    });

    test('orWhereColumn', function () {
        $this->builder->from('users')
            ->where('active', true)
            ->orWhereColumn('first_name', 'last_name');

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[1]['boolean'])->toBe('or');
    });

    test('whereRaw', function () {
        $this->builder->from('users')->whereRaw('age > ?', [18]);

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('Raw');
        expect($this->builder->getBindings())->toBe([18]);
    });

    test('orWhereRaw', function () {
        $this->builder->from('users')
            ->where('active', true)
            ->orWhereRaw('age > ?', [21]);

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[1]['boolean'])->toBe('or');
    });

    test('whereNotIn', function () {
        $this->builder->from('users')->whereNotIn('id', [1, 2, 3]);

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('NotIn');
    });

    test('orWhereIn', function () {
        $this->builder->from('users')
            ->where('active', true)
            ->orWhereIn('role', ['admin', 'moderator']);

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[1]['boolean'])->toBe('or');
        expect($this->builder->wheres[1]['type'])->toBe('In');
    });

    test('orWhereNotIn', function () {
        $this->builder->from('users')
            ->where('active', true)
            ->orWhereNotIn('status', ['banned', 'suspended']);

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[1]['boolean'])->toBe('or');
        expect($this->builder->wheres[1]['type'])->toBe('NotIn');
    });

    test('whereNotNull', function () {
        $this->builder->from('users')->whereNotNull('email_verified_at');

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('NotNull');
    });

    test('whereDate', function () {
        $this->builder->from('users')->whereDate('created_at', '2023-01-01');

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('Date');
    });

    test('whereTime', function () {
        $this->builder->from('users')->whereTime('created_at', '>=', '08:00:00');

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('Time');
    });

    test('whereDay', function () {
        $this->builder->from('users')->whereDay('created_at', 15);

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('Day');
    });

    test('whereMonth', function () {
        $this->builder->from('users')->whereMonth('created_at', 12);

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('Month');
    });

    test('whereYear', function () {
        $this->builder->from('users')->whereYear('created_at', 2023);

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('Year');
    });

    test('whereNested', function () {
        $this->builder->from('users')->whereNested(function ($query) {
            $query->where('name', 'John')->orWhere('name', 'Jane');
        });

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('Nested');
    });

    test('whereExists with closure', function () {
        $this->builder->from('users')->whereExists(function ($query) {
            $query->from('orders')->whereColumn('user_id', 'users.id');
        });

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('Exists');
    });

    test('whereNotExists', function () {
        $this->builder->from('users')->whereNotExists(function ($query) {
            $query->from('orders')->whereColumn('user_id', 'users.id');
        });

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('NotExists');
    });

    test('orWhereExists', function () {
        $this->builder->from('users')
            ->where('active', true)
            ->orWhereExists(function ($query) {
                $query->from('posts')->whereColumn('user_id', 'users.id');
            });

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[1]['boolean'])->toBe('or');
        expect($this->builder->wheres[1]['type'])->toBe('Exists');
    });

    test('orWhereNotExists', function () {
        $this->builder->from('users')
            ->where('active', true)
            ->orWhereNotExists(function ($query) {
                $query->from('bans')->whereColumn('user_id', 'users.id');
            });

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[1]['boolean'])->toBe('or');
        expect($this->builder->wheres[1]['type'])->toBe('NotExists');
    });

    test('whereJsonContains', function () {
        $this->builder->from('users')->whereJsonContains('options->languages', 'en');

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('JsonContains');
    });

    test('whereJsonLength', function () {
        $this->builder->from('users')->whereJsonLength('options->languages', 3);

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('JsonLength');
    });

    test('orderByRaw', function () {
        $this->builder->from('users')->orderByRaw('FIELD(status, ?, ?, ?)', ['active', 'pending', 'inactive']);

        expect($this->builder->orders)->toHaveCount(1);
        expect($this->builder->orders[0]['type'])->toBe('Raw');
    });

    test('orderByDesc', function () {
        $this->builder->from('users')->orderByDesc('created_at');

        expect($this->builder->orders)->toHaveCount(1);
        expect($this->builder->orders[0]['direction'])->toBe('desc');
    });

    test('latest', function () {
        $this->builder->from('users')->latest();

        expect($this->builder->orders)->toHaveCount(1);
        expect($this->builder->orders[0]['column'])->toBe('created_at');
        expect($this->builder->orders[0]['direction'])->toBe('desc');
    });

    test('oldest', function () {
        $this->builder->from('users')->oldest('updated_at');

        expect($this->builder->orders)->toHaveCount(1);
        expect($this->builder->orders[0]['column'])->toBe('updated_at');
        expect($this->builder->orders[0]['direction'])->toBe('asc');
    });

    test('inRandomOrder', function () {
        // Mock the compileRandom method
        $this->grammar->shouldReceive('compileRandom')->with('')->andReturn('RANDOM()');

        $result = $this->builder->from('users')->inRandomOrder();

        expect($result)->toBeInstanceOf(Builder::class);
        expect($this->builder->orders)->toHaveCount(1);
        expect($this->builder->orders[0]['type'])->toBe('Raw');
    });

    test('skip and take', function () {
        $this->builder->from('users')->skip(10)->take(5);

        expect($this->builder->offset)->toBe(10);
        expect($this->builder->limit)->toBe(5);
    });

    test('forPage', function () {
        $this->builder->from('users')->forPage(3, 15);

        expect($this->builder->offset)->toBe(30);
        expect($this->builder->limit)->toBe(15);
    });

    test('rightJoin', function () {
        $this->builder->from('users')->rightJoin('posts', 'users.id', '=', 'posts.user_id');

        expect($this->builder->joins)->toHaveCount(1);
        expect($this->builder->joins[0]->type)->toBe('right');
    });

    test('crossJoin', function () {
        $this->builder->from('users')->crossJoin('roles');

        expect($this->builder->joins)->toHaveCount(1);
        expect($this->builder->joins[0]->type)->toBe('cross');
    });

    test('joinWhere', function () {
        $this->builder->from('users')->joinWhere('posts', 'users.id', '=', 'posts.user_id', function ($join) {
            $join->where('posts.published', true);
        });

        expect($this->builder->joins)->toHaveCount(1);
    });

    test('leftJoinWhere', function () {
        $this->builder->from('users')->leftJoinWhere('posts', 'users.id', '=', 'posts.user_id', function ($join) {
            $join->where('posts.published', true);
        });

        expect($this->builder->joins)->toHaveCount(1);
        expect($this->builder->joins[0]->type)->toBe('left');
    });

    test('union', function () {
        $otherBuilder = clone $this->builder;
        $otherBuilder->from('admins')->select('name');

        $this->builder->from('users')->select('name')->union($otherBuilder);

        expect($this->builder->unions)->toHaveCount(1);
        expect($this->builder->unions[0]['all'])->toBeFalse();
    });

    test('unionAll', function () {
        $otherBuilder = clone $this->builder;
        $otherBuilder->from('admins')->select('name');

        $this->builder->from('users')->select('name')->unionAll($otherBuilder);

        expect($this->builder->unions)->toHaveCount(1);
        expect($this->builder->unions[0]['all'])->toBeTrue();
    });

    test('lock for update', function () {
        $this->builder->from('users')->where('id', 1)->lockForUpdate();

        expect($this->builder->lock)->toBe(true);
    });

    test('shared lock', function () {
        $this->builder->from('users')->where('id', 1)->sharedLock();

        expect($this->builder->lock)->toBe(false);
    });

    test('when condition true', function () {
        $result = $this->builder->from('users')->when(true, function ($query) {
            $query->where('active', true);
        });

        expect($this->builder->wheres)->toHaveCount(1);
        expect($result)->toBe($this->builder);
    });

    test('when condition false with default', function () {
        $this->builder->from('users')->when(false, function ($query) {
            $query->where('active', true);
        }, function ($query) {
            $query->where('active', false);
        });

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['value'])->toBeFalse();
    });

    test('unless condition false', function () {
        $this->builder->from('users')->unless(false, function ($query) {
            $query->where('banned', false);
        });

        expect($this->builder->wheres)->toHaveCount(1);
    });

    test('tap method', function () {
        $tapped = false;

        $result = $this->builder->from('users')->tap(function ($query) use (&$tapped) {
            $tapped = true;
            expect($query)->toBeInstanceOf(Builder::class);
        });

        expect($tapped)->toBeTrue();
        expect($result)->toBe($this->builder);
    });

    test('whereIntegerInRaw', function () {
        $this->builder->from('users')->whereIntegerInRaw('id', [1, 2, 3]);

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('InRaw');
    });

    test('whereIntegerNotInRaw', function () {
        $this->builder->from('users')->whereIntegerNotInRaw('id', [4, 5, 6]);

        expect($this->builder->wheres)->toHaveCount(1);
        expect($this->builder->wheres[0]['type'])->toBe('NotInRaw');
    });

    test('orWhereIntegerInRaw', function () {
        $this->builder->from('users')
            ->where('active', true)
            ->orWhereIntegerInRaw('role_id', [1, 2]);

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[1]['boolean'])->toBe('or');
    });

    test('orWhereIntegerNotInRaw', function () {
        $this->builder->from('users')
            ->where('active', true)
            ->orWhereIntegerNotInRaw('status_id', [3, 4]);

        expect($this->builder->wheres)->toHaveCount(2);
        expect($this->builder->wheres[1]['type'])->toBe('NotInRaw');
    });

    test('havingRaw', function () {
        $this->builder->from('users')
            ->groupBy('status')
            ->havingRaw('COUNT(*) > ?', [5]);

        expect($this->builder->havings)->toHaveCount(1);
        expect($this->builder->havings[0]['type'])->toBe('Raw');
    });

    test('orHavingRaw', function () {
        $this->builder->from('users')
            ->groupBy('status')
            ->having('status', 'active')
            ->orHavingRaw('COUNT(*) < ?', [10]);

        expect($this->builder->havings)->toHaveCount(2);
        expect($this->builder->havings[1]['boolean'])->toBe('or');
    });

    test('reorder clears existing orders', function () {
        $this->builder->from('users')
            ->orderBy('name')
            ->orderBy('email')
            ->reorder();

        expect($this->builder->orders)->toBeNull();
    });

    test('reorder with new column', function () {
        $this->builder->from('users')
            ->orderBy('name')
            ->reorder('created_at', 'desc');

        expect($this->builder->orders)->toHaveCount(1);
        expect($this->builder->orders[0]['column'])->toBe('created_at');
    });
});

test('increment method', function () {
    $this->connection->shouldReceive('update')->andReturn(1);
    $this->connection->shouldReceive('raw')->andReturnUsing(function($value) {
        return new Expression($value);
    });
    $this->grammar->shouldReceive('wrap')->andReturn('`points`');
    $this->grammar->shouldReceive('compileUpdate')->andReturn('UPDATE users SET points = points + ?');

    $result = $this->builder->from('users')->where('id', 1)->increment('points', 5);

    expect($result)->toBe(1);
});

test('decrement method', function () {
    $this->connection->shouldReceive('update')->andReturn(1);
    $this->connection->shouldReceive('raw')->andReturnUsing(function($value) {
        return new Expression($value);
    });
    $this->grammar->shouldReceive('wrap')->andReturn('`points`');
    $this->grammar->shouldReceive('compileUpdate')->andReturn('UPDATE users SET points = points - ?');

    $result = $this->builder->from('users')->where('id', 1)->decrement('points', 3);

    expect($result)->toBe(1);
});

test('truncate method', function () {
    $this->connection->shouldReceive('statement')->andReturn(true);
    $this->grammar->shouldReceive('compileTruncate')->andReturn(['TRUNCATE TABLE users' => []]);

    // Now truncate returns boolean after fixing the implementation
    $result = $this->builder->from('users')->truncate();

    expect($result)->toBeTrue();
});

test('newQuery creates new instance', function () {
    $newBuilder = $this->builder->newQuery();

    expect($newBuilder)->toBeInstanceOf(Builder::class);
    expect($newBuilder)->not->toBe($this->builder);
});

test('clone preserves all properties', function () {
    $this->builder->from('users')
        ->select('name')
        ->where('active', true)
        ->orderBy('name')
        ->limit(10);

    $cloned = clone $this->builder;

    expect($cloned->from)->toBe('users');
    expect($cloned->columns)->toBe(['name']);
    expect($cloned->wheres)->toHaveCount(1);
    expect($cloned->orders)->toHaveCount(1);
    expect($cloned->limit)->toBe(10);
});