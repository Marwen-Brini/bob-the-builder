<?php

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\Builder;
use Bob\Query\Grammar;

beforeEach(function () {
    $this->grammar = new class extends Grammar
    {
        protected array $operators = [
            '=', '<', '>', '<=', '>=', '<>', '!=',
            'like', 'like binary', 'not like', 'ilike',
            '&', '|', '^', '<<', '>>',
            'rlike', 'regexp', 'not regexp',
            '~', '~*', '!~', '!~*', 'similar to',
            'not similar to', 'not ilike', '~~*', '!~~*',
        ];
    };

    $this->processor = Mockery::mock(\Bob\Query\Processor::class);
    $this->connection = Mockery::mock(Connection::class);
    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new Builder($this->connection);
});

afterEach(function () {
    Mockery::close();
});

test('compiles select all', function () {
    $this->builder->from('users');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users"');
});

test('compiles select with columns', function () {
    $this->builder->select(['id', 'name'])->from('users');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select "id", "name" from "users"');
});

test('compiles select with distinct', function () {
    $this->builder->distinct()->select(['name'])->from('users');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select distinct "name" from "users"');
});

test('compiles select with table prefix', function () {
    $this->grammar->setTablePrefix('wp_');
    $this->builder->from('posts');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "wp_posts"');
});

test('compiles where clauses', function () {
    $this->builder->from('users')->where('id', '=', 1);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where "id" = ?');
});

test('compiles multiple where clauses', function () {
    $this->builder->from('users')
        ->where('id', '=', 1)
        ->where('name', '=', 'John');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where "id" = ? and "name" = ?');
});

test('compiles or where clauses', function () {
    $this->builder->from('users')
        ->where('id', '=', 1)
        ->orWhere('name', '=', 'John');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where "id" = ? or "name" = ?');
});

test('compiles where in clauses', function () {
    $this->builder->from('users')->whereIn('id', [1, 2, 3]);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where "id" in (?, ?, ?)');
});

test('compiles where not in clauses', function () {
    $this->builder->from('users')->whereNotIn('id', [1, 2, 3]);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where "id" not in (?, ?, ?)');
});

test('compiles where null clauses', function () {
    $this->builder->from('users')->whereNull('email');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where "email" is null');
});

test('compiles where not null clauses', function () {
    $this->builder->from('users')->whereNotNull('email');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where "email" is not null');
});

test('compiles where between clauses', function () {
    $this->builder->from('users')->whereBetween('age', [18, 65]);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where "age" between ? and ?');
});

test('compiles where not between clauses', function () {
    $this->builder->from('users')->whereNotBetween('age', [18, 65]);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where "age" not between ? and ?');
});

test('compiles raw where clauses', function () {
    $this->builder->from('users')->whereRaw('id = ? and status = ?', [1, 'active']);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" where id = ? and status = ?');
});

test('compiles order by clauses', function () {
    $this->builder->from('users')->orderBy('name');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" order by "name" asc');
});

test('compiles order by desc clauses', function () {
    $this->builder->from('users')->orderBy('name', 'desc');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" order by "name" desc');
});

test('compiles multiple order by clauses', function () {
    $this->builder->from('users')->orderBy('name')->orderBy('email', 'desc');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" order by "name" asc, "email" desc');
});

test('compiles group by clauses', function () {
    $this->builder->from('users')->groupBy('status');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" group by "status"');
});

test('compiles multiple group by clauses', function () {
    $this->builder->from('users')->groupBy(['status', 'role']);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" group by "status", "role"');
});

test('compiles having clauses', function () {
    $this->builder->from('users')
        ->groupBy('status')
        ->having('count', '>', 5);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" group by "status" having "count" > ?');
});

test('compiles limit clauses', function () {
    $this->builder->from('users')->limit(10);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" limit 10');
});

test('compiles offset clauses', function () {
    $this->builder->from('users')->offset(20);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" offset 20');
});

test('compiles limit and offset clauses', function () {
    $this->builder->from('users')->limit(10)->offset(20);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" limit 10 offset 20');
});

test('compiles insert statements', function () {
    $sql = $this->grammar->compileInsert($this->builder->from('users'), [
        'name' => 'John',
        'email' => 'john@example.com',
    ]);

    expect($sql)->toBe('insert into "users" ("name", "email") values (?, ?)');
});

test('compiles insert get id statements', function () {
    $sql = $this->grammar->compileInsertGetId($this->builder->from('users'), [
        'name' => 'John',
        'email' => 'john@example.com',
    ], 'id');

    expect($sql)->toBe('insert into "users" ("name", "email") values (?, ?)');
});

