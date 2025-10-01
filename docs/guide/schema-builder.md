# Schema Builder

Bob's Schema Builder provides a fluent, expressive interface for creating and modifying database tables. It works across MySQL, PostgreSQL, and SQLite, abstracting away database-specific syntax.

## Introduction

The Schema Builder allows you to:

- **Create Tables** - Define new database tables with columns, indexes, and constraints
- **Modify Tables** - Add, remove, or modify columns and indexes
- **Drop Tables** - Remove tables from your database
- **Database Agnostic** - Write once, run on MySQL, PostgreSQL, or SQLite
- **Type Safe** - Leverage PHP's type system for safer schema definitions

## Getting Started

### Setting the Connection

Before using the Schema Builder, set your database connection:

```php
use Bob\Database\Connection;
use Bob\Schema\Schema;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

Schema::setConnection($connection);
```

## Creating Tables

### Basic Table Creation

```php
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});
```

This creates a `users` table with:
- An auto-incrementing `id` column (primary key)
- A `name` VARCHAR column
- An `email` VARCHAR column with a unique constraint
- `created_at` and `updated_at` TIMESTAMP columns

### Full Example

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('content');
    $table->text('excerpt')->nullable();
    $table->string('featured_image')->nullable();
    $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
    $table->foreignId('author_id')->constrained('users');
    $table->integer('views')->default(0);
    $table->boolean('is_featured')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    // Indexes
    $table->index('status');
    $table->index(['author_id', 'status']);
});
```

## Column Types

### Numeric Types

```php
$table->id();                          // Auto-incrementing BIGINT UNSIGNED (alias for bigIncrements)
$table->bigIncrements('id');          // Auto-incrementing BIGINT UNSIGNED
$table->bigInteger('amount');          // BIGINT
$table->integer('votes');              // INT
$table->mediumInteger('count');        // MEDIUMINT
$table->smallInteger('votes');         // SMALLINT
$table->tinyInteger('active');         // TINYINT
$table->increments('id');              // Auto-incrementing INT UNSIGNED

$table->decimal('amount', 8, 2);       // DECIMAL with precision
$table->float('amount', 8, 2);         // FLOAT
$table->double('amount', 8, 2);        // DOUBLE
$table->unsignedBigInteger('amount');  // BIGINT UNSIGNED
$table->unsignedInteger('votes');      // INT UNSIGNED
```

### String Types

```php
$table->char('code', 4);              // CHAR with length
$table->string('name');               // VARCHAR (default 255)
$table->string('name', 100);          // VARCHAR with custom length
$table->text('description');          // TEXT
$table->mediumText('bio');            // MEDIUMTEXT
$table->longText('content');          // LONGTEXT
$table->binary('data');               // BLOB
```

### Date & Time Types

```php
$table->date('birthday');             // DATE
$table->dateTime('created_at');       // DATETIME
$table->dateTime('created_at', 0);    // DATETIME with precision
$table->dateTimeTz('created_at');     // DATETIME with timezone
$table->time('sunrise');              // TIME
$table->time('sunrise', 0);           // TIME with precision
$table->timeTz('sunrise');            // TIME with timezone
$table->timestamp('added_on');        // TIMESTAMP
$table->timestamp('added_on', 0);     // TIMESTAMP with precision
$table->timestampTz('added_on');      // TIMESTAMP with timezone
$table->timestamps();                 // created_at & updated_at
$table->timestamps(0);                // created_at & updated_at with precision
$table->timestampsTz();               // created_at & updated_at with timezone
$table->year('birth_year');           // YEAR
```

### Other Types

```php
$table->boolean('confirmed');         // BOOLEAN (TINYINT(1) on MySQL)
$table->enum('status', ['pending', 'active', 'suspended']);
$table->set('roles', ['admin', 'user', 'guest']);
$table->json('options');              // JSON column
$table->jsonb('options');             // JSONB (PostgreSQL)
$table->uuid('id');                   // UUID/CHAR(36)
$table->ipAddress('visitor');         // IP address (VARCHAR(45))
$table->macAddress('device');         // MAC address (VARCHAR(17))
```

### Special Columns

```php
$table->rememberToken();              // remember_token VARCHAR(100) NULLABLE
$table->softDeletes();                // deleted_at TIMESTAMP NULLABLE
$table->softDeletesTz();              // deleted_at TIMESTAMP with timezone NULLABLE
```

## Column Modifiers

### Nullability

```php
$table->string('email')->nullable();           // Allow NULL
$table->string('name')->nullable(false);       // Explicitly NOT NULL
```

### Default Values

```php
$table->string('status')->default('active');
$table->integer('votes')->default(0);
$table->boolean('confirmed')->default(false);
$table->timestamp('created_at')->useCurrent();         // CURRENT_TIMESTAMP
$table->timestamp('updated_at')->useCurrentOnUpdate(); // ON UPDATE CURRENT_TIMESTAMP
```

### Auto Increment

```php
$table->integer('id')->autoIncrement();
```

### Unsigned

```php
$table->integer('votes')->unsigned();
```

### Character Set & Collation

```php
$table->string('name')->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
```

### Column Comments

```php
$table->string('email')->comment('User email address');
```

### Column Order

```php
$table->string('email')->after('name');         // Place column after 'name'
$table->string('id')->first();                  // Place column first
```

### Virtual/Stored Columns

```php
$table->string('full_name')->storedAs('CONCAT(first_name, " ", last_name)');
$table->string('full_name')->virtualAs('CONCAT(first_name, " ", last_name)');
```

## Modifying Tables

### Adding Columns

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable();
    $table->string('address')->after('phone');
});
```

