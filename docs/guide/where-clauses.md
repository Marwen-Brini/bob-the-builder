# Where Clauses

Bob Query Builder provides a comprehensive set of methods for adding `WHERE` clauses to your queries. These methods make it easy to build complex queries with readable, chainable syntax.

## Basic Where Clauses

### Simple Where

The most basic where clause requires three arguments: column, operator, and value.

```php
$users = $connection->table('users')
    ->where('votes', '=', 100)
    ->get();

// When using '=' operator, you can omit it
$users = $connection->table('users')
    ->where('votes', 100)
    ->get();
```

### Multiple Where Clauses

Chain multiple where clauses to add additional constraints:

```php
$users = $connection->table('users')
    ->where('votes', '>', 100)
    ->where('name', 'John')
    ->where('status', 'active')
    ->get();
// Generates: WHERE votes > ? AND name = ? AND status = ?
```

### Array of Conditions

Pass an array of conditions for cleaner syntax:

```php
$users = $connection->table('users')
    ->where([
        ['status', '=', 'active'],
        ['votes', '>', 100],
        ['created_at', '>=', '2024-01-01']
    ])
    ->get();

// Or with key-value pairs (assumes '=' operator)
$users = $connection->table('users')
    ->where([
        'status' => 'active',
        'subscribed' => true
    ])
    ->get();
```

## Or Statements

### orWhere

Add an OR condition to the query:

```php
$users = $connection->table('users')
    ->where('votes', '>', 100)
    ->orWhere('name', 'John')
    ->get();
// Generates: WHERE votes > ? OR name = ?
```

### orWhere with Closure

Group OR conditions using a closure:

```php
$users = $connection->table('users')
    ->where('votes', '>', 100)
    ->orWhere(function($query) {
        $query->where('name', 'John')
              ->where('status', 'active');
    })
    ->get();
// Generates: WHERE votes > ? OR (name = ? AND status = ?)
```

## Advanced Where Clauses

### whereBetween / whereNotBetween

Check if a column's value is between two values:

```php
$users = $connection->table('users')
    ->whereBetween('votes', [1, 100])
    ->get();

$users = $connection->table('users')
    ->whereNotBetween('votes', [1, 100])
    ->get();

// Or versions
$users = $connection->table('users')
    ->where('status', 'inactive')
    ->orWhereBetween('votes', [1, 100])
    ->get();
```

### whereIn / whereNotIn

Check if a column's value is in an array of values:

```php
$users = $connection->table('users')
    ->whereIn('id', [1, 2, 3])
    ->get();

$users = $connection->table('users')
    ->whereNotIn('id', [1, 2, 3])
    ->get();

// Or versions
$users = $connection->table('users')
    ->where('status', 'active')
    ->orWhereIn('role', ['admin', 'moderator'])
    ->get();
```

### whereNull / whereNotNull

Check for NULL values:

```php
$users = $connection->table('users')
    ->whereNull('email_verified_at')
    ->get();

$users = $connection->table('users')
    ->whereNotNull('email_verified_at')
    ->get();

// Or versions
$users = $connection->table('users')
    ->where('status', 'pending')
    ->orWhereNull('deleted_at')
    ->get();
```

## Date and Time Where Clauses

### whereDate

Compare a column's date portion:

```php
$users = $connection->table('users')
    ->whereDate('created_at', '2024-01-01')
    ->get();

$users = $connection->table('users')
    ->whereDate('created_at', '>', '2024-01-01')
    ->get();
```

### whereMonth

Compare a column's month:

```php
$users = $connection->table('users')
    ->whereMonth('created_at', '12')
    ->get();
```

### whereDay

Compare a column's day:

```php
$users = $connection->table('users')
    ->whereDay('created_at', '25')
    ->get();
```

### whereYear

Compare a column's year:

```php
$users = $connection->table('users')
    ->whereYear('created_at', '2024')
    ->get();
```

### whereTime

Compare a column's time portion:

```php
$users = $connection->table('users')
    ->whereTime('created_at', '=', '12:30:00')
    ->get();

$users = $connection->table('users')
    ->whereTime('created_at', '>=', '10:00:00')
    ->whereTime('created_at', '<=', '18:00:00')
    ->get();
```

## Column Comparisons

### whereColumn

Compare two columns:

```php
$users = $connection->table('users')
    ->whereColumn('first_name', 'last_name')
    ->get();
// WHERE first_name = last_name

$users = $connection->table('users')
    ->whereColumn('updated_at', '>', 'created_at')
    ->get();
// WHERE updated_at > created_at
```

### Multiple Column Comparisons

```php
$users = $connection->table('users')
    ->whereColumn([
        ['first_name', '=', 'last_name'],
        ['updated_at', '>', 'created_at']
    ])
    ->get();
```

## Logical Grouping

### Parameter Grouping

Group where clauses to control logical precedence:

```php
$users = $connection->table('users')
    ->where('name', 'John')
    ->where(function($query) {
        $query->where('votes', '>', 100)
              ->orWhere('title', 'Admin');
    })
    ->get();
// WHERE name = ? AND (votes > ? OR title = ?)
```

