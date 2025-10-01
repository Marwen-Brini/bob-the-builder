<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    Schema::setConnection($this->connection);

    // Clean up any existing test tables
    dropSQLiteTestTables();
});

afterEach(function () {
    dropSQLiteTestTables();
});

function dropSQLiteTestTables(): void
{
    $tables = ['test_table', 'users', 'posts', 'comments', 'products', 'orders'];

    foreach ($tables as $table) {
        try {
            Schema::dropIfExists($table);
        } catch (Exception $e) {
            // Ignore errors during cleanup
        }
    }
}

test('create simple table', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();

    // Check columns exist
    expect(Schema::hasColumn('test_table', 'id'))->toBeTrue();
    expect(Schema::hasColumn('test_table', 'name'))->toBeTrue();
    expect(Schema::hasColumn('test_table', 'created_at'))->toBeTrue();
    expect(Schema::hasColumn('test_table', 'updated_at'))->toBeTrue();
})->group('sqlite', 'integration-sqlite');

test('drop table', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();

    Schema::drop('test_table');

    expect(Schema::hasTable('test_table'))->toBeFalse();
})->group('sqlite', 'integration-sqlite');

test('drop if exists', function () {
    // Should not throw error even if table doesn't exist
    Schema::dropIfExists('non_existent_table');

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
    });

    Schema::dropIfExists('test_table');
    expect(Schema::hasTable('test_table'))->toBeFalse();
})->group('sqlite', 'integration-sqlite');

test('rename table', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::rename('test_table', 'renamed_table');

    expect(Schema::hasTable('test_table'))->toBeFalse();
    expect(Schema::hasTable('renamed_table'))->toBeTrue();

    // Clean up
    Schema::dropIfExists('renamed_table');
})->group('sqlite', 'integration-sqlite');

test('add columns', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::table('test_table', function (Blueprint $table) {
        $table->string('email');
        $table->integer('age')->nullable();
    });

    expect(Schema::hasColumn('test_table', 'email'))->toBeTrue();
    expect(Schema::hasColumn('test_table', 'age'))->toBeTrue();
})->group('sqlite', 'integration-sqlite');

test('column types', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('string_col', 100);
        $table->text('text_col');
        $table->integer('int_col');
        $table->bigInteger('bigint_col');
        $table->float('float_col');
        $table->double('double_col');
        $table->decimal('decimal_col', 10, 2);
        $table->boolean('bool_col');
        $table->date('date_col');
        $table->dateTime('datetime_col');
        $table->timestamp('timestamp_col');
        $table->json('json_col');
        $table->uuid('uuid_col');
        $table->binary('binary_col');
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();

    $columns = Schema::getColumnListing('test_table');
    expect($columns)->toHaveCount(15);
})->group('sqlite', 'integration-sqlite');

test('nullable columns', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->string('required_col');
        $table->string('nullable_col')->nullable();
    });

    // Insert test data
    $this->connection->table('test_table')->insert([
        'required_col' => 'value',
        'nullable_col' => null,
    ]);

    $result = $this->connection->table('test_table')->first();
    expect($result->required_col)->toBe('value');
    expect($result->nullable_col)->toBeNull();
})->group('sqlite', 'integration-sqlite');

test('default values', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('status')->default('pending');
        $table->integer('count')->default(0);
        $table->boolean('active')->default(true);
    });

    $this->connection->table('test_table')->insert([]);

    $result = $this->connection->table('test_table')->first();
    expect($result->status)->toBe('pending');
    expect($result->count)->toBe(0);
    expect($result->active)->toBe(1); // SQLite stores boolean as integer
})->group('sqlite', 'integration-sqlite');

test('indexes', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('name')->index();
        $table->integer('age');
        $table->index(['name', 'age'], 'composite_index');
    });

    // Unique constraint should prevent duplicates
    $this->connection->table('test_table')->insert([
        'email' => 'test@example.com',
        'name' => 'John',
        'age' => 30,
    ]);

    expect(fn () => $this->connection->table('test_table')->insert([
        'email' => 'test@example.com',
        'name' => 'Jane',
        'age' => 25,
    ]))->toThrow(Exception::class);
})->group('sqlite', 'integration-sqlite');

