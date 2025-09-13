# Migration Guide

This guide helps you migrate to Bob Query Builder from other database libraries and query builders.

## Migrating from Raw PDO

If you're currently using raw PDO, Bob provides a much cleaner API while maintaining the same performance.

### Before (PDO)

```php
// Connection
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'root', 'password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Select query
$stmt = $pdo->prepare('SELECT * FROM users WHERE status = ? AND age > ?');
$stmt->execute(['active', 25]);
$users = $stmt->fetchAll(PDO::FETCH_OBJ);

// Insert
$stmt = $pdo->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
$stmt->execute(['John Doe', 'john@example.com']);
$lastId = $pdo->lastInsertId();

// Update
$stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
$stmt->execute(['inactive', $lastId]);
```

### After (Bob)

```php
use Bob\Database\Connection;

// Connection
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

// Select query
$users = $connection->table('users')
    ->where('status', 'active')
    ->where('age', '>', 25)
    ->get();

// Insert
$lastId = $connection->table('users')->insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update
$connection->table('users')
    ->where('id', $lastId)
    ->update(['status' => 'inactive']);
```

## Migrating from WordPress $wpdb

Bob is designed to work seamlessly alongside WordPress's `$wpdb` while providing a modern API.

### Before ($wpdb)

```php
global $wpdb;

// Select
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}users WHERE status = %s AND age > %d",
        'active',
        25
    )
);

// Insert
$wpdb->insert(
    $wpdb->prefix . 'users',
    ['name' => 'John Doe', 'email' => 'john@example.com'],
    ['%s', '%s']
);
$lastId = $wpdb->insert_id;

// Update
$wpdb->update(
    $wpdb->prefix . 'users',
    ['status' => 'inactive'],
    ['id' => $lastId],
    ['%s'],
    ['%d']
);

// Delete
$wpdb->delete(
    $wpdb->prefix . 'users',
    ['id' => $lastId],
    ['%d']
);
```

### After (Bob)

```php
use Bob\Database\Connection;

global $wpdb;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'charset' => DB_CHARSET,
    'prefix' => $wpdb->prefix,
]);

// Select
$results = $connection->table('users')
    ->where('status', 'active')
    ->where('age', '>', 25)
    ->get();

// Insert
$lastId = $connection->table('users')->insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update
$connection->table('users')
    ->where('id', $lastId)
    ->update(['status' => 'inactive']);

// Delete
$connection->table('users')->delete($lastId);
```

### WordPress Integration Tips

1. **Use WordPress Constants**:
```php
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
```

2. **Create a Helper Function**:
```php
function bob_db(): Connection {
    static $connection = null;

    if ($connection === null) {
        global $wpdb;

        $connection = new Connection([
            'driver' => 'mysql',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => DB_CHARSET,
            'prefix' => $wpdb->prefix,
        ]);
    }

    return $connection;
}

// Usage
$users = bob_db()->table('users')->get();
```

3. **Use in Plugins**:
```php
class MyPlugin {
    private Connection $db;

    public function __construct() {
        global $wpdb;

        $this->db = new Connection([
            'driver' => 'mysql',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'prefix' => $wpdb->prefix,
        ]);
    }

    public function getActiveUsers() {
        return $this->db->table('users')
            ->where('status', 'active')
            ->get();
    }
}
```

## Migrating from Laravel Query Builder

Bob's API is heavily inspired by Laravel's query builder, making migration straightforward.

### Key Differences

1. **Connection Setup**:
```php
// Laravel (configured in config/database.php)
DB::table('users')->get();

// Bob
$connection = new Connection($config);
$connection->table('users')->get();
```

2. **Pagination**:
```php
// Laravel
$users = DB::table('users')->paginate(15);

// Bob (manual pagination)
$page = $_GET['page'] ?? 1;
$perPage = 15;
$users = $connection->table('users')
    ->page($page, $perPage)
    ->get();
```

3. **Model Usage**:
```php
// Laravel Eloquent
User::where('active', true)->get();

// Bob Model
User::where('active', true)->get(); // Same API!
```

### Compatibility Table

