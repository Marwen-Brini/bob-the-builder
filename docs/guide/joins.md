# Joins

Bob Query Builder provides a fluent interface for creating SQL joins. You can perform inner joins, left joins, right joins, cross joins, and even advanced joins with complex conditions.

## Inner Join

The `join` method performs an INNER JOIN. The first argument is the table to join, followed by the column constraints:

```php
$users = $connection->table('users')
    ->join('contacts', 'users.id', '=', 'contacts.user_id')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->select('users.*', 'contacts.phone', 'orders.price')
    ->get();
```

## Left Join

Use `leftJoin` to perform a LEFT JOIN:

```php
$users = $connection->table('users')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title')
    ->get();

// Users will be returned even if they have no posts
```

## Right Join

Use `rightJoin` to perform a RIGHT JOIN:

```php
$posts = $connection->table('users')
    ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.name', 'posts.*')
    ->get();

// Posts will be returned even if they have no associated user
```

## Cross Join

Use `crossJoin` to perform a CROSS JOIN (Cartesian product):

```php
$sizes = ['Small', 'Medium', 'Large'];
$colors = ['Red', 'Green', 'Blue'];

$combinations = $connection->table('sizes')
    ->crossJoin('colors')
    ->get();
```

## Advanced Join Clauses

### Join with Closure

For more complex join conditions, use a closure:

```php
$users = $connection->table('users')
    ->join('contacts', function ($join) {
        $join->on('users.id', '=', 'contacts.user_id')
             ->where('contacts.type', '=', 'primary');
    })
    ->get();
```

### Multiple Join Conditions

Add multiple conditions to a join:

```php
$users = $connection->table('users')
    ->join('contacts', function ($join) {
        $join->on('users.id', '=', 'contacts.user_id')
             ->on('users.account_id', '=', 'contacts.account_id')
             ->where('contacts.active', '=', true);
    })
    ->get();
```

### Or Conditions in Joins

Use `orOn` for OR conditions:

```php
$users = $connection->table('users')
    ->join('contacts', function ($join) {
        $join->on('users.id', '=', 'contacts.user_id')
             ->orOn('users.id', '=', 'contacts.proxy_user_id');
    })
    ->get();
```

## Joining Multiple Tables

Chain multiple joins together:

```php
$data = $connection->table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->join('comments', 'posts.id', '=', 'comments.post_id')
    ->join('likes', 'comments.id', '=', 'likes.comment_id')
    ->select(
        'users.name',
        'posts.title',
        'comments.content',
        'likes.created_at as liked_at'
    )
    ->get();
```

## Self Joins

Join a table to itself using aliases:

```php
$employees = $connection->table('employees as e1')
    ->join('employees as e2', 'e1.manager_id', '=', 'e2.id')
    ->select('e1.name as employee', 'e2.name as manager')
    ->get();
```

## Subquery Joins

Join to a subquery:

```php
$latestPosts = $connection->table('posts')
    ->select('user_id', $connection->raw('MAX(created_at) as last_post_at'))
    ->groupBy('user_id');

$users = $connection->table('users')
    ->joinSub($latestPosts, 'latest_posts', function ($join) {
        $join->on('users.id', '=', 'latest_posts.user_id');
    })
    ->get();
```

## Conditional Joins

Add joins conditionally based on request parameters:

```php
$query = $connection->table('users');

if ($request->has('with_posts')) {
    $query->leftJoin('posts', 'users.id', '=', 'posts.user_id')
          ->addSelect('posts.title as post_title');
}

if ($request->has('with_comments')) {
    $query->leftJoin('comments', 'users.id', '=', 'comments.user_id')
          ->addSelect('comments.content as comment');
}

$results = $query->get();
```

## Join with Where Clauses

Combine joins with where clauses:

```php
$activePosts = $connection->table('users')
    ->join('posts', function ($join) {
        $join->on('users.id', '=', 'posts.user_id')
             ->where('posts.status', '=', 'published')
             ->where('posts.created_at', '>', '2024-01-01');
    })
    ->where('users.active', true)
    ->select('users.name', 'posts.title')
    ->get();
```

## Aggregates with Joins

Use aggregate functions with joins:

