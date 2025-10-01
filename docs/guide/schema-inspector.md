# Schema Inspector

The Schema Inspector allows you to examine existing database schemas and reverse engineer them into migration files. This is incredibly useful for:

- **Documenting** existing databases
- **Generating migrations** from legacy systems
- **Comparing** schemas across environments
- **Understanding** database structure programmatically

## Introduction

The `Inspector` class provides methods to introspect your database and extract detailed information about tables, columns, indexes, and foreign keys.

## Getting Started

### Basic Setup

```php
use Bob\Database\Connection;
use Bob\Schema\Inspector;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

$inspector = new Inspector($connection);
```

## Inspecting Tables

### Get All Tables

```php
$tables = $inspector->getTables();

// Returns array of table names
// ['users', 'posts', 'comments', 'categories']
```

### Database-Specific Behavior

The method works across all supported databases:

- **MySQL**: `SHOW TABLES`
- **PostgreSQL**: Queries `pg_catalog.pg_tables`
- **SQLite**: Queries `sqlite_master`

## Inspecting Columns

### Get Table Columns

```php
$columns = $inspector->getColumns('users');

// Returns detailed column information
print_r($columns);
```

Example output:
```php
[
    [
        'name' => 'id',
        'type' => 'bigint unsigned',
        'nullable' => false,
        'default' => null,
        'auto_increment' => true,
        'primary' => true,
        'unique' => false,
        'length' => null,
        'precision' => 20,
        'scale' => 0,
        'comment' => 'Primary key',
    ],
    [
        'name' => 'name',
        'type' => 'varchar(255)',
        'nullable' => false,
        'default' => null,
        'auto_increment' => false,
        'primary' => false,
        'unique' => false,
        'length' => 255,
        'precision' => null,
        'scale' => null,
        'comment' => '',
    ],
    [
        'name' => 'email',
        'type' => 'varchar(255)',
        'nullable' => false,
        'default' => null,
        'auto_increment' => false,
        'primary' => false,
        'unique' => true,
        'length' => 255,
        'precision' => null,
        'scale' => null,
        'comment' => 'User email address',
    ],
    // ... more columns
]
```

### Column Information Fields

Each column array contains:

- `name` - Column name
- `type` - Database-specific type (e.g., "varchar(255)", "bigint unsigned")
- `nullable` - Whether NULL values are allowed (boolean)
- `default` - Default value (mixed or null)
- `auto_increment` - Whether column auto-increments (boolean, MySQL only)
- `primary` - Whether column is primary key (boolean)
- `unique` - Whether column has unique constraint (boolean)
- `length` - For string types (int or null)
- `precision` - For numeric types (int or null)
- `scale` - For decimal types (int or null)
- `comment` - Column comment (string, MySQL only)

## Inspecting Indexes

### Get Table Indexes

```php
$indexes = $inspector->getIndexes('users');

print_r($indexes);
```

Example output:
```php
[
    [
        'name' => 'PRIMARY',
        'columns' => ['id'],
        'unique' => true,
        'primary' => true,
    ],
    [
        'name' => 'users_email_unique',
        'columns' => ['email'],
        'unique' => true,
        'primary' => false,
    ],
    [
        'name' => 'users_status_index',
        'columns' => ['status'],
        'unique' => false,
        'primary' => false,
    ],
    [
        'name' => 'users_created_at_index',
        'columns' => ['created_at', 'updated_at'],
        'unique' => false,
        'primary' => false,
    ],
]
```

### Index Information Fields

- `name` - Index name
- `columns` - Array of column names in the index
- `unique` - Whether index enforces uniqueness (boolean)
- `primary` - Whether this is the primary key (boolean)

## Inspecting Foreign Keys

### Get Foreign Key Constraints

```php
$foreignKeys = $inspector->getForeignKeys('posts');

print_r($foreignKeys);
```

Example output:
```php
[
    [
        'name' => 'posts_user_id_foreign',
        'column' => 'user_id',
        'foreign_table' => 'users',
        'foreign_column' => 'id',
        'on_update' => 'cascade',
        'on_delete' => 'cascade',
    ],
    [
        'name' => 'posts_category_id_foreign',
        'column' => 'category_id',
        'foreign_table' => 'categories',
        'foreign_column' => 'id',
        'on_update' => 'cascade',
        'on_delete' => 'set null',
    ],
]
```

### Foreign Key Information Fields

- `name` - Constraint name
- `column` - Local column name
- `foreign_table` - Referenced table
- `foreign_column` - Referenced column
- `on_update` - Action on update: "cascade", "restrict", "set null", "no action"
- `on_delete` - Action on delete: "cascade", "restrict", "set null", "no action"

## Generating Migrations

### Auto-Generate Migration Files

The most powerful feature of the Inspector is automatic migration generation:

```php
$migration = $inspector->generateMigration('users');

// Save to file
$filename = '2024_01_15_120000_create_users_table.php';
file_put_contents($filename, $migration);
```

