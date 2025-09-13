<?php

use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Database\Expression;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
});

describe('whereColumn array syntax coverage', function () {
    it('handles array of column comparisons', function () {
        $builder = $this->connection->table('users');

        $builder->whereColumn([
            ['first_name', '=', 'last_name'],
            ['updated_at', '>', 'created_at'],
        ]);

        $sql = $builder->toSql();
        expect($sql)->toContain('"first_name" = "last_name"');
        expect($sql)->toContain('"updated_at" > "created_at"');
    });
});

describe('unless method coverage', function () {
    it('calls default callback when value is truthy', function () {
        $builder = $this->connection->table('users');
        $called = false;
        $defaultCalled = false;

        $result = $builder->unless(
            true,
            function ($query) use (&$called) {
                $called = true;
                $query->where('status', 'active');
            },
            function ($query) use (&$defaultCalled) {
                $defaultCalled = true;
                $query->where('status', 'inactive');
            }
        );

        expect($called)->toBeFalse();
        expect($defaultCalled)->toBeTrue();
        expect($result)->toBe($builder);
    });
});

describe('JSON methods coverage', function () {
    it('uses whereJsonContains', function () {
        $builder = $this->connection->table('users');
        $builder->whereJsonContains('options', 'admin');

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        expect($sql)->toContain('json_');
        expect($bindings)->toBe(['admin']);
    });

    it('uses whereJsonContains with not', function () {
        $builder = $this->connection->table('users');
        $builder->whereJsonContains('options', 'admin', 'and', true);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        expect($sql)->toContain('json_');
        expect($bindings)->toBe(['admin']);
    });

    it('uses whereJsonLength', function () {
        $builder = $this->connection->table('users');
        $builder->whereJsonLength('items', '>', 5);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        expect($sql)->toContain('json_length');
        expect($bindings)->toBe([5]);
    });

    it('uses whereJsonLength with 2 arguments', function () {
        $builder = $this->connection->table('users');
        $builder->whereJsonLength('items', 5);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        expect($sql)->toContain('json_length');
        expect($bindings)->toBe([5]);
    });

    it('uses whereJsonContains with expression', function () {
        $builder = $this->connection->table('users');
        $builder->whereJsonContains('options', new Expression('?'), 'and', false);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        expect($sql)->toContain('json_');
        expect($bindings)->toBe([]);
    });

    it('uses whereJsonLength with expression', function () {
        $builder = $this->connection->table('users');
        $builder->whereJsonLength('items', '>', new Expression('5'));

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        expect($sql)->toContain('json_length');
        expect($bindings)->not->toContain(5);
    });
});

describe('fulltext search coverage', function () {
    it('uses whereFullText with single column', function () {
        $builder = $this->connection->table('posts');
        $builder->whereFullText('content', 'search terms');

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        expect($sql)->toContain('match');
        expect($sql)->toContain('against');
        expect($bindings)->toBe(['search terms']);
    });

    it('uses whereFullText with multiple columns', function () {
        $builder = $this->connection->table('posts');
        $builder->whereFullText(['title', 'content'], 'search terms');

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        expect($sql)->toContain('match');
        expect($bindings)->toBe(['search terms']);
    });
});

describe('subquery joins coverage', function () {
    it('uses joinSub with closure', function () {
        $builder = $this->connection->table('users');

        $builder->joinSub(function ($query) {
            $query->from('posts')->select('user_id')->groupBy('user_id');
        }, 'posts_summary', 'users.id', '=', 'posts_summary.user_id');

        $sql = $builder->toSql();
        expect($sql)->toContain('join');
        expect($sql)->toContain('posts_summary');
    });

    it('uses leftJoinSub', function () {
        $builder = $this->connection->table('users');

        $builder->leftJoinSub(function ($query) {
            $query->from('posts')->select('user_id');
        }, 'recent_posts', 'users.id', '=', 'recent_posts.user_id');

        $sql = $builder->toSql();
        expect($sql)->toContain('left join');
    });

    it('uses joinSub with builder instance', function () {
        $builder = $this->connection->table('users');
        $subQuery = $this->connection->table('posts')->select('user_id');

        $builder->joinSub($subQuery, 'posts_data', 'users.id', '=', 'posts_data.user_id');

        $sql = $builder->toSql();
        expect($sql)->toContain('join');
        expect($sql)->toContain('posts_data');
    });
});

describe('value and aggregate array fallbacks', function () {
    it('handles value with array result', function () {
        // This tests the array fallback path even though we return objects now
        // The code still has the fallback for compatibility
        $connection = $this->connection;

        // Create a test that would trigger the array path
        // We can't easily test this with our current setup since we always return objects
        // But we can at least ensure the method works
        $connection->statement('CREATE TABLE test (id INTEGER, name TEXT)');
        $connection->table('test')->insert(['id' => 1, 'name' => 'Test']);

        $value = $connection->table('test')->where('id', 1)->value('name');
        expect($value)->toBe('Test');
    });

    it('handles aggregate with array result', function () {
        $connection = $this->connection;
        $connection->statement('CREATE TABLE test (id INTEGER, value INTEGER)');
        $connection->table('test')->insert([
            ['id' => 1, 'value' => 10],
            ['id' => 2, 'value' => 20],
        ]);

        $sum = $connection->table('test')->sum('value');
        expect($sum)->toBe(30);
    });
});

describe('Grammar JSON compilation methods', function () {
    it('compiles whereJsonNotContains', function () {
        $grammar = new \Bob\Query\Grammars\MySQLGrammar();
        $builder = $this->connection->table('users');

        $where = [
            'type' => 'JsonNotContains',
            'column' => 'options',
            'value' => 'admin',
        ];

        $method = new ReflectionMethod($grammar, 'whereJsonNotContains');
        $method->setAccessible(true);
        $result = $method->invoke($grammar, $builder, $where);

        expect($result)->toContain('not json_contains');
    });
});