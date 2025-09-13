# Examples

## Basic Query Examples

This section provides practical examples of using Bob Query Builder in real-world scenarios.

### Simple CRUD Operations

```php
use Bob\Database\Connection;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

// CREATE - Insert a new user
$userId = $connection->table('users')->insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'created_at' => date('Y-m-d H:i:s')
]);

// READ - Get the user
$user = $connection->table('users')->find($userId);
echo "User: {$user->name} ({$user->email})\n";

// UPDATE - Modify the user
$connection->table('users')
    ->where('id', $userId)
    ->update(['name' => 'John Smith']);

// DELETE - Remove the user
$connection->table('users')->delete($userId);
```

### Finding Records

```php
// Find all active users
$activeUsers = $connection->table('users')
    ->where('status', 'active')
    ->get();

// Find users who registered in the last 30 days
$newUsers = $connection->table('users')
    ->where('created_at', '>=', date('Y-m-d', strtotime('-30 days')))
    ->orderBy('created_at', 'desc')
    ->get();

// Find users with specific roles
$admins = $connection->table('users')
    ->whereIn('role', ['admin', 'super_admin'])
    ->get();

// Find users without profile pictures
$usersWithoutPhotos = $connection->table('users')
    ->whereNull('profile_photo')
    ->get();
```

### Complex Filtering

```php
// Users who are either admins OR have high activity
$importantUsers = $connection->table('users')
    ->where(function($query) {
        $query->where('role', 'admin')
              ->orWhere('activity_score', '>', 1000);
    })
    ->get();

// Users with complete profiles
$completeProfiles = $connection->table('users')
    ->whereNotNull('bio')
    ->whereNotNull('profile_photo')
    ->whereNotNull('phone')
    ->where('email_verified', true)
    ->get();

// Search users by name or email
$searchTerm = 'john';
$searchResults = $connection->table('users')
    ->where(function($query) use ($searchTerm) {
        $query->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('email', 'like', "%{$searchTerm}%");
    })
    ->limit(10)
    ->get();
```

### Working with Dates

```php
// Users who logged in today
$todayLogins = $connection->table('users')
    ->whereDate('last_login', date('Y-m-d'))
    ->get();

// Users born in specific month
$januaryBirthdays = $connection->table('users')
    ->whereMonth('birth_date', '01')
    ->get();

// Users registered in 2024
$users2024 = $connection->table('users')
    ->whereYear('created_at', '2024')
    ->get();

// Users who logged in during business hours
$businessHourLogins = $connection->table('login_logs')
    ->whereTime('created_at', '>=', '09:00:00')
    ->whereTime('created_at', '<=', '17:00:00')
    ->get();
```

### Aggregation Examples

```php
// Dashboard statistics
$stats = [
    'total_users' => $connection->table('users')->count(),
    'active_users' => $connection->table('users')->where('status', 'active')->count(),
    'total_revenue' => $connection->table('orders')->sum('total'),
    'average_order' => $connection->table('orders')->avg('total'),
    'max_order' => $connection->table('orders')->max('total'),
    'orders_today' => $connection->table('orders')->whereDate('created_at', date('Y-m-d'))->count(),
];

// Group by status with counts
$usersByStatus = $connection->table('users')
    ->select('status', $connection->raw('COUNT(*) as count'))
    ->groupBy('status')
    ->get();

// Monthly revenue
$monthlyRevenue = $connection->table('orders')
    ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total) as revenue')
    ->groupBy('year', 'month')
    ->orderBy('year', 'desc')
    ->orderBy('month', 'desc')
    ->get();
```

### Pagination

```php
// Simple pagination
$perPage = 15;
$currentPage = $_GET['page'] ?? 1;

$users = $connection->table('users')
    ->orderBy('created_at', 'desc')
    ->page($currentPage, $perPage)
    ->get();

// Get total for pagination links
$total = $connection->table('users')->count();
$totalPages = ceil($total / $perPage);

// Custom pagination with filters
function getPaginatedUsers($filters, $page = 1, $perPage = 20) {
    global $connection;

    $query = $connection->table('users');

    if (!empty($filters['status'])) {
        $query->where('status', $filters['status']);
    }

    if (!empty($filters['role'])) {
        $query->where('role', $filters['role']);
    }

    if (!empty($filters['search'])) {
        $query->where(function($q) use ($filters) {
            $q->where('name', 'like', "%{$filters['search']}%")
              ->orWhere('email', 'like', "%{$filters['search']}%");
        });
    }

    $total = $query->count();

    $results = $query->orderBy('created_at', 'desc')
                    ->page($page, $perPage)
                    ->get();

    return [
        'data' => $results,
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'last_page' => ceil($total / $perPage),
    ];
}
```

