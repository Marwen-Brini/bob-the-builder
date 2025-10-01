# Database Migrations

Bob's migration system provides a version-controlled approach to managing your database schema. Migrations allow you to define database changes in PHP code, track them in version control, and apply or rollback changes in a controlled manner.

## Introduction

Migrations are like version control for your database, allowing you to:

- **Track Schema Changes** - Keep a history of all database modifications
- **Collaborate** - Share schema changes with your team through version control
- **Rollback** - Easily undo changes if something goes wrong
- **Deploy** - Apply schema changes consistently across environments
- **Test** - Reset and re-run migrations during development

## Creating Migrations

### Basic Migration Structure

A migration is a PHP class that extends `Bob\Database\Migrations\Migration`:

```php
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### Migration File Naming

Migration files should follow this naming convention:

```
YYYY_MM_DD_HHMMSS_description.php
```

Examples:
- `2024_01_15_120000_create_users_table.php`
- `2024_01_15_120100_add_phone_to_users_table.php`
- `2024_01_15_120200_create_posts_table.php`

The timestamp ensures migrations run in the correct order.

## Running Migrations

### Setting Up the Migration Runner

```php
use Bob\Database\Connection;
use Bob\Database\Migrations\MigrationRunner;
use Bob\Database\Migrations\MigrationRepository;

// Create database connection
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

// Create migration repository (stores migration state)
$repository = new MigrationRepository($connection, 'migrations');

// Create migration runner
$runner = new MigrationRunner(
    $connection,
    $repository,
    [__DIR__ . '/migrations'] // Path(s) to migration files
);
```

### Running Pending Migrations

```php
// Run all pending migrations
$runner->run();

// Run with output callback
$runner->setOutput(function($message) {
    echo $message . PHP_EOL;
});

$runner->run();
```

Output:
```
Migration table created successfully.
Migrating: 2024_01_15_120000_create_users_table
Migrated:  2024_01_15_120000_create_users_table (45.23ms)
Migrating: 2024_01_15_120100_add_phone_to_users_table
Migrated:  2024_01_15_120100_add_phone_to_users_table (12.45ms)
```

### Pretend Mode

Test migrations without actually running them:

```php
$runner->run(['pretend' => true]);
```

Output:
```
Would run: CreateUsersTable::up()
  Description: Create users table
  Transaction: within transaction
  Connection: default
```

## Rolling Back Migrations

### Rollback Last Batch

```php
// Rollback the last batch of migrations
$runner->rollback();
```

### Rollback Specific Steps

```php
// Rollback last 3 batches
$runner->rollback(['step' => 3]);
```

### Rollback Specific Batch

```php
// Rollback batch number 2
$runner->rollback(['batch' => 2]);
```

### Reset All Migrations

```php
// Rollback all migrations
$runner->reset();
```

## Migration Commands

### Refresh

Rollback all migrations and re-run them:

```php
$runner->refresh();
```

### Fresh

Drop all tables and re-run all migrations:

```php
$runner->fresh();
```

### Status

Get the status of all migrations:

```php
$status = $runner->status();

print_r($status);
```

Output:
```php
[
    'ran' => [
        '2024_01_15_120000_create_users_table',
        '2024_01_15_120100_add_phone_to_users_table',
    ],
    'pending' => [
        '2024_01_15_120200_create_posts_table',
    ],
    'batches' => [
        '2024_01_15_120000_create_users_table' => 1,
        '2024_01_15_120100_add_phone_to_users_table' => 1,
    ],
    'stats' => [
        'total' => 3,
        'executed' => 2,
        'pending' => 1,
        'last_batch' => 1,
    ]
]
```

## Advanced Features

### Migration Dependencies

Declare dependencies between migrations:

```php
use Bob\Database\Migrations\Migration;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;

