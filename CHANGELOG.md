# Changelog

All notable changes to Bob Query Builder will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Extension system with Macroable, Scopeable, and DynamicFinder traits
- Base Model class for ActiveRecord pattern implementation
- VitePress documentation system
- Comprehensive extension documentation and examples
- Database migration guide for Quantum ORM users
- Performance benchmarking suite
- Query result caching mechanism
- Schema builder functionality

### Changed
- Optimized prepared statement caching algorithm
- Converted all tests from PHPUnit annotations to Pest functional syntax
- Improved test data ranges for date-based scopes

### Fixed
- Memory leak in cursor iteration for large result sets
- Test failures in chain multiple extensions test (date range issue)
- Test failures in global scope removal test (order of operations)
- Deprecated @test annotations warning in PHPUnit 12

## [1.0.0] - 2025-09-07

### Added
- Initial release designed to enhance Quantum ORM but built as a standalone package for ANY PHP project
- Core query building functionality with fluent interface
- Multi-database support (MySQL, PostgreSQL, SQLite)
- Full transaction support including savepoints
- Prepared statement caching for improved performance
- Comprehensive test suite with Pest framework
- Query logging and debugging capabilities
- Support for raw expressions
- Chunking for processing large datasets
- Cursor/Generator support for memory-efficient iteration
- All standard SQL operations:
  - SELECT with complex WHERE clauses
  - INSERT (single and bulk)
  - UPDATE with conditions
  - DELETE with conditions
  - TRUNCATE
- JOIN operations (INNER, LEFT, RIGHT, CROSS)
- Aggregate functions (COUNT, SUM, AVG, MIN, MAX)
- GROUP BY and HAVING clauses
- ORDER BY with multiple columns
- LIMIT and OFFSET for pagination
- DISTINCT queries
- Subquery support
- Connection pooling
- PSR-4 autoloading
- PSR-12 coding standards compliance
- PHP 8.1+ support with full type declarations
- Comprehensive documentation
- GitHub Actions CI/CD pipeline

### Security
- Automatic SQL injection prevention via parameter binding
- Secure password handling in connection configuration
- Input validation and sanitization

### Performance
- Query building overhead < 10ms
- Support for 10k+ row streaming
- Prepared statement caching
- Lazy connection initialization
- Optimized bulk insert operations

### Testing
- 100% code coverage goal
- Contract tests for all interfaces
- Integration tests for each database driver
- Feature tests for fluent interface
- Architecture tests for code quality

## [0.9.0-beta] - 2025-09-01

### Added
- Beta release for testing
- Core interfaces defined
- Basic query building functionality
- SQLite support

### Known Issues
- Missing some WHERE clause types
- JOIN compilation incomplete
- PostgreSQL integration tests pending

## [0.1.0-alpha] - 2025-08-15

### Added
- Initial alpha release
- Project structure setup
- Basic Connection class
- Simple SELECT queries
- Test framework setup

---

## Versioning

This project uses Semantic Versioning:
- MAJOR version for incompatible API changes
- MINOR version for backwards-compatible functionality additions
- PATCH version for backwards-compatible bug fixes

## Upgrade Guide

### From Quantum ORM to 1.0.0

1. Update composer dependencies:
```bash
composer remove quantum/orm
composer require bob/query-builder
```

2. Update namespace imports:
```php
// Old
use QuantumORM\QueryBuilder;

// New
use Bob\Query\Builder;
use Bob\Database\Connection;
```

3. Update connection initialization:
```php
// Old
$builder = new QueryBuilder($wpdb);

// New
$connection = new Connection([
    'driver' => 'mysql',
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'prefix' => $wpdb->prefix,
]);
$builder = $connection->table('posts');
```

4. The query building API remains largely compatible.

### From 0.x to 1.0.0

Breaking changes:
- Renamed `Query` class to `Builder`
- Changed `execute()` to `get()` for consistency
- Removed deprecated `raw()` method from Builder (use Connection::raw())
- Constructor now requires all three dependencies

Non-breaking improvements:
- Added type declarations to all methods
- Improved error messages
- Better transaction handling
- Performance optimizations

## Support

For help with upgrades or migration, please see:
- [Migration Guide](MIGRATION.md)
- [API Documentation](API.md)
- [GitHub Issues](https://github.com/bob/query-builder/issues)

## Contributors

Thanks to all contributors who have helped make Bob Query Builder better:
- Initial extraction from Quantum ORM
- Community testing and feedback
- Documentation improvements
- Bug reports and fixes

[Unreleased]: https://github.com/bob/query-builder/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/bob/query-builder/releases/tag/v1.0.0
[0.9.0-beta]: https://github.com/bob/query-builder/releases/tag/v0.9.0-beta
[0.1.0-alpha]: https://github.com/bob/query-builder/releases/tag/v0.1.0-alpha