| Laravel Method | Bob Equivalent | Notes |
|---------------|----------------|-------|
| `DB::table()` | `$connection->table()` | Same API |
| `->select()` | `->select()` | ✅ Identical |
| `->where()` | `->where()` | ✅ Identical |
| `->orWhere()` | `->orWhere()` | ✅ Identical |
| `->whereIn()` | `->whereIn()` | ✅ Identical |
| `->whereNull()` | `->whereNull()` | ✅ Identical |
| `->join()` | `->join()` | ✅ Identical |
| `->leftJoin()` | `->leftJoin()` | ✅ Identical |
| `->orderBy()` | `->orderBy()` | ✅ Identical |
| `->groupBy()` | `->groupBy()` | ✅ Identical |
| `->having()` | `->having()` | ✅ Identical |
| `->limit()` | `->limit()` | ✅ Identical |
| `->offset()` | `->offset()` | ✅ Identical |
| `->get()` | `->get()` | ✅ Identical |
| `->first()` | `->first()` | ✅ Identical |
| `->find()` | `->find()` | ✅ Identical |
| `->count()` | `->count()` | ✅ Identical |
| `->insert()` | `->insert()` | ✅ Identical |
| `->update()` | `->update()` | ✅ Identical |
| `->delete()` | `->delete()` | ✅ Identical |
| `->paginate()` | `->page()` | Different API |
| `->chunk()` | `->chunk()` | ✅ Identical |
| `->cursor()` | `->cursor()` | ✅ Identical |

## Migrating from Doctrine DBAL

If you're using Doctrine DBAL, Bob provides a simpler alternative for query building.

### Before (Doctrine)

```php
use Doctrine\DBAL\DriverManager;

$conn = DriverManager::getConnection([
    'dbname' => 'myapp',
    'user' => 'root',
    'password' => 'password',
    'host' => 'localhost',
    'driver' => 'pdo_mysql',
]);

// Query Builder
$queryBuilder = $conn->createQueryBuilder();
$queryBuilder
    ->select('u.*')
    ->from('users', 'u')
    ->where('u.status = :status')
    ->andWhere('u.age > :age')
    ->setParameter('status', 'active')
    ->setParameter('age', 25);

$result = $queryBuilder->execute()->fetchAll();
```

### After (Bob)

```php
use Bob\Database\Connection;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

$result = $connection->table('users')
    ->where('status', 'active')
    ->where('age', '>', 25)
    ->get();
```

## Migrating from Medoo

Bob offers similar simplicity to Medoo with a more Laravel-like API.

### Before (Medoo)

```php
use Medoo\Medoo;

$database = new Medoo([
    'type' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password'
]);

// Select
$users = $database->select('users', '*', [
    'status' => 'active',
    'age[>]' => 25
]);

// Insert
$database->insert('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update
$database->update('users', [
    'status' => 'inactive'
], [
    'id' => 1
]);
```

### After (Bob)

```php
use Bob\Database\Connection;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

// Select
$users = $connection->table('users')
    ->where('status', 'active')
    ->where('age', '>', 25)
    ->get();

// Insert
$connection->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update
$connection->table('users')
    ->where('id', 1)
    ->update(['status' => 'inactive']);
```

## Common Migration Patterns

### 1. Database Connection Factory

Create a factory for managing connections:

```php
class DatabaseFactory {
    private static array $connections = [];

    public static function create(string $name = 'default'): Connection {
        if (!isset(self::$connections[$name])) {
            $config = self::getConfig($name);
            self::$connections[$name] = new Connection($config);
        }

        return self::$connections[$name];
    }

    private static function getConfig(string $name): array {
        $configs = [
            'default' => [
                'driver' => $_ENV['DB_DRIVER'],
                'host' => $_ENV['DB_HOST'],
                'database' => $_ENV['DB_DATABASE'],
                'username' => $_ENV['DB_USERNAME'],
                'password' => $_ENV['DB_PASSWORD'],
            ],
            'analytics' => [
                'driver' => $_ENV['ANALYTICS_DB_DRIVER'],
                // ...
            ],
        ];

        return $configs[$name] ?? $configs['default'];
    }
}

// Usage
$db = DatabaseFactory::create();
$users = $db->table('users')->get();
```

### 2. Repository Pattern

Wrap Bob in repositories for better organization:

```php
class UserRepository {
    private Connection $db;

    public function __construct(Connection $db) {
        $this->db = $db;
    }

    public function findActive(): array {
        return $this->db->table('users')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByEmail(string $email): ?object {
        return $this->db->table('users')
            ->where('email', $email)
            ->first();
    }

    public function create(array $data): int {
        return $this->db->table('users')->insertGetId($data);
    }
}
```

### 3. Migration from Raw SQL Queries

