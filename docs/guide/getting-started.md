# Getting Started

## What is Bob Query Builder?

Bob Query Builder is a powerful, standalone PHP database query builder that provides a Laravel-like fluent interface for building and executing database queries. It's designed to be framework-agnostic, meaning you can use it in any PHP project - from WordPress plugins to modern PHP frameworks.

## Key Features

- **Fluent Interface**: Chain methods together for readable, intuitive query building
- **Multi-Database Support**: Works with MySQL, PostgreSQL, and SQLite
- **Zero Dependencies**: Only requires PHP 8.1+ and PDO
- **Performance Optimized**: Prepared statement caching, connection pooling, and query profiling
- **Extensible**: Add custom methods through macros and model extensions
- **Type Safe**: Full type hints and PHPDoc for IDE support

## Requirements

- PHP 8.1 or higher
- PDO extension
- One of: MySQL, PostgreSQL, or SQLite

## Installation

Install Bob Query Builder via Composer:

```bash
composer require marwen-brini/bob-the-builder
```

## Basic Usage

### 1. Create a Connection

```php
use Bob\Database\Connection;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
```

### 2. Build Queries

```php
// Select all users
$users = $connection->table('users')->get();

// Select with conditions
$activeUsers = $connection->table('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->get();

// Insert a new user
$userId = $connection->table('users')->insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active',
]);

// Update a user
$connection->table('users')
    ->where('id', $userId)
    ->update(['status' => 'inactive']);

// Delete a user
$connection->table('users')
    ->where('id', $userId)
    ->delete();
```

## Using Models

Bob also provides an ActiveRecord-style Model class for more object-oriented database interactions:

```php
use Bob\Database\Model;

class User extends Model
{
    protected string $table = 'users';
}

// Configure the model connection
Model::setConnection($connection);

// Now use the model
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// Create a new user
$newUser = User::create([
    'name' => 'Bob Wilson',
    'email' => 'bob@example.com',
]);

// Query through the model
$activeUsers = User::where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->get();
```

## Database Configuration

### MySQL Configuration

```php
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
]);
```

### PostgreSQL Configuration

```php
$connection = new Connection([
    'driver' => 'pgsql',
    'host' => 'localhost',
    'port' => 5432,
    'database' => 'myapp',
    'username' => 'postgres',
    'password' => 'password',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
    'sslmode' => 'prefer',
]);
```

### SQLite Configuration

```php
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => '/path/to/database.sqlite',
    'prefix' => '',
    'foreign_key_constraints' => true,
]);

// Or use in-memory database for testing
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);
```

## WordPress Integration

Bob works great with WordPress. Here's how to use it in a WordPress plugin:

```php
use Bob\Database\Connection;

// Use WordPress database constants
$connection = new Connection([
    'driver' => 'mysql',
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'charset' => DB_CHARSET,
    'collation' => DB_COLLATE ?: 'utf8mb4_unicode_ci',
    'prefix' => $wpdb->prefix,
]);

// Now query WordPress tables
$posts = $connection->table('posts')
    ->where('post_status', 'publish')
    ->where('post_type', 'post')
    ->orderBy('post_date', 'desc')
    ->limit(10)
    ->get();
```

## Error Handling

Bob provides detailed exceptions for debugging:

```php
use Bob\Exceptions\QueryException;
use Bob\Exceptions\ConnectionException;

try {
    $result = $connection->table('users')->get();
} catch (ConnectionException $e) {
    // Handle connection errors
    echo "Connection failed: " . $e->getMessage();
} catch (QueryException $e) {
    // Handle query errors
    echo "Query failed: " . $e->getMessage();
    echo "SQL: " . $e->getSql();
    echo "Bindings: " . print_r($e->getBindings(), true);
}
```

## Query Logging

Enable query logging for debugging:

```php
// Enable logging
$connection->enableQueryLog();

// Run some queries
$connection->table('users')->get();
$connection->table('posts')->where('status', 'published')->get();

// Get the query log
$queries = $connection->getQueryLog();

foreach ($queries as $query) {
    echo "Query: " . $query['query'] . "\n";
    echo "Bindings: " . print_r($query['bindings'], true) . "\n";
    echo "Time: " . $query['time'] . "ms\n\n";
}
```

## Next Steps

Now that you have Bob installed and configured, explore these topics:

- [Query Builder Basics](/guide/query-builder) - Learn all query building methods
- [Where Clauses](/guide/where-clauses) - Master complex conditions
- [Joins](/guide/joins) - Combine data from multiple tables
- [Models](/guide/models) - Use the ActiveRecord pattern
- [Extending Bob](/guide/extending) - Add custom functionality

## Getting Help

- 📖 Read the [full documentation](/guide/getting-started)
- 🐛 [Report issues](https://github.com/Marwen-Brini/bob-the-builder/issues)
- 💬 [Ask questions](https://github.com/Marwen-Brini/bob-the-builder/discussions)
- ⭐ [Star on GitHub](https://github.com/Marwen-Brini/bob-the-builder)