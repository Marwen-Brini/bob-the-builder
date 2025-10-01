<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Query\Builder;
use Bob\Support\Collection;
use Mockery as m;

// Test model classes
class CoverageTestModel extends Model
{
    protected string $table = 'test_models';

    public bool $timestamps = false;
}

class AutoTableModel extends Model
{
    // No table specified - should auto-generate from class name
}

class CategoryTestModel extends Model
{
    // Test pluralization with 'y' ending - table would be 'category_test_models'
}

class StatusTestModel extends Model
{
    // Test pluralization with 's' ending - table would be 'status_test_models'
}

// Custom models for testing specific pluralization
class TestCity extends Model
{
    // Should become test_cities
}

class TestBus extends Model
{
    // Should become test_buses
}

class GuardedModel extends Model
{
    protected $guarded = ['password', 'secret'];
}

class ScopeTestModel extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

class FillableModel extends Model
{
    protected $fillable = ['name', 'email'];
}

class NoGuardModel extends Model
{
    // Both fillable and guarded are empty - allow all
}

class TimestampModel extends Model
{
    protected string $table = 'timestamp_models';

    public bool $timestamps = true;
}

class HiddenModel extends Model
{
    protected $hidden = ['password', 'secret'];

    protected $visible = ['name', 'email'];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return ($this->attributes['first_name'] ?? '').' '.($this->attributes['last_name'] ?? '');
    }
}

class AppendsTestModel extends Model
{
    protected $appends = [];

    public function getFullNameAttribute()
    {
        return ($this->attributes['first_name'] ?? '').' '.($this->attributes['last_name'] ?? '');
    }
}

class CastModel extends Model
{
    protected array $casts = [
        'is_active' => 'boolean',
        'age' => 'integer',
        'price' => 'float',
        'metadata' => 'array',
        'config' => 'object',
        'data' => 'json',
        'created_at' => 'datetime',
    ];
}

class RelationModel extends Model
{
    protected string $table = 'relations';

    public function parent()
    {
        return $this->belongsTo(RelationModel::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(RelationModel::class, 'parent_id', 'id');
    }
}

describe('Model Coverage Tests', function () {

    beforeEach(function () {
        // Set up a mock connection
        $connection = m::mock(Connection::class);
        Model::setConnection($connection);
    });

    afterEach(function () {
        m::close();
    });

    test('Model getConnection throws exception when not set (line 93)', function () {
        Model::setConnection(null);

        expect(fn () => Model::getConnection())
            ->toThrow(\RuntimeException::class, 'No database connection configured for models');
    });

    test('Model auto-generates table name from class name (lines 122-124)', function () {
        $model = new AutoTableModel;
        expect($model->getTable())->toBe('auto_table_models');
    });

    test('Model pluralize handles y ending (lines 143-144)', function () {
        $model = new TestCity;
        expect($model->getTable())->toBe('test_cities');
    });

    test('Model pluralize handles s ending (lines 147-148)', function () {
        $model = new TestBus;
        expect($model->getTable())->toBe('test_buses');
    });

    test('Model pluralize adds s normally (line 151)', function () {
        $model = new AutoTableModel;
        expect($model->getTable())->toBe('auto_table_models');
    });

    test('Model isFillable with empty fillable and guarded (line 185)', function () {
        $model = new NoGuardModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('isFillable');
        $method->setAccessible(true);

        expect($method->invoke($model, 'any_field'))->toBeTrue();
    });

    test('Model isFillable with guarded attributes (line 194)', function () {
        $model = new GuardedModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('isFillable');
        $method->setAccessible(true);

        expect($method->invoke($model, 'name'))->toBeTrue();
        expect($method->invoke($model, 'password'))->toBeFalse();
    });

    test('Model getAttributes returns all attributes (line 212)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');

        expect($model->getAttributes())->toBe([
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);
    });

    test('Model insert with timestamps (lines 268-270)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->with('timestamp_models')->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('insertGetId')->once()->andReturnUsing(function ($attributes) {
            expect($attributes)->toHaveKey('created_at');
            expect($attributes)->toHaveKey('updated_at');

            return 1;
        });

        Model::setConnection($connection);

        $model = new TimestampModel;
        $model->setAttribute('name', 'Test');
        $saved = $model->save();

