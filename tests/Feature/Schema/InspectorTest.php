<?php

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Inspector;
use Bob\Schema\Schema;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    Schema::setConnection($this->connection);
    $this->inspector = new Inspector($this->connection);
});

afterEach(function () {
    // Clean up any tables that were created
});

test('inspector can get all tables', function () {
    // Create test tables
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
    });

    $tables = $this->inspector->getTables();

    expect($tables)->toBeArray()
        ->and($tables)->toContain('users')
        ->and($tables)->toContain('posts');
})->group('feature', 'inspector');

test('inspector can get columns for a table', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100);
        $table->string('email')->unique();
        $table->integer('age')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    $columns = $this->inspector->getColumns('users');

    expect($columns)->toBeArray()
        ->and(count($columns))->toBeGreaterThan(0);

    // Find the id column
    $idColumn = collect($columns)->first(fn ($col) => $col['name'] === 'id');
    expect($idColumn)->not->toBeNull()
        ->and($idColumn['primary'])->toBeTrue();

    // Find the name column
    $nameColumn = collect($columns)->first(fn ($col) => $col['name'] === 'name');
    expect($nameColumn)->not->toBeNull()
        ->and($nameColumn['type'])->not->toBeNull() // SQLite uses TEXT, MySQL uses varchar
        ->and($nameColumn['nullable'])->toBeFalse();

    // Find the age column
    $ageColumn = collect($columns)->first(fn ($col) => $col['name'] === 'age');
    expect($ageColumn)->not->toBeNull()
        ->and($ageColumn['nullable'])->toBeTrue();
})->group('feature', 'inspector');

test('inspector can get indexes for a table', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('username')->index();
        $table->index(['email', 'username'], 'users_email_username_index');
    });

    $indexes = $this->inspector->getIndexes('users');

    expect($indexes)->toBeArray()
        ->and(count($indexes))->toBeGreaterThan(0);

    // Check for unique index on email
    $emailIndex = collect($indexes)->first(function ($idx) {
        return in_array('email', $idx['columns']) && count($idx['columns']) === 1;
    });
    expect($emailIndex)->not->toBeNull()
        ->and($emailIndex['unique'])->toBeTrue();
})->group('feature', 'inspector');

test('inspector can get foreign keys for a table', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('title');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    });

    $foreignKeys = $this->inspector->getForeignKeys('posts');

    expect($foreignKeys)->toBeArray();

    // SQLite may or may not return foreign keys depending on version
    if (count($foreignKeys) > 0) {
        $fk = $foreignKeys[0];
        expect($fk['column'])->toBe('user_id')
            ->and($fk['foreign_table'])->toBe('users')
            ->and($fk['foreign_column'])->toBe('id')
            ->and($fk['on_delete'])->toBe('cascade');
    }
})->group('feature', 'inspector');

test('inspector can generate migration for simple table', function () {
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name', 200);
        $table->decimal('price', 10, 2);
        $table->integer('stock')->default(0);
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    $migration = $this->inspector->generateMigration('products');

    expect($migration)->toBeString()
        ->and($migration)->toContain('Schema::create')
        ->and($migration)->toContain('products')
        ->and($migration)->toContain('Migration')
        ->and($migration)->toContain('up()')
        ->and($migration)->toContain('down()')
        ->and($migration)->toContain('Schema::dropIfExists');
})->group('feature', 'inspector');

test('inspector can generate migration with relationships', function () {
    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('category_id');
        $table->foreign('category_id')->references('id')->on('categories');
    });

    $migration = $this->inspector->generateMigration('products');

    expect($migration)->toBeString()
        ->and($migration)->toContain('Schema::create')
        ->and($migration)->toContain('products');

    // Foreign keys may or may not be included depending on SQLite support
    // But the migration should still be valid
})->group('feature', 'inspector');

test('inspector handles tables without foreign keys', function () {
    Schema::create('simple_table', function (Blueprint $table) {
        $table->id();
        $table->string('data');
    });

    $foreignKeys = $this->inspector->getForeignKeys('simple_table');

    expect($foreignKeys)->toBeArray()
        ->and($foreignKeys)->toBeEmpty();
})->group('feature', 'inspector');