return new class extends Migration
{
    /**
     * Dependencies for this migration
     */
    public function dependencies(): array
    {
        return [
            '2024_01_15_120000_create_users_table',
            '2024_01_15_120100_create_roles_table',
        ];
    }

    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('role_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
```

The migration runner will ensure dependencies are executed before this migration.

### Lifecycle Hooks

Add setup and cleanup logic:

```php
return new class extends Migration
{
    /**
     * Run before the migration
     */
    public function before(): void
    {
        // Disable foreign key checks
        $this->connection->statement('SET FOREIGN_KEY_CHECKS = 0');
    }

    public function up(): void
    {
        // Your migration logic
    }

    /**
     * Run after the migration
     */
    public function after(): void
    {
        // Re-enable foreign key checks
        $this->connection->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(): void
    {
        // Your rollback logic
    }
};
```

### Transaction Control

Control whether migrations run within transactions:

```php
return new class extends Migration
{
    /**
     * Disable transaction for this migration
     */
    public function withinTransaction(): bool
    {
        return false;
    }

    public function up(): void
    {
        // This will not run in a transaction
        // Useful for operations that can't be rolled back
        Schema::create('large_table', function (Blueprint $table) {
            // ...
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('large_table');
    }
};
```

### Conditional Execution

Control whether a migration should run:

```php
return new class extends Migration
{
    public function shouldRun(): bool
    {
        // Only run in production
        return getenv('APP_ENV') === 'production';
    }

    public function up(): void
    {
        // Production-only migration
    }

    public function down(): void
    {
        // Rollback
    }
};
```

### Migration Metadata

Add description and version information:

```php
return new class extends Migration
{
    public function description(): string
    {
        return 'Add email verification columns to users table';
    }

    public function version(): string
    {
        return '1.5.0';
    }

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'email_verification_token']);
        });
    }
};
```

## Migration Events

Hook into the migration lifecycle:

```php
use Bob\Events\EventDispatcherInterface;
use Bob\Database\Migrations\MigrationEvents;

class MyEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(string $event, array $payload = []): void
    {
        match ($event) {
            MigrationEvents::BEFORE_RUN => $this->onBeforeRun($payload),
            MigrationEvents::AFTER_RUN => $this->onAfterRun($payload),
            MigrationEvents::BEFORE_MIGRATION => $this->onBeforeMigration($payload),
            MigrationEvents::AFTER_MIGRATION => $this->onAfterMigration($payload),
            MigrationEvents::ERROR => $this->onError($payload),
            default => null,
        };
    }

    private function onBeforeMigration(array $payload): void
    {
        $migration = $payload['migration'];
        $class = $payload['class'];
        echo "Starting migration: {$migration}\n";
    }

    private function onAfterMigration(array $payload): void
    {
        $migration = $payload['migration'];
        $time = $payload['time'];
        echo "Completed migration: {$migration} in {$time}ms\n";
    }

    private function onError(array $payload): void
    {
        $migration = $payload['migration'];
        $exception = $payload['exception'];
        error_log("Migration failed: {$migration} - " . $exception->getMessage());
    }
}

// Attach event dispatcher
$runner->setEventDispatcher(new MyEventDispatcher());
```

### Available Events

- `BEFORE_RUN` - Before running migration batch
- `AFTER_RUN` - After running migration batch
- `BEFORE_MIGRATION` - Before individual migration
- `AFTER_MIGRATION` - After individual migration
- `BEFORE_ROLLBACK` - Before rollback operation
- `AFTER_ROLLBACK` - After rollback operation
- `REPOSITORY_CREATED` - When migration table is created
- `STATUS_CHECK` - When migration status is checked
- `ERROR` - When a migration fails
- `PRETEND` - When running in pretend mode

## Error Handling

Add custom error handling:

```php
$runner->setErrorHandler(function($exception, $migration) {
    // Log to your logging system
    error_log("Migration {$migration} failed: " . $exception->getMessage());

    // Send notification
    notify_admin("Migration failure", [
        'migration' => $migration,
        'error' => $exception->getMessage(),
    ]);

    // The exception will still be re-thrown after this handler
});
```

## Migration Repository

### Custom Table Name

Use a custom table name for migration tracking:

```php
$repository = new MigrationRepository($connection, 'my_migrations');
```

### Query Migration History

```php
// Get all executed migrations
$ran = $repository->getRan();

// Get last batch
$lastBatch = $repository->getLast();

// Get specific batch
$batch = $repository->getBatch(2);

// Get all migrations with batch numbers
$batches = $repository->getMigrationBatches();

