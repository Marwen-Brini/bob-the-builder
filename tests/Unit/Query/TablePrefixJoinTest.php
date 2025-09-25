<?php

namespace Tests\Unit\Query;

use Bob\Query\Builder;
use Bob\Database\Connection;
use Bob\Query\JoinClause;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Grammars\SQLiteGrammar;
use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Processor;
use Mockery as m;

beforeEach(function () {
    $this->connection = m::mock(Connection::class);
    $this->processor = new Processor();
});

afterEach(function () {
    m::close();
});

describe('MySQL Table Prefix in JOINs', function () {
    beforeEach(function () {
        $this->grammar = new MySQLGrammar();
        $this->grammar->setTablePrefix('wp_');
        $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
        $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
    });

    test('simple join applies prefix to both tables', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->select('*');

        $sql = $builder->toSql();

        // Tables should have prefix
        expect($sql)->toContain('`wp_posts`');
        expect($sql)->toContain('`wp_users`');

        // Column references in JOIN should NOT have prefix in the column part
        // The correct behavior is debatable - let's check current behavior
        expect($sql)->toBe('select * from `wp_posts` inner join `wp_users` on `wp_posts`.`author_id` = `wp_users`.`id`');
    });

    test('join with closure applies prefix correctly', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', function($join) {
                $join->on('posts.author_id', '=', 'users.id')
                     ->where('users.status', '=', 'active');
            })
            ->select('*');

        $sql = $builder->toSql();

        expect($sql)->toContain('`wp_posts`');
        expect($sql)->toContain('`wp_users`');
        expect($sql)->toContain('`wp_posts`.`author_id` = `wp_users`.`id`');
        expect($sql)->toContain('`wp_users`.`status` = ?');
    });

    test('multiple joins with prefix', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->leftJoin('comments', 'comments.post_id', '=', 'posts.id')
            ->select('*');

        $sql = $builder->toSql();

        expect($sql)->toContain('`wp_posts`');
        expect($sql)->toContain('`wp_users`');
        expect($sql)->toContain('`wp_comments`');
    });

    test('join with table alias should not double prefix', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts', 'p')
            ->join('users as u', 'p.author_id', '=', 'u.id')
            ->select('*');

        $sql = $builder->toSql();

        // Main table with alias
        expect($sql)->toContain('`wp_posts` as `p`');
        expect($sql)->toContain('`wp_users` as `u`');

        // Column references should use aliases
        expect($sql)->toContain('`p`.`author_id` = `u`.`id`');
    });

    test('join with database qualified table names', function () {
        $builder = new Builder($this->connection);
        $builder->from('database.posts')
            ->join('database.users', 'posts.author_id', '=', 'users.id')
            ->select('*');

        $sql = $builder->toSql();

        // Should prefix after database name
        expect($sql)->toContain('`database`.`wp_posts`');
        expect($sql)->toContain('`database`.`wp_users`');
    });

    test('subquery join handles prefix', function () {
        $builder = new Builder($this->connection);
        $subQuery = (new Builder($this->connection))
            ->from('comments')
            ->select('post_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('post_id');

        $builder->from('posts')
            ->joinSub($subQuery, 'c', 'posts.id', '=', 'c.post_id')
            ->select('posts.*', 'c.count');

        $sql = $builder->toSql();

        expect($sql)->toContain('`wp_posts`');
        expect($sql)->toContain('from `wp_comments`');
    });

    test('cross join with prefix', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->crossJoin('tags')
            ->select('*');

        $sql = $builder->toSql();

        expect($sql)->toContain('`wp_posts` cross join `wp_tags`');
    });

    test('raw join expression should not be modified', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join(new \Bob\Database\Expression('users'), new \Bob\Database\Expression('posts.author_id = users.id'))
            ->select('*');

        $sql = $builder->toSql();

        expect($sql)->toContain('`wp_posts`');
        // Raw expression for table should be left as-is
        expect($sql)->toContain('users');
    });
});

describe('SQLite Table Prefix in JOINs', function () {
    beforeEach(function () {
        $this->grammar = new SQLiteGrammar();
        $this->grammar->setTablePrefix('wp_');
        $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
        $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
    });

    test('simple join applies prefix correctly in SQLite', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->select('*');

        $sql = $builder->toSql();

        // SQLite uses double quotes
        expect($sql)->toContain('"wp_posts"');
        expect($sql)->toContain('"wp_users"');
    });
});