### Complex Nested Groups

```php
$users = $connection->table('users')
    ->where(function($query) {
        $query->where('status', 'active')
              ->where(function($query) {
                  $query->where('votes', '>', 100)
                        ->orWhere('is_featured', true);
              });
    })
    ->orWhere(function($query) {
        $query->where('role', 'admin')
              ->whereNotNull('verified_at');
    })
    ->get();
// WHERE (status = ? AND (votes > ? OR is_featured = ?)) OR (role = ? AND verified_at IS NOT NULL)
```

## Subquery Where Clauses

### whereExists

Check for existence using a subquery:

```php
$users = $connection->table('users')
    ->whereExists(function($query) {
        $query->select('*')
              ->from('orders')
              ->whereColumn('orders.user_id', 'users.id');
    })
    ->get();
// WHERE EXISTS (SELECT * FROM orders WHERE orders.user_id = users.id)
```

### whereNotExists

```php
$users = $connection->table('users')
    ->whereNotExists(function($query) {
        $query->select('*')
              ->from('orders')
              ->whereColumn('orders.user_id', 'users.id');
    })
    ->get();
```

### Where with Subquery

Use a subquery in a where clause:

```php
use Bob\Query\Builder;

$users = $connection->table('users')
    ->whereIn('id', function($query) {
        $query->select('user_id')
              ->from('orders')
              ->where('status', 'completed');
    })
    ->get();
// WHERE id IN (SELECT user_id FROM orders WHERE status = ?)
```

## JSON Where Clauses

For databases that support JSON columns:

### whereJsonContains

```php
$users = $connection->table('users')
    ->whereJsonContains('options->languages', 'en')
    ->get();

$users = $connection->table('users')
    ->whereJsonContains('options->languages', ['en', 'fr'])
    ->get();
```

### whereJsonLength

```php
$users = $connection->table('users')
    ->whereJsonLength('options->languages', 2)
    ->get();

$users = $connection->table('users')
    ->whereJsonLength('options->languages', '>', 1)
    ->get();
```

## Full Text Search

For MySQL and PostgreSQL:

```php
$posts = $connection->table('posts')
    ->whereFullText(['title', 'content'], 'Laravel')
    ->get();
```

## Raw Where Clauses

When you need complete control:

```php
$users = $connection->table('users')
    ->whereRaw('votes > IF(type = "premium", 100, 50)')
    ->get();

// With bindings
$users = $connection->table('users')
    ->whereRaw('votes > ? AND created_at > ?', [100, '2024-01-01'])
    ->get();

// Or version
$users = $connection->table('users')
    ->where('status', 'active')
    ->orWhereRaw('votes > 100')
    ->get();
```

## Conditional Clauses

### when

Apply clauses conditionally:

```php
$role = $request->input('role');

$users = $connection->table('users')
    ->when($role, function($query, $role) {
        $query->where('role', $role);
    })
    ->get();
```

### when with else

```php
$sortBy = $request->input('sort');

$users = $connection->table('users')
    ->when(
        $sortBy,
        function($query, $sortBy) {
            $query->orderBy($sortBy);
        },
        function($query) {
            $query->orderBy('name');
        }
    )
    ->get();
```

### unless

The opposite of when:

```php
$excludeDeleted = true;

$users = $connection->table('users')
    ->unless($excludeDeleted, function($query) {
        $query->whereNotNull('deleted_at');
    })
    ->get();
```

## Operators

Bob supports all standard SQL operators:

```php
// Comparison operators
->where('votes', '=', 100)
->where('votes', '<>', 100)
->where('votes', '!=', 100)
->where('votes', '<', 100)
->where('votes', '<=', 100)
->where('votes', '>', 100)
->where('votes', '>=', 100)

// Pattern matching
->where('name', 'like', 'T%')
->where('name', 'not like', '%admin%')
->where('name', 'ilike', 't%')  // PostgreSQL case-insensitive

// Other operators
->where('name', 'regexp', '^[A-Z]')  // MySQL
->where('name', '~', '^[A-Z]')       // PostgreSQL
```

## Best Practices

1. **Use Parameter Binding**: Always use parameter binding instead of concatenating values
   ```php
   // Good
   ->where('name', $userName)
   
   // Bad - SQL injection risk!
   ->whereRaw("name = '$userName'")
   ```

2. **Group Complex Logic**: Use closures to group related conditions
   ```php
   ->where(function($query) use ($filters) {
       foreach ($filters as $filter) {
           $query->orWhere($filter['column'], $filter['value']);
       }
   })
   ```

3. **Use Specific Methods**: Prefer specific methods over raw queries
   ```php
   // Good
   ->whereNull('deleted_at')
   
   // Less clear
   ->whereRaw('deleted_at IS NULL')
   ```

4. **Chain Responsibly**: While chaining is powerful, consider readability
   ```php
   // Consider breaking very long chains
   $query = $connection->table('users');
   $query->where('status', 'active');
   $query->where('role', 'admin');
   $users = $query->get();
   ```