// Get next batch number
$nextBatch = $repository->getNextBatchNumber();
```

## Custom Migration Loaders

Implement custom migration loading logic:

```php
use Bob\Database\Migrations\MigrationLoaderInterface;

class CustomMigrationLoader implements MigrationLoaderInterface
{
    public function load(string $path): string
    {
        // Custom loading logic
        require_once $path;

        // Return the migration class name
        return $this->extractClassName($path);
    }

    private function extractClassName(string $path): string
    {
        // Your class name extraction logic
    }
}

// Use custom loader
$runner->setLoader(new CustomMigrationLoader());
```

## Best Practices

### 1. Always Make Migrations Reversible

Every migration should have a working `down()` method:

```php
// Good
public function up(): void
{
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });
}

public function down(): void
{
    Schema::dropIfExists('posts');
}

// Bad - no down() method means can't rollback
public function up(): void
{
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });
}

public function down(): void
{
    // Empty - can't rollback!
}
```

### 2. One Change Per Migration

Keep migrations focused on a single change:

```php
// Good - focused on one table
2024_01_15_120000_create_users_table.php
2024_01_15_120100_create_posts_table.php

// Bad - too much in one migration
2024_01_15_120000_create_all_tables.php
```

### 3. Use Descriptive Names

Make migration names clear and descriptive:

```php
// Good
2024_01_15_120000_add_status_to_orders_table.php
2024_01_15_120100_create_user_preferences_table.php

// Bad
2024_01_15_120000_update.php
2024_01_15_120100_changes.php
```

### 4. Test Rollbacks

Always test that your rollbacks work:

```php
$runner->run();
$runner->rollback(); // Make sure this works
$runner->run();      // Should work again
```

### 5. Use Transactions When Possible

Let migrations run in transactions for automatic rollback on error:

```php
// Default behavior - runs in transaction
public function withinTransaction(): bool
{
    return true; // This is the default
}
```

### 6. Handle Data Migrations Carefully

When migrating data, consider using `before()` and `after()` hooks:

```php
public function before(): void
{
    // Back up data before schema change
}

public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('new_column');
    });
}

public function after(): void
{
    // Migrate data to new column
    DB::table('users')->update([
        'new_column' => DB::raw('old_column')
    ]);
}
```

## WordPress Integration

Bob's migration system works seamlessly with WordPress:

```php
use Bob\Database\Connection;
use Bob\Database\Migrations\MigrationRunner;
use Bob\Database\Migrations\MigrationRepository;

// In your WordPress plugin
function my_plugin_run_migrations() {
    global $wpdb;

    $connection = new Connection([
        'driver' => 'mysql',
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASSWORD,
        'prefix' => $wpdb->prefix,
    ]);

    $repository = new MigrationRepository($connection, 'my_plugin_migrations');
    $runner = new MigrationRunner(
        $connection,
        $repository,
        [plugin_dir_path(__FILE__) . 'migrations']
    );

    $runner->run();
}

// Run on plugin activation
register_activation_hook(__FILE__, 'my_plugin_run_migrations');
```

## Troubleshooting

### Migration Table Not Found

If you see "Table 'migrations' doesn't exist":

```php
// The migration table is created automatically on first run
$runner->run(); // Creates table if needed
```

### Circular Dependencies

If migrations have circular dependencies:

```
Migration A depends on B
Migration B depends on C
Migration C depends on A  // Circular!
```

The runner will detect this and throw an exception. Fix by removing the circular dependency.

### Stuck Migrations

If a migration is stuck (recorded as running but not finished):

```php
// Manually delete the stuck migration from the table
$connection->table('migrations')
    ->where('migration', 'stuck_migration_name')
    ->delete();
```

### Foreign Key Errors

If you get foreign key constraint errors:

```php
public function before(): void
{
    Schema::disableForeignKeyConstraints();
}

public function after(): void
{
    Schema::enableForeignKeyConstraints();
}
```

Or use the helper:

```php
public function up(): void
{
    Schema::withoutForeignKeyConstraints(function () {
        // Your schema changes
    });
}
```

## Next Steps

- Learn about [Schema Building](schema-builder.md)
- Explore [WordPress Schema Helpers](wordpress-schema.md)
- Check out [Schema Inspector](schema-inspector.md) for reverse engineering