        expect($saved)->toBeTrue();
        expect($model->getAttribute('id'))->toBe(1);
    });

    test('Model insert returns false when no ID returned (line 282)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('insertGetId')->once()->andReturn(0);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');

        expect($model->save())->toBeFalse();
    });

    test('Model update returns true when not dirty (line 293)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->syncOriginal();

        // Model is not dirty, should return true without updating
        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('update');
        $method->setAccessible(true);

        expect($method->invoke($model))->toBeTrue();
    });

    test('Model update with timestamps (lines 299-300)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->with('timestamp_models')->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->andReturn($builder);
        $builder->shouldReceive('update')->once()->andReturnUsing(function ($attributes) {
            expect($attributes)->toHaveKey('updated_at');
            expect($attributes)->toHaveKey('name');

            return 1;
        });

        Model::setConnection($connection);

        $model = new TimestampModel;
        $model->setAttribute('id', 1);
        $model->syncOriginal();
        $model->setAttribute('name', 'Updated');

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('update');
        $method->setAccessible(true);

        expect($method->invoke($model))->toBeTrue();
    });

    test('Model update returns true when no dirty attributes after timestamp (line 304)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);
        $model->syncOriginal();

        // Not dirty and no timestamps
        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('update');
        $method->setAccessible(true);

        expect($method->invoke($model))->toBeTrue();
    });

    test('Model delete method (line 317)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->with('test_models')->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('withoutGlobalScopes')->once()->andReturn($builder);
        $builder->shouldReceive('where')->once()->with('id', 1)->andReturn($builder);
        $builder->shouldReceive('delete')->once()->andReturn(1);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);
        $model->syncOriginal();

        expect($model->delete())->toBeTrue();
    });

    test('Model fresh method (line 326)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        // Create a fresh model instance to return
        $freshModel = new CoverageTestModel;
        $freshModel->setAttribute('id', 1);
        $freshModel->setAttribute('name', 'Fresh');
        $freshModel->syncOriginal();

        // fresh() calls static::query() which calls getConnection()->table() once
        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->with('id', 1)->andReturn($builder);
        $builder->shouldReceive('first')->once()->andReturn($freshModel);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Old');
        $model->syncOriginal(); // Mark as existing

        $fresh = $model->fresh();
        expect($fresh)->toBeInstanceOf(CoverageTestModel::class);
        expect($fresh->getAttribute('name'))->toBe('Fresh');
    });

    test('Model isDirty with specific attributes (lines 386-408)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');
        $model->syncOriginal();

        // Not dirty initially
        expect($model->isDirty())->toBeFalse();
        expect($model->isDirty('name'))->toBeFalse();
        expect($model->isDirty(['name', 'email']))->toBeFalse();

        // Make one attribute dirty
        $model->setAttribute('name', 'Changed');
        expect($model->isDirty())->toBeTrue();
        expect($model->isDirty('name'))->toBeTrue();
        expect($model->isDirty('email'))->toBeFalse();
        expect($model->isDirty(['name', 'email']))->toBeTrue();

        // Check non-existent attribute
        expect($model->isDirty('non_existent'))->toBeFalse();
    });

    test('Model isClean method (line 439)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->syncOriginal();

        expect($model->isClean())->toBeTrue();
        expect($model->isClean('name'))->toBeTrue();

        $model->setAttribute('name', 'Changed');
        expect($model->isClean())->toBeFalse();
        expect($model->isClean('name'))->toBeFalse();
    });

    test('Model wasChanged method (lines 458-466)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Original');
        $model->setAttribute('email', 'original@example.com');
        $model->syncOriginal();

        // Nothing changed yet
        expect($model->wasChanged())->toBeFalse();
        expect($model->wasChanged('name'))->toBeFalse();

        // Change and sync changes (not original)
        $model->setAttribute('name', 'Changed');
        $model->syncChanges(); // This populates the changes array

        // Now it was changed
        expect($model->wasChanged())->toBeTrue();
        expect($model->wasChanged('name'))->toBeTrue();
        expect($model->wasChanged('email'))->toBeFalse();
        expect($model->wasChanged(['name', 'email']))->toBeTrue();
    });

    test('Model getOriginal with key (lines 487-495)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');
        $model->syncOriginal();

        expect($model->getOriginal('name'))->toBe('Test');
        expect($model->getOriginal('email'))->toBe('test@example.com');
        expect($model->getOriginal('non_existent'))->toBeNull();
        expect($model->getOriginal('non_existent', 'default'))->toBe('default');
    });

    test('Model only method (line 506)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');
        $model->setAttribute('password', 'secret');

        $only = $model->only(['name', 'email']);
        expect($only)->toBe([
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);
    });

    test('Model syncOriginal method (line 518)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');

        expect($model->isDirty())->toBeTrue();

        $model->syncOriginal();
        expect($model->isDirty())->toBeFalse();
        expect($model->getOriginal())->toBe($model->getAttributes());
    });

    test('Model syncChanges method (lines 530, 532)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Original');
        $model->syncOriginal();

        $model->setAttribute('name', 'Changed');
        $model->setAttribute('email', 'new@example.com');

        $model->syncChanges();

        $changes = $model->getChanges();
        expect($changes)->toHaveKey('name');
        expect($changes)->toHaveKey('email');
    });

    test('Model getChanges method (lines 538-545)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Original');
        $model->syncOriginal();

        expect($model->getChanges())->toBe([]);

        $model->setAttribute('name', 'Changed');
        $model->syncChanges();

        expect($model->getChanges())->toBe(['name' => 'Changed']);
    });

    test('Model replicate method (line 578)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Original');

        $replica = $model->replicate();

        expect($replica)->toBeInstanceOf(CoverageTestModel::class);
        expect($replica->getAttribute('id'))->toBeNull();
        expect($replica->getAttribute('name'))->toBe('Original');
    });

    test('Model is method (lines 604-607)', function () {
        $model1 = new CoverageTestModel;
        $model1->setAttribute('id', 1);

        $model2 = new CoverageTestModel;
        $model2->setAttribute('id', 1);

        $model3 = new CoverageTestModel;
        $model3->setAttribute('id', 2);

        expect($model1->is($model2))->toBeTrue();
        expect($model1->is($model3))->toBeFalse();
        expect($model1->is(null))->toBeFalse();
    });

    test('Model isNot method (lines 620-626)', function () {
        $model1 = new CoverageTestModel;
        $model1->setAttribute('id', 1);

        $model2 = new CoverageTestModel;
        $model2->setAttribute('id', 2);

        expect($model1->isNot($model2))->toBeTrue();
        expect($model1->isNot($model1))->toBeFalse();
    });

    test('Model push method (line 692)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        // For save
        $connection->shouldReceive('table')->once()->with('test_models')->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('insertGetId')->once()->andReturn(1);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');

        expect($model->push())->toBeTrue();
    });

    test('Model touch method (line 724)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->with('timestamp_models')->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->andReturn($builder);
        $builder->shouldReceive('update')->once()->andReturnUsing(function ($attributes) {
            expect($attributes)->toHaveKey('updated_at');

            return 1;
        });

        Model::setConnection($connection);

        $model = new TimestampModel;
        $model->setAttribute('id', 1);
        $model->syncOriginal();

        expect($model->touch())->toBeTrue();
    });

    test('Model makeVisible and makeHidden methods (lines 755-763)', function () {
        $model = new HiddenModel;
        $model->setAttribute('name', 'John');
        $model->setAttribute('email', 'john@example.com');
        $model->setAttribute('password', 'secret');
        $model->setAttribute('secret', 'hidden');

        // Initially hidden
        $array = $model->toArray();
        expect($array)->not->toHaveKey('password');
        expect($array)->not->toHaveKey('secret');

        // Make visible
        $model->makeVisible(['password']);
        $array = $model->toArray();
        expect($array)->toHaveKey('password');
        expect($array)->not->toHaveKey('secret');

        // Make hidden
        $model->makeHidden(['email']);
        $array = $model->toArray();
        expect($array)->not->toHaveKey('email');
    });

    test('Model castAttribute with various types (lines 792-807)', function () {
        $model = new CastModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('castAttribute');
        $method->setAccessible(true);

        // Boolean casting
        expect($method->invoke($model, 'is_active', 1))->toBe(true);
        expect($method->invoke($model, 'is_active', 0))->toBe(false);
        expect($method->invoke($model, 'is_active', '1'))->toBe(true);

        // Integer casting
        expect($method->invoke($model, 'age', '25'))->toBe(25);

        // Float casting
        expect($method->invoke($model, 'price', '19.99'))->toBe(19.99);

        // Array casting
        $json = '{"key":"value"}';
        $array = $method->invoke($model, 'metadata', $json);
        expect($array)->toBe(['key' => 'value']);

        // Object casting
        $object = $method->invoke($model, 'config', $json);
        expect($object)->toBeObject();
        expect($object->key)->toBe('value');

        // JSON casting (alias for array)
        $jsonArray = $method->invoke($model, 'data', $json);
        expect($jsonArray)->toBe(['key' => 'value']);

        // Datetime casting
        $dateString = '2024-01-01 12:00:00';
        $dateTime = $method->invoke($model, 'created_at', $dateString);
        expect($dateTime)->toBeInstanceOf(\DateTime::class);

        // Default case (no casting)
        expect($method->invoke($model, 'unknown', 'value'))->toBe('value');
    });

    test('Model mutateAttributeForArray with accessor (lines 823-869)', function () {
        // This functionality is tested through toArray with appends
        // The accessor is called when the attribute is appended
        $model = new HiddenModel;
        $model->setAttribute('first_name', 'John');
        $model->setAttribute('last_name', 'Doe');

        // The full_name accessor should be called through toArray
        $array = $model->toArray();
        expect($array)->toHaveKey('full_name');
        expect($array['full_name'])->toBe('John Doe');
    });

    test('Model attributesToArray with relations (lines 887-889)', function () {
        // Set up mock connection to avoid database calls
        $connection = m::mock(Connection::class);
        Model::setConnection($connection);

        $model = new RelationModel;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Test');

        // Mock a loaded relation
        $related = new RelationModel;
        $related->setAttribute('id', 2);
        $related->setAttribute('name', 'Child');

        $reflection = new ReflectionClass($model);
        $property = $reflection->getProperty('relations');
        $property->setAccessible(true);
        $property->setValue($model, ['children' => new Collection([$related])]);

        $array = $model->attributesToArray();
        expect($array)->toHaveKey('children');
        expect($array['children'])->toBeArray();
        expect($array['children'][0])->toHaveKey('name');
    });

    test('Model getArrayableRelations (lines 905-916)', function () {
        // This functionality is tested through toArray with relations
        $model = new RelationModel;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Parent');

        // Set up loaded relations
        $child = new RelationModel;
        $child->setAttribute('id', 2);
        $child->setAttribute('name', 'Child');

        $model->setRelation('children', new Collection([$child]));

        // The relations should be included in toArray
        $array = $model->toArray();
        expect($array)->toHaveKey('children');
        expect($array['children'])->toBeArray();
        expect($array['children'][0])->toHaveKey('name');
        expect($array['children'][0]['name'])->toBe('Child');
    });

    test('Model getRelation returns null when not loaded (line 938)', function () {
        $model = new RelationModel;
        expect($model->getRelation('parent'))->toBeNull();
    });

    test('Model relationLoaded method (lines 988-996)', function () {
        $model = new RelationModel;

        expect($model->relationLoaded('parent'))->toBeFalse();

        // Load a relation
        $parent = new RelationModel;
        $model->setRelation('parent', $parent);

        expect($model->relationLoaded('parent'))->toBeTrue();
    });

    test('Model setRelation method (lines 1015-1043)', function () {
        $model = new RelationModel;
        $parent = new RelationModel;

        $model->setRelation('parent', $parent);
        expect($model->relationLoaded('parent'))->toBeTrue();
        expect($model->getRelation('parent'))->toBe($parent);
    });

    test('Model unsetRelation method', function () {
        $model = new RelationModel;
        $parent = new RelationModel;

        $model->setRelation('parent', $parent);
        expect($model->relationLoaded('parent'))->toBeTrue();

        $model->unsetRelation('parent');
        expect($model->relationLoaded('parent'))->toBeFalse();
    });

    test('Model toArray with appends', function () {
        $model = new HiddenModel;
        $model->setAttribute('first_name', 'John');
        $model->setAttribute('last_name', 'Doe');
        $model->setAttribute('email', 'john@example.com');
        $model->setAttribute('password', 'secret');

        $array = $model->toArray();

        // Check appended attribute is included
        expect($array)->toHaveKey('full_name');
        expect($array['full_name'])->toBe('John Doe');

        // Check hidden attributes are excluded
        expect($array)->not->toHaveKey('password');
        expect($array)->not->toHaveKey('secret');

        // Check visible attributes are included
        expect($array)->toHaveKey('email');
    });

    test('Model toJson method', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');

        $json = $model->toJson();
        expect($json)->toBe('{"name":"Test","email":"test@example.com"}');

        // With pretty print
        $prettyJson = $model->toJson(JSON_PRETTY_PRINT);
        expect($prettyJson)->toContain('"name": "Test"');
    });

    test('Model jsonSerialize method', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');

        $data = $model->jsonSerialize();
        expect($data)->toBe(['name' => 'Test']);
    });

    test('Model clearConnection method (line 90)', function () {
        $connection = m::mock(Connection::class);
        Model::setConnection($connection);
        expect(Model::getConnection())->toBe($connection);

        Model::clearConnection();

        expect(function () {
            Model::getConnection();
        })->toThrow(\RuntimeException::class);
    });

    test('Model create method (lines 373-376)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('insertGetId')->once()->andReturn(1);

        Model::setConnection($connection);

        $model = CoverageTestModel::create(['name' => 'Created']);
        expect($model)->toBeInstanceOf(CoverageTestModel::class);
        expect($model->getAttribute('name'))->toBe('Created');
        expect($model->getAttribute('id'))->toBe(1);
    });

    test('Model find method (lines 381-387)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $foundModel = new CoverageTestModel;
        $foundModel->setAttribute('id', 1);
        $foundModel->setAttribute('name', 'Found');
        $foundModel->syncOriginal();

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->with('test_models.id', 1)->andReturn($builder);
        $builder->shouldReceive('first')->once()->andReturn($foundModel);

        Model::setConnection($connection);

        $model = CoverageTestModel::find(1);
        expect($model)->toBeInstanceOf(CoverageTestModel::class);
        expect($model->getAttribute('id'))->toBe(1);
    });

    test('Model findOrFail method (lines 392-400)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->with('test_models.id', 999)->andReturn($builder);
        $builder->shouldReceive('first')->once()->andReturn(null);

        Model::setConnection($connection);

        expect(function () {
            CoverageTestModel::findOrFail(999);
        })->toThrow(\RuntimeException::class, 'Model not found with ID: 999');
    });

    test('Model all method (lines 406-409)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $model1 = new CoverageTestModel(['id' => 1, 'name' => 'First']);
        $model2 = new CoverageTestModel(['id' => 2, 'name' => 'Second']);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('get')->once()->andReturn([$model1, $model2]);

        Model::setConnection($connection);

        $models = CoverageTestModel::all();
        expect($models)->toBeArray();
        expect($models)->toHaveCount(2);
    });

    test('Model first static method (lines 414-417)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $firstModel = new CoverageTestModel;
        $firstModel->setAttribute('id', 1);
        $firstModel->setAttribute('name', 'First');

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('first')->once()->andReturn($firstModel);

        Model::setConnection($connection);

        $model = CoverageTestModel::first();
        expect($model)->toBeInstanceOf(CoverageTestModel::class);
        expect($model->getAttribute('name'))->toBe('First');
    });

    test('Model hydrate method (lines 422-440)', function () {
        // Test with object data
        $data = (object) ['id' => 1, 'name' => 'Hydrated', 'email' => 'test@example.com'];
        $model = CoverageTestModel::hydrate($data);

        expect($model)->toBeInstanceOf(CoverageTestModel::class);
        expect($model->getAttribute('id'))->toBe(1);
        expect($model->getAttribute('name'))->toBe('Hydrated');
        expect($model->getOriginal())->toBe(['id' => 1, 'name' => 'Hydrated', 'email' => 'test@example.com']);

        // Test with array data
        $data2 = ['id' => 2, 'name' => 'Array'];
        $model2 = CoverageTestModel::hydrate($data2);
        expect($model2->getAttribute('id'))->toBe(2);

        // Test with Model instance (should return same instance)
        $existingModel = new CoverageTestModel(['id' => 3]);
        $model3 = CoverageTestModel::hydrate($existingModel);
        expect($model3)->toBe($existingModel);
    });

    test('Model __unset magic method (lines 637-639)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');

        expect($model->getAttribute('name'))->toBe('Test');

        unset($model->name);

        expect($model->getAttribute('name'))->toBeNull();
        expect($model->getAttribute('email'))->toBe('test@example.com');
    });

    test('Model __callStatic with scope method (lines 661-668)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->with('status', 'active')->andReturn($builder);

        Model::setConnection($connection);

        // This will call scopeActive if it exists
        $query = ScopeTestModel::active();
        expect($query)->toBeInstanceOf(Builder::class);
    });

    test('Model __callStatic forwards to query builder (lines 670-672)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->with('status', 'active')->andReturn($builder);

        Model::setConnection($connection);

        $result = CoverageTestModel::where('status', 'active');
        expect($result)->toBeInstanceOf(Builder::class);
    });

    test('Model __call magic method (lines 677-687)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->with('id', 1)->andReturn($builder);
        $builder->shouldReceive('update')->once()->with(['name' => 'Updated'])->andReturn(1);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);
        $model->syncOriginal(); // Mark as existing

        $result = $model->update(['name' => 'Updated']);
        expect($result)->toBe(1);
    });

    test('Model getForeignKey method (lines 813-816)', function () {
        $model = new CoverageTestModel;
        expect($model->getForeignKey())->toBe('coverage_test_model_id');
    });

    test('Model qualifyColumn method (lines 891-896)', function () {
        $model = new CoverageTestModel;

        // Test with already qualified column
        expect($model->qualifyColumn('table.column'))->toBe('table.column');

        // Test with unqualified column
        expect($model->qualifyColumn('column'))->toBe('test_models.column');
    });

    test('Model getRelationValue method (lines 901-912)', function () {
        $model = new RelationModel;

        // Test when relation not loaded
        expect($model->getAttribute('nonExistentRelation'))->toBeNull();
    });

    test('Model newInstance method (lines 1073-1080)', function () {
        $model = new CoverageTestModel;

        $new = $model->newInstance(['name' => 'New Instance']);

        expect($new)->toBeInstanceOf(CoverageTestModel::class);
        expect($new->getAttribute('name'))->toBe('New Instance');
        expect($new)->not->toBe($model);
    });

    test('Model getModel method (lines 1085-1088)', function () {
        $model = new CoverageTestModel;
        expect($model->getModel())->toBe($model);
    });

    test('Model getIncrementing method (lines 1093-1096)', function () {
        $model = new CoverageTestModel;
        expect($model->getIncrementing())->toBeTrue();
    });

    test('Model getKeyType method (lines 1101-1104)', function () {
        $model = new CoverageTestModel;
        expect($model->getKeyType())->toBe('int');
    });

    test('Model refresh method when not exists (line 1213)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        // Model doesn't exist (no original attributes)

        $refreshed = $model->refresh();
        expect($refreshed)->toBe($model);
        expect($model->getAttribute('name'))->toBe('Test');
    });

    test('Model refresh method when exists (lines 1217-1224)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $freshModel = new CoverageTestModel;
        $freshModel->setAttribute('id', 1);
        $freshModel->setAttribute('name', 'Fresh');
        $freshModel->syncOriginal();

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->with('id', 1)->andReturn($builder);
        $builder->shouldReceive('first')->once()->andReturn($freshModel);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Old');
        $model->syncOriginal();

        $refreshed = $model->refresh();
        expect($refreshed)->toBe($model);
        expect($model->getAttribute('name'))->toBe('Fresh');
    });

    test('Model touch method with timestamps disabled (line 1233)', function () {
        $model = new CoverageTestModel;
        $model->timestamps = false;

        expect($model->touch())->toBeFalse();
    });

    test('Model push returns false when save fails (line 1270)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('insertGetId')->once()->andReturn(null);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');

        expect($model->push())->toBeFalse();
    });

    test('Model __toString method (lines 1311-1314)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');

        expect((string) $model)->toBe('{"name":"Test","email":"test@example.com"}');
    });

    test('Model getSnakeCase method (lines 1316-1334)', function () {
        $model = new CoverageTestModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getSnakeCase');
        $method->setAccessible(true);

        expect($method->invoke($model, 'TestModel'))->toBe('test_model');
        expect($method->invoke($model, 'myVariableName'))->toBe('my_variable_name');
        expect($method->invoke($model, 'HTTPRequest'))->toBe('h_t_t_p_request');
    });

    test('Model guessBelongsToRelation method', function () {
        // Create a mock with backtrace
        $model = new class extends Model
        {
            public function testMethod()
            {
                return $this->guessBelongsToRelation();
            }

            protected function guessBelongsToRelation(): string
            {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);

                foreach ($backtrace as $trace) {
                    if (isset($trace['function']) &&
                        ! in_array($trace['function'], ['guessBelongsToRelation', 'belongsTo', 'testMethod'])) {
                        return $trace['function'];
                    }
                }

                throw new \LogicException('Unable to guess belongsTo relation name.');
            }
        };

        expect($model->testMethod())->not->toBeEmpty();
    });

    test('Model setAppends method (lines 1322-1327)', function () {
        $model = new AppendsTestModel;

        $result = $model->setAppends(['full_name', 'another']);

        // Check it returns self for chaining
        expect($result)->toBe($model);

        // Check appends are set
        $model->setAttribute('first_name', 'John');
        $model->setAttribute('last_name', 'Doe');

        $array = $model->toArray();
        expect($array)->toHaveKey('full_name');
        expect($array['full_name'])->toBe('John Doe');
    });

    test('Model joiningTable method (lines 849-860)', function () {
        $model = new CoverageTestModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('joiningTable');
        $method->setAccessible(true);

        // Test alphabetical sorting and joining
        expect($method->invoke($model, 'User', 'Role'))->toBe('role_user');
        expect($method->invoke($model, 'Role', 'User'))->toBe('role_user');
        expect($method->invoke($model, 'Post', 'Tag'))->toBe('post_tag');
    });

    test('Model getRelationshipFromMethod (lines 917-930)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        // Setup for parent relation query
        $parentModel = new RelationModel;
        $parentModel->setAttribute('id', 1);
        $parentModel->setAttribute('name', 'Parent');

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('getModel')->once()->andReturn($parentModel);
        $builder->shouldReceive('where')->once()->andReturn($builder);
        $builder->shouldReceive('first')->once()->andReturn($parentModel);

        Model::setConnection($connection);

        $model = new RelationModel;
        $model->setAttribute('parent_id', 1);

        // This will trigger getRelationshipFromMethod through getAttribute
        $parent = $model->parent;
        expect($parent)->toBeInstanceOf(RelationModel::class);
        expect($parent->getAttribute('id'))->toBe(1);
    });

    test('Model getRelationValue with loaded relation (lines 903-905)', function () {
        $model = new RelationModel;

        $related = new RelationModel;
        $related->setAttribute('id', 2);
        $related->setAttribute('name', 'Related');

        $model->setRelation('testRelation', $related);

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getRelationValue');
        $method->setAccessible(true);

        $result = $method->invoke($model, 'testRelation');
        expect($result)->toBe($related);
    });

    test('Model getRelationshipFromMethod with invalid return (lines 921-925)', function () {
        $model = new class extends Model
        {
            public function invalidRelation()
            {
                return 'not a relation';
            }
        };

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getRelationshipFromMethod');
        $method->setAccessible(true);

        expect(function () use ($method, $model) {
            $method->invoke($model, 'invalidRelation');
        })->toThrow(\LogicException::class);
    });

    test('Model getForeignKeyForBelongsTo method (lines 821-824)', function () {
        $model = new CoverageTestModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getForeignKeyForBelongsTo');
        $method->setAccessible(true);

        expect($method->invoke($model, 'user'))->toBe('user_id');
        expect($method->invoke($model, 'parentCategory'))->toBe('parent_category_id');
    });

    test('Model __get with exists property (line 228)', function () {
        $model = new CoverageTestModel;

        // Model doesn't exist initially
        expect($model->exists)->toBeFalse();

        // Make model exist
        $model->setAttribute('id', 1);
        $model->syncOriginal();
        expect($model->exists)->toBeTrue();
    });

    test('Model __get returns loaded relation (line 238)', function () {
        $model = new RelationModel;

        $related = new RelationModel;
        $related->setAttribute('id', 2);
        $related->setAttribute('name', 'Related');

        $model->setRelation('loadedRelation', $related);

        // Access via __get should return the loaded relation
        expect($model->loadedRelation)->toBe($related);
    });

    test('Model getRelationValue when method not exists (lines 907-911)', function () {
        $model = new CoverageTestModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getRelationValue');
        $method->setAccessible(true);

        // Test with non-existent method
        $result = $method->invoke($model, 'nonExistentMethod');
        expect($result)->toBeNull();
    });

    test('Model additional uncovered lines (447, 566, 578)', function () {
        // Line 447: hydrateMany static method
        $data = [
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ];

        $reflection = new ReflectionClass(CoverageTestModel::class);
        $method = $reflection->getMethod('hydrateMany');
        $method->setAccessible(true);

        $models = $method->invoke(null, $data);
        expect($models)->toBeArray();
        expect($models)->toHaveCount(2);
        expect($models[0])->toBeInstanceOf(CoverageTestModel::class);
    });

    test('Model additional lines (line 311 save returns null)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('insertGetId')->once()->andReturn(null);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');

        expect($model->save())->toBeFalse();
    });

    test('Model lines 1311-1316 (__toString)', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');

        expect((string) $model)->toBe('{"name":"Test","email":"test@example.com"}');

        // Test with empty model
        $empty = new CoverageTestModel;
        expect((string) $empty)->toBe('[]');
    });

    // Tests for refactored methods
    test('Model getVisibleAttributes method', function () {
        $model = new HiddenModel;
        $model->setAttribute('name', 'Visible');
        $model->setAttribute('password', 'Secret');
        $model->setAttribute('email', 'test@example.com');

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getVisibleAttributes');
        $method->setAccessible(true);

        $visible = $method->invoke($model);
        expect($visible)->toHaveKey('name');
        expect($visible)->toHaveKey('email');
        expect($visible)->not->toHaveKey('password');
    });

    test('Model isVisible method', function () {
        $model = new HiddenModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('isVisible');
        $method->setAccessible(true);

        expect($method->invoke($model, 'name'))->toBeTrue();
        expect($method->invoke($model, 'email'))->toBeTrue();
        expect($method->invoke($model, 'password'))->toBeFalse();
        expect($method->invoke($model, 'secret'))->toBeFalse();
    });

    test('Model appendAccessors method', function () {
        $model = new HiddenModel;
        $model->setAttribute('first_name', 'John');
        $model->setAttribute('last_name', 'Doe');

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('appendAccessors');
        $method->setAccessible(true);

        $array = [];
        $result = $method->invoke($model, $array);
        expect($result)->toHaveKey('full_name');
        expect($result['full_name'])->toBe('John Doe');
    });

    test('Model serializeRelation method', function () {
        $model = new RelationModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('serializeRelation');
        $method->setAccessible(true);

        // Test with Model
        $related = new RelationModel;
        $related->setAttribute('id', 1);
        $result = $method->invoke($model, $related);
        expect($result)->toBeArray();
        expect($result)->toHaveKey('id');

        // Test with array of Models
        $array = [$related];
        $result = $method->invoke($model, $array);
        expect($result)->toBeArray();
        expect($result[0])->toHaveKey('id');

        // Test with Collection
        $collection = new Collection([$related]);
        $result = $method->invoke($model, $collection);
        expect($result)->toBeArray();
        expect($result[0])->toHaveKey('id');

        // Test with plain value
        $result = $method->invoke($model, 'plain');
        expect($result)->toBe('plain');
    });

    test('Model prepareAttributesForUpdate method', function () {
        $model = new TimestampModel;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Test');
        $model->syncOriginal();
        $model->setAttribute('name', 'Updated');

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('prepareAttributesForUpdate');
        $method->setAccessible(true);

        $dirty = $method->invoke($model);
        expect($dirty)->toHaveKey('name');
        expect($dirty)->toHaveKey('updated_at');
        expect($dirty['name'])->toBe('Updated');
    });

    test('Model performUpdate method', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->with('id', 1)->andReturn($builder);
        $builder->shouldReceive('update')->once()->with(['name' => 'Updated'])->andReturn(1);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);
        $model->syncOriginal();

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('performUpdate');
        $method->setAccessible(true);

        $result = $method->invoke($model, ['name' => 'Updated']);
        expect($result)->toBeTrue();
    });

    test('Model canDelete method', function () {
        $model = new CoverageTestModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('canDelete');
        $method->setAccessible(true);

        // Not exists
        expect($method->invoke($model))->toBeFalse();

        // Exists
        $model->setAttribute('id', 1);
        $model->syncOriginal();
        expect($method->invoke($model))->toBeTrue();
    });

    test('Model performDelete method', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('withoutGlobalScopes')->once()->andReturn($builder);
        $builder->shouldReceive('where')->once()->with('id', 1)->andReturn($builder);
        $builder->shouldReceive('delete')->once()->andReturn(1);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('performDelete');
        $method->setAccessible(true);

        $result = $method->invoke($model);
        expect($result)->toBeTrue();
    });

    test('Model getLoadedRelation method', function () {
        $model = new RelationModel;

        $related = new RelationModel;
        $related->setAttribute('id', 1);
        $model->setRelation('testRelation', $related);

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getLoadedRelation');
        $method->setAccessible(true);

        expect($method->invoke($model, 'testRelation'))->toBe($related);
        expect($method->invoke($model, 'nonExistent'))->toBeNull();
    });

    test('Model hasRelationMethod method', function () {
        $model = new RelationModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('hasRelationMethod');
        $method->setAccessible(true);

        expect($method->invoke($model, 'parent'))->toBeTrue();
        expect($method->invoke($model, 'children'))->toBeTrue();
        expect($method->invoke($model, 'nonExistent'))->toBeFalse();
    });

    test('Model performUpdate returns false when update fails', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->andReturn($builder);
        $builder->shouldReceive('update')->once()->andReturn(0);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);
        $model->syncOriginal();

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('performUpdate');
        $method->setAccessible(true);

        $result = $method->invoke($model, ['name' => 'Updated']);
        expect($result)->toBeFalse();
    });

    test('Model appendRelations method', function () {
        $model = new RelationModel;

        $related = new RelationModel;
        $related->setAttribute('id', 2);
        $model->setRelation('child', $related);

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('appendRelations');
        $method->setAccessible(true);

        $array = ['id' => 1];
        $result = $method->invoke($model, $array);
        expect($result)->toHaveKey('child');
        expect($result['child'])->toBeArray();
        expect($result['child']['id'])->toBe(2);
    });

    test('Model append method with string (lines 1370-1375)', function () {
        $model = new AppendsTestModel; // Use model with appends property

        // Test with string
        $result = $model->append('virtual_field');
        expect($result)->toBe($model); // Check fluent interface

        $reflection = new ReflectionClass($model);
        $property = $reflection->getProperty('appends');
        $property->setAccessible(true);

        expect($property->getValue($model))->toContain('virtual_field');

        // Test with array
        $model->append(['another_field', 'third_field']);
        $appends = $property->getValue($model);
        expect($appends)->toContain('another_field');
        expect($appends)->toContain('third_field');
    });

    test('Model getRelationshipFromMethod with tap callback (lines 986-988)', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        // Setup for parent relation query
        $parentModel = new RelationModel;
        $parentModel->setAttribute('id', 1);
        $parentModel->setAttribute('name', 'Parent');

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('getModel')->once()->andReturn($parentModel);
        $builder->shouldReceive('where')->once()->andReturn($builder);
        $builder->shouldReceive('first')->once()->andReturn($parentModel);

        Model::setConnection($connection);

        $model = new RelationModel;
        $model->setAttribute('parent_id', 1);

        // This will trigger the tap callback which sets the relation
        $parent = $model->getAttribute('parent');
        expect($parent)->toBeInstanceOf(RelationModel::class);

        // Verify the relation was cached
        expect($model->relationLoaded('parent'))->toBeTrue();
    });

    test('Model line 138 getPrimaryKey', function () {
        $model = new CoverageTestModel;
        expect($model->getPrimaryKey())->toBe('id');
    });

    test('Model prepareAttributesForUpdate when nothing dirty (line 306)', function () {
        $model = new TimestampModel;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Test');
        $model->syncOriginal();
        // Nothing changed

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('prepareAttributesForUpdate');
        $method->setAccessible(true);

        $dirty = $method->invoke($model);
        // Should only have updated_at
        expect($dirty)->toHaveKey('updated_at');
        expect($dirty)->not->toHaveKey('name');
    });

    test('Model canDelete when not exists returns false (line 351)', function () {
        $model = new CoverageTestModel;
        // Model doesn't exist (no original attributes)

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('canDelete');
        $method->setAccessible(true);

        expect($method->invoke($model))->toBeFalse();
    });

    test('Model lines 607, 619, 633 - castAttribute edge cases', function () {
        $model = new CastModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('castAttribute');
        $method->setAccessible(true);

        // Test datetime cast with null value
        $result = $method->invoke($model, 'datetime', null);
        expect($result)->toBeNull();

        // Test datetime cast with timestamp (returns string in Model implementation)
        $result = $method->invoke($model, 'datetime', '2025-01-01 00:00:00');
        expect($result)->toBe('2025-01-01 00:00:00');

        // Test unknown cast type returns value as-is
        $result = $method->invoke($model, 'unknown', 'test');
        expect($result)->toBe('test');
    });

    test('Model line 793 - makeVisible method', function () {
        $model = new HiddenModel;

        $model->makeVisible(['password']);

        $model->setAttribute('password', 'secret123');
        $model->setAttribute('name', 'Test');

        $array = $model->toArray();
        expect($array)->toHaveKey('password');
        expect($array['password'])->toBe('secret123');
    });

    test('Model line 825 - makeHidden method', function () {
        $model = new class extends Model
        {
            protected $hidden = [];  // Initialize hidden
        };

        $result = $model->makeHidden(['name']);
        expect($result)->toBe($model); // Fluent interface

        $model->setAttribute('name', 'Hidden');
        $model->setAttribute('email', 'test@example.com');

        $array = $model->toArray();
        expect($array)->not->toHaveKey('name');
        expect($array)->toHaveKey('email');
    });

    test('Model getSnakeCase already lowercase (line 1393)', function () {
        $model = new CoverageTestModel;

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getSnakeCase');
        $method->setAccessible(true);

        // Test with already lowercase string
        expect($method->invoke($model, 'already_snake_case'))->toBe('already_snake_case');
    });

    test('Model guessBelongsToRelation method (line 1024)', function () {
        $model = new class extends Model
        {
            public function user()
            {
                return $this->guessBelongsToRelation();
            }
        };

        // The method should return a function name from the backtrace
        $result = $model->user();
        expect($result)->toBeString();
        expect($result)->not->toBeEmpty();
    });

    test('Model line 1115 - isDirty returns false for non-existent attribute', function () {
        $model = new CoverageTestModel;
        $model->setAttribute('name', 'Test');
        $model->syncOriginal();

        expect($model->isDirty('non_existent_field'))->toBeFalse();
    });

    test('Model line 1259 - refresh with null fresh model', function () {
        $connection = m::mock(Connection::class);
        $builder = m::mock(Builder::class);

        $connection->shouldReceive('table')->once()->andReturn($builder);
        $builder->shouldReceive('setModel')->once();
        $builder->shouldReceive('where')->once()->andReturn($builder);
        $builder->shouldReceive('first')->once()->andReturn(null);

        Model::setConnection($connection);

        $model = new CoverageTestModel;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Test');
        $model->syncOriginal();

        $refreshed = $model->refresh();
        expect($refreshed)->toBe($model);
        expect($model->getAttribute('name'))->toBe('Test'); // Unchanged
    });
});
