# Troubleshooting

This guide helps you diagnose and fix common issues when using Bob Query Builder.

## Connection Issues

### Cannot Connect to Database

**Problem**: Connection fails with "Connection refused" or similar error.

**Solutions**:

1. **Check Database Server**:
```php
// Test if database server is running
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'test',
    'username' => 'root',
    'password' => 'password',
]);

try {
    $connection->getPdo();
    echo "Connected successfully";
} catch (ConnectionException $e) {
    echo "Connection failed: " . $e->getMessage();
    // Check: Is MySQL running? Is the port correct?
}
```

2. **Verify Credentials**:
```php
// Test with command line first
mysql -h localhost -u root -p

// If that works, verify PHP configuration
var_dump([
    'host' => $config['host'],
    'database' => $config['database'],
    'username' => $config['username'],
    'password' => str_repeat('*', strlen($config['password'])),
]);
```

3. **Check Socket Connection (Unix)**:
```php
// For local MySQL on Unix/Linux
$connection = new Connection([
    'driver' => 'mysql',
    'unix_socket' => '/var/run/mysqld/mysqld.sock',
    'database' => 'test',
    'username' => 'root',
    'password' => 'password',
]);
```

### Access Denied Errors

**Problem**: "Access denied for user" error.

**Solutions**:

```php
// 1. Check user privileges in MySQL
mysql -u root -p
SHOW GRANTS FOR 'username'@'localhost';

// 2. Grant necessary privileges
GRANT ALL PRIVILEGES ON database.* TO 'username'@'localhost';
FLUSH PRIVILEGES;

// 3. Try connecting with minimal privileges
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'test',
    'username' => 'readonly_user',
    'password' => 'password',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ],
]);
```

### Character Set Issues

**Problem**: Special characters display incorrectly.

**Solution**:
```php
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'test',
    'username' => 'root',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

// Verify charset
$result = $connection->select("SHOW VARIABLES LIKE 'character_set_%'");
foreach ($result as $row) {
    echo $row->Variable_name . ': ' . $row->Value . "\n";
}
```

## Query Issues

### Query Returns Empty Results

**Problem**: Query returns no results when data exists.

**Debugging Steps**:

```php
// 1. Enable query logging
$connection->enableQueryLog();

// 2. Run your query
$users = $connection->table('users')
    ->where('status', 'active')
    ->where('age', '>', 25)
    ->get();

// 3. Check the actual SQL
$log = $connection->getQueryLog();
$lastQuery = end($log);
echo "SQL: " . $lastQuery['query'] . "\n";
echo "Bindings: " . json_encode($lastQuery['bindings']) . "\n";

// 4. Test raw SQL directly
$rawResults = $connection->select(
    "SELECT * FROM users WHERE status = ? AND age > ?",
    ['active', 25]
);

// 5. Check for case sensitivity
$users = $connection->table('users')
    ->whereRaw('LOWER(status) = ?', [strtolower('active')])
    ->get();
```

### Incorrect Query Results

**Problem**: Query returns unexpected data.

**Solutions**:

```php
// 1. Check your WHERE conditions
$query = $connection->table('users')
    ->where('status', 'active')
    ->orWhere('role', 'admin'); // This might include inactive admins!

// Should be:
$query = $connection->table('users')
    ->where(function($q) {
        $q->where('status', 'active')
          ->orWhere('role', 'admin');
    });

// 2. Verify JOIN conditions
$posts = $connection->table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.*', 'users.name as author')
    ->get();

// Debug: Check for NULL values
$posts = $connection->table('posts')
    ->leftJoin('users', 'posts.user_id', '=', 'users.id')
    ->whereNull('users.id')
    ->get(); // Posts with missing users

// 3. Check GROUP BY usage
$stats = $connection->table('orders')
    ->select('user_id', $connection->raw('SUM(total) as total_amount'))
    ->groupBy('user_id')
    ->get();
```

### SQL Syntax Errors

**Problem**: "SQL syntax error" exceptions.

**Solutions**:

```php
// 1. Use proper escaping for identifiers
$table = 'user-data'; // Contains hyphen
$connection->table('`user-data`')->get(); // Use backticks

// 2. Handle reserved words
$connection->table('users')
    ->select('id', 'name', '`order`') // 'order' is reserved
    ->get();

// 3. Check database-specific syntax
try {
    // PostgreSQL
    $users = $connection->table('users')
        ->whereRaw('created_at::date = ?', ['2024-01-01'])
        ->get();
} catch (QueryException $e) {
    // MySQL equivalent
    $users = $connection->table('users')
        ->whereRaw('DATE(created_at) = ?', ['2024-01-01'])
        ->get();
}
```