### Renaming Columns

```php
Schema::table('users', function (Blueprint $table) {
    $table->renameColumn('name', 'full_name');
});
```

### Modifying Columns

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('email', 100)->change();  // Change length
    $table->text('bio')->nullable()->change(); // Make nullable
});
```

### Dropping Columns

```php
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('phone');
    $table->dropColumn(['phone', 'address']); // Drop multiple
});
```

## Indexes

### Creating Indexes

```php
Schema::table('users', function (Blueprint $table) {
    // Single column indexes
    $table->string('email')->unique();     // Unique constraint
    $table->string('status')->index();     // Regular index

    // Or create after column definition
    $table->unique('email');
    $table->index('status');
    $table->primary('id');

    // Multi-column indexes
    $table->index(['user_id', 'status']);
    $table->unique(['user_id', 'email']);

    // Named indexes
    $table->index('email', 'idx_email');
    $table->unique(['user_id', 'email'], 'uniq_user_email');

    // Full-text indexes (MySQL)
    $table->fullText('content');
    $table->fullText(['title', 'content'], 'idx_fulltext_posts');

    // Spatial indexes (MySQL)
    $table->spatialIndex('location');
});
```

### Dropping Indexes

```php
Schema::table('users', function (Blueprint $table) {
    $table->dropIndex(['email']);              // Drop by columns
    $table->dropIndex('idx_email');            // Drop by name
    $table->dropUnique(['user_id', 'email']); // Drop unique
    $table->dropPrimary();                     // Drop primary key
    $table->dropFullText(['content']);        // Drop full-text
    $table->dropSpatialIndex(['location']);   // Drop spatial
});
```

## Foreign Keys

### Creating Foreign Keys

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    // Creates: foreign key on user_id referencing users(id)

    // Or with explicit table
    $table->foreignId('author_id')->constrained('users');

    // Full syntax
    $table->unsignedBigInteger('category_id');
    $table->foreign('category_id')
          ->references('id')
          ->on('categories')
          ->onDelete('cascade')
          ->onUpdate('cascade');
});
```

### Foreign Key Actions

```php
$table->foreign('user_id')
      ->references('id')
      ->on('users')
      ->onDelete('cascade');    // or 'restrict', 'set null', 'no action'
      ->onUpdate('restrict');   // or 'cascade', 'set null', 'no action'
```

### Dropping Foreign Keys

```php
Schema::table('posts', function (Blueprint $table) {
    $table->dropForeign(['user_id']);         // Drop by columns
    $table->dropForeign('posts_user_id_foreign'); // Drop by name
});
```

## Checking Table/Column Existence

### Check if Table Exists

```php
if (Schema::hasTable('users')) {
    // Table exists
}
```

### Check if Column Exists

```php
if (Schema::hasColumn('users', 'email')) {
    // Column exists
}

// Check multiple columns
if (Schema::hasColumns('users', ['email', 'phone'])) {
    // All columns exist
}
```

### Get Column Information