test('compiles update statements', function () {
    $this->builder->from('users')->where('id', 1);
    $sql = $this->grammar->compileUpdate($this->builder, [
        'name' => 'Jane',
        'email' => 'jane@example.com',
    ]);

    expect($sql)->toBe('update "users" set "name" = ?, "email" = ? where "id" = ?');
});

test('compiles delete statements', function () {
    $this->builder->from('users')->where('id', 1);
    $sql = $this->grammar->compileDelete($this->builder);

    expect($sql)->toBe('delete from "users" where "id" = ?');
});

test('compiles truncate statements', function () {
    $sql = $this->grammar->compileTruncate($this->builder->from('users'));

    expect($sql)->toBe(['truncate table "users"' => []]);
});

test('wraps columns correctly', function () {
    $wrapped = $this->grammar->wrap('users.name');
    expect($wrapped)->toBe('"users"."name"');
});

test('wraps aliased columns', function () {
    $wrapped = $this->grammar->wrap('users.name as username');
    expect($wrapped)->toBe('"users"."name" as "username"');
});

test('wraps tables correctly', function () {
    $wrapped = $this->grammar->wrapTable('users');
    expect($wrapped)->toBe('"users"');
});

test('wraps tables with alias', function () {
    $wrapped = $this->grammar->wrapTable('users as u');
    expect($wrapped)->toBe('"users" as "u"');
});

test('handles expressions without wrapping', function () {
    $expression = new Expression('count(*)');
    $wrapped = $this->grammar->wrap($expression);
    expect($wrapped)->toBe('count(*)');
});

test('columnizes array of columns', function () {
    $columns = ['id', 'name', 'email'];
    $result = $this->grammar->columnize($columns);
    expect($result)->toBe('"id", "name", "email"');
});

test('parameterizes values', function () {
    $values = [1, 2, 3, 4, 5];
    $result = $this->grammar->parameterize($values);
    expect($result)->toBe('?, ?, ?, ?, ?');
});

test('compiles exists query', function () {
    $this->builder->from('users')->where('id', 1);
    $sql = $this->grammar->compileExists($this->builder);

    expect($sql)->toBe('select exists(select * from "users" where "id" = ?) as "exists"');
});

test('gets operators', function () {
    $operators = $this->grammar->getOperators();
    expect($operators)->toBeArray();
    expect($operators)->toContain('=');
    expect($operators)->toContain('>');
    expect($operators)->toContain('<');
    expect($operators)->toContain('like');
});

test('compiles aggregate count', function () {
    $this->builder->from('users');
    $this->builder->aggregate = ['function' => 'count', 'columns' => ['*']];
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select count(*) as aggregate from "users"');
});

test('compiles aggregate with distinct', function () {
    $this->builder->from('users')->distinct();
    $this->builder->aggregate = ['function' => 'count', 'columns' => ['email']];
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select count(distinct "email") as aggregate from "users"');
});

test('compiles inner join', function () {
    $this->builder->from('users')
        ->join('posts', 'users.id', '=', 'posts.user_id');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" inner join "posts" on "users"."id" = "posts"."user_id"');
});

test('compiles left join', function () {
    $this->builder->from('users')
        ->leftJoin('posts', 'users.id', '=', 'posts.user_id');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" left join "posts" on "users"."id" = "posts"."user_id"');
});

test('compiles cross join', function () {
    $this->builder->from('users')->crossJoin('posts');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "users" cross join "posts" on');
});

test('compiles raw expressions in select', function () {
    $this->builder->from('users')
        ->select([new Expression('count(*) as user_count'), 'status']);
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select count(*) as user_count, "status" from "users"');
});

test('supports database prefix', function () {
    $this->grammar->setTablePrefix('test_');
    $this->builder->from('users');
    $sql = $this->grammar->compileSelect($this->builder);

    expect($sql)->toBe('select * from "test_users"');
});

test('compiles increment', function () {
    $this->builder->from('users')->where('id', 1);
    $sql = $this->grammar->compileUpdate($this->builder, ['votes' => new Expression('votes + 1')]);

    expect($sql)->toContain('update "users" set "votes" = votes + 1');
});

test('compiles decrement', function () {
    $this->builder->from('users')->where('id', 1);
    $sql = $this->grammar->compileUpdate($this->builder, ['votes' => new Expression('votes - 1')]);

    expect($sql)->toContain('update "users" set "votes" = votes - 1');
});