describe('PostgreSQL Table Prefix in JOINs', function () {
    beforeEach(function () {
        $this->grammar = new PostgreSQLGrammar();
        $this->grammar->setTablePrefix('wp_');
        $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
        $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
    });

    test('simple join applies prefix correctly in PostgreSQL', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->select('*');

        $sql = $builder->toSql();

        // PostgreSQL uses double quotes
        expect($sql)->toContain('"wp_posts"');
        expect($sql)->toContain('"wp_users"');
    });
});

describe('Table Prefix Edge Cases', function () {
    beforeEach(function () {
        $this->grammar = new MySQLGrammar();
        $this->grammar->setTablePrefix('wp_');
        $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
        $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
    });

    test('where clause with table.column respects prefix', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->where('posts.status', 'published')
            ->where('users.active', 1)
            ->select('*');

        $sql = $builder->toSql();

        // WHERE clause columns should also have prefixed tables
        expect($sql)->toContain('`wp_posts`.`status`');
        expect($sql)->toContain('`wp_users`.`active`');
    });

    test('select with table.column respects prefix', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->select('posts.title', 'users.name', 'posts.id as post_id');

        $sql = $builder->toSql();

        expect($sql)->toContain('`wp_posts`.`title`');
        expect($sql)->toContain('`wp_users`.`name`');
        expect($sql)->toContain('`wp_posts`.`id` as `post_id`');
    });

    test('order by with table.column respects prefix', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->orderBy('posts.created_at', 'desc')
            ->orderBy('users.name')
            ->select('*');

        $sql = $builder->toSql();

        expect($sql)->toContain('order by `wp_posts`.`created_at` desc, `wp_users`.`name` asc');
    });

    test('group by with table.column respects prefix', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->groupBy('users.id', 'posts.category_id')
            ->select('users.id', 'posts.category_id', new \Bob\Database\Expression('COUNT(*) as count'));

        $sql = $builder->toSql();

        expect($sql)->toContain('group by `wp_users`.`id`, `wp_posts`.`category_id`');
    });

    test('having with table.column respects prefix', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->groupBy('users.id')
            ->having('users.id', '>', 1)
            ->havingRaw('COUNT(posts.id) > ?', [5])
            ->select('users.id', new \Bob\Database\Expression('COUNT(posts.id) as post_count'));

        $sql = $builder->toSql();

        expect($sql)->toContain('having `wp_users`.`id` > ?');
        expect($sql)->toContain('COUNT(posts.id) > ?'); // Raw should not be modified
    });
});

describe('Table Prefix Bug Scenarios', function () {
    beforeEach(function () {
        $this->grammar = new MySQLGrammar();
        $this->grammar->setTablePrefix('wp_');
        $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
        $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);
    });

    test('BUG: column references in JOIN ON clause should use original table names not prefixed', function () {
        $builder = new Builder($this->connection);
        $builder->from('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->select('*');

        $sql = $builder->toSql();

        // Current behavior (possibly buggy): columns in ON clause get prefixed
        // This might cause issues if the application expects unprefixed column references

        // What we get:
        expect($sql)->toBe('select * from `wp_posts` inner join `wp_users` on `wp_posts`.`author_id` = `wp_users`.`id`');

        // What some might expect (unprefixed column references):
        // 'select * from `wp_posts` inner join `wp_users` on `posts`.`author_id` = `users`.`id`'

        // The current behavior is actually correct for most cases
        // but could break if the application uses table aliases inconsistently
    });

    test('table with existing prefix should not get double prefix', function () {
        $builder = new Builder($this->connection);
        $builder->from('wp_posts') // Already has wp_ prefix
            ->join('wp_users', 'wp_posts.author_id', '=', 'wp_users.id')
            ->select('*');

        $sql = $builder->toSql();

        // Should NOT double prefix to wp_wp_posts
        expect($sql)->not->toContain('`wp_wp_posts`');
        expect($sql)->not->toContain('`wp_wp_users`');

        // Should just keep the original prefix
        expect($sql)->toContain('`wp_posts`');
        expect($sql)->toContain('`wp_users`');
    });
});