### Conditional Queries

```php
// Build query based on request parameters
function searchProducts($request) {
    global $connection;

    $query = $connection->table('products');

    // Apply filters conditionally
    $query->when($request['category'], function($q, $category) {
        return $q->where('category_id', $category);
    });

    $query->when($request['min_price'], function($q, $minPrice) {
        return $q->where('price', '>=', $minPrice);
    });

    $query->when($request['max_price'], function($q, $maxPrice) {
        return $q->where('price', '<=', $maxPrice);
    });

    $query->when($request['in_stock'], function($q) {
        return $q->where('stock', '>', 0);
    });

    // Apply sorting
    $query->when(
        $request['sort'],
        function($q, $sort) {
            $direction = $request['direction'] ?? 'asc';
            return $q->orderBy($sort, $direction);
        },
        function($q) {
            return $q->orderBy('created_at', 'desc');
        }
    );

    return $query->get();
}
```

### Batch Operations

```php
// Update multiple records efficiently
$connection->table('users')
    ->whereIn('id', [1, 2, 3, 4, 5])
    ->update(['newsletter' => true]);

// Bulk insert
$users = [
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com'],
    ['name' => 'User 3', 'email' => 'user3@example.com'],
    // ... hundreds more
];

// Insert in chunks to avoid memory issues
$chunks = array_chunk($users, 100);
foreach ($chunks as $chunk) {
    $connection->table('users')->insert($chunk);
}

// Process large datasets
$connection->table('orders')
    ->where('status', 'pending')
    ->chunk(100, function($orders) use ($connection) {
        foreach ($orders as $order) {
            // Process order
            processOrder($order);

            // Update status
            $connection->table('orders')
                ->where('id', $order->id)
                ->update(['status' => 'processed']);
        }
    });
```

### Subqueries

```php
// Users who have made orders
$usersWithOrders = $connection->table('users')
    ->whereExists(function($query) {
        $query->select('*')
              ->from('orders')
              ->whereColumn('orders.user_id', 'users.id');
    })
    ->get();

// Users with order count
$usersWithOrderCount = $connection->table('users')
    ->selectRaw('users.*, (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) as order_count')
    ->get();

// Products with above average price
$avgPrice = $connection->table('products')->avg('price');
$expensiveProducts = $connection->table('products')
    ->where('price', '>', $avgPrice)
    ->get();

// Users who haven't ordered in 30 days
$inactiveUsers = $connection->table('users')
    ->whereNotExists(function($query) {
        $query->select('*')
              ->from('orders')
              ->whereColumn('orders.user_id', 'users.id')
              ->where('orders.created_at', '>', date('Y-m-d', strtotime('-30 days')));
    })
    ->get();
```

### Error Handling

```php
use Bob\Exceptions\QueryException;
use Bob\Exceptions\ConnectionException;

try {
    // Attempt database operations
    $connection->transaction(function() use ($connection) {
        $userId = $connection->table('users')->insertGetId([
            'email' => 'duplicate@example.com' // This might already exist
        ]);

        $connection->table('profiles')->insert([
            'user_id' => $userId,
            'bio' => 'User bio'
        ]);
    });
} catch (QueryException $e) {
    // Handle query errors (e.g., duplicate key)
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        echo "This email is already registered.";
    } else {
        echo "Database error: " . $e->getMessage();
    }

    // Log the error
    error_log("Query failed: " . $e->getSql());
    error_log("Bindings: " . json_encode($e->getBindings()));
} catch (ConnectionException $e) {
    // Handle connection errors
    echo "Could not connect to database. Please try again later.";
    error_log("Connection failed: " . $e->getMessage());
} catch (\Exception $e) {
    // Handle other errors
    echo "An unexpected error occurred.";
    error_log("Error: " . $e->getMessage());
}
```

## Next Steps

Explore more guides:
- [Query Builder Guide](/guide/query-builder) - Complete query builder reference
- [Joins Guide](/guide/joins) - Advanced join operations
- [Models Guide](/guide/models) - ActiveRecord pattern with Bob
- [Performance Guide](/guide/performance) - Optimization techniques
- [Migration Guide](/guide/migration) - Migrate from other libraries