Convert complex raw SQL to Bob's fluent interface:

```php
// Before - Raw SQL
$sql = "
    SELECT u.*, COUNT(p.id) as post_count
    FROM users u
    LEFT JOIN posts p ON u.id = p.user_id
    WHERE u.status = 'active'
        AND u.created_at > '2024-01-01'
    GROUP BY u.id
    HAVING post_count > 5
    ORDER BY post_count DESC
    LIMIT 10
";
$stmt = $pdo->query($sql);
$topUsers = $stmt->fetchAll();

// After - Bob Query Builder
$topUsers = $connection->table('users as u')
    ->leftJoin('posts as p', 'u.id', '=', 'p.user_id')
    ->select('u.*', $connection->raw('COUNT(p.id) as post_count'))
    ->where('u.status', 'active')
    ->where('u.created_at', '>', '2024-01-01')
    ->groupBy('u.id')
    ->having('post_count', '>', 5)
    ->orderBy('post_count', 'desc')
    ->limit(10)
    ->get();
```

## Testing Your Migration

### 1. Create Integration Tests

```php
class MigrationTest extends PHPUnit\Framework\TestCase {
    private Connection $bob;
    private PDO $pdo;

    protected function setUp(): void {
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];

        $this->bob = new Connection($config);
        $this->pdo = new PDO('sqlite::memory:');

        // Create test table
        $this->bob->statement('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT,
                email TEXT,
                status TEXT
            )
        ');
    }

    public function testSelectMigration() {
        // Insert test data
        $this->bob->table('users')->insert([
            ['name' => 'John', 'email' => 'john@example.com', 'status' => 'active'],
            ['name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'inactive'],
        ]);

        // Bob query
        $bobResults = $this->bob->table('users')
            ->where('status', 'active')
            ->get();

        // Verify results
        $this->assertCount(1, $bobResults);
        $this->assertEquals('John', $bobResults[0]->name);
    }
}
```

### 2. Performance Comparison

```php
$start = microtime(true);

// Old method (PDO)
for ($i = 0; $i < 1000; $i++) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$i]);
    $user = $stmt->fetch();
}

$pdoTime = microtime(true) - $start;

$start = microtime(true);

// New method (Bob)
for ($i = 0; $i < 1000; $i++) {
    $user = $connection->table('users')->find($i);
}

$bobTime = microtime(true) - $start;

echo "PDO Time: {$pdoTime}s\n";
echo "Bob Time: {$bobTime}s\n";
```

## Gradual Migration Strategy

### Phase 1: Setup
1. Install Bob alongside existing code
2. Create connection wrapper
3. Set up logging for debugging

### Phase 2: New Features
1. Use Bob for all new features
2. Keep existing code unchanged
3. Monitor performance

### Phase 3: Gradual Refactoring
1. Identify most-used queries
2. Convert one module at a time
3. Test thoroughly after each conversion

### Phase 4: Complete Migration
1. Remove old database code
2. Optimize Bob configuration
3. Update documentation

## Troubleshooting Migration Issues

### Connection Issues

```php
// Debug connection
try {
    $connection = new Connection($config);
    $connection->getPdo(); // Force connection
    echo "Connected successfully";
} catch (\Exception $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

### Query Differences

```php
// Log queries to compare
$connection->enableQueryLog();

// Run your query
$results = $connection->table('users')->get();

// Check generated SQL
$log = $connection->getQueryLog();
echo $log[0]['query']; // Compare with old SQL
```

### Performance Issues

```php
// Enable profiling
use Bob\Database\QueryProfiler;

$profiler = new QueryProfiler();
$connection->setProfiler($profiler);
$profiler->enable();

// Run queries
// ...

// Check for slow queries
$slowQueries = $profiler->getSlowQueries();
foreach ($slowQueries as $query) {
    echo "Slow: " . $query['sql'] . " ({$query['time']}ms)\n";
}
```

## Next Steps

After migration:

1. **Optimize Queries**: Use Bob's profiling to identify slow queries
2. **Implement Caching**: Use Bob's query caching features
3. **Add Models**: Create model classes for common entities
4. **Set Up Logging**: Configure logging for production monitoring
5. **Review Security**: Ensure all user input is properly bound

For more details, see:
- [Configuration Guide](/guide/configuration)
- [Query Builder Guide](/guide/query-builder)
- [Performance Optimization](/guide/performance)
- [Models Guide](/guide/models)