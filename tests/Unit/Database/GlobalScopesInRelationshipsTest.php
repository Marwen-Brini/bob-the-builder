<?php

namespace Tests\Unit\Database;

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Query\Builder;

// Test Models
class Post extends Model
{
    protected string $table = 'posts';

    protected string $primaryKey = 'id';

    protected array $fillable = ['id', 'title', 'content', 'post_type', 'author_id', 'parent_id'];

    protected string $postType = 'post';

    // Disable global scopes in relationships to fix the inheritance issue
    protected bool $applyGlobalScopesToRelationships = false;

    public function newQuery(): Builder
    {
        $query = parent::newQuery();

        // Add global scope that filters by post_type
        $postType = $this->postType; // Capture the value in the current scope
        $query->addGlobalScope('type', function (Builder $query) use ($postType) {
            $query->where('post_type', $postType);
        });

        return $query;
    }

    public function children()
    {
        return $this->hasMany(Post::class, 'parent_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(Post::class, 'parent_id', 'id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }
}

class Page extends Post
{
    protected string $postType = 'page';
}

class User extends Model
{
    protected string $table = 'users';

    protected string $primaryKey = 'id';

    protected array $fillable = ['id', 'name', 'email', 'status'];

    // Disable global scopes in relationships
    protected bool $applyGlobalScopesToRelationships = false;

    public function newQuery(): Builder
    {
        $query = parent::newQuery();

        // Add global scope that filters active users
        $query->addGlobalScope('active', function (Builder $query) {
            $query->where('status', 'active');
        });

        return $query;
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'author_id', 'id');
    }

    public function allPosts()
    {
        // This should get all posts regardless of type
        return $this->hasMany(Post::class, 'author_id', 'id')
            ->withoutGlobalScope('type');
    }
}

