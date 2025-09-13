# Quick Start

Get up and running with Bob Query Builder in 5 minutes!

## Basic Setup

```php
<?php
require 'vendor/autoload.php';

use Bob\Database\Connection;

// Create a connection
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => 'database.sqlite', // or ':memory:' for testing
]);

// Start querying!
$users = $connection->table('users')->get();
```

## Your First Queries

### Creating Tables

```php
// Create a users table
$connection->statement('
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        age INTEGER,
        status TEXT DEFAULT "active",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
');

// Create a posts table
$connection->statement('
    CREATE TABLE posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT NOT NULL,
        content TEXT,
        published BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )
');
```

### Inserting Data

```php
// Insert a single record
$connection->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Insert multiple records
$connection->table('users')->insert([
    ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 25],
    ['name' => 'Bob Wilson', 'email' => 'bob@example.com', 'age' => 35],
]);

// Insert and get ID
$userId = $connection->table('users')->insertGetId([
    'name' => 'Alice Brown',
    'email' => 'alice@example.com',
    'age' => 28
]);

echo "New user ID: $userId";
```

### Retrieving Data

```php
// Get all records
$users = $connection->table('users')->get();

foreach ($users as $user) {
    echo $user->name . ' - ' . $user->email . PHP_EOL;
}

// Get first record
$user = $connection->table('users')->first();
echo $user->name;

// Find by ID
$user = $connection->table('users')->find(1);

// Get specific columns
$users = $connection->table('users')
    ->select('name', 'email')
    ->get();

// Get single column value
$email = $connection->table('users')
    ->where('id', 1)
    ->value('email');
```

### Where Clauses

```php
// Basic where
$activeUsers = $connection->table('users')
    ->where('status', 'active')
    ->get();

// Multiple conditions
$users = $connection->table('users')
    ->where('status', 'active')
    ->where('age', '>', 25)
    ->get();

// Or conditions
$users = $connection->table('users')
    ->where('status', 'active')
    ->orWhere('age', '>', 30)
    ->get();

// Where in
$users = $connection->table('users')
    ->whereIn('id', [1, 2, 3])
    ->get();

// Where between
$users = $connection->table('users')
    ->whereBetween('age', [25, 35])
    ->get();

// Where null
$users = $connection->table('users')
    ->whereNull('deleted_at')
    ->get();
```

### Updating Data

```php
// Update records
$affected = $connection->table('users')
    ->where('id', 1)
    ->update(['status' => 'inactive']);

echo "$affected rows updated";

// Increment/Decrement
$connection->table('users')
    ->where('id', 1)
    ->increment('age');

$connection->table('users')
    ->where('id', 1)
    ->decrement('age', 5);
```

### Deleting Data

```php
// Delete specific records
$deleted = $connection->table('users')
    ->where('status', 'inactive')
    ->delete();

echo "$deleted rows deleted";

// Delete by ID
$connection->table('users')->delete(5);

// Truncate table (delete all records)
$connection->table('users')->truncate();
```

## Working with Relationships

### Joins

```php
// Inner join
$usersWithPosts = $connection->table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title as post_title')
    ->get();

// Left join
$allUsers = $connection->table('users')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.name', 'posts.title')
    ->get();

// Join with multiple conditions
$results = $connection->table('users')
    ->join('posts', function($join) {
        $join->on('users.id', '=', 'posts.user_id')
             ->where('posts.published', '=', 1);
    })
    ->get();
```

### Aggregates

```php
// Count
$count = $connection->table('users')->count();
echo "Total users: $count";

// Sum
$totalAge = $connection->table('users')->sum('age');

// Average
$avgAge = $connection->table('users')->avg('age');

// Min/Max
$youngest = $connection->table('users')->min('age');
$oldest = $connection->table('users')->max('age');

// Grouping with aggregates
$usersByStatus = $connection->table('users')
    ->select('status', $connection->raw('COUNT(*) as total'))
    ->groupBy('status')
    ->get();
```

## Advanced Features

### Ordering and Limiting

```php
// Order by
$users = $connection->table('users')
    ->orderBy('created_at', 'desc')
    ->get();

// Multiple ordering
$users = $connection->table('users')
    ->orderBy('status')
    ->orderBy('name', 'asc')
    ->get();

// Limit and offset
$users = $connection->table('users')
    ->limit(10)
    ->offset(20)
    ->get();

// Pagination
$page2 = $connection->table('users')
    ->page(2, 15) // Page 2, 15 items per page
    ->get();
```

### Raw Expressions

```php
// Raw select
$users = $connection->table('users')
    ->selectRaw('COUNT(*) as user_count, status')
    ->groupBy('status')
    ->get();

// Raw where
$users = $connection->table('users')
    ->whereRaw('age > ? AND created_at < ?', [25, '2024-01-01'])
    ->get();

// Raw order by
$users = $connection->table('users')
    ->orderByRaw('RANDOM()')
    ->limit(5)
    ->get();
```

### Transactions

```php
// Using closure (automatic rollback on exception)
$connection->transaction(function() use ($connection) {
    $connection->table('users')->insert([
        'name' => 'New User',
        'email' => 'new@example.com'
    ]);

    $connection->table('posts')->insert([
        'user_id' => $connection->getPdo()->lastInsertId(),
        'title' => 'First Post'
    ]);
});

// Manual transaction control
$connection->beginTransaction();

try {
    // Perform operations
    $connection->table('users')->update(['status' => 'processing']);

    // If all good, commit
    $connection->commit();
} catch (\Exception $e) {
    // On error, rollback
    $connection->rollBack();
    throw $e;
}
```

### Chunking Large Datasets

```php
// Process records in chunks to save memory
$connection->table('users')->chunk(100, function($users) {
    foreach ($users as $user) {
        // Process each user
        echo "Processing: " . $user->name . PHP_EOL;
    }
});

// Using cursor for even better memory efficiency
foreach ($connection->table('users')->cursor() as $user) {
    // Process one user at a time
    echo "Processing: " . $user->name . PHP_EOL;
}
```

## Using Models

```php
use Bob\Database\Model;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email', 'age', 'status'];
}

// Set the connection
Model::setConnection($connection);

// Create
$user = User::create([
    'name' => 'New User',
    'email' => 'newuser@example.com',
    'age' => 30
]);

// Find
$user = User::find(1);
echo $user->name;

// Update
$user->name = 'Updated Name';
$user->save();

// Delete
$user->delete();

// Query through model
$activeUsers = User::where('status', 'active')
    ->orderBy('name')
    ->get();
```

## Debugging Queries

```php
// Get SQL and bindings
$query = $connection->table('users')->where('status', 'active');
echo $query->toSql();
// Output: SELECT * FROM users WHERE status = ?

print_r($query->getBindings());
// Output: ['active']

// Enable query logging
$connection->enableQueryLog();

$connection->table('users')->get();
$connection->table('posts')->get();

$log = $connection->getQueryLog();
foreach ($log as $query) {
    echo $query['query'] . PHP_EOL;
    echo 'Time: ' . $query['time'] . 'ms' . PHP_EOL;
}
```

## What's Next?

Now that you've mastered the basics, explore:

- [Where Clauses](/guide/where-clauses) - Advanced filtering
- [Joins](/guide/joins) - Complex data relationships
- [Models](/guide/models) - Object-oriented database access
- [Performance](/guide/performance) - Optimization techniques
- [API Reference](/api/) - Complete method documentation