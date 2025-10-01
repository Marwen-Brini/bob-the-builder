<?php

require __DIR__.'/../vendor/autoload.php';

use Bob\Database\Connection;
use Bob\Query\Builder;

// Initialize connection
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);

// Create a test table
$connection->statement('
    CREATE TABLE posts (
        id INTEGER PRIMARY KEY,
        title TEXT,
        post_type TEXT,
        status TEXT,
        user_id INTEGER,
        deleted_at TIMESTAMP NULL
    )
');

// Insert test data
$connection->table('posts')->insert([
    ['title' => 'Published Post', 'post_type' => 'post', 'status' => 'published', 'user_id' => 1, 'deleted_at' => null],
    ['title' => 'Draft Post', 'post_type' => 'post', 'status' => 'draft', 'user_id' => 1, 'deleted_at' => null],
    ['title' => 'Deleted Post', 'post_type' => 'post', 'status' => 'published', 'user_id' => 1, 'deleted_at' => '2024-01-01'],
    ['title' => 'Page', 'post_type' => 'page', 'status' => 'published', 'user_id' => 1, 'deleted_at' => null],
    ['title' => 'Other User Post', 'post_type' => 'post', 'status' => 'published', 'user_id' => 2, 'deleted_at' => null],
]);

echo "=== Global Scopes Demo ===\n\n";

// Example 1: Basic global scope with closure
echo "1. Basic Global Scope (Soft Deletes):\n";
$builder = $connection->table('posts');
$builder->addGlobalScope('soft_deletes', function (Builder $query) {
    $query->whereNull('deleted_at');
});

$results = $builder->get();
echo '   Found '.count($results)." non-deleted posts\n";

// Example 2: Multiple global scopes
echo "\n2. Multiple Global Scopes:\n";
$builder = $connection->table('posts');

// Add soft delete scope
$builder->addGlobalScope('soft_deletes', function (Builder $query) {
    $query->whereNull('deleted_at');
});

// Add post type scope
$builder->addGlobalScope('post_type', function (Builder $query) {
    $query->where('post_type', 'post');
});

// Add published scope
$builder->addGlobalScope('published', function (Builder $query) {
    $query->where('status', 'published');
});

$results = $builder->get();
echo '   Found '.count($results)." published posts (not deleted)\n";
foreach ($results as $post) {
    echo "   - {$post->title}\n";
}

// Example 3: Removing specific global scopes
echo "\n3. Removing Specific Global Scopes:\n";
$builder = $connection->table('posts');

$builder->addGlobalScope('soft_deletes', function (Builder $query) {
    $query->whereNull('deleted_at');
});

$builder->addGlobalScope('published', function (Builder $query) {
    $query->where('status', 'published');
});

// Remove the published scope to include drafts
$builder->withoutGlobalScope('published');

$results = $builder->where('post_type', 'post')->get();
echo '   Found '.count($results)." posts (including drafts, excluding deleted)\n";
foreach ($results as $post) {
    echo "   - {$post->title} (status: {$post->status})\n";
}

// Example 4: Removing all global scopes
echo "\n4. Removing All Global Scopes:\n";
$builder = $connection->table('posts');

$builder->addGlobalScope('soft_deletes', function (Builder $query) {
    $query->whereNull('deleted_at');
});

$builder->addGlobalScope('user', function (Builder $query) {
    $query->where('user_id', 1);
});

// Remove all scopes to see everything
$builder->withoutGlobalScopes();

$results = $builder->get();
echo '   Found '.count($results)." total posts (including deleted and from all users)\n";

// Example 5: Using scope classes instead of closures
echo "\n5. Using Scope Classes:\n";

class PublishedScope
{
    public function apply(Builder $builder, $model)
    {
        $builder->where('status', 'published');
    }
}

class CurrentUserScope
{
    private $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function apply(Builder $builder, $model)
    {
        $builder->where('user_id', $this->userId);
    }
}

$builder = $connection->table('posts');
$builder->addGlobalScope('published', new PublishedScope);
$builder->addGlobalScope('current_user', new CurrentUserScope(1));

$results = $builder->get();
echo '   Found '.count($results)." published posts for user 1\n";

// Example 6: Global scopes with other query operations
echo "\n6. Global Scopes with Other Operations:\n";
$builder = $connection->table('posts');

$builder->addGlobalScope('soft_deletes', function (Builder $query) {
    $query->whereNull('deleted_at');
});

// Global scopes work with aggregates
$count = $builder->count();
echo "   Total non-deleted posts: $count\n";

// Global scopes work with exists
$hasPublished = $builder->where('status', 'published')->exists();
echo '   Has published posts: '.($hasPublished ? 'Yes' : 'No')."\n";

// Global scopes work with delete
$builder2 = $connection->table('posts');
$builder2->addGlobalScope('drafts_only', function (Builder $query) {
    $query->where('status', 'draft');
});
// This would only delete drafts due to the global scope
// $builder2->delete();

// Global scopes work with update
$builder3 = $connection->table('posts');
$builder3->addGlobalScope('user_posts', function (Builder $query) {
    $query->where('user_id', 1);
});
// This would only update user 1's posts
// $builder3->update(['status' => 'archived']);

echo "\n=== Demo Complete ===\n";
echo "\nKey Features:\n";
echo "✅ addGlobalScope() - Add scopes that apply to all queries\n";
echo "✅ withoutGlobalScope() - Remove specific scope\n";
echo "✅ withoutGlobalScopes() - Remove all or multiple scopes\n";
echo "✅ Works with closures and scope classes\n";
echo "✅ Applies to SELECT, UPDATE, DELETE, EXISTS, and aggregate queries\n";
echo "✅ Laravel-compatible API for easy migration\n";