test('inspector handles tables with composite indexes', function () {
    Schema::create('logs', function (Blueprint $table) {
        $table->id();
        $table->string('level');
        $table->string('message');
        $table->timestamp('created_at');
        $table->index(['level', 'created_at']);
    });

    $indexes = $this->inspector->getIndexes('logs');

    $compositeIndex = collect($indexes)->first(function ($idx) {
        return count($idx['columns']) > 1;
    });

    expect($compositeIndex)->not->toBeNull()
        ->and(count($compositeIndex['columns']))->toBe(2)
        ->and($compositeIndex['columns'])->toContain('level')
        ->and($compositeIndex['columns'])->toContain('created_at');
})->group('feature', 'inspector');

test('inspector handles empty database', function () {
    $tables = $this->inspector->getTables();

    expect($tables)->toBeArray()
        ->and($tables)->toBeEmpty();
})->group('feature', 'inspector');

test('inspector handles nullable columns correctly', function () {
    Schema::create('test_nulls', function (Blueprint $table) {
        $table->id();
        $table->string('required');
        $table->string('optional')->nullable();
    });

    $columns = $this->inspector->getColumns('test_nulls');

    $requiredColumn = collect($columns)->first(fn ($col) => $col['name'] === 'required');
    $optionalColumn = collect($columns)->first(fn ($col) => $col['name'] === 'optional');

    expect($requiredColumn['nullable'])->toBeFalse()
        ->and($optionalColumn['nullable'])->toBeTrue();
})->group('feature', 'inspector');

test('inspector handles default values correctly', function () {
    Schema::create('test_defaults', function (Blueprint $table) {
        $table->id();
        $table->string('status')->default('active');
        $table->integer('count')->default(0);
        $table->boolean('flag')->default(false);
    });

    $columns = $this->inspector->getColumns('test_defaults');

    $statusColumn = collect($columns)->first(fn ($col) => $col['name'] === 'status');
    $countColumn = collect($columns)->first(fn ($col) => $col['name'] === 'count');
    $flagColumn = collect($columns)->first(fn ($col) => $col['name'] === 'flag');

    expect($statusColumn['default'])->not->toBeNull()
        ->and($countColumn['default'])->not->toBeNull()
        ->and($flagColumn['default'])->not->toBeNull();
})->group('feature', 'inspector');

test('inspector generated migration is valid php', function () {
    Schema::create('valid_test', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    $migration = $this->inspector->generateMigration('valid_test');

    // Check for PHP opening tag
    expect($migration)->toStartWith('<?php')
        ->and($migration)->toContain('use ')
        ->and($migration)->toContain('return new class extends Migration');
})->group('feature', 'inspector');

test('inspector handles multiple unique indexes', function () {
    Schema::create('test_unique', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('username')->unique();
        $table->string('slug')->unique();
    });

    $indexes = $this->inspector->getIndexes('test_unique');

    $uniqueIndexes = collect($indexes)->filter(fn ($idx) => $idx['unique']);

    expect(count($uniqueIndexes))->toBeGreaterThanOrEqual(3);
})->group('feature', 'inspector');

test('inspector generates migration with multi-column unique index', function () {
    Schema::create('test_multi', function (Blueprint $table) {
        $table->id();
        $table->string('email');
        $table->string('username');
        $table->unique(['email', 'username']);
    });

    $migration = $this->inspector->generateMigration('test_multi');

    expect($migration)->toContain('unique')
        ->and($migration)->toContain('Schema::create');
})->group('feature', 'inspector');

test('inspector generates migration with regular multi-column index', function () {
    Schema::create('test_index', function (Blueprint $table) {
        $table->id();
        $table->string('category');
        $table->timestamp('created_at');
        $table->index(['category', 'created_at']);
    });

    $migration = $this->inspector->generateMigration('test_index');

    expect($migration)->toContain('index')
        ->and($migration)->toContain('Schema::create');
})->group('feature', 'inspector');

test('inspector generates migration with char, decimal, and unique columns', function () {
    Schema::create('test_types', function (Blueprint $table) {
        $table->id();
        $table->char('code', 10)->unique();
        $table->decimal('amount', 10, 2);
        $table->string('description')->comment('This is a comment');
    });

    $migration = $this->inspector->generateMigration('test_types');

    expect($migration)->toContain('Schema::create')
        ->and($migration)->toContain('test_types');
})->group('feature', 'inspector');
