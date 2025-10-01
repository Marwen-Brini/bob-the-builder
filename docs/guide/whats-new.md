# What's New

## v3.0.0 - Complete Migration System & Schema Builder 🎉

### 🗄️ Major Release: Database Migrations & Schema Management

Bob v3.0 introduces the most significant update since the ORM was added in v2.0! This release transforms Bob from a query builder + ORM into a **complete database toolkit** with full migration and schema management capabilities.

### Database Migrations

Bob now includes a comprehensive migration system:

```php
use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

**Migration Features:**
- ✅ Dependency resolution between migrations
- ✅ Transaction support with automatic rollback on failure
- ✅ Batch tracking for organized rollbacks
- ✅ Lifecycle hooks (`before()`, `after()`)
- ✅ Event system for logging and monitoring
- ✅ Pretend mode for safe testing
- ✅ Migration status and history tracking

[Learn more about Database Migrations →](/guide/migrations)

### Schema Builder

Create and modify database tables with a fluent interface:

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->foreignId('author_id')->constrained('users');
    $table->enum('status', ['draft', 'published'])->default('draft');
    $table->timestamps();
    $table->softDeletes();
});
```

**Supported Operations:**
- ✅ All standard column types (string, integer, decimal, json, etc.)
- ✅ Column modifiers (nullable, default, unique, etc.)
- ✅ Indexes (primary, unique, index, fulltext)
- ✅ Foreign key constraints
- ✅ Works across MySQL, PostgreSQL, and SQLite

[Learn more about Schema Builder →](/guide/schema-builder)

### WordPress Schema Helpers

Specialized helpers for WordPress and WooCommerce:

```php
Schema::createWordPress('custom_posts', function (WordPressBlueprint $table) {
    $table->wpPost();           // All standard WordPress post columns
    $table->wpPostIndexes();    // Standard WordPress indexes
});

Schema::createWordPress('custom_meta', function (WordPressBlueprint $table) {
    $table->wpMeta('custom');   // Instant meta table structure
});
```

[Learn more about WordPress Schema →](/guide/wordpress-schema)

### Schema Inspector

Reverse engineer existing databases:

```php
$inspector = new Inspector($connection);

// Get all tables
$tables = $inspector->getTables();

// Get detailed structure
$columns = $inspector->getColumns('users');
$indexes = $inspector->getIndexes('users');
$foreignKeys = $inspector->getForeignKeys('users');

// Auto-generate migration from existing table
$migration = $inspector->generateMigration('users');
```

[Learn more about Schema Inspector →](/guide/schema-inspector)

### 🔄 Backward Compatibility

**All existing functionality remains 100% backward compatible!** The migration system is entirely new functionality that integrates seamlessly with your existing Bob code.

### 📚 Complete Documentation

New comprehensive guides:
- [Database Migrations Guide](/guide/migrations)
- [Schema Builder Reference](/guide/schema-builder)
- [WordPress Schema Helpers](/guide/wordpress-schema)
- [Schema Inspector Guide](/guide/schema-inspector)

---

## v2.2.2 - 100% Test Coverage Achieved

### 🎯 Major Milestone

Bob ORM has achieved **100% code coverage** across the entire codebase! This release focuses on test suite improvements and reliability enhancements.

### ✅ Test Suite Improvements

- **1773 tests passing** with 4387 assertions (up from 1738 tests)
- **Model class**: Full 100% coverage including edge cases for existing ID updates
- **Fixed test failures**: Resolved all remaining test issues for complete stability
- **Enhanced reliability**: Better handling of complex scenarios and edge cases

### 🛠️ Fixed Issues

- **Class naming conflicts**: Fixed duplicate class declarations in Issue #13 debug tests
- **Test expectations**: Updated Issue #15 tests to reflect corrected behavior
- **BelongsToMany relationships**: Improved configuration for WordPress-style tables
- **Model ID handling**: Added comprehensive tests for models with existing database IDs

### 📊 Coverage Details

- All core components now at 100% coverage
- Added strategic tests for previously uncovered edge cases
- Improved test organization and maintainability

## v2.2.1 - Bug Fixes