## Performance Issues

### Slow Queries

**Problem**: Queries take too long to execute.

**Diagnosis and Solutions**:

```php
// 1. Enable profiling
use Bob\Database\QueryProfiler;

$profiler = new QueryProfiler();
$connection->setProfiler($profiler);
$profiler->enable();
$profiler->setSlowQueryThreshold(100); // 100ms

// Run queries
$users = $connection->table('users')->get();

// Check for slow queries
$slowQueries = $profiler->getSlowQueries();
foreach ($slowQueries as $query) {
    echo "Slow query: " . $query['sql'] . "\n";
    echo "Time: " . $query['time'] . "ms\n";

    // Get query explanation
    $explanation = $connection->select("EXPLAIN " . $query['sql']);
    print_r($explanation);
}

// 2. Add indexes
$connection->statement('CREATE INDEX idx_users_status ON users(status)');
$connection->statement('CREATE INDEX idx_users_created ON users(created_at)');

// 3. Optimize query structure
// Bad: Multiple queries
foreach ($userIds as $id) {
    $posts = $connection->table('posts')
        ->where('user_id', $id)
        ->get();
}

// Good: Single query
$posts = $connection->table('posts')
    ->whereIn('user_id', $userIds)
    ->get();

// 4. Use select to limit columns
// Bad: Fetching all columns
$users = $connection->table('users')->get();

// Good: Only needed columns
$users = $connection->table('users')
    ->select('id', 'name', 'email')
    ->get();
```

### Memory Issues

**Problem**: "Allowed memory size exhausted" errors.

**Solutions**:

```php
// 1. Use chunking for large datasets
$connection->table('logs')->chunk(100, function($logs) {
    foreach ($logs as $log) {
        // Process 100 logs at a time
        $this->processLog($log);
    }

    // Force garbage collection
    gc_collect_cycles();
});

// 2. Use cursor for minimal memory
foreach ($connection->table('large_table')->cursor() as $row) {
    // Process one row at a time
    $this->processRow($row);
}

// 3. Clear query log in long-running scripts
$connection->enableQueryLog();

for ($i = 0; $i < 10000; $i++) {
    // Do queries...

    if ($i % 100 === 0) {
        $connection->clearQueryLog(); // Prevent memory leak
    }
}

// 4. Limit result sets
$users = $connection->table('users')
    ->limit(1000)
    ->get();
```

### Connection Pool Exhaustion

**Problem**: "No available connections in pool" error.

**Solutions**:

```php
use Bob\Database\ConnectionPool;

$pool = new ConnectionPool($config, [
    'min_connections' => 2,
    'max_connections' => 10,
    'max_idle_time' => 300,
    'acquisition_timeout' => 5,
]);

// 1. Always release connections
$connection = $pool->acquire();
try {
    // Use connection
    $users = $connection->table('users')->get();
} finally {
    $pool->release($connection); // Always release
}

// 2. Use automatic release
$users = $pool->using(function($connection) {
    return $connection->table('users')->get();
}); // Automatically released

// 3. Monitor pool status
$stats = $pool->getStatistics();
if ($stats['waiting_count'] > 0) {
    // Pool is exhausted, increase max_connections
    error_log("Connection pool exhausted: " . json_encode($stats));
}

// 4. Clean up idle connections
$pool->cleanupIdleConnections();
```

## Model Issues

### Model Not Found

**Problem**: "Class not found" errors when using models.

**Solutions**:

```php
// 1. Check autoloading
composer dump-autoload

// 2. Verify namespace and file location
// File: app/Models/User.php
namespace App\Models;

use Bob\Database\Model;

class User extends Model {
    protected string $table = 'users';
}

// 3. Set connection before use
use Bob\Database\Connection;
use Bob\Database\Model;

$connection = new Connection($config);
Model::setConnection($connection);

// Now use the model
$users = User::all();
```

### Model Returns Array Instead of Object

**Problem**: Model methods return arrays instead of model instances.

**Solution**:

```php
class User extends Model {
    // Ensure hydration is working
    public static function findByEmail(string $email): ?self {
        $result = static::query()
            ->where('email', $email)
            ->first();

        // Hydrate to model instance
        return $result ? static::hydrate($result) : null;
    }
}

// Check that results are objects
$user = User::find(1);
var_dump($user instanceof User); // Should be true
```

## Transaction Issues

### Nested Transactions Not Working

**Problem**: Nested transactions don't behave as expected.

**Solutions**:

