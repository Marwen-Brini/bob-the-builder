# Changelog

## [2.0.7] - 2025-09-24

### Fixed
- **Global Scopes**: Added instance-level global scopes support with `addGlobalScope()`, `withoutGlobalScope()`, and `withoutGlobalScopes()` methods for better query filtering
- **Nested WHERE Closures**: Fixed SQL generation bug that was producing invalid "where where" syntax in nested conditions
- **Delete Operation Bindings**: Resolved parameter binding mismatch in delete operations by properly isolating WHERE clause bindings
- **Timestamp Handling**: Verified and improved respect for `$timestamps = false` property, essential for WordPress/WooCommerce compatibility
- **Scope Chaining**: Implemented full support for chaining custom scope methods (e.g., `Post::published()->byAuthor(1)->recent()->get()`)
- **Aggregate Functions**: Added automatic detection and proper handling of SQL aggregate functions (COUNT, SUM, AVG, MIN, MAX, etc.) in select statements
- **Subquery Support**: Fixed `whereIn()` and related methods to properly handle Builder subqueries with correct parameter binding

### Added
- **PHP 8.4 Compatibility**: Full support for PHP 8.4 with explicit nullable type declarations
- **Test Coverage**: Added 7 new test files with 50+ tests covering all fixed issues

### WordPress/WooCommerce Integration
This release specifically addresses issues found during integration with Quantum ORM for WordPress/WooCommerce projects, making Bob ORM a perfect fit for WordPress plugin development.

## [2.0.0] - 2025-01-22

### Added
- **Complete ORM Layer** - Bob is now a full-featured ORM, not just a query builder
- **Model System** - ActiveRecord pattern implementation with `Bob\Database\Model` base class
- **Relationships** - Full support for HasOne, HasMany, BelongsTo, BelongsToMany
- **Eager Loading** - Prevent N+1 queries with `with()` method
- **Collections** - Powerful collection class for result sets

## [1.1.0] - 2025-09-13

### Changed
- **BREAKING**: All query methods now return `stdClass` objects instead of arrays for consistency
  - `Connection::select()` now returns array of `stdClass` objects
  - `Connection::selectOne()` now returns `?object` instead of `?array`
  - `Builder::get()` continues to return `stdClass` objects (unchanged)
  - `Builder::first()` continues to return `?stdClass` (unchanged)

### Why This Change?
Previously, there was an inconsistency:
- Query builder methods (`table()->get()`) returned objects
- Direct select methods (`select()`) returned arrays

Now both return objects, providing:
- Consistent API across all query methods
- Cleaner syntax with object property access (`$user->name` vs `$user['name']`)
- Better IDE autocomplete support
- Alignment with modern PHP practices

### Migration Guide
If you were using direct `select()` or `selectOne()` methods:

```php
// Before (arrays)
$results = $connection->select('SELECT * FROM users');
echo $results[0]['name'];

// After (objects)
$results = $connection->select('SELECT * FROM users');
echo $results[0]->name;
```

Query builder usage remains unchanged:
```php
// Still works the same
$users = $connection->table('users')->get();
echo $users[0]->name;
```

### Documentation Updates
- Updated all documentation to reflect object return types
- Fixed examples to use object property syntax
- Added notes about the consistency improvement

## [1.0.0] - Previous Release
- Initial release with full query builder functionality
- Laravel-like fluent interface
- Support for MySQL, PostgreSQL, and SQLite
- Comprehensive test coverage
- Full documentation