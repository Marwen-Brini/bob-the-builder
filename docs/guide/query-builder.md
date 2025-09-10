# Query Builder

The Query Builder provides a convenient, fluent interface for creating and executing database queries. It can be used to perform most database operations in your application and works across all supported database systems.

## Getting a Query Builder Instance

You can get a query builder instance using the `table` method on a `Connection` instance:

```php
use Bob\Database\Connection;

$connection = new Connection($config);
$query = $connection->table('users');
```

## Retrieving Results

### Retrieving All Rows

```php
$users = $connection->table('users')->get();

foreach ($users as $user) {
    echo $user->name;
}
```

### Retrieving a Single Row

```php
// Get the first row
$user = $connection->table('users')->first();

// Get a specific row by ID
$user = $connection->table('users')->find(1);
```

### Retrieving a Single Column

```php
$email = $connection->table('users')
    ->where('name', 'John')
    ->value('email');
```

### Retrieving a List of Column Values

```php
$titles = $connection->table('posts')->pluck('title');

// With a key
$titles = $connection->table('posts')->pluck('title', 'id');
```

## Chunking Results

For processing large result sets efficiently:

```php
$connection->table('users')->chunk(100, function ($users) {
    foreach ($users as $user) {
        // Process user...
    }
});

// Stop chunking early
$connection->table('users')->chunk(100, function ($users) {
    // Process...
    return false; // Stop chunking
});
```

## Streaming Results

For memory-efficient processing of large datasets:

```php
foreach ($connection->table('users')->cursor() as $user) {
    // Process one user at a time
}
```

## Aggregates

The query builder provides various aggregate methods:

```php
$count = $connection->table('users')->count();
$max = $connection->table('orders')->max('price');
$min = $connection->table('orders')->min('price');
$avg = $connection->table('orders')->avg('price');
$sum = $connection->table('orders')->sum('price');
```

### Determining if Records Exist

```php
if ($connection->table('users')->where('email', $email)->exists()) {
    // User exists
}

if ($connection->table('users')->where('email', $email)->doesntExist()) {
    // User doesn't exist
}
```

## Select Statements

### Specifying Columns

```php
// Select specific columns
$users = $connection->table('users')
    ->select('name', 'email')
    ->get();

// Add more columns to existing select
$query = $connection->table('users')->select('name');
$users = $query->addSelect('email')->get();
```

### Distinct Results

```php
$users = $connection->table('users')->distinct()->get();
```

### Raw Expressions

```php
use Bob\Database\Expression;

$users = $connection->table('users')
    ->select(new Expression('count(*) as user_count'))
    ->get();

// Or use selectRaw
$users = $connection->table('users')
    ->selectRaw('price * ? as total', [1.0825])
    ->get();
```

## Insert Statements

### Basic Insert

```php
$connection->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

### Insert Multiple Records

```php
$connection->table('users')->insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com']
]);
```

### Insert and Get ID

```php
$id = $connection->table('users')->insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

### Insert or Ignore

```php
$connection->table('users')->insertOrIgnore([
    'id' => 1,
    'email' => 'user@example.com'
]);
```

## Update Statements

### Basic Update

```php
$affected = $connection->table('users')
    ->where('id', 1)
    ->update(['votes' => 100]);
```

### Update or Insert (Upsert)

```php
$connection->table('users')->upsert(
    [
        ['email' => 'john@example.com', 'name' => 'John', 'votes' => 0],
        ['email' => 'jane@example.com', 'name' => 'Jane', 'votes' => 0],
    ],
    ['email'], // Unique columns
    ['name', 'votes'] // Columns to update
);
```

### Increment and Decrement

```php
// Increment
$connection->table('users')->increment('votes');
$connection->table('users')->increment('votes', 5);

// Decrement
$connection->table('users')->decrement('votes');
$connection->table('users')->decrement('votes', 5);

// With additional updates
$connection->table('users')->increment('votes', 1, [
    'updated_at' => now()
]);
```

## Delete Statements

### Basic Delete

```php
$connection->table('users')->delete();

$connection->table('users')
    ->where('votes', '<', 100)
    ->delete();
```

### Truncate

```php
$connection->table('users')->truncate();
```

## Debugging Queries

### Get SQL and Bindings

```php
$query = $connection->table('users')
    ->where('status', 'active');

$sql = $query->toSql();
$bindings = $query->getBindings();

echo $sql; // SELECT * FROM users WHERE status = ?
print_r($bindings); // ['active']
```

### Dump and Die

```php
$connection->table('users')
    ->where('status', 'active')
    ->dd(); // Dump query and die

$connection->table('users')
    ->where('status', 'active')
    ->dump() // Dump query and continue
    ->get();
```

### Query Log

```php
$connection->enableQueryLog();

// Run queries...
$users = $connection->table('users')->get();

$log = $connection->getQueryLog();
```

## Method Chaining

All query builder methods return the query instance, allowing you to chain methods:

```php
$users = $connection->table('users')
    ->select('name', 'email')
    ->where('status', 'active')
    ->where('votes', '>', 100)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

## Cloning Queries

Sometimes you need to reuse a base query:

```php
$baseQuery = $connection->table('users')
    ->where('status', 'active');

$admins = $baseQuery->clone()
    ->where('role', 'admin')
    ->get();

$users = $baseQuery->clone()
    ->where('role', 'user')
    ->get();
```