### 🛠️ Fixed Issues

#### Global Scope Field References
Fixed issue where fields selected from JOINed tables in global scopes could not be referenced in WHERE clauses without table prefixes:

```php
// Define a model with a global scope that adds JOINs
class Category extends Model {
    protected static function booted() {
        static::addGlobalScope('withMetadata', function ($builder) {
            $builder->join('category_meta', 'categories.id', '=', 'category_meta.category_id')
                   ->select('categories.*', 'category_meta.parent', 'category_meta.count');
        });
    }
}

// Now you can reference JOINed fields directly
$rootCategories = Category::where('parent', 0)->get(); // ✅ Works!
$popularCategories = Category::where('count', '>', 100)->get(); // ✅ Works!
```

**Technical Details:**
- Global scopes are now properly applied in the `toSql()` method
- Added tracking to prevent duplicate scope application
- Builder is cloned in `toSql()` to avoid side effects

## v2.1.1 - Bug Fixes

### 🛠️ Fixed Issues

#### Table Prefix Handling in JOIN Clauses
Fixed table prefix issues in production environments:

- **Double prefix bug fixed**: JOIN WHERE clauses no longer duplicate prefixes
- **Global scopes with JOINs**: Now work correctly without prefix duplication
- **Table aliases**: Proper handling in SELECT statements with JOINs
- **Subqueries**: `whereIn()` with subquery builders handles prefixes correctly

#### New Model Method: forceFill()
Added Laravel-compatible `forceFill()` method for bypassing mass assignment:

```php
// Hydrate models from database without checking fillable/guarded
$model = new User();
$model->forceFill([
    'id' => 1,
    'name' => 'John Doe',
    'admin' => true,  // Bypasses guarded
    'role' => 'superadmin'  // Bypasses guarded
]);

// Chain with syncOriginal() for clean state
$model->forceFill($databaseRow)->syncOriginal();
```

## v2.1.0 - Query Caching & Improvements

### 🎉 New Features

#### Query Caching for exists()

Optimize repeated existence checks with the new opt-in caching mechanism. Perfect for validation logic and conditional workflows where the same existence check happens multiple times:

```php
// Enable caching with custom TTL (in seconds)
$builder = $connection->table('users')
    ->enableExistsCache(300) // Cache for 5 minutes
    ->where('email', 'user@example.com');

// First call - hits database
if ($builder->exists()) {
    // User exists
}

// Subsequent calls within 5 minutes - uses cache!
if ($builder->exists()) { // No database query
    // Still fast!
}

// Disable caching when needed
$builder->disableExistsCache();

// Check if caching is enabled
if ($builder->isExistsCachingEnabled()) {
    // Caching is active
}
```

**Benefits:**
- Eliminates redundant database queries
- Configurable TTL per builder instance
- Integrates with existing QueryCache infrastructure
- Disabled by default for backward compatibility

## 🛠️ Critical Fixes

### Table Prefix Handling in JOINs

Complete fix for table prefix issues affecting WordPress/WooCommerce installations:

```php
// All these scenarios now work correctly with prefix 'wp_'

// No more double prefixing
$builder->from('wp_posts')->join('users', ...);
// Before: FROM `wp_wp_posts` ❌
// Now: FROM `wp_posts` ✅

// Database qualified names
$builder->from('database.posts')->join('database.users', ...);
// Before: FROM `wp_database`.`posts` ❌
// Now: FROM `database`.`wp_posts` ✅

// Subquery JOINs with expressions
$subquery = $builder->newQuery()->from('comments')->select('post_id', DB::raw('COUNT(*) as count'));
$builder->from('posts')->joinSub($subquery, 'c', 'posts.id', '=', 'c.post_id');
// Now correctly preserves Expression objects without prefixing
```

### Global Scopes in Relationships

Relationships now properly inherit and respect global scopes:

```php
class Post extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('published', function ($query) {
            $query->where('status', 'published');
        });
    }
}

// Global scopes automatically apply to relationships
$user->posts()->get(); // Only returns published posts

// New property to control scope inheritance
class User extends Model
{
    protected bool $applyGlobalScopesToRelationships = true; // Default
}

// Disable global scopes for specific queries
$user->posts()->withoutGlobalScopes()->get(); // All posts
$user->posts()->withoutGlobalScope('published')->get(); // Without specific scope
```

