# API Reference

## Table of Contents
- [Connection Methods](#connection-methods)
- [Query Builder Methods](#query-builder-methods)
- [Select Statements](#select-statements)
- [Where Clauses](#where-clauses)
- [Joins](#joins)
- [Ordering, Grouping & Limiting](#ordering-grouping--limiting)
- [Aggregates](#aggregates)
- [Insert Statements](#insert-statements)
- [Update Statements](#update-statements)
- [Delete Statements](#delete-statements)
- [Utility Methods](#utility-methods)

## Connection Methods

### Creating a Connection

```php
use Bob\Database\Connection;

$connection = new Connection([
    'driver' => 'mysql',      // mysql, pgsql, sqlite
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'mydb',
    'username' => 'user',
    'password' => 'pass',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
```

### table($table)
Get a query builder for a table.

```php
$query = $connection->table('users');
```

### raw($value)
Create a raw SQL expression.

```php
$expression = $connection->raw('COUNT(*) as total');
```

### statement($query, $bindings = [])
Execute a raw SQL statement.

```php
$connection->statement('CREATE TABLE test (id INT)');
```

### select($query, $bindings = [])
Run a raw select query.

```php
$results = $connection->select('SELECT * FROM users WHERE id = ?', [1]);
```

### insert($query, $bindings = [])
Run a raw insert query.

```php
$connection->insert('INSERT INTO users (name) VALUES (?)', ['John']);
```

### update($query, $bindings = [])
Run a raw update query.

```php
$affected = $connection->update('UPDATE users SET name = ? WHERE id = ?', ['Jane', 1]);
```

### delete($query, $bindings = [])
Run a raw delete query.

```php
$deleted = $connection->delete('DELETE FROM users WHERE id = ?', [1]);
```

### transaction($callback, $attempts = 1)
Execute a closure within a transaction.

```php
$connection->transaction(function () use ($connection) {
    $connection->table('users')->insert(['name' => 'John']);
    $connection->table('posts')->insert(['title' => 'Hello']);
});
```

### beginTransaction()
Start a new database transaction.

```php
$connection->beginTransaction();
```

### commit()
Commit the active database transaction.

```php
$connection->commit();
```

### rollBack()
Rollback the active database transaction.

```php
$connection->rollBack();
```

## Query Builder Methods

### Creating a Query

```php
$query = $connection->table('users');
```

## Select Statements

### select($columns = ['*'])
Set the columns to be selected.

```php
$query->select('id', 'name', 'email');
$query->select(['id', 'name']);
```

### addSelect($column)
Add a column to the existing select.

```php
$query->select('id')->addSelect('name');
```

### selectRaw($expression, $bindings = [])
Add a raw select expression.

```php
$query->selectRaw('COUNT(*) as user_count');
$query->selectRaw('price * ? as total', [1.0825]);
```

### distinct()
Force the query to return distinct results.

```php
$query->distinct()->select('status');
```

### from($table, $as = null)
Set the table for the query.

```php
$query->from('users');
$query->from('users', 'u');
```

## Where Clauses

### where($column, $operator = null, $value = null, $boolean = 'and')
Add a basic where clause.

```php
$query->where('votes', '>', 100);
$query->where('name', 'John');  // Uses '=' operator
$query->where([
    ['status', '=', 'active'],
    ['votes', '>', 100]
]);
```

### orWhere($column, $operator = null, $value = null)
Add an OR where clause.

```php
$query->where('votes', '>', 100)->orWhere('name', 'John');
```

### whereIn($column, $values, $boolean = 'and', $not = false)
Add a where in clause.

```php
$query->whereIn('id', [1, 2, 3]);
$query->whereIn('id', function($query) {
    $query->select('user_id')->from('posts');
});
```

### whereNotIn($column, $values, $boolean = 'and')
Add a where not in clause.

```php
$query->whereNotIn('id', [1, 2, 3]);
```

### whereNull($column, $boolean = 'and', $not = false)
Add a where null clause.

```php
$query->whereNull('deleted_at');
```

### whereNotNull($column, $boolean = 'and')
Add a where not null clause.

```php
$query->whereNotNull('email_verified_at');
```

### orWhereNull($column)
Add an OR where null clause.

```php
$query->orWhereNull('deleted_at');
```

### orWhereNotNull($column)
Add an OR where not null clause.

```php
$query->orWhereNotNull('email_verified_at');
```

### whereBetween($column, array $values, $boolean = 'and', $not = false)
Add a where between clause.

```php
$query->whereBetween('votes', [1, 100]);
```

### whereNotBetween($column, array $values, $boolean = 'and')
Add a where not between clause.

```php
$query->whereNotBetween('votes', [1, 100]);
```

### whereExists(Closure $callback, $boolean = 'and', $not = false)
Add a where exists clause.

```php
$query->whereExists(function($query) {
    $query->select('*')
        ->from('orders')
        ->whereColumn('orders.user_id', 'users.id');
});
```

### whereNotExists(Closure $callback, $boolean = 'and')
Add a where not exists clause.

```php
$query->whereNotExists(function($query) {
    $query->select('*')->from('orders')->whereColumn('orders.user_id', 'users.id');
});
```

### whereRaw($sql, $bindings = [], $boolean = 'and')
Add a raw where clause.

```php
$query->whereRaw('votes > 100');
$query->whereRaw('votes > ? AND created_at > ?', [100, '2024-01-01']);
```

### orWhereRaw($sql, $bindings = [])
Add an OR raw where clause.

```php
$query->orWhereRaw('votes > 100');
```

### whereDate($column, $operator, $value = null, $boolean = 'and')
Add a where date clause.

```php
$query->whereDate('created_at', '2024-01-01');
$query->whereDate('created_at', '>', '2024-01-01');
```

### whereTime($column, $operator, $value = null, $boolean = 'and')
Add a where time clause.

```php
$query->whereTime('created_at', '12:30:00');
$query->whereTime('created_at', '>=', '09:00:00');
```

### whereDay($column, $operator, $value = null, $boolean = 'and')
Add a where day clause.

```php
$query->whereDay('created_at', '25');
$query->whereDay('created_at', '>', '15');
```

### whereMonth($column, $operator, $value = null, $boolean = 'and')
Add a where month clause.

```php
$query->whereMonth('created_at', '12');
$query->whereMonth('created_at', '<', '6');
```

### whereYear($column, $operator, $value = null, $boolean = 'and')
Add a where year clause.

```php
$query->whereYear('created_at', '2024');
$query->whereYear('created_at', '>=', '2020');
```

### whereColumn($first, $operator = null, $second = null, $boolean = 'and')
Add a where column clause comparing two columns.

```php
$query->whereColumn('first_name', 'last_name');
$query->whereColumn('updated_at', '>', 'created_at');
$query->whereColumn([
    ['first_name', '=', 'last_name'],
    ['updated_at', '>', 'created_at']
]);
```

### orWhereColumn($first, $operator = null, $second = null)
Add an OR where column clause.

```php
$query->orWhereColumn('first_name', 'last_name');
```

### whereJsonContains($column, $value, $boolean = 'and', $not = false)
Add a where JSON contains clause (MySQL/PostgreSQL).

```php
$query->whereJsonContains('options->languages', 'en');
$query->whereJsonContains('options->languages', ['en', 'fr']);
```

### whereJsonLength($column, $operator, $value = null, $boolean = 'and')
Add a where JSON length clause (MySQL/PostgreSQL).

```php
$query->whereJsonLength('options->languages', 2);
$query->whereJsonLength('options->languages', '>', 1);
```

### whereFullText($columns, $value, $boolean = 'and')
Add a full text search clause (MySQL/PostgreSQL).

```php
$query->whereFullText(['title', 'content'], 'Laravel');
```

### when($value, callable $callback, ?callable $default = null)
Apply clauses conditionally.

```php
$query->when($request->has('filter'), function($query) use ($request) {
    return $query->where('status', $request->filter);
});

$query->when(
    $sortBy,
    function($query, $value) {
        return $query->orderBy($value);
    },
    function($query) {
        return $query->orderBy('created_at');
    }
);
```

### unless($value, callable $callback, ?callable $default = null)
Apply clauses unless a condition is true.

```php
$query->unless($includeDeleted, function($query) {
    return $query->whereNull('deleted_at');
});
```

## Joins

### join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
Add an inner join.

```php
$query->join('contacts', 'users.id', '=', 'contacts.user_id');
$query->join('contacts', function($join) {
    $join->on('users.id', '=', 'contacts.user_id')
         ->where('contacts.type', '=', 'primary');
});
```

### leftJoin($table, $first, $operator = null, $second = null)
Add a left join.

```php
$query->leftJoin('posts', 'users.id', '=', 'posts.user_id');
```

### rightJoin($table, $first, $operator = null, $second = null)
Add a right join.

```php
$query->rightJoin('posts', 'users.id', '=', 'posts.user_id');
```

### crossJoin($table, $first = null, $operator = null, $second = null)
Add a cross join.

```php
$query->crossJoin('colors');
$query->crossJoin('sizes', 'sizes.id', '>', 'colors.id');
```

### joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner')
Join to a subquery.

```php
$subquery = $connection->table('posts')
    ->select('user_id', $connection->raw('MAX(created_at) as last_post'))
    ->groupBy('user_id');

$query->joinSub($subquery, 'latest', 'users.id', '=', 'latest.user_id');
```

### leftJoinSub($query, $as, $first, $operator = null, $second = null)
Left join to a subquery.

```php
$query->leftJoinSub($subquery, 'latest', 'users.id', '=', 'latest.user_id');
```

## Ordering, Grouping & Limiting

### orderBy($column, $direction = 'asc')
Add an order by clause.

```php
$query->orderBy('name');
$query->orderBy('created_at', 'desc');
```

### orderByDesc($column)
Add a descending order by clause.

```php
$query->orderByDesc('created_at');
```

### orderByRaw($sql, $bindings = [])
Add a raw order by clause.

```php
$query->orderByRaw('FIELD(status, ?, ?, ?)', ['pending', 'active', 'completed']);
```

### latest($column = 'created_at')
Order by the latest date.

```php
$query->latest();
$query->latest('updated_at');
```

### oldest($column = 'created_at')
Order by the oldest date.

```php
$query->oldest();
$query->oldest('updated_at');
```

### inRandomOrder($seed = '')
Order results randomly.

```php
$query->inRandomOrder();
$query->inRandomOrder('12345'); // With seed for consistency
```

### groupBy(...$groups)
Add a group by clause.

```php
$query->groupBy('status');
$query->groupBy('status', 'type');
```

### having($column, $operator = null, $value = null, $boolean = 'and')
Add a having clause.

```php
$query->groupBy('status')->having('COUNT(*)', '>', 5);
```

### orHaving($column, $operator = null, $value = null)
Add an OR having clause.

```php
$query->orHaving('SUM(amount)', '>', 1000);
```

### havingRaw($sql, $bindings = [], $boolean = 'and')
Add a raw having clause.

```php
$query->havingRaw('SUM(amount) > ?', [1000]);
```

### limit($value)
Set the limit.

```php
$query->limit(10);
```

### take($value)
Alias for limit.

```php
$query->take(10);
```

### offset($value)
Set the offset.

```php
$query->offset(20);
```

### skip($value)
Alias for offset.

```php
$query->skip(20);
```

### page($page, $perPage = 15)
Set limit and offset for pagination.

```php
$query->page(2, 20); // Page 2 with 20 items per page
```

## Aggregates

### count($columns = '*')
Get the count of results.

```php
$count = $query->count();
$count = $query->count('id');
```

### min($column)
Get the minimum value.

```php
$min = $query->min('price');
```

### max($column)
Get the maximum value.

```php
$max = $query->max('price');
```

### sum($column)
Get the sum of values.

```php
$sum = $query->sum('amount');
```

### avg($column)
Get the average value.

```php
$avg = $query->avg('rating');
```

### average($column)
Alias for avg.

```php
$average = $query->average('rating');
```

### exists()
Determine if any results exist.

```php
if ($query->exists()) {
    // Records exist
}
```

### doesntExist()
Determine if no results exist.

```php
if ($query->doesntExist()) {
    // No records exist
}
```

## Retrieving Results

### get($columns = ['*'])
Execute the query and get all results.

```php
$users = $query->get();
$users = $query->get(['id', 'name']);

foreach ($users as $user) {
    echo $user->name;
}
```

### first($columns = ['*'])
Get the first result.

```php
$user = $query->first();
if ($user) {
    echo $user->name;
}
```

### find($id, $columns = ['*'])
Find a record by ID.

```php
$user = $query->find(1);
```

### value($column)
Get a single column value from the first result.

```php
$email = $query->where('id', 1)->value('email');
```

### pluck($column, $key = null)
Get an array of column values.

```php
$names = $query->pluck('name');
// ['John', 'Jane', 'Bob']

$names = $query->pluck('name', 'id');
// [1 => 'John', 2 => 'Jane', 3 => 'Bob']
```

### chunk($count, callable $callback)
Process results in chunks.

```php
$query->chunk(100, function($users) {
    foreach ($users as $user) {
        // Process user
    }

    // Return false to stop chunking
    if (someCondition()) {
        return false;
    }
});
```

### cursor()
Get a generator for memory-efficient iteration.

```php
foreach ($query->cursor() as $user) {
    // Process one user at a time
}
```

## Insert Statements

### insert(array $values)
Insert records.

```php
// Single record
$query->insert([
    'name' => 'John',
    'email' => 'john@example.com'
]);

// Multiple records
$query->insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com']
]);
```

### insertGetId(array $values, $sequence = null)
Insert and get the ID.

```php
$id = $query->insertGetId([
    'name' => 'John',
    'email' => 'john@example.com'
]);
```

### insertOrIgnore(array $values)
Insert records, ignoring duplicates.

```php
$query->insertOrIgnore([
    'id' => 1,
    'email' => 'john@example.com'
]);
```

## Update Statements

### update(array $values)
Update records.

```php
$affected = $query->where('id', 1)->update([
    'name' => 'Jane',
    'updated_at' => now()
]);
```

### updateOrInsert(array $attributes, array $values = [])
Update or insert a record.

```php
$query->updateOrInsert(
    ['email' => 'john@example.com'],
    ['name' => 'John Doe', 'votes' => 0]
);
```

### increment($column, $amount = 1, array $extra = [])
Increment a column value.

```php
$query->where('id', 1)->increment('votes');
$query->where('id', 1)->increment('votes', 5);
$query->where('id', 1)->increment('votes', 1, ['updated_at' => now()]);
```

### decrement($column, $amount = 1, array $extra = [])
Decrement a column value.

```php
$query->where('id', 1)->decrement('votes');
$query->where('id', 1)->decrement('votes', 5);
$query->where('id', 1)->decrement('votes', 1, ['updated_at' => now()]);
```

## Delete Statements

### delete($id = null)
Delete records.

```php
$deleted = $query->where('votes', '<', 100)->delete();
$deleted = $query->delete(5); // Delete by ID
```

### truncate()
Remove all records from the table.

```php
$query->truncate();
```

## Utility Methods

### toSql()
Get the SQL representation of the query.

```php
$sql = $query->where('status', 'active')->toSql();
// SELECT * FROM `users` WHERE `status` = ?
```

### getBindings()
Get the query bindings.

```php
$bindings = $query->where('status', 'active')->getBindings();
// ['active']
```

### dd()
Dump the query and die.

```php
$query->where('status', 'active')->dd();
```

### dump()
Dump the query and continue.

```php
$query->where('status', 'active')->dump()->get();
```

### clone()
Clone the query builder instance.

```php
$baseQuery = $connection->table('users')->where('status', 'active');
$admins = $baseQuery->clone()->where('role', 'admin')->get();
$users = $baseQuery->clone()->where('role', 'user')->get();
```

### newQuery()
Get a new query builder instance.

```php
$newQuery = $query->newQuery();
```

### getConnection()
Get the database connection instance.

```php
$connection = $query->getConnection();
```

### raw($value)
Create a raw expression.

```php
$query->select($query->raw('COUNT(*) as total'));
```

## Macros and Extensions

Bob Query Builder supports macros for extending functionality:

```php
use Bob\Query\Builder;

// Register a macro
Builder::macro('whereActive', function() {
    return $this->where('status', 'active');
});

// Use the macro
$activeUsers = $connection->table('users')->whereActive()->get();

// Register multiple macros
Builder::mixin([
    'whereActive' => function() {
        return $this->where('status', 'active');
    },
    'whereInactive' => function() {
        return $this->where('status', 'inactive');
    }
]);
```

## Error Handling

```php
use Bob\Exceptions\QueryException;
use Bob\Exceptions\ConnectionException;

try {
    $results = $connection->table('users')->get();
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

```php
// Enable query logging
$connection->enableQueryLog();

// Run queries
$connection->table('users')->get();

// Get the query log
$queries = $connection->getQueryLog();

foreach ($queries as $query) {
    echo "Query: " . $query['query'] . "\n";
    echo "Bindings: " . print_r($query['bindings'], true) . "\n";
    echo "Time: " . $query['time'] . "ms\n";
}

// Disable query logging
$connection->disableQueryLog();

// Clear the query log
$connection->flushQueryLog();
```

## Database Transactions

```php
// Using closures (recommended)
$connection->transaction(function () use ($connection) {
    $connection->table('users')->update(['votes' => 1]);
    $connection->table('posts')->delete();
});

// Manual transaction control
$connection->beginTransaction();

try {
    $connection->table('users')->update(['votes' => 1]);
    $connection->table('posts')->delete();

    $connection->commit();
} catch (\Exception $e) {
    $connection->rollBack();
    throw $e;
}

// Transaction with attempts
$connection->transaction(function () use ($connection) {
    $connection->table('users')->update(['votes' => 1]);
    $connection->table('posts')->delete();
}, 5); // Retry up to 5 times
```