<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;

beforeEach(function () {
    // Create an in-memory SQLite database for testing
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'qt_',
    ]);

    // Create test tables
    $this->connection->statement('CREATE TABLE qt_terms (term_id INTEGER, name TEXT)');
    $this->connection->statement('CREATE TABLE qt_term_taxonomy (term_id INTEGER, taxonomy TEXT)');
    $this->connection->statement('CREATE TABLE qt_posts (id INTEGER, title TEXT, author_id INTEGER)');
    $this->connection->statement('CREATE TABLE qt_users (id INTEGER, name TEXT, status TEXT)');

    // Insert test data
    $this->connection->table('terms')->insert(['term_id' => 1, 'name' => 'Technology']);
    $this->connection->table('term_taxonomy')->insert(['term_id' => 1, 'taxonomy' => 'category']);
    $this->connection->table('posts')->insert(['id' => 1, 'title' => 'Test Post', 'author_id' => 1]);
    $this->connection->table('users')->insert(['id' => 1, 'name' => 'John', 'status' => 'active']);
});

test('join with where on joined table does not double prefix table name', function () {
    $sql = $this->connection->table('terms')
        ->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
        ->where('term_taxonomy.taxonomy', 'category')
        ->toSql();

    // Should NOT have double prefix (qt_qt_term_taxonomy)
    expect($sql)->not->toContain('qt_qt_');

    // Should have properly prefixed tables
    expect($sql)->toContain('qt_terms');
    expect($sql)->toContain('qt_term_taxonomy');

    // Execute the query to ensure it works
    $results = $this->connection->table('terms')
        ->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
        ->where('term_taxonomy.taxonomy', 'category')
        ->get();

    expect($results)->toHaveCount(1);
    expect($results[0]->name)->toBe('Technology');
})->group('integration', 'table-prefix');

test('join with table alias works', function () {
    $sql = $this->connection->table('posts as p')
        ->join('users as u', 'p.author_id', '=', 'u.id')
        ->where('u.status', 'active')
        ->where('p.title', 'Test Post')
        ->toSql();

    // Check that the table names are prefixed but aliases are not
    expect($sql)->toContain('qt_posts');
    expect($sql)->toContain('qt_users');

    // Check that aliases p and u are used in conditions without prefix
    expect($sql)->toContain('"p"."author_id"');
    expect($sql)->toContain('"u"."id"');
    expect($sql)->toContain('"u"."status"');
    expect($sql)->toContain('"p"."title"');

    // Execute to ensure it works
    $results = $this->connection->table('posts as p')
        ->join('users as u', 'p.author_id', '=', 'u.id')
        ->where('u.status', 'active')
        ->where('p.title', 'Test Post')
        ->get();

    expect($results)->toHaveCount(1);
})->group('integration', 'table-prefix');

test('multiple joins with where conditions', function () {
    // Create additional test data
    $this->connection->statement('CREATE TABLE qt_comments (id INTEGER, post_id INTEGER, user_id INTEGER, content TEXT)');
    $this->connection->table('comments')->insert(['id' => 1, 'post_id' => 1, 'user_id' => 1, 'content' => 'Great post!']);

    $sql = $this->connection->table('posts')
        ->join('users', 'posts.author_id', '=', 'users.id')
        ->join('comments', 'posts.id', '=', 'comments.post_id')
        ->where('users.status', 'active')
        ->where('comments.content', 'like', '%Great%')
        ->toSql();

    // Should not have any double prefixes
    expect($sql)->not->toContain('qt_qt_');

    // Execute the query
    $results = $this->connection->table('posts')
        ->join('users', 'posts.author_id', '=', 'users.id')
        ->join('comments', 'posts.id', '=', 'comments.post_id')
        ->where('users.status', 'active')
        ->where('comments.content', 'like', '%Great%')
        ->get();

    expect($results)->toHaveCount(1);
})->group('integration', 'table-prefix');

test('join with subquery and prefix', function () {
    $subquery = $this->connection->table('term_taxonomy')
        ->where('taxonomy', 'category')
        ->select('term_id');

    $results = $this->connection->table('terms')
        ->whereIn('term_id', $subquery)
        ->get();

    expect($results)->toHaveCount(1);
    expect($results[0]->name)->toBe('Technology');
})->group('integration', 'table-prefix');

test('left join with prefix', function () {
    $sql = $this->connection->table('posts')
        ->leftJoin('users', 'posts.author_id', '=', 'users.id')
        ->where('users.status', 'active')
        ->orWhereNull('users.id')
        ->toSql();

    // Should not have double prefixes
    expect($sql)->not->toContain('qt_qt_');

    $results = $this->connection->table('posts')
        ->leftJoin('users', 'posts.author_id', '=', 'users.id')
        ->where('users.status', 'active')
        ->get();

    expect($results)->toHaveCount(1);
})->group('integration', 'table-prefix');

test('complex join with closure and prefix', function () {
    $sql = $this->connection->table('posts')
        ->join('users', function ($join) {
            $join->on('posts.author_id', '=', 'users.id')
                ->where('users.status', '=', 'active');
        })
        ->toSql();

    // Should not have double prefixes
    expect($sql)->not->toContain('qt_qt_');

    $results = $this->connection->table('posts')
        ->join('users', function ($join) {
            $join->on('posts.author_id', '=', 'users.id')
                ->where('users.status', '=', 'active');
        })
        ->get();

    expect($results)->toHaveCount(1);
})->group('integration', 'table-prefix');
