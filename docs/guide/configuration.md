# Configuration

## Basic Configuration

Bob Query Builder uses a configuration array to establish database connections. Here's the basic structure:

```php
use Bob\Database\Connection;

$config = [
    'driver'    => 'mysql',     // Database driver
    'host'      => 'localhost',  // Database host
    'port'      => 3306,         // Database port
    'database'  => 'myapp',      // Database name
    'username'  => 'root',       // Database username
    'password'  => 'password',   // Database password
    'charset'   => 'utf8mb4',    // Character set
    'collation' => 'utf8mb4_unicode_ci', // Collation
    'prefix'    => '',           // Table prefix
    'fetch'     => PDO::FETCH_ASSOC, // Fetch mode (default: PDO::FETCH_ASSOC)
];

$connection = new Connection($config);
```

## Driver-Specific Configuration

### MySQL Configuration

Full MySQL configuration with all options:

```php
$mysqlConfig = [
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'port'      => 3306,
    'database'  => 'myapp',
    'username'  => 'root',
    'password'  => 'password',
    'unix_socket' => '', // Use socket connection instead of TCP
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
    'prefix_indexes' => true,
    'strict'    => true,  // Enable strict mode
    'engine'    => 'InnoDB', // Default storage engine
    'options'   => [
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca.pem', // SSL configuration
    ],
];
```

### PostgreSQL Configuration

Full PostgreSQL configuration:

```php
$pgsqlConfig = [
    'driver'    => 'pgsql',
    'host'      => 'localhost',
    'port'      => 5432,
    'database'  => 'myapp',
    'username'  => 'postgres',
    'password'  => 'password',
    'charset'   => 'utf8',
    'prefix'    => '',
    'prefix_indexes' => true,
    'schema'    => 'public',  // Database schema
    'sslmode'   => 'prefer',  // SSL mode: disable, allow, prefer, require
    'options'   => [
        PDO::ATTR_PERSISTENT => false,
    ],
];
```

### SQLite Configuration

SQLite configuration options:

```php
// File-based database
$sqliteConfig = [
    'driver'   => 'sqlite',
    'database' => '/path/to/database.sqlite',
    'prefix'   => '',
    'foreign_key_constraints' => true, // Enable foreign keys
];

// In-memory database (great for testing)
$sqliteMemoryConfig = [
    'driver'   => 'sqlite',
    'database' => ':memory:',
    'prefix'   => '',
    'foreign_key_constraints' => true,
];
```

## Connection Options

### Fetch Mode Configuration

Bob Query Builder allows you to configure how query results are returned. By default, results are returned as associative arrays.

```php
// Configure fetch mode in connection config
$config = [
    'driver' => 'mysql',
    'database' => 'myapp',
    // ... other config
    'fetch' => PDO::FETCH_ASSOC, // Default: associative arrays
];

$connection = new Connection($config);
```

Available fetch modes:

| Mode | Description | Example Result |
|------|-------------|----------------|
| `PDO::FETCH_ASSOC` | Associative array (default) | `['id' => 1, 'name' => 'John']` |
| `PDO::FETCH_OBJ` | stdClass object | `(object) ['id' => 1, 'name' => 'John']` |
| `PDO::FETCH_NUM` | Numeric array | `[1, 'John']` |
| `PDO::FETCH_BOTH` | Both numeric and associative | `[0 => 1, 'id' => 1, 1 => 'John', 'name' => 'John']` |
| `PDO::FETCH_CLASS` | Custom class instances | Instance of specified class |

#### Dynamic Fetch Mode Changes

You can change the fetch mode at runtime:

```php
// Start with arrays (default)
$users = $connection->table('users')->get();
// $users[0]['name'] - array access

// Switch to objects
$connection->setFetchMode(PDO::FETCH_OBJ);
$posts = $connection->table('posts')->get();
// $posts[0]->title - object access

// Switch to numeric arrays
$connection->setFetchMode(PDO::FETCH_NUM);
$comments = $connection->table('comments')->get();
// $comments[0][1] - numeric index

// Check current fetch mode
$mode = $connection->getFetchMode();
```

#### Per-Query Fetch Mode

For one-off queries with different fetch modes:

```php
// Default connection uses arrays
$connection = new Connection($config);

// Temporarily use objects for this query
$oldMode = $connection->getFetchMode();
$connection->setFetchMode(PDO::FETCH_OBJ);
$users = $connection->table('users')->get();
$connection->setFetchMode($oldMode); // Restore
```

### PDO Attributes

You can pass PDO attributes through the options array:

```php
$config = [
    'driver' => 'mysql',
    // ... other config
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true, // Persistent connections
        PDO::MYSQL_ATTR_FOUND_ROWS => true, // Return matched rows, not affected
    ],
];
```

### Connection Pooling

