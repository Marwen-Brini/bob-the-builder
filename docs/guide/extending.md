# Extending Bob Query Builder

Bob Query Builder is designed to be extensible, allowing you to add custom functionality without modifying the core library. This makes it perfect for domain-specific use cases like WordPress, Laravel applications, or any custom PHP project.

## Table of Contents
- [Macros](#macros)
- [Query Scopes](#query-scopes)
- [Dynamic Finders](#dynamic-finders)
- [WordPress Extension Example](#wordpress-extension-example)
- [Creating Your Own Extension Package](#creating-your-own-extension-package)

## Macros

Macros allow you to add custom methods to the Builder class at runtime.

### Basic Macro Example

```php
use Bob\Query\Builder;

// Register a custom method
Builder::macro('whereActive', function() {
    return $this->where('status', '=', 'active');
});

// Use it anywhere
$users = $connection->table('users')
    ->whereActive()
    ->get();
```

### Complex Macro Example

```php
// Add a method to find records created in the last N days
Builder::macro('whereRecentDays', function($days = 7) {
    $date = date('Y-m-d', strtotime("-{$days} days"));
    return $this->where('created_at', '>=', $date);
});

// Usage
$recentPosts = $connection->table('posts')
    ->whereRecentDays(30)
    ->orderBy('created_at', 'desc')
    ->get();
```

### Registering Multiple Macros

```php
Builder::mixin([
    'wherePublished' => function() {
        return $this->where('status', 'published');
    },
    'whereDraft' => function() {
        return $this->where('status', 'draft');
    },
    'whereAuthor' => function($authorId) {
        return $this->where('author_id', $authorId);
    }
]);
```

## Query Scopes

Scopes allow you to define reusable query constraints that can be applied globally or locally.

### Local Scopes

Local scopes are applied on-demand:

```php
// Register a local scope
Builder::scope('popular', function() {
    return $this->where('views', '>', 1000)
                ->orderBy('views', 'desc');
});

// Use the scope
$popularPosts = $connection->table('posts')
    ->withScope('popular')
    ->limit(10)
    ->get();
```

### Global Scopes

Global scopes are automatically applied to all queries:

```php
// Register a global scope (e.g., soft deletes)
Builder::globalScope('notDeleted', function() {
    return $this->whereNull('deleted_at');
});

// All queries will automatically exclude soft-deleted records
$users = $connection->table('users')->get(); // Automatically adds WHERE deleted_at IS NULL

// To include soft-deleted records, remove the global scope
$allUsers = $connection->table('users')
    ->withoutGlobalScope('notDeleted')
    ->get();
```

### Parameterized Scopes

```php
Builder::scope('ofType', function($type) {
    return $this->where('type', $type);
});

Builder::scope('betweenDates', function($start, $end) {
    return $this->whereBetween('created_at', [$start, $end]);
});

// Usage
$products = $connection->table('products')
    ->withScope('ofType', 'electronics')
    ->withScope('betweenDates', '2024-01-01', '2024-12-31')
    ->get();
```

## Dynamic Finders

Dynamic finders provide intuitive method names for common query patterns.

### Built-in Dynamic Finders

Bob comes with several built-in dynamic finder patterns:

```php
// Find a single record by column
$user = $connection->table('users')->findByEmail('user@example.com');

// Find all records by column
$posts = $connection->table('posts')->findAllByStatus('published');

// Add where conditions dynamically
$query = $connection->table('products')
    ->whereByCategory('electronics')
    ->whereByBrand('Apple');

// Count by column
$count = $connection->table('orders')->countByStatus('pending');

// Check existence
$exists = $connection->table('users')->existsByEmail('test@example.com');

// Delete by column
$deleted = $connection->table('logs')->deleteByCreatedAt('2023-01-01');

// Order dynamically
$posts = $connection->table('posts')
    ->orderByCreatedAtDesc()
    ->orderByTitleAsc()
    ->get();
```

### Custom Dynamic Finders

Register your own dynamic finder patterns:

```php
// Register a custom finder for slug-based lookups
Builder::registerFinder('/^findBySlug(.+)$/', function($matches, $params) {
    $slug = $params[0] ?? null;
    return $this->where('slug', '=', $slug)->first();
});

// Register a finder for status-based queries
Builder::registerFinder('/^whereStatus(.+)$/', function($matches, $params) {
    $status = strtolower($matches[1]);
    return $this->where('status', '=', $status);
});

// Usage
$post = $connection->table('posts')->findBySlugMyAwesomePost('my-awesome-post');
$published = $connection->table('posts')->whereStatusPublished()->get();
```

## WordPress Extension Example

Here's a complete example of extending Bob for WordPress usage:

```php
<?php

namespace YourProject\Database;

use Bob\Query\Builder;
use Bob\Database\Connection;

class WordPressExtension
{
    /**
     * Register WordPress-specific extensions
     */
    public static function register(): void
    {
        self::registerMacros();
        self::registerScopes();
        self::registerFinders();
    }
    
    protected static function registerMacros(): void
    {
        // WordPress post queries
        Builder::mixin([
            'wherePublished' => function() {
                return $this->where('post_status', 'publish');
            },
            'whereDraft' => function() {
                return $this->where('post_status', 'draft');
            },
            'wherePostType' => function($type) {
                return $this->where('post_type', $type);
            },
            'whereMetaKey' => function($key, $value = null, $compare = '=') {
                $this->join('postmeta', 'posts.ID', '=', 'postmeta.post_id');
                $this->where('postmeta.meta_key', $key);
                if ($value !== null) {
                    $this->where('postmeta.meta_value', $compare, $value);
                }
                return $this;
            },
            'withMeta' => function() {
                return $this->leftJoin('postmeta', 'posts.ID', '=', 'postmeta.post_id')
                           ->select('posts.*', 'postmeta.meta_key', 'postmeta.meta_value');
            },
            'withAuthor' => function() {
                return $this->join('users', 'posts.post_author', '=', 'users.ID')
                           ->addSelect('users.display_name as author_name');
            },
            'withCommentCount' => function() {
                return $this->leftJoin('comments', function($join) {
                    $join->on('posts.ID', '=', 'comments.comment_post_ID')
                         ->where('comments.comment_approved', '=', '1');
                })->selectRaw('posts.*, COUNT(comments.comment_ID) as comment_count')
                  ->groupBy('posts.ID');
            }
        ]);

        // WooCommerce specific
        Builder::macro('whereInStock', function() {
            return $this->whereMetaKey('_stock_status', 'instock');
        });

        Builder::macro('whereProductType', function($type) {
            return $this->join('term_relationships', 'posts.ID', '=', 'term_relationships.object_id')
                        ->join('term_taxonomy', 'term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id')
                        ->join('terms', 'term_taxonomy.term_id', '=', 'terms.term_id')
                        ->where('term_taxonomy.taxonomy', 'product_type')
                        ->where('terms.slug', $type);
        });
    }
    
    protected static function registerScopes(): void
    {
        // Global scope to respect WordPress table prefix
        Builder::globalScope('wpPrefix', function() {
            // This would be handled by Connection configuration
            // Just an example of how you might use global scopes
        });
        
        // Local scope for post queries
        Builder::scope('published', function() {
            return $this->where('post_status', 'publish')
                       ->whereNotNull('post_date')
                       ->where('post_date', '<=', current_time('mysql'));
        });
        
        // Scope for specific post types
        Builder::scope('posts', function() {
            return $this->where('post_type', 'post');
        });
        
        Builder::scope('pages', function() {
            return $this->where('post_type', 'page');
        });
        
        Builder::scope('attachments', function() {
            return $this->where('post_type', 'attachment');
        });
    }
    
    protected static function registerFinders(): void
    {
        // Find by slug
        Builder::registerFinder('/^findBySlug$/', function($matches, $params) {
            $slug = $params[0] ?? null;
            return $this->where('post_name', '=', $slug)->first();
        });
        
        // Find by post meta
        Builder::registerFinder('/^findByMeta(.+)$/', function($matches, $params) {
            $metaKey = $this->camelToSnake($matches[1]);
            $metaValue = $params[0] ?? null;
            
            return $this->join('postmeta', 'posts.ID', '=', 'postmeta.post_id')
                       ->where('postmeta.meta_key', $metaKey)
                       ->where('postmeta.meta_value', $metaValue)
                       ->first();
        });
        
        // Where by post status
        Builder::registerFinder('/^whereStatus(.+)$/', function($matches, $params) {
            $status = strtolower($matches[1]);
            return $this->where('post_status', $status);
        });
    }
}

// Usage in your WordPress plugin/theme
WordPressExtension::register();

// Now you can use WordPress-specific methods
$connection = new Connection([
    'driver' => 'mysql',
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'charset' => DB_CHARSET,
    'prefix' => $wpdb->prefix
]);

// Use the extended functionality
$posts = $connection->table('posts')
    ->wherePublished()
    ->wherePostType('post')
    ->withAuthor()
    ->withCommentCount()
    ->orderBy('post_date', 'desc')
    ->limit(10)
    ->get();

$product = $connection->table('posts')
    ->wherePostType('product')
    ->whereInStock()
    ->findBySlug('awesome-product');

$pageWithMeta = $connection->table('posts')
    ->wherePostType('page')
    ->findByMetaCustomField('special-value');
```

## Creating Your Own Extension Package

You can create a reusable extension package for Bob:

### 1. Create Your Extension Class

```php
<?php

namespace YourVendor\BobExtension;

use Bob\Query\Builder;

class MyExtension
{
    protected static bool $registered = false;
    
    public static function register(): void
    {
        if (static::$registered) {
            return;
        }
        
        static::registerMacros();
        static::registerScopes();
        static::registerFinders();
        
        static::$registered = true;
    }
    
    public static function unregister(): void
    {
        Builder::clearMacros();
        Builder::clearScopes();
        Builder::clearFinders();
        static::$registered = false;
    }
    
    protected static function registerMacros(): void
    {
        // Your custom macros
    }
    
    protected static function registerScopes(): void
    {
        // Your custom scopes
    }
    
    protected static function registerFinders(): void
    {
        // Your custom finders
    }
}
```

### 2. Create a Service Provider (if using a framework)

```php
<?php

namespace YourVendor\BobExtension;

class BobExtensionServiceProvider
{
    public function register(): void
    {
        MyExtension::register();
    }
    
    public function boot(): void
    {
        // Additional bootstrapping if needed
    }
}
```

### 3. Package Structure

```
your-bob-extension/
├── src/
│   ├── MyExtension.php
│   └── Providers/
│       └── BobExtensionServiceProvider.php
├── tests/
│   └── ExtensionTest.php
├── composer.json
└── README.md
```

### 4. Composer.json Example

```json
{
    "name": "your-vendor/bob-wordpress-extension",
    "description": "WordPress extension for Bob Query Builder",
    "require": {
        "php": "^8.1",
        "marwen-brini/bob-the-builder": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "YourVendor\\BobExtension\\": "src/"
        }
    },
    "extra": {
        "bob": {
            "register": "YourVendor\\BobExtension\\MyExtension::register"
        }
    }
}
```

## Testing Your Extensions

Bob Query Builder uses Pest for testing. Here's how to write tests for your extensions:

### Setting Up Tests with Pest

```php
use Bob\Query\Builder;
use Bob\Database\Connection;

beforeEach(function () {
    // Setup database connection
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:'
    ]);
    
    // Clear any existing extensions
    Builder::clearMacros();
    Builder::clearScopes();
    Builder::clearFinders();
});

afterEach(function () {
    // Clean up extensions after each test
    Builder::clearMacros();
    Builder::clearScopes();
    Builder::clearFinders();
});
```

### Testing Macros

```php
test('can register and use custom macros', function () {
    // Register a macro
    Builder::macro('whereActive', function() {
        return $this->where('status', '=', 'active');
    });
    
    // Test that the macro exists
    expect(Builder::hasMacro('whereActive'))->toBeTrue();
    
    // Use the macro
    $query = $this->connection->table('users')->whereActive();
    $sql = $query->toSql();
    
    expect($sql)->toContain('status');
    expect($query->getBindings())->toContain('active');
});

test('can chain multiple macros', function () {
    Builder::macro('active', function() {
        return $this->where('status', '=', 'active');
    });
    
    Builder::macro('recent', function($days = 7) {
        $date = date('Y-m-d', strtotime("-{$days} days"));
        return $this->where('created_at', '>=', $date);
    });
    
    $query = $this->connection->table('users')
        ->active()
        ->recent(30);
    
    expect($query->toSql())->toContain('status')
        ->and($query->toSql())->toContain('created_at');
});
```

### Testing Scopes

```php
test('can use local scopes', function () {
    Builder::scope('published', function() {
        return $this->where('status', '=', 'published');
    });
    
    expect(Builder::hasScope('published'))->toBeTrue();
    
    $query = $this->connection->table('posts')
        ->withScope('published');
    
    expect($query->getBindings())->toContain('published');
});

test('can use parameterized scopes', function () {
    Builder::scope('ofType', function($type) {
        return $this->where('type', '=', $type);
    });
    
    $query = $this->connection->table('products')
        ->withScope('ofType', 'electronics');
    
    expect($query->getBindings())->toContain('electronics');
});
```

### Testing Dynamic Finders

```php
test('can use dynamic finders', function () {
    // Create test table
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            email TEXT,
            status TEXT
        )
    ');
    
    $this->connection->table('users')->insert([
        ['email' => 'test@example.com', 'status' => 'active']
    ]);
    
    $user = $this->connection->table('users')
        ->findByEmail('test@example.com');
    
    expect($user)->toBeArray()
        ->and($user['email'])->toBe('test@example.com');
});

test('can register custom finder patterns', function () {
    Builder::registerFinder('/^getBySlug$/', function($matches, $params) {
        $slug = $params[0] ?? null;
        return $this->where('slug', '=', $slug)->first();
    });
    
    // Mock the behavior
    $query = $this->connection->table('posts');
    
    expect(fn() => $query->getBySlug('test-slug'))
        ->not->toThrow();
});
```

### Testing Chain Combinations

```php
test('can chain multiple extensions together', function () {
    // Setup test data
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            email TEXT,
            status TEXT,
            created_at TEXT
        )
    ');
    
    $this->connection->table('users')->insert([
        ['name' => 'Active User', 'email' => 'active@example.com', 'status' => 'active', 'created_at' => date('Y-m-d')],
        ['name' => 'Old User', 'email' => 'old@example.com', 'status' => 'active', 'created_at' => '2020-01-01'],
        ['name' => 'Inactive User', 'email' => 'inactive@example.com', 'status' => 'inactive', 'created_at' => date('Y-m-d')],
    ]);
    
    // Register extensions
    Builder::macro('active', function() {
        return $this->where('status', '=', 'active');
    });
    
    Builder::scope('recent', function($days = 7) {
        $date = date('Y-m-d', strtotime("-{$days} days"));
        return $this->where('created_at', '>=', $date);
    });
    
    // Chain everything
    $users = $this->connection->table('users')
        ->active()
        ->withScope('recent', 30)
        ->whereByEmail('active@example.com')
        ->get();
    
    expect($users)->toHaveCount(1)
        ->and($users[0]['name'])->toBe('Active User');
});
```

### Common Testing Pitfalls and Solutions

1. **Date Range Issues**: When testing date-based scopes, ensure your test data falls within the expected range:
   ```php
   // Bad: May exclude test data if dates are old
   ->recent(365)
   
   // Good: Use a large enough range for test data
   ->recent(5000)
   ```

2. **Global Scope Order**: When removing global scopes, call `withoutGlobalScope()` before `withGlobalScopes()`:
   ```php
   // Correct order
   $query = $connection->table('users')
       ->withoutGlobalScope('activeOnly')
       ->withGlobalScopes();
   ```

3. **Extension Cleanup**: Always clear extensions in test setup/teardown to prevent interference:
   ```php
   beforeEach(function () {
       Builder::clearMacros();
       Builder::clearScopes();
       Builder::clearFinders();
   });
   ```

4. **Array vs Object Results**: PDO returns arrays by default, not objects:
   ```php
   // Correct assertion for array results
   expect($user['email'])->toBe('test@example.com');
   
   // Not this (unless you've configured PDO differently)
   expect($user->email)->toBe('test@example.com');
   ```

### Running Tests

```bash
# Run all tests
vendor/bin/pest

# Run specific test file
vendor/bin/pest tests/Feature/ExtensionSystemTest.php

# Run with filter
vendor/bin/pest --filter="can register and use macros"

# Run with coverage
vendor/bin/pest --coverage
```

### Example Complete Test File

```php
<?php

use Bob\Database\Connection;
use Bob\Query\Builder;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    
    // Create test table
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            email TEXT,
            status TEXT,
            created_at TEXT
        )
    ');
    
    // Insert test data
    $this->connection->table('users')->insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'created_at' => '2024-01-01'],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'inactive', 'created_at' => '2024-01-02'],
    ]);
    
    // Clear extensions
    Builder::clearMacros();
    Builder::clearScopes();
    Builder::clearFinders();
});

afterEach(function () {
    Builder::clearMacros();
    Builder::clearScopes();
    Builder::clearFinders();
});

test('my custom extension works', function () {
    // Your test implementation
    Builder::macro('whereActive', function() {
        return $this->where('status', '=', 'active');
    });
    
    $activeUsers = $this->connection->table('users')
        ->whereActive()
        ->get();
    
    expect($activeUsers)->toHaveCount(1)
        ->and($activeUsers[0]['name'])->toBe('John Doe');
});
```

## Best Practices

1. **Namespace Your Extensions**: Use descriptive names to avoid conflicts
2. **Document Your Methods**: Add PHPDoc comments for IDE support
3. **Test Thoroughly**: Write tests for all custom functionality
4. **Version Carefully**: Follow semantic versioning for your extensions
5. **Keep It Focused**: Each extension package should have a single responsibility
6. **Avoid Global State**: Use dependency injection where possible
7. **Performance**: Be mindful of performance implications, especially with global scopes

## Advanced Usage

### Conditional Extensions

```php
// Only register extensions in specific environments
if (defined('WP_ENV') && WP_ENV === 'development') {
    Builder::macro('debugQuery', function() {
        dd($this->toSql(), $this->getBindings());
    });
}
```

### Extension Hooks

```php
// Allow other developers to hook into your extension
Builder::macro('withHooks', function($hookName) {
    // Allow WordPress-style filters
    if (function_exists('apply_filters')) {
        $this->wheres = apply_filters("bob_query_{$hookName}_wheres", $this->wheres);
    }
    return $this;
});
```

### Chain-able Extensions

```php
// Ensure your extensions return $this for chaining
Builder::macro('cache', function($minutes = 60) {
    $this->cacheMinutes = $minutes;
    return $this; // Always return $this for chaining
});

// Usage
$posts = $connection->table('posts')
    ->cache(120)
    ->wherePublished()
    ->get();
```

## Using the Model Class

Bob provides a base `Model` class that combines the power of the query builder with ActiveRecord-style patterns. This allows you to define model-specific methods while still benefiting from global extensions.

### Basic Model Setup

```php
use Bob\Database\Model;
use Bob\Database\Connection;

// First, configure the connection for all models
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

Model::setConnection($connection);
```

### Creating Your Own Model

```php
<?php

namespace App\Models;

use Bob\Database\Model;
use Bob\Query\Builder;

class Post extends Model
{
    protected string $table = 'posts';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;

    /**
     * Custom finder method - specific to Post model
     * Usage: Post::findBySlug('my-awesome-post')
     */
    public static function findBySlug(string $slug): ?self
    {
        $result = static::query()
            ->where('slug', $slug)
            ->first();
        
        return $result ? static::hydrate($result) : null;
    }

    /**
     * Find published posts - specific to Post model
     * Usage: Post::findPublished(10)
     */
    public static function findPublished(int $limit = 10): array
    {
        $results = static::query()
            ->where('status', 'published')
            ->where('published_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
        
        return static::hydrateMany($results);
    }

    /**
     * Scope for draft posts
     * Usage: Post::draft()->get()
     */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    /**
     * Scope for featured posts
     * Usage: Post::featured()->limit(5)->get()
     */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * Instance method to publish a post
     */
    public function publish(): bool
    {
        $this->status = 'published';
        $this->published_at = date('Y-m-d H:i:s');
        return $this->save();
    }
}

class User extends Model
{
    protected string $table = 'users';
    
    /**
     * Find user by email - specific to User model
     * Usage: User::findByEmail('user@example.com')
     */
    public static function findByEmail(string $email): ?self
    {
        $result = static::query()
            ->where('email', $email)
            ->first();
        
        return $result ? static::hydrate($result) : null;
    }

    /**
     * Scope for active users
     * Usage: User::active()->get()
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active')
              ->whereNotNull('email_verified_at');
    }
}
```

### Using Model-Specific Methods

```php
// Model-specific methods are only available on that model
$post = Post::findBySlug('hello-world');        // ✅ Works
$publishedPosts = Post::findPublished(5);       // ✅ Works
$user = User::findByEmail('john@example.com');  // ✅ Works

// These won't work - methods are model-specific
$user = User::findBySlug('john');               // ❌ Error - User doesn't have findBySlug
$posts = User::findPublished();                 // ❌ Error - User doesn't have findPublished
```

### Combining Model-Specific Methods with Global Extensions

```php
// First, add a global macro (affects ALL models and queries)
Builder::macro('whereRecent', function($days = 7) {
    $date = date('Y-m-d', strtotime("-{$days} days"));
    return $this->where('created_at', '>=', $date);
});

// Now you can use both model-specific and global methods
$recentPublished = Post::query()
    ->whereRecent(30)          // Global macro - works on any model
    ->where('status', 'published')  // Standard query builder
    ->get();

$recentUsers = User::query()
    ->whereRecent(7)            // Same global macro works here too
    ->get();

// Use model-specific scope with global macro
$featuredRecent = Post::featured()  // Model-specific scope
    ->whereRecent(14)               // Global macro
    ->limit(5)
    ->get();
```

### Model CRUD Operations

```php
// Create
$post = Post::create([
    'title' => 'My New Post',
    'slug' => 'my-new-post',
    'content' => 'Post content here...',
    'status' => 'draft',
]);

// Read
$post = Post::find(1);
$post = Post::findOrFail(1);  // Throws exception if not found
$allPosts = Post::all();

// Update
$post = Post::find(1);
$post->title = 'Updated Title';
$post->save();

// Delete
$post = Post::find(1);
$post->delete();

// Using query builder methods on models
$posts = Post::where('status', 'published')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

### Best Practices for Model Methods

1. **Model-Specific Methods**: Define methods that are specific to the model's domain
   ```php
   class Product extends Model {
       public static function findInStock(): array { /* ... */ }
       public static function findByCategory(string $category): array { /* ... */ }
   }
   ```

2. **Use Scopes for Reusable Query Logic**: Scopes are better for chainable query modifiers
   ```php
   public function scopeActive(Builder $query): void {
       $query->where('active', true);
   }
   // Usage: Product::active()->inStock()->get()
   ```

3. **Global Extensions for Cross-Model Features**: Use macros for functionality needed across all models
   ```php
   Builder::macro('whereToday', function() {
       return $this->whereDate('created_at', date('Y-m-d'));
   });
   // Works on any model: User::whereToday()->get(), Post::whereToday()->get()
   ```

## WordPress Integration with Models

For WordPress projects, you can create a base WordPress model:

```php
namespace App\Models;

use Bob\Database\Model;

abstract class WordPressModel extends Model
{
    /**
     * Get WordPress table prefix
     */
    protected function getTable(): string
    {
        global $wpdb;
        $table = parent::getTable();
        return $wpdb->prefix . $table;
    }
}

class WPPost extends WordPressModel
{
    protected string $table = 'posts';  // Will become wp_posts
    
    public static function findBySlug(string $slug): ?self
    {
        $result = static::query()
            ->where('post_name', $slug)
            ->where('post_status', 'publish')
            ->first();
        
        return $result ? static::hydrate($result) : null;
    }
    
    public function scopePublished(Builder $query): void
    {
        $query->where('post_status', 'publish')
              ->where('post_type', 'post');
    }
}
```

## Conclusion

Bob Query Builder's extension system makes it incredibly flexible and adaptable to any domain or framework. Whether you're building for WordPress, Laravel, or a custom application, you can extend Bob to fit your specific needs without modifying the core library.

The combination of:
- **Global Macros** - for cross-cutting functionality
- **Model-Specific Methods** - for domain logic
- **Query Scopes** - for reusable query patterns
- **Dynamic Finders** - for intuitive method names

Gives you complete control over how you interact with your database while maintaining clean, readable code.

For more examples and the latest updates, visit the [Bob Query Builder GitHub repository](https://github.com/Marwen-Brini/bob-the-builder).