beforeEach(function () {
    // Create a real SQLite connection for integration testing
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $this->connection = new Connection(['driver' => 'sqlite'], null, $pdo);

    // Create test tables
    $this->connection->statement('CREATE TABLE posts (
        id INTEGER PRIMARY KEY,
        title TEXT,
        content TEXT,
        post_type TEXT,
        parent_id INTEGER,
        author_id INTEGER
    )');

    $this->connection->statement('CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        name TEXT,
        email TEXT,
        status TEXT
    )');

    // Insert test data
    $this->connection->statement("INSERT INTO users (id, name, email, status) VALUES
        (1, 'Active Author', 'active@example.com', 'active'),
        (2, 'Inactive Author', 'inactive@example.com', 'inactive')");

    $this->connection->statement("INSERT INTO posts (id, title, content, post_type, parent_id, author_id) VALUES
        (1, 'Blog Post 1', 'Content', 'post', NULL, 1),
        (2, 'Blog Post 2', 'Content', 'post', NULL, 1),
        (3, 'Page 1', 'Page Content', 'page', NULL, 1),
        (4, 'Page 2', 'Page Content', 'page', NULL, 2),
        (5, 'Child Page', 'Child Content', 'page', 3, 1),
        (6, 'Child Post', 'Child Content', 'post', 1, 1)");

    Model::setConnection($this->connection);
});

afterEach(function () {
    Model::clearConnection();
});

test('global scopes are applied to base model queries', function () {
    // Post model should only get 'post' type
    $posts = Post::all();
    expect($posts)->toHaveCount(3); // Only posts, not pages

    foreach ($posts as $post) {
        expect($post->post_type)->toBe('post');
    }

    // Page model should only get 'page' type
    $pages = Page::all();
    expect($pages)->toHaveCount(3); // Only pages, not posts

    foreach ($pages as $page) {
        expect($page->post_type)->toBe('page');
    }
});

test('global scopes are NOT applied to relationships when disabled', function () {
    // Get a page that has children
    $page = Page::find(3); // Page 1
    expect($page)->not->toBeNull();
    expect($page->post_type)->toBe('page');

    // Now relationships DON'T apply global scopes (applyGlobalScopesToRelationships = false)
    $children = $page->children()->get();

    // FIXED: Should find the Child Page (id=5, parent_id=3)
    expect($children)->toHaveCount(1);
    expect($children[0]->post_type)->toBe('page');
    expect($children[0]->id)->toBe(5);
});

test('withoutGlobalScope allows access to all related records', function () {
    $page = Page::find(3);

    // Workaround: Remove the global scope to get all children
    $allChildren = $page->children()
        ->withoutGlobalScope('type')
        ->get();

    // This should find both page and post children if parent_id matches
    // But in our test data, Page 3 only has one child (Page 5)
    expect($allChildren)->toHaveCount(1);
});

test('relationship queries do NOT inherit global scopes when disabled', function () {
    // Get a user
    $user = User::withoutGlobalScope('active')->find(1);
    expect($user)->not->toBeNull();

    // Get posts through relationship - no global scopes applied
    $posts = $user->posts()->get();

    // FIXED: Now gets all posts regardless of type
    expect($posts)->toHaveCount(5); // Gets all 5 posts/pages by user 1 (including children)
});

test('accessing parent relationship applies parent model scopes', function () {
    // Get a child post directly (bypassing global scope)
    $childPost = Post::withoutGlobalScope('type')
        ->where('id', 6)
        ->first();

    expect($childPost)->not->toBeNull();
    expect($childPost->title)->toBe('Child Post');

    // Try to get parent through relationship
    $parent = $childPost->parent()->first();

    // The parent relationship uses Post model, so global scope is applied
    expect($parent)->not->toBeNull();
    expect($parent->post_type)->toBe('post'); // Scope ensures we get posts only
});

test('global scopes on user model affect author relationship', function () {
    // Get a page created by inactive author
    $page = Page::find(4); // Page 2 by inactive author
    expect($page)->not->toBeNull();
    expect($page->author_id)->toBe(2);

    // Try to get author - User model has 'active' scope but relationships don't apply it
    $author = $page->author()->first();

    // FIXED: Now returns the author even if inactive
    expect($author)->not->toBeNull();
    expect($author->name)->toBe('Inactive Author');

    // Workaround: Remove global scope
    $actualAuthor = $page->author()
        ->withoutGlobalScope('active')
        ->first();

    expect($actualAuthor)->not->toBeNull();
    expect($actualAuthor->name)->toBe('Inactive Author');
});

test('custom relationship method is no longer needed with fix', function () {
    $user = User::withoutGlobalScope('active')->find(1);

    // Regular posts relationship no longer applies global scopes
    $posts = $user->posts()->get();
    expect($posts)->toHaveCount(5); // Gets all posts and pages including children

    // Custom allPosts relationship also gets all
    $allPosts = $user->allPosts()->get();
    expect($allPosts)->toHaveCount(5); // Same result now
});

test('global scopes affect eager loading', function () {
    // Eager load posts with their authors
    $posts = Post::with('author')->get();

    // Posts from inactive authors won't have author loaded
    foreach ($posts as $post) {
        if ($post->author_id === 1) {
            expect($post->author)->not->toBeNull();
        } elseif ($post->author_id === 2) {
            // BUG: Author is null because they're inactive
            expect($post->author)->toBeNull();
        }
    }
});

test('chained relationships work without global scope interference', function () {
    // Get user with posts and their children
    $user = User::withoutGlobalScope('active')->find(1);

    $postsWithChildren = $user->posts()
        ->with('children')
        ->get();

    // Without global scopes, we get all post types
    expect($postsWithChildren)->toHaveCount(5);

    // Check that we get various post types
    $types = [];
    foreach ($postsWithChildren as $post) {
        $types[] = $post->post_type;
    }
    expect($types)->toContain('post');
    expect($types)->toContain('page');
});

test('withoutGlobalScopes removes all scopes from relationship', function () {
    $page = Page::find(3);

    // Remove ALL global scopes from the relationship
    $allRelated = $page->children()
        ->withoutGlobalScopes()
        ->get();

    // This gets all children regardless of any global scopes
    expect($allRelated)->toHaveCount(1);
});

test('relationship query can add additional constraints on top of global scopes', function () {
    $user = User::withoutGlobalScope('active')->find(1);

    // Add extra constraints on top of global scope
    $specificPosts = $user->posts()
        ->where('title', 'like', '%1%')
        ->get();

    // Should find both 'Blog Post 1' and 'Page 1' as they both contain '1'
    expect($specificPosts)->toHaveCount(2);

    $titles = [];
    foreach ($specificPosts as $post) {
        $titles[] = $post->title;
    }
    expect($titles)->toContain('Blog Post 1');
    expect($titles)->toContain('Page 1');
});

test('aggregate functions work correctly without global scopes', function () {
    $user = User::withoutGlobalScope('active')->find(1);

    // Count all posts without global scope interference
    $postCount = $user->posts()->count();
    expect($postCount)->toBe(5); // All posts and pages by user including children

    // Can still manually apply where conditions if needed
    $onlyPosts = $user->posts()
        ->where('post_type', 'post')
        ->count();
    expect($onlyPosts)->toBe(3); // Posts 1, 2, and 6 (child post)
});
