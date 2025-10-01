<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\ColumnDefinition;

beforeEach(function () {
    $this->column = new ColumnDefinition;
});

test('nullable', function () {
    $result = $this->column->nullable();

    expect($result)->toBe($this->column);
    expect($this->column->nullable)->toBeTrue();
});

test('nullable with false', function () {
    $result = $this->column->nullable(false);

    expect($result)->toBe($this->column);
    expect($this->column->nullable)->toBeFalse();
});

test('default', function () {
    $result = $this->column->default('test_value');

    expect($result)->toBe($this->column);
    expect($this->column->default)->toBe('test_value');
});

test('default with different types', function () {
    // Test string
    $this->column->default('string_value');
    expect($this->column->default)->toBe('string_value');

    // Test integer
    $this->column->default(42);
    expect($this->column->default)->toBe(42);

    // Test boolean
    $this->column->default(true);
    expect($this->column->default)->toBeTrue();

    // Test null
    $this->column->default(null);
    expect($this->column->default)->toBeNull();

    // Test array
    $this->column->default(['key' => 'value']);
    expect($this->column->default)->toBe(['key' => 'value']);
});

test('unsigned', function () {
    $result = $this->column->unsigned();

    expect($result)->toBe($this->column);
    expect($this->column->unsigned)->toBeTrue();
});

test('unsigned with false', function () {
    $result = $this->column->unsigned(false);

    expect($result)->toBe($this->column);
    expect($this->column->unsigned)->toBeFalse();
});

test('autoIncrement', function () {
    $result = $this->column->autoIncrement();

    expect($result)->toBe($this->column);
    expect($this->column->autoIncrement)->toBeTrue();
});

test('autoIncrement with false', function () {
    $result = $this->column->autoIncrement(false);

    expect($result)->toBe($this->column);
    expect($this->column->autoIncrement)->toBeFalse();
});

test('primary', function () {
    $result = $this->column->primary();

    expect($result)->toBe($this->column);
    expect($this->column->primary)->toBeTrue();
});

test('primary with false', function () {
    $result = $this->column->primary(false);

    expect($result)->toBe($this->column);
    expect($this->column->primary)->toBeFalse();
});

test('unique', function () {
    $result = $this->column->unique();

    expect($result)->toBe($this->column);
    expect($this->column->unique)->toBeTrue();
});

test('unique with false', function () {
    $result = $this->column->unique(false);

    expect($result)->toBe($this->column);
    expect($this->column->unique)->toBeFalse();
});

test('index', function () {
    $result = $this->column->index();

    expect($result)->toBe($this->column);
    expect($this->column->index)->toBeTrue();
});

test('index with false', function () {
    $result = $this->column->index(false);

    expect($result)->toBe($this->column);
    expect($this->column->index)->toBeFalse();
});

test('fulltext', function () {
    $result = $this->column->fulltext();

    expect($result)->toBe($this->column);
    expect($this->column->fulltext)->toBeTrue();
});

test('fulltext with false', function () {
    $result = $this->column->fulltext(false);

    expect($result)->toBe($this->column);
    expect($this->column->fulltext)->toBeFalse();
});

test('spatialIndex', function () {
    $result = $this->column->spatialIndex();

    expect($result)->toBe($this->column);
    expect($this->column->spatialIndex)->toBeTrue();
});

test('spatialIndex with false', function () {
    $result = $this->column->spatialIndex(false);

    expect($result)->toBe($this->column);
    expect($this->column->spatialIndex)->toBeFalse();
});

test('comment', function () {
    $result = $this->column->comment('This is a test comment');

    expect($result)->toBe($this->column);
    expect($this->column->comment)->toBe('This is a test comment');
});

test('charset', function () {
    $result = $this->column->charset('utf8mb4');

    expect($result)->toBe($this->column);
    expect($this->column->charset)->toBe('utf8mb4');
});

test('collation', function () {
    $result = $this->column->collation('utf8mb4_unicode_ci');

    expect($result)->toBe($this->column);
    expect($this->column->collation)->toBe('utf8mb4_unicode_ci');
});