Example generated migration:
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
            $table->id('id');
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->rememberToken();
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('created_at')->default('CURRENT_TIMESTAMP');
            $table->dateTime('updated_at')->default('CURRENT_TIMESTAMP');
            $table->index(['created_at']);
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

### Type Mapping

The Inspector intelligently maps database types to Blueprint methods:

| Database Type | Blueprint Method |
|--------------|------------------|
| BIGINT | `bigInteger()` |
| INT | `integer()` |
| SMALLINT | `smallInteger()` |
| TINYINT | `tinyInteger()` |
| VARCHAR | `string()` |
| CHAR | `char()` |
| TEXT | `text()` |
| LONGTEXT | `longText()` |
| MEDIUMTEXT | `mediumText()` |
| DECIMAL | `decimal()` |
| FLOAT | `float()` |
| DOUBLE | `double()` |
| BOOLEAN | `boolean()` |
| DATE | `date()` |
| DATETIME | `dateTime()` |
| TIMESTAMP | `timestamp()` |
| TIME | `time()` |
| JSON | `json()` |
| BLOB | `binary()` |
| UUID | `uuid()` |

Auto-increment columns are automatically converted to `id()` or `increments()`.

## Complete Examples

### Documenting an Entire Database

```php
use Bob\Schema\Inspector;

$inspector = new Inspector($connection);

// Get all tables
$tables = $inspector->getTables();

$documentation = [];

foreach ($tables as $table) {
    $documentation[$table] = [
        'columns' => $inspector->getColumns($table),
        'indexes' => $inspector->getIndexes($table),
        'foreign_keys' => $inspector->getForeignKeys($table),
    ];
}

// Save as JSON
file_put_contents('database_schema.json', json_encode($documentation, JSON_PRETTY_PRINT));
```

### Generating Migrations for All Tables

```php
$inspector = new Inspector($connection);
$tables = $inspector->getTables();

$timestamp = time();
$migrationDir = __DIR__ . '/migrations';

if (!is_dir($migrationDir)) {
    mkdir($migrationDir, 0755, true);
}

foreach ($tables as $index => $table) {
    // Generate migration content
    $migration = $inspector->generateMigration($table);

    // Create timestamp (increment by 1 second for each migration)
    $time = $timestamp + $index;
    $date = date('Y_m_d_His', $time);

    // Create filename
    $filename = "{$date}_create_{$table}_table.php";
    $filepath = $migrationDir . '/' . $filename;

    // Save migration file
    file_put_contents($filepath, $migration);

    echo "Generated migration for {$table}: {$filename}\n";
}
```

### Comparing Schemas Across Environments

```php
function compareSchemas(Connection $conn1, Connection $conn2): array
{
    $inspector1 = new Inspector($conn1);
    $inspector2 = new Inspector($conn2);

    $tables1 = $inspector1->getTables();
    $tables2 = $inspector2->getTables();

    $differences = [
        'missing_in_env2' => array_diff($tables1, $tables2),
        'missing_in_env1' => array_diff($tables2, $tables1),
        'column_differences' => [],
    ];

    // Compare columns for tables that exist in both
    $commonTables = array_intersect($tables1, $tables2);

    foreach ($commonTables as $table) {
        $columns1 = $inspector1->getColumns($table);
        $columns2 = $inspector2->getColumns($table);

        $columnNames1 = array_column($columns1, 'name');
        $columnNames2 = array_column($columns2, 'name');

        if ($columnNames1 !== $columnNames2) {
            $differences['column_differences'][$table] = [
                'env1' => $columnNames1,
                'env2' => $columnNames2,
            ];
        }
    }

    return $differences;
}

// Usage
$dev = new Connection([/* dev config */]);
$prod = new Connection([/* prod config */]);

$diff = compareSchemas($dev, $prod);
print_r($diff);
```

### WordPress Database Documentation

```php
use Bob\Schema\Inspector;

function documentWordPressDatabase(): void
{
    global $wpdb;

    $connection = new Connection([
        'driver' => 'mysql',
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASSWORD,
        'prefix' => $wpdb->prefix,
    ]);

    $inspector = new Inspector($connection);
    $tables = $inspector->getTables();

    echo "# WordPress Database Schema\n\n";

    foreach ($tables as $table) {
        echo "## Table: {$table}\n\n";

        // Columns
        $columns = $inspector->getColumns($table);
        echo "### Columns\n\n";
        echo "| Name | Type | Nullable | Default | Comment |\n";
        echo "|------|------|----------|---------|----------|\n";

        foreach ($columns as $column) {
            $nullable = $column['nullable'] ? 'YES' : 'NO';
            $default = $column['default'] ?? 'NULL';
            $comment = $column['comment'] ?? '';

            echo "| {$column['name']} | {$column['type']} | {$nullable} | {$default} | {$comment} |\n";
        }

        // Indexes
        $indexes = $inspector->getIndexes($table);
        if (!empty($indexes)) {
            echo "\n### Indexes\n\n";
            echo "| Name | Columns | Type |\n";
            echo "|------|---------|------|\n";

            foreach ($indexes as $index) {
                $columns = implode(', ', $index['columns']);
                $type = $index['primary'] ? 'PRIMARY' : ($index['unique'] ? 'UNIQUE' : 'INDEX');
                echo "| {$index['name']} | {$columns} | {$type} |\n";
            }
        }

        // Foreign Keys
        $foreignKeys = $inspector->getForeignKeys($table);
        if (!empty($foreignKeys)) {
            echo "\n### Foreign Keys\n\n";
            echo "| Name | Column | References | On Update | On Delete |\n";
            echo "|------|--------|------------|-----------|------------|\n";

            foreach ($foreignKeys as $fk) {
                $ref = "{$fk['foreign_table']}.{$fk['foreign_column']}";
                echo "| {$fk['name']} | {$fk['column']} | {$ref} | {$fk['on_update']} | {$fk['on_delete']} |\n";
            }
        }

        echo "\n---\n\n";
    }
}
```