```php
// Get all column names
$columns = Schema::getColumnListing('users');
// ['id', 'name', 'email', 'created_at', 'updated_at']

// Get column type
$type = Schema::getColumnType('users', 'email');
// 'string' or 'varchar'
```

## Dropping Tables

### Drop Table

```php
Schema::drop('users');
```

### Drop Table If Exists

```php
Schema::dropIfExists('users');
```

### Drop All Tables

```php
Schema::dropAllTables();
```

## Renaming Tables

```php
Schema::rename('posts', 'articles');
```

## Foreign Key Constraints

### Disable Foreign Key Checks

```php
Schema::disableForeignKeyConstraints();

// Do work...

Schema::enableForeignKeyConstraints();
```

### With Callback

```php
Schema::withoutForeignKeyConstraints(function () {
    // Truncate tables without foreign key errors
    DB::table('posts')->truncate();
    DB::table('users')->truncate();
});
```

## Database-Specific Features

### MySQL

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');

    // MySQL-specific options
    $table->engine = 'InnoDB';
    $table->charset = 'utf8mb4';
    $table->collation = 'utf8mb4_unicode_ci';
});
```

### PostgreSQL

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');

    // PostgreSQL-specific
    $table->jsonb('metadata');  // JSONB type
    $table->uuid('uuid');        // UUID type

    // Returning clause support (automatic)
});
```

### SQLite

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');

    // SQLite limitations handled automatically
    // No column dropping (requires table rebuild)
    // No column renaming (requires table rebuild)
});
```

## Advanced Examples

### Blog Posts Table

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('excerpt')->nullable();
    $table->longText('content');
    $table->string('featured_image')->nullable();
    $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
    $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->integer('views')->default(0);
    $table->integer('likes')->default(0);
    $table->boolean('allow_comments')->default(true);
    $table->json('meta')->nullable();
    $table->timestamps();
    $table->softDeletes();

    // Indexes
    $table->index(['status', 'published_at']);
    $table->index('author_id');
    $table->fullText(['title', 'content']);
});
```

### E-commerce Order Table

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('order_number')->unique();
    $table->foreignId('customer_id')->constrained('users');
    $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])
          ->default('pending');
    $table->decimal('subtotal', 10, 2);
    $table->decimal('tax', 10, 2);
    $table->decimal('shipping', 10, 2);
    $table->decimal('discount', 10, 2)->default(0);
    $table->decimal('total', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->text('shipping_address');
    $table->text('billing_address');
    $table->string('payment_method');
    $table->string('payment_status')->default('pending');
    $table->text('notes')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('shipped_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamps();

    $table->index(['customer_id', 'status']);
    $table->index('order_number');
    $table->index('created_at');
});
```

### Pivot Table (Many-to-Many)

```php
Schema::create('role_user', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('role_id')->constrained()->onDelete('cascade');
    $table->timestamps();

    $table->primary(['user_id', 'role_id']);
});
```

## Best Practices

### 1. Use Migrations for Schema Changes

Don't use Schema Builder directly in your application. Always create migrations:

```php
// Good - in a migration file
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // ...
        });
    }
}

// Bad - in application code
Schema::create('users', function (Blueprint $table) {
    // Don't do this!
});
```

### 2. Always Add Indexes

Add indexes for columns used in WHERE, JOIN, and ORDER BY:

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('status');
    $table->foreignId('author_id');

    // Add indexes
    $table->index('status');        // Used in WHERE
    $table->index('author_id');     // Used in JOIN
});
```

### 3. Use Foreign Keys

Use foreign keys to maintain referential integrity:

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
    // If user is deleted, their posts are automatically deleted
});
```

### 4. Use Appropriate Column Types

Choose the right column type for your data:

```php
// Good
$table->tinyInteger('age');           // 0-255
$table->boolean('is_active');         // true/false
$table->decimal('price', 10, 2);      // Money

// Bad
$table->string('age');                // Storing numbers as strings
$table->string('is_active');          // Storing booleans as strings
$table->float('price');               // Precision issues with money
```

### 5. Add Comments

Document your columns:

```php
$table->string('status')
      ->comment('Post status: draft, published, or archived');
```

## Next Steps

- Learn about [Database Migrations](migrations.md)
- Explore [WordPress Schema Helpers](wordpress-schema.md)
- Check out [Schema Inspector](schema-inspector.md)
