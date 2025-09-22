<?php

use Bob\Database\Model;
use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Database\Relations\BelongsTo;
use Bob\Database\Relations\HasMany;
use Bob\Database\Relations\HasOne;
use Bob\Database\Relations\BelongsToMany;
use Mockery as m;

// Test model with various configurations
class TestModelForCoverage extends Model {
    protected string $table = 'test_models';
    protected string $primaryKey = 'id';
    protected $fillable = ['name', 'email', 'status', 'data'];
    protected $guarded = ['admin_only'];
    protected $hidden = ['password'];
    protected array $casts = [
        'status' => 'string',
        'count' => 'int',
        'price' => 'float',
        'active' => 'bool',
        'data' => 'array',
        'meta' => 'json',
        'created_at' => 'datetime',
    ];
    public bool $timestamps = true;

    // Relationship methods for testing
    public function parent()
    {
        return $this->belongsTo(TestModelForCoverage::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(TestModelForCoverage::class, 'parent_id');
    }

    public function profile()
    {
        return $this->hasOne(TestProfileModel::class, 'user_id');
    }
}

class TestProfileModel extends Model {
    protected string $table = 'profiles';
    public bool $timestamps = false;
}

class ModelWithoutTimestamps extends Model {
    protected string $table = 'no_timestamps';
    public bool $timestamps = false;
}

beforeEach(function () {
    $this->connection = m::mock(Connection::class);
    $this->builder = m::mock(Builder::class);

    Model::setConnection($this->connection);

    $this->connection->shouldReceive('table')->andReturn($this->builder);
    $this->connection->shouldReceive('getQueryGrammar')->andReturn(m::mock());
    $this->connection->shouldReceive('getPostProcessor')->andReturn(m::mock());

    // Common builder expectations
    $this->builder->shouldReceive('setModel')->andReturn($this->builder);
    $this->builder->shouldReceive('where')->andReturn($this->builder);
    $this->builder->shouldReceive('first')->andReturn(null);
    $this->builder->shouldReceive('getModel')->andReturn(null);
    $this->builder->shouldReceive('whereIn')->andReturn($this->builder);
    $this->builder->shouldReceive('orderBy')->andReturn($this->builder);
    $this->builder->shouldReceive('get')->andReturn([]);
});

afterEach(function () {
    m::close();
});

describe('Model Full Coverage Tests', function () {

    // Line 306: save() when no dirty attributes
    test('save returns true when no dirty attributes', function () {
        $model = new TestModelForCoverage();
        $model->exists = true;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Test');
        $model->syncOriginal(); // Make it not dirty - no changes

        // save() will call update() which checks for dirty attributes
        $result = $model->save();

        expect($result)->toBeTrue();
    });

    // Line 351: delete() when canDelete() returns false
    test('delete returns false when canDelete returns false', function () {
        $model = new class extends TestModelForCoverage {
            protected function canDelete(): bool {
                return false;
            }
        };
        $model->exists = true;

        $result = $model->delete();

        expect($result)->toBeFalse();
    });

    // Line 607: castAttribute when no cast defined
    test('castAttribute returns value unchanged when no cast defined', function () {
        $model = new TestModelForCoverage();

        // Use reflection to call protected method
        $method = new ReflectionMethod($model, 'castAttribute');
        $method->setAccessible(true);

        $result = $method->invoke($model, 'unknown_field', 'test_value');

        expect($result)->toBe('test_value');
    });

    // Line 633: castAttribute for string type
    test('castAttribute casts to string type', function () {
        $model = new TestModelForCoverage();

        $method = new ReflectionMethod($model, 'castAttribute');
        $method->setAccessible(true);

        $result = $method->invoke($model, 'status', 123);

        expect($result)->toBe('123');
    });

    // Line 862: getForeignKeyForBelongsTo generates correct key
    test('getForeignKeyForBelongsTo generates correct key', function () {
        $model = new TestModelForCoverage();

        // Use reflection to test the protected method directly
        $method = new ReflectionMethod($model, 'getForeignKeyForBelongsTo');
        $method->setAccessible(true);

        // Should generate based on relation name
        $foreignKey = $method->invoke($model, 'parent');
        expect($foreignKey)->toBe('parent_id');

        // When called with a specific name, should use it
        $foreignKey = $method->invoke($model, 'custom');
        expect($foreignKey)->toBe('custom_id');
    });

    // Line 854: getForeignKey generates correct key for hasMany
    test('getForeignKey generates correct key for hasMany', function () {
        $model = new TestModelForCoverage();

        // Public method, can call directly
        $foreignKey = $model->getForeignKey();

        // Should be model_name_id
        expect($foreignKey)->toBe('test_model_for_coverage_id');
    });

    // Line 951: getRelationValue returns loaded relations
    test('getRelationValue returns loaded relations', function () {
        $model = new TestModelForCoverage();
        $model->setAttribute('id', 1);

        // Set a loaded relation directly
        $relatedModel = new TestModelForCoverage();
        $relatedModel->setAttribute('id', 2);
        $model->setRelation('parent', $relatedModel);

        // Use reflection to call protected method
        $method = new ReflectionMethod($model, 'getRelationValue');
        $method->setAccessible(true);

        // Get relation value should return the loaded relation
        $result = $method->invoke($model, 'parent');

        expect($result)->toBe($relatedModel);
        expect($result->getAttribute('id'))->toBe(2);
    });

    // Lines 986-988: toArray with relations
    test('toArray includes loaded relations', function () {
        $model = new TestModelForCoverage();
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Test');

        // Set a loaded relation
        $relatedModel = new TestModelForCoverage();
        $relatedModel->setAttribute('id', 2);
        $relatedModel->setAttribute('name', 'Related');

        $model->setRelation('parent', $relatedModel);

        $array = $model->toArray();

        expect($array)->toHaveKey('parent');
        expect($array['parent'])->toHaveKey('name');
        expect($array['parent']['name'])->toBe('Related');
    });

    // Line 1024: __set magic method
    test('__set magic method sets attribute', function () {
        $model = new TestModelForCoverage();

        $model->name = 'John Doe';

        expect($model->getAttribute('name'))->toBe('John Doe');
    });

    // Line 1115: newInstance with exists = false
    test('newInstance creates model without exists flag', function () {
        $model = new TestModelForCoverage();

        $newModel = $model->newInstance(['name' => 'New Model'], false);

        expect($newModel->exists)->toBeFalse();
        expect($newModel->getAttribute('name'))->toBe('New Model');
    });

    // Line 1259: getForeignKeyForBelongsTo
    test('getForeignKeyForBelongsTo generates correct foreign key', function () {
        $model = new TestModelForCoverage();

        $method = new ReflectionMethod($model, 'getForeignKeyForBelongsTo');
        $method->setAccessible(true);

        $foreignKey = $method->invoke($model, 'parent');

        expect($foreignKey)->toBe('parent_id');
    });

    // Line 1393: hasRelationMethod checks if method exists
    test('hasRelationMethod returns true for existing relation methods', function () {
        $model = new TestModelForCoverage();

        $method = new ReflectionMethod($model, 'hasRelationMethod');
        $method->setAccessible(true);

        expect($method->invoke($model, 'parent'))->toBeTrue();
        expect($method->invoke($model, 'children'))->toBeTrue();
        expect($method->invoke($model, 'nonexistent'))->toBeFalse();
    });

    // Test various cast types
    test('castAttribute handles all cast types correctly', function () {
        $model = new TestModelForCoverage();

        $method = new ReflectionMethod($model, 'castAttribute');
        $method->setAccessible(true);

        // Test integer cast
        expect($method->invoke($model, 'count', '42'))->toBe(42);

        // Test float cast
        expect($method->invoke($model, 'price', '19.99'))->toBe(19.99);

        // Test boolean cast
        expect($method->invoke($model, 'active', 1))->toBeTrue();
        expect($method->invoke($model, 'active', 0))->toBeFalse();

        // Test array cast from JSON string
        $jsonData = '{"key":"value"}';
        $result = $method->invoke($model, 'data', $jsonData);
        expect($result)->toBeArray();
        expect($result['key'])->toBe('value');

        // Test array cast from array
        $arrayData = ['test' => 'data'];
        $result = $method->invoke($model, 'data', $arrayData);
        expect($result)->toBe($arrayData);

        // Test json cast (returns array like 'array' cast in this implementation)
        $result = $method->invoke($model, 'meta', $jsonData);
        expect($result)->toBeArray();
        expect($result['key'])->toBe('value');
    });

    // Test model without timestamps
    test('model without timestamps does not update timestamps', function () {
        $model = new ModelWithoutTimestamps();
        $model->setAttribute('name', 'Test');

        // This should not set created_at or updated_at
        $model->syncOriginal();

        expect($model->getAttribute('created_at'))->toBeNull();
        expect($model->getAttribute('updated_at'))->toBeNull();
    });

    // Test fillable and guarded
    test('fill respects fillable and guarded attributes', function () {
        $model = new TestModelForCoverage();

        $model->fill([
            'name' => 'John',
            'email' => 'john@example.com',
            'admin_only' => 'secret', // This is guarded
            'non_fillable' => 'ignored'
        ]);

        expect($model->getAttribute('name'))->toBe('John');
        expect($model->getAttribute('email'))->toBe('john@example.com');
        expect($model->getAttribute('admin_only'))->toBeNull(); // Guarded
        expect($model->getAttribute('non_fillable'))->toBeNull(); // Not fillable
    });

    // Test hidden attributes in toArray
    test('toArray respects hidden attributes', function () {
        $model = new TestModelForCoverage();
        $model->setAttribute('name', 'John');
        $model->setAttribute('password', 'secret123');

        $array = $model->toArray();

        expect($array)->toHaveKey('name');
        expect($array)->not->toHaveKey('password');
    });

    // Test hasRelationMethod
    test('hasRelationMethod detects existing relationship methods', function () {
        $model = new TestModelForCoverage();

        $method = new ReflectionMethod($model, 'hasRelationMethod');
        $method->setAccessible(true);

        // Test that it finds existing methods
        expect($method->invoke($model, 'parent'))->toBeTrue();
        expect($method->invoke($model, 'children'))->toBeTrue();
        expect($method->invoke($model, 'profile'))->toBeTrue();

        // Test that it returns false for non-existent methods
        expect($method->invoke($model, 'nonexistent'))->toBeFalse();
        expect($method->invoke($model, 'randomMethod'))->toBeFalse();
    });

    // Test dirty attributes
    test('getDirty returns only changed attributes', function () {
        $model = new TestModelForCoverage();

        // Set original attributes
        $model->setAttribute('name', 'Original');
        $model->syncOriginal();

        // Make changes
        $model->setAttribute('name', 'Changed');
        $model->setAttribute('email', 'new@example.com');

        $dirty = $model->getDirty();

        expect($dirty)->toHaveKey('name');
        expect($dirty)->toHaveKey('email');
        expect($dirty['name'])->toBe('Changed');
    });

    // Test insert and update methods
    test('insert handles auto-incrementing keys', function () {
        $model = new TestModelForCoverage();
        $model->setAttribute('name', 'Test');

        // Mock the static query() method using the connection's builder
        $this->builder->shouldReceive('insertGetId')
            ->once()
            ->with(m::type('array'))
            ->andReturn(123);

        $method = new ReflectionMethod($model, 'insert');
        $method->setAccessible(true);

        $result = $method->invoke($model);

        expect($result)->toBeTrue();
        expect($model->getAttribute('id'))->toBe(123);
    });

    test('prepareAttributesForUpdate adds updated_at timestamp', function () {
        $model = new TestModelForCoverage();
        $model->exists = true;

        // Set original attributes
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Original');
        $model->syncOriginal(); // This sets original = current attributes

        // Now change the name
        $model->setAttribute('name', 'Updated');

        $method = new ReflectionMethod($model, 'prepareAttributesForUpdate');
        $method->setAccessible(true);

        $dirty = $method->invoke($model);

        expect($dirty)->toHaveKey('name');
        expect($dirty['name'])->toBe('Updated');
        expect($dirty)->toHaveKey('updated_at');
        expect($dirty['updated_at'])->toBeString();
    });
});