## Use Cases

### 1. Legacy Database Migration

When taking over a legacy project without migrations:

```php
// Generate migrations for entire legacy database
$inspector = new Inspector($legacyConnection);
$tables = $inspector->getTables();

foreach ($tables as $table) {
    $migration = $inspector->generateMigration($table);
    // Save migration file...
}
```

### 2. Schema Validation

Verify production schema matches expected structure:

```php
$inspector = new Inspector($connection);

// Check if table exists
if (!in_array('users', $inspector->getTables())) {
    throw new Exception('Users table is missing!');
}

// Check for required columns
$columns = array_column($inspector->getColumns('users'), 'name');
$required = ['id', 'email', 'password', 'created_at', 'updated_at'];

$missing = array_diff($required, $columns);
if (!empty($missing)) {
    throw new Exception('Missing columns: ' . implode(', ', $missing));
}
```

### 3. Database Documentation

Generate markdown documentation:

```php
function generateMarkdownDocs(Inspector $inspector, string $table): string
{
    $md = "# Table: {$table}\n\n";

    // Columns
    $columns = $inspector->getColumns($table);
    $md .= "## Columns\n\n";
    $md .= "| Column | Type | Nullable | Default | Comment |\n";
    $md .= "|--------|------|----------|---------|----------|\n";

    foreach ($columns as $col) {
        $nullable = $col['nullable'] ? '✓' : '✗';
        $default = $col['default'] ?? 'NULL';
        $comment = $col['comment'] ?? '';
        $md .= "| {$col['name']} | {$col['type']} | {$nullable} | {$default} | {$comment} |\n";
    }

    return $md;
}
```

### 4. Schema Sync Tool

Build a tool to sync development to production schema:

```php
class SchemaSyncer
{
    public function __construct(
        private Inspector $sourceInspector,
        private Inspector $targetInspector
    ) {}

    public function sync(): array
    {
        $operations = [];

        $sourceTables = $this->sourceInspector->getTables();
        $targetTables = $this->targetInspector->getTables();

        // Find missing tables
        $missingTables = array_diff($sourceTables, $targetTables);

        foreach ($missingTables as $table) {
            $operations[] = [
                'type' => 'create_table',
                'table' => $table,
                'migration' => $this->sourceInspector->generateMigration($table),
            ];
        }

        // Compare columns for existing tables
        $commonTables = array_intersect($sourceTables, $targetTables);

        foreach ($commonTables as $table) {
            $diff = $this->compareColumns($table);
            if (!empty($diff)) {
                $operations[] = [
                    'type' => 'modify_table',
                    'table' => $table,
                    'changes' => $diff,
                ];
            }
        }

        return $operations;
    }

    private function compareColumns(string $table): array
    {
        $sourceColumns = $this->sourceInspector->getColumns($table);
        $targetColumns = $this->targetInspector->getColumns($table);

        $sourceNames = array_column($sourceColumns, 'name');
        $targetNames = array_column($targetColumns, 'name');

        return [
            'missing' => array_diff($sourceNames, $targetNames),
            'extra' => array_diff($targetNames, $sourceNames),
        ];
    }
}
```

## Best Practices

### 1. Always Review Generated Migrations

Generated migrations are a starting point - always review and adjust:

```php
$migration = $inspector->generateMigration('users');

// Save for review
file_put_contents('migration_draft.php', $migration);

// Review, then move to migrations directory
```

### 2. Handle Complex Types

Some database-specific types may need manual adjustment:

```php
// Generated might use:
$table->string('data');

// But you might want:
$table->json('data');
```

### 3. Document Why

Add comments to generated migrations:

```php
/**
 * Migration generated from existing 'users' table
 * Generated on: 2024-01-15 12:00:00
 * Source database: production_v1
 */
return new class extends Migration { ... };
```

### 4. Test Generated Migrations

Always test generated migrations on a clean database:

```php
// Run generated migration
$runner->run();

// Verify structure matches original
$newColumns = $inspector->getColumns('users');
// Compare with original...
```

## Next Steps

- Learn about [Database Migrations](migrations.md)
- Explore [Schema Builder](schema-builder.md)
- Check out [WordPress Schema Helpers](wordpress-schema.md)