test('foreign keys', function () {
    // Enable foreign key constraints in SQLite
    $this->connection->statement('PRAGMA foreign_keys = ON');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    });

    // Insert parent record
    $userId = $this->connection->table('users')->insertGetId(['name' => 'John']);

    // Insert child record
    $postId = $this->connection->table('posts')->insertGetId([
        'title' => 'Test Post',
        'user_id' => $userId,
    ]);

    // Delete parent should cascade delete child
    $this->connection->table('users')->where('id', $userId)->delete();

    $postCount = $this->connection->table('posts')->count();
    expect($postCount)->toBe(0);
})->group('sqlite', 'integration-sqlite');

test('enum column', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->enum('status', ['active', 'inactive', 'pending']);
    });

    // Should allow valid enum value
    $this->connection->table('test_table')->insert(['status' => 'active']);

    // SQLite implements enum as CHECK constraint
    // Invalid value should fail
    expect(fn () => $this->connection->table('test_table')->insert(['status' => 'invalid']))
        ->toThrow(Exception::class);
})->group('sqlite', 'integration-sqlite');

test('timestamps', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    expect(Schema::hasColumn('test_table', 'created_at'))->toBeTrue();
    expect(Schema::hasColumn('test_table', 'updated_at'))->toBeTrue();
})->group('sqlite', 'integration-sqlite');

test('soft deletes', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->softDeletes();
    });

    expect(Schema::hasColumn('test_table', 'deleted_at'))->toBeTrue();
})->group('sqlite', 'integration-sqlite');

test('check table exists', function () {
    expect(Schema::hasTable('non_existent_table'))->toBeFalse();

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();
})->group('sqlite', 'integration-sqlite');

test('check column exists', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    expect(Schema::hasColumn('test_table', 'id'))->toBeTrue();
    expect(Schema::hasColumn('test_table', 'name'))->toBeTrue();
    expect(Schema::hasColumn('test_table', 'non_existent'))->toBeFalse();
})->group('sqlite', 'integration-sqlite');

test('check multiple columns', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
    });

    expect(Schema::hasColumns('test_table', ['id', 'name', 'email']))->toBeTrue();
    expect(Schema::hasColumns('test_table', ['id', 'name', 'non_existent']))->toBeFalse();
})->group('sqlite', 'integration-sqlite');

test('get column listing', function () {
    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });

    $columns = Schema::getColumnListing('test_table');

    expect($columns)->toContain('id');
    expect($columns)->toContain('name');
    expect($columns)->toContain('email');
    expect($columns)->toContain('created_at');
    expect($columns)->toContain('updated_at');
    expect($columns)->toHaveCount(5);
})->group('sqlite', 'integration-sqlite');

test('temporary table', function () {
    Schema::create('temp_table', function (Blueprint $table) {
        $table->temporary = true;
        $table->id();
        $table->string('name');
    });

    // Temporary tables exist in SQLite memory
    expect(Schema::hasTable('temp_table'))->toBeTrue();
})->group('sqlite', 'integration-sqlite');

test('complex table', function () {
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('sku', 50)->unique();
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2);
        $table->integer('stock')->default(0);
        $table->boolean('featured')->default(false);
        $table->json('attributes')->nullable();
        $table->string('status')->default('active');
        $table->timestamps();
        $table->softDeletes();

        $table->index('name');
        $table->index(['status', 'featured']);
    });

    expect(Schema::hasTable('products'))->toBeTrue();

    // Insert test product
    $productId = $this->connection->table('products')->insertGetId([
        'sku' => 'TEST-001',
        'name' => 'Test Product',
        'description' => 'A test product',
        'price' => 99.99,
        'stock' => 10,
        'featured' => true,
        'attributes' => json_encode(['color' => 'red', 'size' => 'large']),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $product = $this->connection->table('products')->find($productId);

    expect($product->sku)->toBe('TEST-001');
    expect($product->name)->toBe('Test Product');
    expect($product->price)->toBe(99.99);
    expect($product->stock)->toBe(10);
    expect($product->featured)->toBe(1);
    expect($product->attributes)->not->toBeNull();
})->group('sqlite', 'integration-sqlite');
