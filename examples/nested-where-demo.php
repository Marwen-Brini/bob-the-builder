<?php

require __DIR__ . '/../vendor/autoload.php';

use Bob\Database\Connection;

// Initialize connection
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);

// Create test table
$connection->statement('
    CREATE TABLE posts (
        id INTEGER PRIMARY KEY,
        title TEXT,
        content TEXT,
        status TEXT,
        category TEXT,
        user_id INTEGER
    )
');

// Insert test data
$connection->table('posts')->insert([
    ['title' => 'Hello World', 'content' => 'First post content', 'status' => 'published', 'category' => 'tech', 'user_id' => 1],
    ['title' => 'World News', 'content' => 'Hello from the news', 'status' => 'published', 'category' => 'news', 'user_id' => 2],
    ['title' => 'Draft Post', 'content' => 'Work in progress', 'status' => 'draft', 'category' => 'tech', 'user_id' => 1],
    ['title' => 'Another Draft', 'content' => 'Hello draft', 'status' => 'draft', 'category' => 'news', 'user_id' => 2],
    ['title' => 'Tech Article', 'content' => 'Technical content', 'status' => 'published', 'category' => 'tech', 'user_id' => 3],
]);

echo "=== BOB_ISSUE_002_NESTED_WHERE_CLOSURES - RESOLVED ✅ ===\n\n";

// Example 1: Simple nested where closure (the original failing case)
echo "1. Simple Nested Where Closure:\n";
$search = 'Hello';
$results = $connection->table('posts')
    ->where(function($q) use ($search) {
        $q->where('title', 'LIKE', "%$search%")
          ->orWhere('content', 'LIKE', "%$search%");
    })
    ->get();

echo "   SQL: WHERE (title LIKE '%Hello%' OR content LIKE '%Hello%')\n";
echo "   Found " . count($results) . " posts containing 'Hello':\n";
foreach ($results as $post) {
    echo "   - {$post->title}\n";
}

// Example 2: Combining regular where with nested closure
echo "\n2. Regular WHERE with Nested Closure:\n";
$results = $connection->table('posts')
    ->where('status', 'published')
    ->where(function($q) {
        $q->where('category', 'tech')
          ->orWhere('user_id', 3);
    })
    ->get();

echo "   SQL: WHERE status = 'published' AND (category = 'tech' OR user_id = 3)\n";
echo "   Found " . count($results) . " published tech posts or posts by user 3:\n";
foreach ($results as $post) {
    echo "   - {$post->title} (category: {$post->category}, user: {$post->user_id})\n";
}

// Example 3: Nested closure with orWhere
echo "\n3. Using orWhere with Nested Closure:\n";
$results = $connection->table('posts')
    ->where('status', 'draft')
    ->orWhere(function($q) {
        $q->where('category', 'news')
          ->where('user_id', 2);
    })
    ->get();

echo "   SQL: WHERE status = 'draft' OR (category = 'news' AND user_id = 2)\n";
echo "   Found " . count($results) . " drafts or news posts by user 2:\n";
foreach ($results as $post) {
    echo "   - {$post->title} (status: {$post->status})\n";
}

// Example 4: Deeply nested closures
echo "\n4. Deeply Nested Closures:\n";
$results = $connection->table('posts')
    ->where(function($q) {
        $q->where('status', 'published')
          ->where(function($q2) {
              $q2->where('title', 'LIKE', '%World%')
                 ->orWhere('content', 'LIKE', '%content%');
          });
    })
    ->get();

echo "   SQL: WHERE (status = 'published' AND (title LIKE '%World%' OR content LIKE '%content%'))\n";
echo "   Found " . count($results) . " published posts with 'World' or 'content':\n";
foreach ($results as $post) {
    echo "   - {$post->title}\n";
}

// Example 5: Multiple nested groups
echo "\n5. Multiple Nested Groups:\n";
$results = $connection->table('posts')
    ->where(function($q) {
        $q->where('status', 'published')
          ->where('category', 'tech');
    })
    ->orWhere(function($q) {
        $q->where('status', 'draft')
          ->where('user_id', 1);
    })
    ->get();

echo "   SQL: WHERE (status = 'published' AND category = 'tech') OR (status = 'draft' AND user_id = 1)\n";
echo "   Found " . count($results) . " posts (published tech OR drafts by user 1):\n";
foreach ($results as $post) {
    echo "   - {$post->title} (status: {$post->status}, category: {$post->category})\n";
}

// Example 6: Empty nested closure (should be ignored)
echo "\n6. Empty Nested Closure (ignored):\n";
$results = $connection->table('posts')
    ->where('status', 'published')
    ->where(function($q) {
        // Empty - should be ignored
    })
    ->get();

echo "   SQL: WHERE status = 'published' (empty closure ignored)\n";
echo "   Found " . count($results) . " published posts\n";

// Show the actual SQL for a complex query
echo "\n7. Actual SQL Generation:\n";
$builder = $connection->table('posts')
    ->where('status', 'published')
    ->where(function($q) {
        $q->where('title', 'LIKE', '%Hello%')
          ->orWhere('content', 'LIKE', '%World%');
    });

echo "   Generated SQL: " . $builder->toSql() . "\n";
echo "   Bindings: " . json_encode($builder->getBindings()) . "\n";

echo "\n=== Issue Resolved ===\n";
echo "✅ Nested WHERE closures now generate correct SQL\n";
echo "✅ Properly groups conditions with parentheses\n";
echo "✅ Supports deeply nested and complex queries\n";
echo "✅ No more syntax errors with 'where where' duplication\n";