<?php

namespace Bob\Tests\Integration;

use Bob\Database\Connection;
use Bob\Query\Builder;
use PHPUnit\Framework\TestCase;

class TablePrefixJoinTest extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function testJoinWithWhereOnJoinedTableDoesNotDoublePrefixTableName()
    {
        $sql = $this->connection->table('terms')
            ->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
            ->where('term_taxonomy.taxonomy', 'category')
            ->toSql();

        // Should NOT have double prefix (qt_qt_term_taxonomy)
        $this->assertStringNotContainsString('qt_qt_', $sql);

        // Should have properly prefixed tables
        $this->assertStringContainsString('qt_terms', $sql);
        $this->assertStringContainsString('qt_term_taxonomy', $sql);

        // Execute the query to ensure it works
        $results = $this->connection->table('terms')
            ->join('term_taxonomy', 'terms.term_id', '=', 'term_taxonomy.term_id')
            ->where('term_taxonomy.taxonomy', 'category')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Technology', $results[0]->name);
    }

    public function testJoinWithTableAliasWorks()
    {
        $sql = $this->connection->table('posts as p')
            ->join('users as u', 'p.author_id', '=', 'u.id')
            ->where('u.status', 'active')
            ->where('p.title', 'Test Post')
            ->toSql();

        // Check that the table names are prefixed but aliases are not
        $this->assertStringContainsString('qt_posts', $sql);
        $this->assertStringContainsString('qt_users', $sql);

        // Check that aliases p and u are used in conditions without prefix
        $this->assertStringContainsString('"p"."author_id"', $sql);
        $this->assertStringContainsString('"u"."id"', $sql);
        $this->assertStringContainsString('"u"."status"', $sql);
        $this->assertStringContainsString('"p"."title"', $sql);

        // Execute to ensure it works
        $results = $this->connection->table('posts as p')
            ->join('users as u', 'p.author_id', '=', 'u.id')
            ->where('u.status', 'active')
            ->where('p.title', 'Test Post')
            ->get();

        $this->assertCount(1, $results);
    }

    public function testMultipleJoinsWithWhereConditions()
    {
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
        $this->assertStringNotContainsString('qt_qt_', $sql);

        // Execute the query
        $results = $this->connection->table('posts')
            ->join('users', 'posts.author_id', '=', 'users.id')
            ->join('comments', 'posts.id', '=', 'comments.post_id')
            ->where('users.status', 'active')
            ->where('comments.content', 'like', '%Great%')
            ->get();

        $this->assertCount(1, $results);
    }

    public function testJoinWithSubqueryAndPrefix()
    {
        $subquery = $this->connection->table('term_taxonomy')
            ->where('taxonomy', 'category')
            ->select('term_id');

        $results = $this->connection->table('terms')
            ->whereIn('term_id', $subquery)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Technology', $results[0]->name);
    }

    public function testLeftJoinWithPrefix()
    {
        $sql = $this->connection->table('posts')
            ->leftJoin('users', 'posts.author_id', '=', 'users.id')
            ->where('users.status', 'active')
            ->orWhereNull('users.id')
            ->toSql();

        // Should not have double prefixes
        $this->assertStringNotContainsString('qt_qt_', $sql);

        $results = $this->connection->table('posts')
            ->leftJoin('users', 'posts.author_id', '=', 'users.id')
            ->where('users.status', 'active')
            ->get();

        $this->assertCount(1, $results);
    }

    public function testComplexJoinWithClosureAndPrefix()
    {
        $sql = $this->connection->table('posts')
            ->join('users', function($join) {
                $join->on('posts.author_id', '=', 'users.id')
                     ->where('users.status', '=', 'active');
            })
            ->toSql();

        // Should not have double prefixes
        $this->assertStringNotContainsString('qt_qt_', $sql);

        $results = $this->connection->table('posts')
            ->join('users', function($join) {
                $join->on('posts.author_id', '=', 'users.id')
                     ->where('users.status', '=', 'active');
            })
            ->get();

        $this->assertCount(1, $results);
    }
}