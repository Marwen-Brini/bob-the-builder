# Global Scopes Documentation

## Overview

Global scopes allow you to add constraints to all queries for a given model or query builder instance. This is particularly useful for features like soft deletes, multi-tenancy, or filtering by user permissions.

## BOB_ISSUE_001_GLOBAL_SCOPES - RESOLVED ✅

This implementation resolves the critical issue where Bob didn't support Laravel-style global scopes via the `addGlobalScope()` method.

## Basic Usage

### Adding a Global Scope with a Closure

```php
use Bob\Query\Builder;

$builder = $connection->table('posts');

// Add a global scope to filter soft deleted records
$builder->addGlobalScope('soft_deletes', function (Builder $query) {
    $query->whereNull('deleted_at');
});

// All subsequent queries will automatically include the scope
$posts = $builder->get(); // Only returns non-deleted posts
```

### Adding a Global Scope with a Class

```php
class PublishedScope {
    public function apply(Builder $builder, $model) {
        $builder->where('status', 'published');
    }
}

$builder->addGlobalScope('published', new PublishedScope());
```

## Removing Global Scopes

### Remove a Specific Scope

```php
$builder->withoutGlobalScope('soft_deletes');
```

### Remove Multiple Scopes

```php
$builder->withoutGlobalScopes(['scope1', 'scope2']);
```

### Remove All Scopes

```php
$builder->withoutGlobalScopes();
```

## Advanced Examples

### Multi-Tenant Application

```php
class TenantScope {
    private $tenantId;

    public function __construct($tenantId) {
        $this->tenantId = $tenantId;
    }

    public function apply(Builder $builder, $model) {
        $builder->where('tenant_id', $this->tenantId);
    }
}

// In your application bootstrap
$builder->addGlobalScope('tenant', new TenantScope($currentTenantId));
```

### WordPress Post Types

```php
// Filter to only show posts (not pages or other post types)
$builder->addGlobalScope('post_type', function (Builder $query) {
    $query->where('post_type', 'post');
});
```

### User-Specific Data

```php
$builder->addGlobalScope('user', function (Builder $query) use ($userId) {
    $query->where('user_id', $userId);
});
```

## Model Integration

When using with models, you can add global scopes in the model's boot method:

```php
class Post extends Model {
    protected static function boot() {
        parent::boot();

        // Add global scope to all queries for this model
        static::addGlobalScope('published', function (Builder $builder) {
            $builder->where('status', 'published');
        });
    }
}
```

## Scope Application

Global scopes are automatically applied to the following query methods:
- `get()`
- `first()`
- `find()`
- `exists()`
- `count()` and other aggregates
- `update()`
- `delete()`

## Key Features

✅ **Laravel-compatible API** - Easy migration from Laravel applications
✅ **Closure and Class Support** - Use anonymous functions or dedicated scope classes
✅ **Selective Removal** - Remove specific scopes or all scopes as needed
✅ **Automatic Application** - Scopes are applied automatically to all query operations
✅ **Model Integration** - Works seamlessly with Bob's Model class
✅ **Performance** - Minimal overhead, scopes are only applied when queries are executed

## Migration from Laravel

The API is fully compatible with Laravel's global scopes:

```php
// Laravel code - works with Bob unchanged
$query->addGlobalScope('active', function ($builder) {
    $builder->where('active', true);
});

$query->withoutGlobalScope('active');
$query->withoutGlobalScopes();
```

## Testing

The global scopes feature includes comprehensive test coverage:
- 17 test cases covering all functionality
- Tests for closure and class-based scopes
- Tests for scope removal operations
- Integration tests with query operations
- Model integration tests

## Performance Considerations

- Scopes are only evaluated when queries are executed
- No performance impact when scopes are not used
- Minimal overhead even with multiple scopes
- Scopes are applied in the order they were added

## Troubleshooting

### Scope Not Applied

Make sure you're calling `addGlobalScope()` before executing the query:

```php
// Correct
$builder->addGlobalScope('scope', $callback);
$results = $builder->get();

// Incorrect - scope added after query execution
$results = $builder->get();
$builder->addGlobalScope('scope', $callback);
```

### Removing Non-Existent Scope

Removing a non-existent scope is safe and won't cause errors:

```php
$builder->withoutGlobalScope('non_existent'); // No error
```

## Implementation Details

The implementation adds:
- `addGlobalScope($identifier, $scope)` - Register a global scope
- `withoutGlobalScope($scope)` - Remove a specific scope
- `withoutGlobalScopes(array $scopes = null)` - Remove multiple or all scopes
- `getGlobalScopes()` - Get all registered scopes
- `applyScopes()` - Apply all non-removed scopes (called automatically)

The scopes are stored at the builder instance level, ensuring they don't interfere with other queries or builder instances.