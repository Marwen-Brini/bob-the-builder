# Bob Query Builder

A highly optimized, standalone PHP query builder with Laravel-like fluent syntax. Originally designed to enhance Quantum ORM's query building capabilities, **Bob Query Builder is a fully independent package that can be used in ANY PHP project** - from WordPress plugins to modern PHP frameworks, microservices, or standalone applications.

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://www.php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-301%20passing-brightgreen)](https://github.com/Marwen-Brini/bob-the-builder/actions)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](https://github.com/Marwen-Brini/bob-the-builder)

## Why Bob Query Builder?

While initially created to modernize Quantum ORM's query building capabilities, Bob Query Builder was designed from the ground up as a **universal PHP query builder** that can enhance ANY PHP application:

- ✅ **Framework Agnostic** - Use it with Laravel, Symfony, WordPress, or vanilla PHP
- ✅ **Zero Lock-in** - No framework dependencies, just pure PHP and PDO
- ✅ **Modern PHP** - Built for PHP 8.1+ with full type safety
- ✅ **Production Ready** - 301 tests, 100% passing, battle-tested
- ✅ **High Performance** - <10ms query building overhead, handles 50k+ rows efficiently

## Features

- 🚀 **Laravel-like Fluent Interface** - Familiar, expressive syntax
- 🔧 **Database Agnostic** - Support for MySQL, PostgreSQL, SQLite via PDO
- 🎯 **Zero Dependencies** - Only requires PHP and PDO
- ⚡ **High Performance** - Query caching, prepared statements, 1M+ rows/second streaming
- 🧪 **Fully Tested** - 301 tests with Pest, 100% passing rate
- 🔒 **Secure** - Automatic SQL injection prevention via parameter binding
- 📦 **Modular** - Easy integration with ANY PHP project
- 🔄 **Transaction Support** - Including savepoints for nested transactions
- 📊 **PSR-3 Logging** - Built-in query logging with slow query detection
- 💾 **Memory Efficient** - Stream 50k+ rows with minimal memory usage

## Requirements

- PHP 8.1, 8.2, 8.3, or 8.4
- PDO extension
- Database-specific PDO driver (pdo_mysql, pdo_pgsql, pdo_sqlite)

## Installation

Install via Composer:

```bash
composer require bob/query-builder
```

## Quick Start

```php
use Bob\Database\Connection;

// Configure your database connection
$connection = new Connection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

// Start building queries
$users = $connection->table('users')
    ->where('active', true)
    ->where('age', '>=', 18)
    ->orderBy('name')
    ->limit(10)
    ->get();
```

## Basic Usage

### Select Queries

```php
// Get all records
$users = $connection->table('users')->get();

// Get specific columns
$users = $connection->table('users')
    ->select('id', 'name', 'email')
    ->get();

// Get first record
$user = $connection->table('users')
    ->where('email', 'john@example.com')
    ->first();

// Get single value
$email = $connection->table('users')
    ->where('id', 1)
    ->value('email');
```

### Where Clauses

```php
// Basic where
$users = $connection->table('users')
    ->where('status', 'active')
    ->get();

// Multiple conditions
$users = $connection->table('users')
    ->where('status', 'active')
    ->where('age', '>', 18)
    ->get();

// Or where
$users = $connection->table('users')
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// Where in
$users = $connection->table('users')
    ->whereIn('id', [1, 2, 3])
    ->get();

// Where between
$users = $connection->table('users')
    ->whereBetween('age', [18, 65])
    ->get();

// Where null
$users = $connection->table('users')
    ->whereNull('deleted_at')
    ->get();
```

### Joins

```php
// Inner join
$users = $connection->table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title')
    ->get();

// Left join
$users = $connection->table('users')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->get();

// Multiple joins
$users = $connection->table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->join('comments', 'posts.id', '=', 'comments.post_id')
    ->get();
```

### Aggregates

```php
// Count
$count = $connection->table('users')->count();

// Sum
$total = $connection->table('orders')->sum('amount');

// Average
$avg = $connection->table('products')->avg('price');

// Min/Max
$min = $connection->table('products')->min('price');
$max = $connection->table('products')->max('price');
```

