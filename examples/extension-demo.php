<?php

/**
 * Extension System Demonstration
 *
 * This example shows how to use Bob Query Builder's extension system
 * including macros, scopes, and dynamic finders.
 */

require __DIR__.'/../vendor/autoload.php';

use Bob\Database\Connection;
use Bob\Query\Builder;

// Setup database connection
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);

// Create test table
$connection->statement('
    CREATE TABLE posts (
        id INTEGER PRIMARY KEY,
        title TEXT,
        slug TEXT,
        status TEXT,
        author_id INTEGER,
        created_at TEXT,
        updated_at TEXT
    )
');

// Insert test data
$connection->table('posts')->insert([
    ['title' => 'Getting Started with Bob', 'slug' => 'getting-started', 'status' => 'published', 'author_id' => 1, 'created_at' => '2024-01-01', 'updated_at' => '2024-01-01'],
    ['title' => 'Advanced Query Building', 'slug' => 'advanced-queries', 'status' => 'published', 'author_id' => 2, 'created_at' => '2024-01-15', 'updated_at' => '2024-01-15'],
    ['title' => 'Draft Post', 'slug' => 'draft-post', 'status' => 'draft', 'author_id' => 1, 'created_at' => '2024-02-01', 'updated_at' => '2024-02-01'],
    ['title' => 'Extending Bob Builder', 'slug' => 'extending-bob', 'status' => 'published', 'author_id' => 3, 'created_at' => '2024-02-15', 'updated_at' => '2024-02-15'],
]);

echo "=== Bob Query Builder Extension System Demo ===\n\n";

// 1. MACROS - Add custom methods globally
echo "1. Macros - Adding Custom Methods\n";
echo str_repeat('-', 40)."\n";

// Register a macro for published posts
Builder::macro('published', function () {
    return $this->where('status', '=', 'published');
});

// Register a macro for posts by a specific author
Builder::macro('byAuthor', function ($authorId) {
    return $this->where('author_id', '=', $authorId);
});

// Use the macros
$publishedPosts = $connection->table('posts')
    ->published()
    ->get();

echo 'Published posts: '.count($publishedPosts)."\n";
foreach ($publishedPosts as $post) {
    echo "  - {$post['title']}\n";
}

$author1Posts = $connection->table('posts')
    ->byAuthor(1)
    ->get();

echo "\nPosts by Author 1: ".count($author1Posts)."\n";
foreach ($author1Posts as $post) {
    echo "  - {$post['title']} ({$post['status']})\n";
}

// 2. SCOPES - Reusable query patterns
echo "\n2. Scopes - Reusable Query Patterns\n";
echo str_repeat('-', 40)."\n";

// Register a local scope for recent posts
Builder::scope('recent', function ($days = 30) {
    $date = date('Y-m-d', strtotime("-{$days} days"));

    return $this->where('created_at', '>=', $date);
});

// Register a scope for ordering
Builder::scope('latest', function () {
    return $this->orderBy('created_at', 'desc');
});

// Use scopes
$recentPosts = $connection->table('posts')
    ->withScope('recent', 365)
    ->withScope('latest')
    ->get();

echo "Recent posts (last 365 days):\n";
foreach ($recentPosts as $post) {
    echo "  - {$post['title']} (created: {$post['created_at']})\n";
}

// 3. DYNAMIC FINDERS - Intuitive finder methods
echo "\n3. Dynamic Finders - Intuitive Methods\n";
echo str_repeat('-', 40)."\n";

// Built-in dynamic finders work automatically
$post = $connection->table('posts')->findBySlug('getting-started');
echo "Found by slug: {$post['title']}\n";

$drafts = $connection->table('posts')->findAllByStatus('draft');
echo 'Draft posts: '.count($drafts)."\n";

$hasPublished = $connection->table('posts')->existsByStatus('published');
echo 'Has published posts: '.($hasPublished ? 'Yes' : 'No')."\n";

$publishedCount = $connection->table('posts')->countByStatus('published');
echo "Published count: {$publishedCount}\n";

// Register custom finder pattern
Builder::registerFinder('/^findLatest(\d+)$/', function ($matches, $params) {
    $limit = (int) $matches[1];

    return $this->orderBy('created_at', 'desc')->limit($limit)->get();
});

$latestThree = $connection->table('posts')->findLatest3();
echo "\nLatest 3 posts:\n";
foreach ($latestThree as $post) {
    echo "  - {$post['title']}\n";
}

// 4. COMBINING EXTENSIONS - Chain everything together
echo "\n4. Combining Extensions\n";
echo str_repeat('-', 40)."\n";

// Chain macros, scopes, and finders
$results = $connection->table('posts')
    ->published()                    // Macro
    ->withScope('recent', 60)        // Scope
    ->withScope('latest')            // Scope
    ->whereByAuthorId(1)            // Dynamic finder
    ->get();

echo "Published recent posts by Author 1:\n";
foreach ($results as $post) {
    echo "  - {$post['title']} (created: {$post['created_at']})\n";
}

// 5. GLOBAL SCOPES - Apply to all queries
echo "\n5. Global Scopes\n";
echo str_repeat('-', 40)."\n";

// Register a global scope
Builder::globalScope('onlyPublished', function () {
    return $this->where('status', '=', 'published');
});

// New queries will automatically apply global scopes
$allWithGlobal = $connection->table('posts')
    ->withGlobalScopes()
    ->get();

echo 'With global scope (only published): '.count($allWithGlobal)."\n";

// You can remove global scopes when needed
$allWithoutGlobal = $connection->table('posts')
    ->withoutGlobalScope('onlyPublished')
    ->withGlobalScopes()
    ->get();

echo 'Without global scope (all posts): '.count($allWithoutGlobal)."\n";

// Clean up for next run
Builder::clearMacros();
Builder::clearScopes();
Builder::clearFinders();

echo "\n=== Demo Complete ===\n";
