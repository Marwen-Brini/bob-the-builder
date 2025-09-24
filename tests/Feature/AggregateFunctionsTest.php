<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Database\Expression;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    Model::setConnection($this->connection);

    // Create test table
    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            status TEXT,
            category TEXT,
            views INTEGER
        )
    ');

    // Insert test data
    $this->connection->table('posts')->insert([
        ['title' => 'Post 1', 'status' => 'published', 'category' => 'tech', 'views' => 100],
        ['title' => 'Post 2', 'status' => 'draft', 'category' => 'tech', 'views' => 0],
        ['title' => 'Post 3', 'status' => 'published', 'category' => 'news', 'views' => 250],
        ['title' => 'Post 4', 'status' => 'published', 'category' => 'tech', 'views' => 150],
        ['title' => 'Post 5', 'status' => 'draft', 'category' => 'news', 'views' => 0],
    ]);
});

afterEach(function () {
    Model::clearConnection();
});

test('can use aggregate functions in select with Expression', function () {
    // This should work with Expression
    $results = $this->connection->table('posts')
        ->select('status', new Expression('COUNT(*) as count'))
        ->groupBy('status')
        ->get();

    expect($results)->toHaveCount(2);
    
    $statusCounts = [];
    foreach ($results as $row) {
        $statusCounts[$row->status] = $row->count;
    }
    
    expect($statusCounts['published'])->toBe(3);
    expect($statusCounts['draft'])->toBe(2);
});

test('can use aggregate functions in select as string', function () {
    // This is what fails - aggregate functions should be automatically wrapped
    $results = $this->connection->table('posts')
        ->select('status', 'COUNT(*) as count')
        ->groupBy('status')
        ->get();

    expect($results)->toHaveCount(2);
    
    $statusCounts = [];
    foreach ($results as $row) {
        $statusCounts[$row->status] = $row->count;
    }
    
    expect($statusCounts['published'])->toBe(3);
    expect($statusCounts['draft'])->toBe(2);
});

test('can use multiple aggregate functions', function () {
    $results = $this->connection->table('posts')
        ->select('category', 'COUNT(*) as count', 'SUM(views) as total_views', 'AVG(views) as avg_views')
        ->groupBy('category')
        ->get();

    expect($results)->toHaveCount(2);
    
    foreach ($results as $row) {
        if ($row->category === 'tech') {
            expect($row->count)->toBe(3);
            expect($row->total_views)->toBe(250);
            expect((float) $row->avg_views)->toBeGreaterThan(83.0);
        } elseif ($row->category === 'news') {
            expect($row->count)->toBe(2);
            expect($row->total_views)->toBe(250);
            expect((float) $row->avg_views)->toBe(125.0);
        }
    }
});

test('selectRaw works with aggregate functions', function () {
    $results = $this->connection->table('posts')
        ->selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->get();

    expect($results)->toHaveCount(2);
    
    $statusCounts = [];
    foreach ($results as $row) {
        $statusCounts[$row->status] = $row->count;
    }
    
    expect($statusCounts['published'])->toBe(3);
    expect($statusCounts['draft'])->toBe(2);
});

test('aggregate function with DISTINCT', function () {
    // Add duplicate categories
    $this->connection->table('posts')->insert([
        ['title' => 'Post 6', 'status' => 'published', 'category' => 'tech', 'views' => 200],
    ]);

    $results = $this->connection->table('posts')
        ->selectRaw('COUNT(DISTINCT category) as unique_categories')
        ->get();

    // Get first result from array
    expect($results)->toHaveCount(1);
    expect($results[0]->unique_categories)->toBe(2);
});

test('aggregate function in having clause', function () {
    $results = $this->connection->table('posts')
        ->select('category', 'COUNT(*) as count')
        ->groupBy('category')
        ->havingRaw('COUNT(*) > 2')
        ->get();

    expect($results)->toHaveCount(1);
    expect($results[0]->category)->toBe('tech');
    expect($results[0]->count)->toBe(3);
});

test('common aggregate patterns work', function () {
    // These patterns should all work
    $patterns = [
        'COUNT(*)',
        'COUNT(id)',
        'COUNT(DISTINCT status)',
        'SUM(views)',
        'AVG(views)',
        'MIN(views)',
        'MAX(views)',
        'GROUP_CONCAT(title)',
    ];

    foreach ($patterns as $pattern) {
        $query = $this->connection->table('posts')
            ->select($pattern . ' as result')
            ->toSql();

        // Should not wrap aggregate functions in quotes
        expect($query)->toContain($pattern);
    }
});

test('addSelect with aggregate function', function () {
    // This test covers the addSelect method with aggregate functions
    $results = $this->connection->table('posts')
        ->select('category')
        ->addSelect('COUNT(*) as count')
        ->addSelect('SUM(views) as total_views')
        ->groupBy('category')
        ->get();

    expect($results)->toHaveCount(2);

    foreach ($results as $row) {
        expect($row)->toHaveProperty('category');
        expect($row)->toHaveProperty('count');
        expect($row)->toHaveProperty('total_views');

        if ($row->category === 'tech') {
            expect($row->count)->toBe(3);
            expect($row->total_views)->toBe(250);
        }
    }
});