# Changelog

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