# Logging

Bob Query Builder provides a comprehensive logging system that helps you debug queries, monitor performance, and track database operations.

## Quick Start

The simplest way to enable logging is using the static `Log` facade:

```php
use Bob\Logging\Log;

// Enable logging globally
Log::enable();

// Your database operations are now logged
$connection->table('users')->get();

// Get the query log
$queries = Log::getQueryLog();

// Disable logging when done
Log::disable();
```

## Global Logging Control

The `Log` facade provides static methods for controlling logging across all connections:

### Enable/Disable Logging

```php
use Bob\Logging\Log;

// Enable logging for all connections
Log::enable();

// Check if logging is enabled
if (Log::isEnabled()) {
    // Logging is active
}

// Disable logging for all connections
Log::disable();
```

### Connection-Specific Logging

You can also control logging for specific connections:

```php
use Bob\Logging\Log;
use Bob\Database\Connection;

$connection1 = new Connection($config1);
$connection2 = new Connection($config2);

// Enable logging only for connection1
Log::enableFor($connection1);

// Disable logging for connection2
Log::disableFor($connection2);
```

## Connection-Level Logging

You can configure logging at the connection level:

### Enable via Configuration

```php
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret',
    'logging' => true,  // Enable logging for this connection
]);
```

### Enable Programmatically

```php
// Enable logging
$connection->enableQueryLog();

// Check if logging is enabled
if ($connection->isLoggingEnabled()) {
    // Logging is active
}

// Disable logging
$connection->disableQueryLog();
```

## Retrieving Query Logs

### Get All Queries

```php
// Get queries from all connections
$queries = Log::getQueryLog();

// Get queries from a specific connection
$queries = $connection->getQueryLog();

foreach ($queries as $query) {
    echo "SQL: " . $query['query'] . "\n";
    echo "Bindings: " . json_encode($query['bindings']) . "\n";
    echo "Time: " . $query['time'] . "\n";
}
```

### Clear Query Log

```php
// Clear log for all connections
Log::clearQueryLog();

// Clear log for specific connection
$connection->clearQueryLog();
```

## Query Statistics

Get detailed statistics about executed queries:

```php
$stats = Log::getStatistics();

echo "Total Queries: " . $stats['total_queries'] . "\n";
echo "Total Time: " . $stats['total_time'] . "\n";
echo "Average Time: " . $stats['average_time'] . "\n";
echo "Slow Queries: " . $stats['slow_queries'] . "\n";
echo "Active Connections: " . $stats['connections'] . "\n";

// Query breakdown by type
foreach ($stats['queries_by_type'] as $type => $count) {
    echo "$type: $count queries\n";
}
```

## PSR-3 Logger Integration

Bob Query Builder supports any PSR-3 compliant logger:

### Using Monolog

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Bob\Logging\Log;

// Create a Monolog instance
$monolog = new Logger('database');
$monolog->pushHandler(new StreamHandler('path/to/database.log', Logger::DEBUG));

// Set it as the global logger
Log::setLogger($monolog);
Log::enable();

// All queries will now be logged to the file
```

### Using with Connection

```php
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret',
    'logger' => $monolog,  // Pass PSR-3 logger
]);
```

## Slow Query Detection

Identify performance bottlenecks by detecting slow queries:

```php
use Bob\Logging\Log;

// Configure slow query threshold (in milliseconds)
Log::configure([
    'slow_query_threshold' => 100,  // Queries over 100ms are considered slow
]);

Log::enable();

// Run your queries...
$connection->table('large_table')->get();

// Check for slow queries
$stats = Log::getStatistics();
echo "Slow queries: " . $stats['slow_queries'];

// Get detailed log with slow query warnings
$queries = Log::getQueryLog();
foreach ($queries as $query) {
    if (isset($query['slow_query']) && $query['slow_query']) {
        echo "SLOW QUERY: " . $query['query'] . " took " . $query['time'] . "\n";
    }
}
```

## Transaction Logging

Track transaction lifecycle:

```php
$connection->enableQueryLog();

$connection->beginTransaction();
// Logs: "Transaction started"

$connection->table('users')->insert(['name' => 'John']);

$connection->commit();
// Logs: "Transaction committed"

// Or on rollback
$connection->rollBack();
// Logs: "Transaction rolled back"
```

### Savepoint Logging (MySQL/PostgreSQL)

For nested transactions with savepoints:

```php
$connection->beginTransaction();
// Logs: "Transaction started"

$connection->beginTransaction();
// Logs: "Transaction savepoint trans2"

$connection->rollBack();
// Logs: "Transaction savepoint rolled back trans2"

$connection->commit();
// Logs: "Transaction committed"
```

## Configuration Options

Configure logging behavior globally:

```php
use Bob\Logging\Log;