---

# What's New in v2.0.7

## WordPress/WooCommerce Integration Fixes

Bob ORM v2.0.7 brings critical fixes for WordPress and WooCommerce integration, making it the perfect ORM solution for WordPress plugin development.

## 🐛 Bug Fixes

### 1. Global Scopes Support

Added Laravel-style instance-level global scopes for better query filtering:

```php
// Add a global scope to filter only published posts
$builder->addGlobalScope('published', function($query) {
    $query->where('status', 'published');
});

// Remove specific scope
$builder->withoutGlobalScope('published');

// Remove all scopes
$builder->withoutGlobalScopes();
```

### 2. Nested WHERE Closures

Fixed SQL generation for complex nested conditions:

```php
// Now works correctly!
$users = User::where('active', true)
    ->where(function($query) {
        $query->where('role', 'admin')
              ->orWhere('role', 'moderator');
    })
    ->get();
// Generates: WHERE active = ? AND (role = ? OR role = ?)
```

### 3. Delete Operation Bindings

Fixed parameter binding isolation for delete operations:

```php
// Complex query with joins and multiple conditions
$builder->join('posts', 'users.id', '=', 'posts.user_id')
    ->where('users.status', 'inactive')
    ->where('posts.created_at', '<', '2024-01-01')
    ->delete(); // Now uses only WHERE bindings, not JOIN bindings
```

### 4. Timestamp Handling

Proper respect for `$timestamps = false` - essential for WordPress tables:

```php
class WPPost extends Model {
    protected string $table = 'wp_posts';
    protected bool $timestamps = false; // No created_at/updated_at columns
    
    // Works perfectly with WordPress table structure!
}
```

### 5. Scope Chaining

Full support for chaining custom scope methods:

```php
class Post extends Model {
    public function scopePublished($query) {
        return $query->where('status', 'published');
    }
    
    public function scopeByAuthor($query, $authorId) {
        return $query->where('author_id', $authorId);
    }
}

// Chain scopes fluently!
$posts = Post::published()
    ->byAuthor(1)
    ->recent()
    ->get();
```

### 6. Aggregate Functions

Automatic detection and handling of SQL aggregate functions:

```php
// These now work without selectRaw()!
$results = $db->table('orders')
    ->select('status', 'COUNT(*) as count', 'SUM(total) as revenue')
    ->groupBy('status')
    ->get();

// Supports: COUNT, SUM, AVG, MIN, MAX, GROUP_CONCAT, etc.
```

### 7. Subquery Support

Fixed `whereIn()` with Builder subqueries:

```php
// Get all users who have posted recently
$recentPosters = $db->table('posts')
    ->select('user_id')
    ->where('created_at', '>', now()->subDays(7));

$users = User::whereIn('id', $recentPosters)->get();
// Properly handles subquery bindings!
```

## 🚀 PHP 8.4 Compatibility

Full PHP 8.4 support with explicit nullable type declarations:

```php
// All method signatures updated
public function where($column, mixed $operator = null, mixed $value = null): self
public function delete(mixed $id = null): int
public function getBindings(?string $type = null): array
```

## 📈 Impact

These fixes make Bob ORM ideal for:
- **WordPress Plugin Development** - Full compatibility with WordPress database structure
- **WooCommerce Extensions** - Handle complex e-commerce queries efficiently
- **Legacy Database Integration** - Work with existing databases that don't follow modern conventions
- **Complex Query Building** - Build sophisticated queries with confidence

## 🔄 Upgrading

Upgrading to v2.0.7 is seamless with no breaking changes:

```bash
composer update marwen-brini/bob-the-builder
```

All existing code continues to work, with the added benefit of these bug fixes and improvements.

## 📚 Learn More

- [Query Builder Documentation](/guide/query-builder)
- [Model Scopes](/guide/models#scopes)
- [Troubleshooting Guide](/guide/troubleshooting)
- [Changelog](/guide/CHANGELOG)