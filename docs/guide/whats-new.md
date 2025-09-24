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

- [Global Scopes Documentation](/guide/query-builder#global-scopes)
- [Model Scopes](/guide/models#scopes)
- [WordPress Integration Guide](/guide/wordpress)
- [Troubleshooting Guide](/guide/troubleshooting)