test('after', function () {
    $result = $this->column->after('name');

    expect($result)->toBe($this->column);
    expect($this->column->after)->toBe('name');
});

test('first', function () {
    $result = $this->column->first();

    expect($result)->toBe($this->column);
    expect($this->column->first)->toBeTrue();
});

test('change', function () {
    $result = $this->column->change();

    expect($result)->toBe($this->column);
    expect($this->column->change)->toBeTrue();
});

test('useCurrent', function () {
    $result = $this->column->useCurrent();

    expect($result)->toBe($this->column);
    expect($this->column->useCurrent)->toBeTrue();
});

test('useCurrentOnUpdate', function () {
    $result = $this->column->useCurrentOnUpdate();

    expect($result)->toBe($this->column);
    expect($this->column->useCurrentOnUpdate)->toBeTrue();
});

test('virtualAs', function () {
    $result = $this->column->virtualAs('price * quantity');

    expect($result)->toBe($this->column);
    expect($this->column->virtualAs)->toBe('price * quantity');
});

test('storedAs', function () {
    $result = $this->column->storedAs('price + tax');

    expect($result)->toBe($this->column);
    expect($this->column->storedAs)->toBe('price + tax');
});

test('generatedAs', function () {
    $result = $this->column->generatedAs('(id + 1000)');

    expect($result)->toBe($this->column);
    expect($this->column->generatedAs)->toBe('(id + 1000)');
});

test('always', function () {
    $result = $this->column->always();

    expect($result)->toBe($this->column);
    expect($this->column->always)->toBeTrue();
});

test('invisible', function () {
    $result = $this->column->invisible();

    expect($result)->toBe($this->column);
    expect($this->column->invisible)->toBeTrue();
});

test('from', function () {
    $result = $this->column->from(1000);

    expect($result)->toBe($this->column);
    expect($this->column->from)->toBe(1000);
});

test('startingValue', function () {
    $result = $this->column->startingValue(500);

    expect($result)->toBe($this->column);
    expect($this->column->from)->toBe(500);
});

test('constrained', function () {
    $result = $this->column->constrained();

    expect($result)->toBe($this->column);
    expect($this->column->constrained)->toBe([
        'table' => null,
        'column' => 'id',
        'indexName' => null,
    ]);
});

test('constrained with parameters', function () {
    $result = $this->column->constrained('users', 'user_id', 'custom_foreign_key');

    expect($result)->toBe($this->column);
    expect($this->column->constrained)->toBe([
        'table' => 'users',
        'column' => 'user_id',
        'indexName' => 'custom_foreign_key',
    ]);
});

test('cascadeOnDelete', function () {
    $result = $this->column->cascadeOnDelete();

    expect($result)->toBe($this->column);
    expect($this->column->onDelete)->toBe('cascade');
});

test('restrictOnDelete', function () {
    $result = $this->column->restrictOnDelete();

    expect($result)->toBe($this->column);
    expect($this->column->onDelete)->toBe('restrict');
});

test('cascadeOnUpdate', function () {
    $result = $this->column->cascadeOnUpdate();

    expect($result)->toBe($this->column);
    expect($this->column->onUpdate)->toBe('cascade');
});

test('restrictOnUpdate', function () {
    $result = $this->column->restrictOnUpdate();

    expect($result)->toBe($this->column);
    expect($this->column->onUpdate)->toBe('restrict');
});

test('persisted', function () {
    $result = $this->column->persisted();

    expect($result)->toBe($this->column);
    expect($this->column->persisted)->toBeTrue();
});