Log::configure([
    'log_bindings' => true,           // Log query bindings
    'log_time' => true,               // Log execution time
    'slow_query_threshold' => 1000,  // Slow query threshold in ms
    'max_query_log' => 100,           // Maximum queries to keep in memory
]);
```

## Manual Logging

You can also log custom messages:

```php
use Bob\Logging\Log;

// Log a query manually
Log::logQuery('SELECT * FROM users WHERE id = ?', [1], 15.5);

// Log an error
Log::logError('Database connection failed', [
    'host' => 'localhost',
    'error' => 'Connection refused'
]);

// Log informational message
Log::logInfo('Cache cleared', [
    'duration' => '100ms'
]);
```

## Pretend Mode

Test what queries would be executed without actually running them:

```php
$queries = $connection->pretend(function ($connection) {
    $connection->table('users')->insert(['name' => 'Test']);
    $connection->table('posts')->where('user_id', 1)->delete();
});

foreach ($queries as $query) {
    echo "Would execute: " . $query['query'] . "\n";
    echo "With bindings: " . json_encode($query['bindings']) . "\n";
}
```

## Performance Considerations

### Memory Usage

When logging is enabled, queries are stored in memory. To prevent memory issues:

```php
// Set maximum number of queries to keep
Log::configure([
    'max_query_log' => 50,  // Keep only last 50 queries
]);

// Periodically clear the log
Log::clearQueryLog();
```

### Production Usage

For production environments, consider:

1. **Use file-based logging**: Integrate with PSR-3 loggers that write to files
2. **Increase slow query threshold**: Focus on truly slow queries
3. **Disable bindings logging**: Reduce log verbosity
4. **Use sampling**: Enable logging only for specific requests

```php
// Production configuration
Log::configure([
    'log_bindings' => false,        // Don't log sensitive data
    'slow_query_threshold' => 5000, // Only log very slow queries (5s+)
    'max_query_log' => 10,          // Keep minimal in-memory log
]);

// Enable only for debugging sessions
if ($request->hasHeader('X-Debug-Token')) {
    Log::enable();
}
```

## Examples

### Debug a Specific Operation

```php
use Bob\Logging\Log;

// Enable logging temporarily
Log::enable();

// Perform the operation you want to debug
$results = $connection->table('orders')
    ->join('users', 'orders.user_id', '=', 'users.id')
    ->where('orders.status', 'pending')
    ->where('orders.created_at', '>', now()->subDays(7))
    ->get();

// Get and display the executed queries
$queries = Log::getQueryLog();
foreach ($queries as $query) {
    echo "Query: " . $query['query'] . "\n";
    echo "Time: " . $query['time'] . "\n\n";
}

// Disable logging
Log::disable();
```

### Monitor Long-Running Script

```php
use Bob\Logging\Log;

Log::enable();
Log::configure(['slow_query_threshold' => 100]);

// Your long-running process
foreach ($records as $record) {
    processRecord($record);
    
    // Periodically check for slow queries
    $stats = Log::getStatistics();
    if ($stats['slow_queries'] > 10) {
        // Alert about performance issues
        alertAdmin("Too many slow queries: " . $stats['slow_queries']);
    }
    
    // Clear log to prevent memory issues
    if ($stats['total_queries'] > 1000) {
        Log::clearQueryLog();
    }
}

// Final statistics
$finalStats = Log::getStatistics();
echo "Processed with {$finalStats['total_queries']} queries\n";
echo "Total time: {$finalStats['total_time']}\n";
```

### Integration with Laravel-style Logging

```php
use Bob\Logging\Log;
use Illuminate\Support\Facades\Log as LaravelLog;

// Create a custom logger that forwards to Laravel
$customLogger = new class implements \Psr\Log\LoggerInterface {
    use \Psr\Log\LoggerTrait;
    
    public function log($level, $message, array $context = []): void
    {
        LaravelLog::log($level, $message, $context);
    }
};

Log::setLogger($customLogger);
Log::enable();

// Now all database queries are logged through Laravel's logging system
```

## Troubleshooting

### Queries Not Being Logged

1. Check if logging is enabled:
```php
var_dump(Log::isEnabled());
var_dump($connection->isLoggingEnabled());
```

2. Ensure connection is registered:
```php
Log::registerConnection($connection);
```

3. Check for query execution:
```php
// toSql() doesn't execute, so won't log
$sql = $connection->table('users')->toSql();  // Not logged

// get() executes, so will log
$users = $connection->table('users')->get();  // Logged
```

### Memory Issues

If you encounter memory issues with large query logs:

```php
// Reduce the maximum log size
Log::configure(['max_query_log' => 10]);

// Clear logs periodically
Log::clearQueryLog();

// Or disable logging for bulk operations
Log::disable();
// ... bulk operations ...
Log::enable();
```

### Missing Transaction Logs

Ensure the connection has logging enabled before starting transactions:

```php
$connection->enableQueryLog();  // Enable first
$connection->beginTransaction(); // Now it will be logged
```