```php
// Count related records
$userPostCounts = $connection->table('users')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.name', $connection->raw('COUNT(posts.id) as post_count'))
    ->groupBy('users.id', 'users.name')
    ->get();

// Sum with join
$orderTotals = $connection->table('users')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->select('users.name', $connection->raw('SUM(orders.amount) as total'))
    ->groupBy('users.id', 'users.name')
    ->having('total', '>', 1000)
    ->get();
```

## Complex Join Examples

### E-commerce: Users, Orders, and Products

```php
$orderDetails = $connection->table('users')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->join('order_items', 'orders.id', '=', 'order_items.order_id')
    ->join('products', 'order_items.product_id', '=', 'products.id')
    ->select(
        'users.name as customer',
        'orders.order_number',
        'orders.created_at as order_date',
        'products.name as product',
        'order_items.quantity',
        'order_items.price'
    )
    ->where('orders.status', 'completed')
    ->orderBy('orders.created_at', 'desc')
    ->get();
```

### Blog: Posts with Author and Category

```php
$posts = $connection->table('posts')
    ->join('users', 'posts.author_id', '=', 'users.id')
    ->join('categories', 'posts.category_id', '=', 'categories.id')
    ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
    ->select(
        'posts.*',
        'users.name as author',
        'categories.name as category',
        $connection->raw('COUNT(DISTINCT comments.id) as comment_count')
    )
    ->where('posts.status', 'published')
    ->groupBy('posts.id')
    ->orderBy('posts.created_at', 'desc')
    ->get();
```

### Social Network: Friends and Their Posts

```php
$friendPosts = $connection->table('users as u1')
    ->join('friendships', function ($join) {
        $join->on('u1.id', '=', 'friendships.user_id')
             ->orOn('u1.id', '=', 'friendships.friend_id');
    })
    ->join('users as u2', function ($join) {
        $join->on('friendships.friend_id', '=', 'u2.id')
             ->where('u2.id', '!=', $connection->raw('u1.id'));
    })
    ->join('posts', 'u2.id', '=', 'posts.user_id')
    ->where('u1.id', $currentUserId)
    ->where('friendships.status', 'accepted')
    ->select('u2.name as friend', 'posts.*')
    ->orderBy('posts.created_at', 'desc')
    ->limit(20)
    ->get();
```

## Performance Considerations

### Use Indexes

Ensure columns used in joins are indexed:

```sql
-- Add indexes for better join performance
CREATE INDEX idx_user_id ON posts(user_id);
CREATE INDEX idx_post_id ON comments(post_id);
```

### Select Only Needed Columns

```php
// Good - select only needed columns
$users = $connection->table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.name', 'posts.title')
    ->get();

// Avoid - selecting all columns
$users = $connection->table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->select('*')
    ->get();
```

### Consider Join Order

Place smaller tables first and filter early:

```php
// Better - filter before joining
$results = $connection->table('posts')
    ->where('status', 'published')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->get();

// Less efficient - join then filter
$results = $connection->table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->where('posts.status', 'published')
    ->get();
```

## Join Types Summary

| Join Type | Method | Description |
|-----------|--------|-------------|
| INNER JOIN | `join()` | Returns records with matching values in both tables |
| LEFT JOIN | `leftJoin()` | Returns all records from left table, matched records from right |
| RIGHT JOIN | `rightJoin()` | Returns all records from right table, matched records from left |
| CROSS JOIN | `crossJoin()` | Returns Cartesian product of both tables |
| SUBQUERY JOIN | `joinSub()` | Joins to a subquery result |

## Common Pitfalls

1. **Ambiguous Column Names**: Always qualify column names when joining
   ```php
   // Good
   ->select('users.name', 'posts.title')
   
   // Bad - ambiguous if both tables have 'name'
   ->select('name', 'title')
   ```

2. **Missing Group By**: When using aggregates with joins, include all non-aggregate columns in GROUP BY
   ```php
   ->select('users.id', 'users.name', 'COUNT(posts.id) as count')
   ->groupBy('users.id', 'users.name')
   ```

3. **N+1 Problem**: Consider using joins instead of multiple queries
   ```php
   // Good - one query with join
   $users = $connection->table('users')
       ->join('posts', 'users.id', '=', 'posts.user_id')
       ->get();
   
   // Bad - N+1 queries
   $users = $connection->table('users')->get();
   foreach ($users as $user) {
       $posts = $connection->table('posts')
           ->where('user_id', $user->id)
           ->get();
   }
   ```