Enable connection pooling for better performance:

```php
use Bob\Database\ConnectionPool;

$pool = new ConnectionPool($config, [
    'min_connections' => 2,    // Minimum idle connections
    'max_connections' => 10,   // Maximum total connections
    'max_idle_time' => 300,    // Seconds before closing idle connections
    'acquire_timeout' => 5,    // Seconds to wait for available connection
]);

// Get a connection from the pool
$connection = $pool->acquire();

// Use the connection
$users = $connection->table('users')->get();

// Return connection to pool
$pool->release($connection);
```

### Read/Write Connections

Configure separate read and write connections:

```php
$config = [
    'driver' => 'mysql',
    'read' => [
        'host' => [
            '192.168.1.1',
            '192.168.1.2',
        ],
    ],
    'write' => [
        'host' => '192.168.1.3',
    ],
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
    // ... other config
];
```

## Environment-Based Configuration

### Using Environment Variables

```php
$config = [
    'driver'   => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host'     => $_ENV['DB_HOST'] ?? 'localhost',
    'port'     => $_ENV['DB_PORT'] ?? 3306,
    'database' => $_ENV['DB_DATABASE'] ?? 'myapp',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    'prefix'   => $_ENV['DB_PREFIX'] ?? '',
];
```

### Using .env Files

With vlucas/phpdotenv:

```bash
composer require vlucas/phpdotenv
```

```php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$config = [
    'driver'   => $_ENV['DB_DRIVER'],
    'host'     => $_ENV['DB_HOST'],
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
];
```

## Configuration File

Create a `config/database.php` file:

```php
<?php

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', 'localhost'),
            'port'      => env('DB_PORT', 3306),
            'database'  => env('DB_DATABASE', 'myapp'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
        ],

        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => env('DB_HOST', 'localhost'),
            'port'     => env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'myapp'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
        ],

        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'   => '',
        ],

        'testing' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ],
    ],
];
```

Usage:

```php
$config = require 'config/database.php';
$connection = new Connection($config['connections'][$config['default']]);
```

## Performance Configuration

### Query Caching

Enable query result caching:

```php
use Bob\Cache\QueryCache;

$cache = new QueryCache();
$connection->setQueryCache($cache);

// Configure cache settings
$cache->setDefaultTTL(300); // 5 minutes
$cache->setMaxSize(100);    // Maximum 100 cached queries
```

### Statement Caching

Enable prepared statement caching:

```php
$connection->enableStatementCaching();
$connection->setMaxCachedStatements(50); // Cache up to 50 statements
```

### Query Logging

Configure query logging:

```php
// Enable logging
$connection->enableQueryLog();

// Configure log settings
$connection->setSlowQueryThreshold(100); // Log queries > 100ms as slow

// Set custom logger (PSR-3 compatible)
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('queries');
$logger->pushHandler(new StreamHandler('queries.log', Logger::DEBUG));

$connection->setLogger($logger);
```

## Advanced Configuration

### Custom Grammar

Use a custom SQL grammar:

```php
use Bob\Query\Grammars\CustomGrammar;

$connection = new Connection($config);
$connection->setQueryGrammar(new CustomGrammar());
```

### Custom Processor

Use a custom result processor:

```php
use Bob\Query\CustomProcessor;

$connection = new Connection($config);
$connection->setPostProcessor(new CustomProcessor());
```

### Timezone Configuration

Set the connection timezone:

```php
$connection = new Connection($config);

// MySQL
$connection->statement("SET time_zone = '+00:00'");

// PostgreSQL
$connection->statement("SET TIME ZONE 'UTC'");
```

## Testing Configuration

Configuration for testing environments:

```php
class TestCase extends PHPUnit\Framework\TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->migrate();
    }

    protected function migrate(): void
    {
        $this->connection->statement('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT,
                email TEXT UNIQUE
            )
        ');
    }

    protected function tearDown(): void
    {
        $this->connection->disconnect();
    }
}
```

## Configuration Validation

Validate configuration before use:

```php
function validateConfig(array $config): bool
{
    $required = ['driver', 'database'];

    foreach ($required as $key) {
        if (!isset($config[$key])) {
            throw new InvalidArgumentException("Missing required config: {$key}");
        }
    }

    if (!in_array($config['driver'], ['mysql', 'pgsql', 'sqlite'])) {
        throw new InvalidArgumentException("Invalid driver: {$config['driver']}");
    }

    return true;
}

try {
    validateConfig($config);
    $connection = new Connection($config);
} catch (InvalidArgumentException $e) {
    echo "Configuration error: " . $e->getMessage();
}
```

## Next Steps

- [Quick Start](/guide/quick-start) - Start building queries
- [Query Builder](/guide/query-builder) - Master query building
- [Performance](/guide/performance) - Optimize your queries