### Insert

```php
// Single record
$connection->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('password')
]);

// Multiple records
$connection->table('users')->insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com']
]);

// Insert and get ID
$id = $connection->table('users')->insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

### Update

```php
// Update records
$affected = $connection->table('users')
    ->where('id', 1)
    ->update(['status' => 'active']);

// Update with increment
$connection->table('posts')
    ->where('id', 1)
    ->update(['views' => $connection->raw('views + 1')]);
```

### Delete

```php
// Delete records
$deleted = $connection->table('users')
    ->where('status', 'inactive')
    ->delete();

// Delete by ID
$connection->table('users')->delete(5);

// Truncate table
$connection->table('users')->truncate();
```

### Transactions

```php
// Basic transaction
$connection->transaction(function ($connection) {
    $connection->table('users')->insert([...]);
    $connection->table('posts')->insert([...]);
});

// Manual transaction control
$connection->beginTransaction();
try {
    // Your queries here
    $connection->commit();
} catch (Exception $e) {
    $connection->rollBack();
    throw $e;
}

// Transaction with retries
$connection->transaction(function ($connection) {
    // Your queries here
}, attempts: 3);
```

### Raw Expressions

```php
// Raw select
$users = $connection->table('users')
    ->select($connection->raw('COUNT(*) as user_count'))
    ->get();

// Raw where
$users = $connection->table('users')
    ->where('created_at', '>', $connection->raw('NOW() - INTERVAL 1 DAY'))
    ->get();
```

### Pagination

```php
// Simple pagination
$page = 2;
$perPage = 15;

$users = $connection->table('users')
    ->page($page, $perPage)
    ->get();

// Manual limit/offset
$users = $connection->table('users')
    ->limit(10)
    ->offset(20)
    ->get();
```

### Chunking

Process large datasets efficiently:

```php
$connection->table('users')->chunk(100, function ($users) {
    foreach ($users as $user) {
        // Process user
    }
});
```

### Cursor Streaming

For extremely large datasets (50k+ rows), use cursor for memory-efficient processing:

```php
// Stream millions of rows with minimal memory usage
foreach ($connection->table('users')->cursor() as $user) {
    // Process one user at a time
    // Memory usage stays constant regardless of dataset size
    processUser($user);
}

// Performance: 1M+ rows/second throughput
// Memory: ~8MB for 15,000 rows
```

### Query Debugging

```php
// Enable query logging
$connection->enableQueryLog();

// Run queries
$users = $connection->table('users')->get();

// Get query log
$queries = $connection->getQueryLog();
print_r($queries);

// Get SQL without executing
$sql = $connection->table('users')
    ->where('active', true)
    ->toSql();
echo $sql; // select * from "users" where "active" = ?

// Get bindings
$bindings = $connection->table('users')
    ->where('active', true)
    ->getBindings();
print_r($bindings); // [true]
```

## Database Configuration

### MySQL/MariaDB

```php
$connection = new Connection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'database_name',
    'username' => 'username',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'options' => [
        PDO::ATTR_PERSISTENT => false,
    ]
]);
```

### PostgreSQL

```php
$connection = new Connection([
    'driver' => 'pgsql',
    'host' => '127.0.0.1',
    'port' => 5432,
    'database' => 'database_name',
    'username' => 'username',
    'password' => 'password',
    'prefix' => '',
    'schema' => 'public',
]);
```

### SQLite

```php
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => '/path/to/database.sqlite',
    'prefix' => '',
]);

// In-memory database (great for testing)
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
```

## Advanced Features

### Query Builder Cloning

```php
$baseQuery = $connection->table('users')->where('active', true);

// Clone for variations
$admins = $baseQuery->clone()->where('role', 'admin')->get();
$users = $baseQuery->clone()->where('role', 'user')->get();
```

### Subqueries

```php
$subquery = $connection->table('posts')
    ->select('user_id')
    ->where('published', true);

$users = $connection->table('users')
    ->whereIn('id', $subquery)
    ->get();