test('method chaining', function () {
    $result = $this->column
        ->nullable()
        ->default('test')
        ->unsigned()
        ->autoIncrement()
        ->primary()
        ->unique()
        ->index()
        ->comment('Test column')
        ->charset('utf8mb4')
        ->collation('utf8mb4_unicode_ci')
        ->after('id')
        ->first()
        ->change()
        ->useCurrent()
        ->useCurrentOnUpdate()
        ->virtualAs('price * quantity')
        ->storedAs('price + tax')
        ->generatedAs('(id + 1000)')
        ->always()
        ->invisible()
        ->from(1000)
        ->constrained('users', 'user_id', 'custom_fk')
        ->cascadeOnDelete()
        ->restrictOnUpdate()
        ->persisted();

    expect($result)->toBe($this->column);

    // Verify all properties are set correctly
    expect($this->column->nullable)->toBeTrue();
    expect($this->column->default)->toBe('test');
    expect($this->column->unsigned)->toBeTrue();
    expect($this->column->autoIncrement)->toBeTrue();
    expect($this->column->primary)->toBeTrue();
    expect($this->column->unique)->toBeTrue();
    expect($this->column->index)->toBeTrue();
    expect($this->column->comment)->toBe('Test column');
    expect($this->column->charset)->toBe('utf8mb4');
    expect($this->column->collation)->toBe('utf8mb4_unicode_ci');
    expect($this->column->after)->toBe('id');
    expect($this->column->first)->toBeTrue();
    expect($this->column->change)->toBeTrue();
    expect($this->column->useCurrent)->toBeTrue();
    expect($this->column->useCurrentOnUpdate)->toBeTrue();
    expect($this->column->virtualAs)->toBe('price * quantity');
    expect($this->column->storedAs)->toBe('price + tax');
    expect($this->column->generatedAs)->toBe('(id + 1000)');
    expect($this->column->always)->toBeTrue();
    expect($this->column->invisible)->toBeTrue();
    expect($this->column->from)->toBe(1000);
    expect($this->column->constrained)->toBe([
        'table' => 'users',
        'column' => 'user_id',
        'indexName' => 'custom_fk',
    ]);
    expect($this->column->onDelete)->toBe('cascade');
    expect($this->column->onUpdate)->toBe('restrict');
    expect($this->column->persisted)->toBeTrue();
});

test('database specific features', function () {
    // Test MySQL-specific features
    $this->column
        ->charset('utf8mb4')
        ->collation('utf8mb4_unicode_ci')
        ->virtualAs('price * quantity')
        ->storedAs('price + tax')
        ->invisible()
        ->after('id')
        ->first();

    expect($this->column->charset)->toBe('utf8mb4');
    expect($this->column->collation)->toBe('utf8mb4_unicode_ci');
    expect($this->column->virtualAs)->toBe('price * quantity');
    expect($this->column->storedAs)->toBe('price + tax');
    expect($this->column->invisible)->toBeTrue();
    expect($this->column->after)->toBe('id');
    expect($this->column->first)->toBeTrue();

    // Test PostgreSQL-specific features
    $this->column
        ->generatedAs('(id + 1000)')
        ->always();

    expect($this->column->generatedAs)->toBe('(id + 1000)');
    expect($this->column->always)->toBeTrue();

    // Test SQL Server-specific features
    $this->column->persisted();
    expect($this->column->persisted)->toBeTrue();
});

test('foreign key constraints', function () {
    // Test basic constraint
    $this->column->constrained();
    expect($this->column->constrained)->toBe([
        'table' => null,
        'column' => 'id',
        'indexName' => null,
    ]);

    // Test constraint with table
    $this->column->constrained('users');
    expect($this->column->constrained['table'])->toBe('users');

    // Test with cascade actions
    $this->column
        ->cascadeOnDelete()
        ->cascadeOnUpdate();

    expect($this->column->onDelete)->toBe('cascade');
    expect($this->column->onUpdate)->toBe('cascade');
});

test('inherited fluent behavior', function () {
    // Test that ColumnDefinition inherits Fluent behavior
    expect($this->column)->toBeInstanceOf(\Bob\Schema\Fluent::class);

    // Test magic property access
    $this->column->customProperty = 'test';
    expect($this->column->customProperty)->toBe('test');
});

test('overriding properties', function () {
    // Test that later method calls override earlier ones
    $this->column
        ->nullable()
        ->nullable(false)
        ->default('first')
        ->default('second')
        ->cascadeOnDelete()
        ->restrictOnDelete();

    expect($this->column->nullable)->toBeFalse();
    expect($this->column->default)->toBe('second');
    expect($this->column->onDelete)->toBe('restrict');
});
