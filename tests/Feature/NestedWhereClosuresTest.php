<?php

use Bob\Database\Connection;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create test table
    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY,
            title TEXT,
            content TEXT,
            status TEXT
        )
    ');

    // Insert test data
    $this->connection->table('posts')->insert([
        ['title' => 'Hello World', 'content' => 'This is a test post', 'status' => 'published'],
        ['title' => 'Test Post', 'content' => 'Hello there', 'status' => 'draft'],
        ['title' => 'Another Post', 'content' => 'World of tests', 'status' => 'published'],
    ]);
});

test('nested where closure generates correct SQL', function () {
    $builder = $this->connection->table('posts');

    $search = 'Hello';
    $builder->where(function ($q) use ($search) {
        $q->where('title', 'LIKE', "%$search%")
            ->orWhere('content', 'LIKE', "%$search%");
    });

    $sql = $builder->toSql();

    // Should generate: select * from `posts` where (`title` LIKE ? or `content` LIKE ?)
    expect($sql)->toContain('where (');
    expect($sql)->toContain('or');

    // Execute to ensure it works
    $results = $builder->get();
    expect($results)->toHaveCount(2); // "Hello World" and "Hello there" posts
});

test('multiple nested where closures work correctly', function () {
    $builder = $this->connection->table('posts');

    $builder->where('status', 'published')
        ->where(function ($q) {
            $q->where('title', 'LIKE', '%World%')
                ->orWhere('content', 'LIKE', '%World%');
        });

    $sql = $builder->toSql();

    // Should have both regular where and nested where
    // SQLite uses double quotes, MySQL uses backticks
    expect($sql)->toMatch('/where ["`]status["`] = \?/');
    expect($sql)->toContain('and (');

    $results = $builder->get();
    expect($results)->toHaveCount(2); // Both "Hello World" and "Another Post" (World of tests)
});

test('deeply nested where closures', function () {
    $builder = $this->connection->table('posts');

    $builder->where(function ($q) {
        $q->where('status', 'published')
            ->where(function ($q2) {
                $q2->where('title', 'LIKE', '%Hello%')
                    ->orWhere('title', 'LIKE', '%Another%');
            });
    });

    $results = $builder->get();
    expect($results)->toHaveCount(2); // "Hello World" and "Another Post"
});

test('nested where with orWhere works', function () {
    $builder = $this->connection->table('posts');

    $builder->where('id', '>', 0)
        ->orWhere(function ($q) {
            $q->where('title', 'Test Post')
                ->where('status', 'draft');
        });

    $sql = $builder->toSql();
    expect($sql)->toContain('or (');

    $results = $builder->get();
    expect($results)->toHaveCount(3); // All posts match
});

test('empty nested where closure is ignored', function () {
    $builder = $this->connection->table('posts');

    $builder->where('status', 'published')
        ->where(function ($q) {
            // Empty closure - should be ignored
        });

    $sql = $builder->toSql();

    // Should not have nested parentheses for empty closure
    expect($sql)->not->toContain('()');

    $results = $builder->get();
    expect($results)->toHaveCount(2);
});

test('nested where with different operators', function () {
    $builder = $this->connection->table('posts');

    $builder->where(function ($q) {
        $q->where('id', '>=', 2)
            ->where('id', '<=', 3);
    })->orWhere(function ($q) {
        $q->where('title', 'LIKE', '%Hello%');
    });

    $results = $builder->get();
    expect($results)->toHaveCount(3); // Posts 2, 3, and "Hello World"
});

test('nested whereIn and whereNotIn', function () {
    $builder = $this->connection->table('posts');

    $builder->where(function ($q) {
        $q->whereIn('id', [1, 2])
            ->orWhereNotIn('status', ['archived']);
    });

    $results = $builder->get();
    expect($results)->toHaveCount(3); // All posts match
});

test('bindings are properly handled in nested closures', function () {
    $builder = $this->connection->table('posts');

    $search1 = 'Hello';
    $search2 = 'World';

    $builder->where(function ($q) use ($search1, $search2) {
        $q->where('title', 'LIKE', "%$search1%")
            ->orWhere('content', 'LIKE', "%$search2%");
    });

    $bindings = $builder->getBindings();
    // getBindings() returns a flat array for compatibility
    expect($bindings)->toHaveCount(2);
    expect($bindings[0])->toBe('%Hello%');
    expect($bindings[1])->toBe('%World%');

    $results = $builder->get();
    expect($results)->toHaveCount(2); // "Hello World" and "Another Post" (has World)
});