```

### Custom Grammars

Extend the grammar for custom SQL dialects:

```php
use Bob\Query\Grammar;

class CustomGrammar extends Grammar
{
    // Override methods for custom SQL generation
}

$connection->setQueryGrammar(new CustomGrammar());
```

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test suite
vendor/bin/pest tests/Integration

# Run with verbose output
vendor/bin/pest -vvv
```

## Performance Benchmarks

Bob Query Builder is highly optimized for real-world applications:

### Query Building Performance
- **Simple SELECT**: ~0.02ms average overhead
- **Complex queries with joins**: ~0.1ms average overhead
- **Subqueries**: ~0.07ms average overhead
- **All query types**: <10ms overhead guaranteed

### Large Dataset Handling
- **Streaming**: 1M+ rows per second throughput
- **Memory usage**: ~8MB for 15,000 rows with cursor
- **50,000 rows**: Handled efficiently with <30MB memory
- **Chunk processing**: Process millions of rows without memory issues

### Optimization Features

The query builder includes several optimization features:

- **Prepared Statement Caching**: Reuses prepared statements for identical queries
- **Connection Pooling**: Efficient connection management
- **Query Result Caching**: Optional caching of query results
- **Lazy Loading**: Use `cursor()` for memory-efficient iteration
- **Bulk Operations**: Optimized bulk inserts and updates
- **Statement Caching**: Second run of cached statements is significantly faster

## Use Cases

Bob Query Builder is perfect for:

- **WordPress Plugins** - Modern query building without the overhead
- **Legacy PHP Applications** - Modernize database interactions incrementally  
- **Microservices** - Lightweight, efficient database layer
- **API Development** - Clean, readable query construction
- **Any PHP Project** - From simple scripts to complex applications

## Integration Examples

### WordPress / Quantum ORM

```php
use Bob\Database\Connection;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'prefix' => $wpdb->prefix,
]);

// Now use modern query building in WordPress!
$posts = $connection->table('posts')
    ->where('post_status', 'publish')
    ->orderBy('post_date', 'desc')
    ->limit(10)
    ->get();
```

### Standalone PHP Application

```php
use Bob\Database\Connection;

$connection = new Connection([
    'driver' => 'sqlite',
    'database' => 'database.sqlite',
]);

$users = $connection->table('users')
    ->where('active', true)
    ->get();
```

### Integration with Any Framework

Bob Query Builder can be registered as a service in any dependency injection container:

```php
// In your service provider or bootstrap
$container->singleton(Connection::class, function () {
    return new Connection(config('database'));
});
```

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Testing Requirements

- Tests must be written before implementation (TDD)
- All tests must pass before merging
- Maintain 100% code coverage
- Follow PSR-12 coding standards

## License

The Bob Query Builder is open-sourced software licensed under the [MIT license](LICENSE).

## Project Repository

🔗 **GitHub**: [https://github.com/Marwen-Brini/bob-the-builder](https://github.com/Marwen-Brini/bob-the-builder)

## Credits

- Originally built to enhance Quantum ORM, but designed as a **standalone solution for ANY PHP project**
- Inspired by Laravel's Eloquent Query Builder
- Designed with modern PHP best practices and 100% test coverage
- Built with love for the PHP community

## Support

For bugs and feature requests, please use the [GitHub issues](https://github.com/Marwen-Brini/bob-the-builder/issues).

## Roadmap

### Completed ✅
- [x] Core query building functionality
- [x] Multi-database support (MySQL, PostgreSQL, SQLite)
- [x] Transaction support
- [x] Prepared statement caching
- [x] Query result caching
- [x] Connection pooling
- [x] Performance profiling
- [x] PSR-3 logging integration
- [x] Slow query detection
- [x] Memory-efficient streaming (cursor/chunk)
- [x] CLI tools for testing and query building
- [x] Comprehensive test suite (301 tests)
- [x] Performance benchmarks

### Planned Features
- [ ] Schema builder
- [ ] Migration system
- [ ] Query builder macros/extensions
- [ ] Additional database support (SQL Server, Oracle)
- [ ] Query builder IDE helpers
- [ ] Database event listeners