```php
// 1. Check database support
$driver = $connection->getDriverName();

if ($driver === 'sqlite') {
    echo "SQLite doesn't support savepoints\n";
    // Use single-level transactions only
}

// 2. For MySQL/PostgreSQL, use savepoints
$connection->beginTransaction(); // Main transaction

try {
    $connection->table('users')->insert([...]);

    $connection->beginTransaction(); // Creates savepoint
    try {
        $connection->table('posts')->insert([...]);
        $connection->commit(); // Commits savepoint
    } catch (\Exception $e) {
        $connection->rollBack(); // Rolls back to savepoint
    }

    $connection->commit(); // Commits main transaction
} catch (\Exception $e) {
    $connection->rollBack(); // Rolls back everything
}
```

### Deadlocks

**Problem**: "Deadlock found when trying to get lock" errors.

**Solutions**:

```php
// 1. Retry on deadlock
$maxRetries = 3;
$attempt = 0;

while ($attempt < $maxRetries) {
    try {
        $connection->transaction(function($connection) {
            // Your transaction code
        });
        break; // Success
    } catch (QueryException $e) {
        if (str_contains($e->getMessage(), 'Deadlock')) {
            $attempt++;
            usleep(100000 * $attempt); // Exponential backoff
        } else {
            throw $e; // Different error
        }
    }
}

// 2. Order operations consistently
// Always update tables in the same order
$connection->transaction(function($connection) {
    // Always: users first, then posts
    $connection->table('users')->where('id', 1)->update([...]);
    $connection->table('posts')->where('user_id', 1)->update([...]);
});

// 3. Use shorter transactions
// Bad: Long transaction
$connection->beginTransaction();
$data = fetchDataFromAPI(); // Slow!
$connection->table('users')->insert($data);
$connection->commit();

// Good: Fetch data outside transaction
$data = fetchDataFromAPI();
$connection->transaction(function($connection) use ($data) {
    $connection->table('users')->insert($data);
});
```

## Testing Issues

### Tests Affecting Each Other

**Problem**: Tests pass individually but fail when run together.

**Solutions**:

```php
class DatabaseTest extends PHPUnit\Framework\TestCase {
    private Connection $connection;

    protected function setUp(): void {
        // Use separate test database
        $this->connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // Create fresh schema
        $this->createSchema();
    }

    protected function tearDown(): void {
        // Clean up
        $this->connection->disconnect();
    }

    private function createSchema(): void {
        $this->connection->statement('CREATE TABLE ...');
    }

    public function testSomething() {
        // Use transactions for isolation
        $this->connection->beginTransaction();

        // Run test

        $this->connection->rollBack();
    }
}
```

## Common Error Messages

### "Column not found"

```php
// Check column exists
$columns = $connection->select("SHOW COLUMNS FROM users");
$columnNames = array_map(fn($col) => $col->Field, $columns);

if (!in_array('status', $columnNames)) {
    throw new \Exception("Column 'status' does not exist");
}
```

### "Duplicate entry"

```php
try {
    $connection->table('users')->insert([
        'email' => 'duplicate@example.com'
    ]);
} catch (QueryException $e) {
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        // Handle duplicate
        $connection->table('users')
            ->where('email', 'duplicate@example.com')
            ->update(['updated_at' => now()]);
    }
}
```

### "Foreign key constraint fails"

```php
// Check references exist
$userId = 999;
if (!$connection->table('users')->where('id', $userId)->exists()) {
    throw new \Exception("User $userId does not exist");
}

// Or disable foreign key checks temporarily
$connection->statement('SET FOREIGN_KEY_CHECKS = 0');
// Do operations
$connection->statement('SET FOREIGN_KEY_CHECKS = 1');
```

## Getting Help

If you're still experiencing issues:

1. **Enable Debug Mode**:
```php
$connection->enableQueryLog();
// Run problematic code
$log = $connection->getQueryLog();
file_put_contents('debug.log', json_encode($log, JSON_PRETTY_PRINT));
```

2. **Check Database Logs**:
- MySQL: `/var/log/mysql/error.log`
- PostgreSQL: `/var/log/postgresql/postgresql-*.log`
- SQLite: Enable with `PRAGMA journal_mode=WAL`

3. **Report Issues**:
- GitHub: https://github.com/Marwen-Brini/bob-the-builder/issues
- Include: Bob version, PHP version, database type/version, error message, and minimal reproduction code

4. **Check Documentation**:
- [Configuration Guide](/guide/configuration)
- [Query Builder Guide](/guide/query-builder)
- [Migration Guide](/guide/migration)