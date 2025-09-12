<?php

use Bob\Database\Model;
use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Query\Grammars\MySQLGrammar;

// Create a test model class
class UnitTestModel extends Model
{
    protected string $table = 'test_models';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
}

class UnitUserModel extends Model
{
    // Let it auto-generate table name from class name
    protected bool $timestamps = false;
}

class UnitPostModel extends Model
{
    protected string $table = 'posts';
    
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }
    
    public static function findBySlug(string $slug): ?self
    {
        $result = static::query()->where('slug', $slug)->first();
        return $result ? static::hydrate($result) : null;
    }
}

beforeEach(function () {
    // Reset static connection using reflection
    $reflection = new ReflectionClass(UnitTestModel::class);
    $connectionProp = $reflection->getProperty('connection');
    $connectionProp->setAccessible(true);
    $connectionProp->setValue(null, null);
});

it('throws exception when no connection is set', function () {
    expect(fn() => UnitTestModel::getConnection())
        ->toThrow(RuntimeException::class, 'No database connection configured for models');
});

it('can set and get connection', function () {
    $connection = Mockery::mock(Connection::class);
    UnitTestModel::setConnection($connection);
    
    expect(UnitTestModel::getConnection())->toBe($connection);
});

it('auto-generates table name from class name', function () {
    $model = new UnitUserModel();
    expect($model->getTable())->toBe('unit_user_models');
});

it('pluralizes table names correctly', function () {
    // Test different pluralization rules
    $testCases = [
        'User' => 'users',
        'Category' => 'categories',
        'Bus' => 'buses',
        'Day' => 'days',
    ];
    
    foreach ($testCases as $singular => $expectedPlural) {
        $modelClass = new class extends Model {
            public function testPluralize($word) {
                return $this->pluralize(strtolower($word));
            }
        };
        
        $model = new $modelClass();
        expect($model->testPluralize($singular))->toBe($expectedPlural);
    }
});

it('fills attributes correctly', function () {
    $model = new UnitTestModel([
        'name' => 'John',
        'email' => 'john@example.com'
    ]);
    
    expect($model->getAttribute('name'))->toBe('John');
    expect($model->getAttribute('email'))->toBe('john@example.com');
});

it('tracks dirty attributes', function () {
    $model = new UnitTestModel([
        'name' => 'John',
        'email' => 'john@example.com'
    ]);
    
    // Initially no dirty attributes
    expect($model->getDirty())->toBeEmpty();
    
    // Change an attribute
    $model->setAttribute('name', 'Jane');
    $dirty = $model->getDirty();
    
    expect($dirty)->toHaveKey('name');
    expect($dirty['name'])->toBe('Jane');
    expect($dirty)->not->toHaveKey('email');
});

it('detects if model exists', function () {
    $model = new UnitTestModel();
    expect($model->exists())->toBeFalse();
    
    // Simulate loaded from database
    $model = new UnitTestModel(['id' => 1, 'name' => 'John']);
    $reflection = new ReflectionClass($model);
    $originalProp = $reflection->getProperty('original');
    $originalProp->setAccessible(true);
    $originalProp->setValue($model, ['id' => 1, 'name' => 'John']);
    
    expect($model->exists())->toBeTrue();
});

it('converts to array', function () {
    $attributes = [
        'id' => 1,
        'name' => 'John',
        'email' => 'john@example.com'
    ];
    
    $model = new UnitTestModel($attributes);
    expect($model->toArray())->toBe($attributes);
});

it('converts to json', function () {
    $attributes = [
        'id' => 1,
        'name' => 'John',
        'email' => 'john@example.com'
    ];
    
    $model = new UnitTestModel($attributes);
    expect($model->toJson())->toBe(json_encode($attributes));
    expect($model->toJson(JSON_PRETTY_PRINT))->toBe(json_encode($attributes, JSON_PRETTY_PRINT));
});

it('handles magic get and set', function () {
    $model = new UnitTestModel();
    
    $model->name = 'John';
    expect($model->name)->toBe('John');
    expect(isset($model->name))->toBeTrue();
    expect(isset($model->nonexistent))->toBeFalse();
    
    unset($model->name);
    expect(isset($model->name))->toBeFalse();
});

it('creates query builder', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    UnitTestModel::setConnection($connection);
    
    $query = UnitTestModel::query();
    expect($query)->toBe($builder);
});

it('creates new model with create method', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('insertGetId')
        ->with(Mockery::on(function ($attrs) {
            return isset($attrs['name']) && 
                   $attrs['name'] === 'John' &&
                   isset($attrs['created_at']) &&
                   isset($attrs['updated_at']);
        }))
        ->andReturn(1);
    
    UnitTestModel::setConnection($connection);
    
    $model = UnitTestModel::create(['name' => 'John']);
    
    expect($model)->toBeInstanceOf(UnitTestModel::class);
    expect($model->getAttribute('id'))->toBe(1);
    expect($model->getAttribute('name'))->toBe('John');
});

it('returns null when create fails', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('insertGetId')
        ->andReturn(false);
    
    UnitTestModel::setConnection($connection);
    
    $model = UnitTestModel::create(['name' => 'John']);
    expect($model)->toBeNull();
});

it('finds model by id', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 1)
        ->andReturn($builder);
    
    $builder->shouldReceive('first')
        ->andReturn(['id' => 1, 'name' => 'John']);
    
    UnitTestModel::setConnection($connection);
    
    $model = UnitTestModel::find(1);
    
    expect($model)->toBeInstanceOf(UnitTestModel::class);
    expect($model->getAttribute('id'))->toBe(1);
    expect($model->getAttribute('name'))->toBe('John');
});

it('returns null when find fails', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 999)
        ->andReturn($builder);
    
    $builder->shouldReceive('first')
        ->andReturn(null);
    
    UnitTestModel::setConnection($connection);
    
    $model = UnitTestModel::find(999);
    expect($model)->toBeNull();
});

it('throws exception with findOrFail', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 999)
        ->andReturn($builder);
    
    $builder->shouldReceive('first')
        ->andReturn(null);
    
    UnitTestModel::setConnection($connection);
    
    expect(fn() => UnitTestModel::findOrFail(999))
        ->toThrow(RuntimeException::class, 'Model not found with ID: 999');
});

it('gets all models', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('get')
        ->andReturn([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ]);
    
    UnitTestModel::setConnection($connection);
    
    $models = UnitTestModel::all();
    
    expect($models)->toHaveCount(2);
    expect($models[0])->toBeInstanceOf(UnitTestModel::class);
    expect($models[0]->getAttribute('name'))->toBe('John');
    expect($models[1]->getAttribute('name'))->toBe('Jane');
});

it('gets first model', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('first')
        ->andReturn(['id' => 1, 'name' => 'John']);
    
    UnitTestModel::setConnection($connection);
    
    $model = UnitTestModel::first();
    
    expect($model)->toBeInstanceOf(UnitTestModel::class);
    expect($model->getAttribute('name'))->toBe('John');
});

it('saves new model with insert', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('insertGetId')
        ->andReturn(1);
    
    UnitTestModel::setConnection($connection);
    
    $model = new UnitTestModel(['name' => 'John']);
    $result = $model->save();
    
    expect($result)->toBeTrue();
    expect($model->getAttribute('id'))->toBe(1);
});

it('saves existing model with update', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 1)
        ->andReturn($builder);
    
    $builder->shouldReceive('update')
        ->with(Mockery::on(function ($attrs) {
            return isset($attrs['name']) && 
                   $attrs['name'] === 'Jane' &&
                   isset($attrs['updated_at']);
        }))
        ->andReturn(1);
    
    UnitTestModel::setConnection($connection);
    
    // Create model that exists
    $model = new UnitTestModel(['id' => 1, 'name' => 'John']);
    $reflection = new ReflectionClass($model);
    $originalProp = $reflection->getProperty('original');
    $originalProp->setAccessible(true);
    $originalProp->setValue($model, ['id' => 1, 'name' => 'John']);
    
    $model->setAttribute('name', 'Jane');
    $result = $model->save();
    
    expect($result)->toBeTrue();
});

it('returns true when update has no dirty attributes', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    // Set up expectations for the update call with only the updated_at field
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 1)
        ->andReturn($builder);
    
    $builder->shouldReceive('update')
        ->with(Mockery::on(function ($attrs) {
            // Should only have updated_at since no other fields changed
            return count($attrs) === 1 && isset($attrs['updated_at']);
        }))
        ->andReturn(1);
    
    UnitTestModel::setConnection($connection);
    
    // Create model that exists with no changes
    $model = new UnitTestModel(['id' => 1, 'name' => 'John']);
    $reflection = new ReflectionClass($model);
    $originalProp = $reflection->getProperty('original');
    $originalProp->setAccessible(true);
    $originalProp->setValue($model, ['id' => 1, 'name' => 'John']);
    
    // Save should return true even with no dirty attributes (except updated_at)
    $result = $model->save();
    expect($result)->toBeTrue();
});

it('deletes existing model', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 1)
        ->andReturn($builder);
    
    $builder->shouldReceive('delete')
        ->andReturn(1);
    
    UnitTestModel::setConnection($connection);
    
    $model = new UnitTestModel(['id' => 1, 'name' => 'John']);
    $reflection = new ReflectionClass($model);
    $originalProp = $reflection->getProperty('original');
    $originalProp->setAccessible(true);
    $originalProp->setValue($model, ['id' => 1, 'name' => 'John']);
    
    $result = $model->delete();
    expect($result)->toBeTrue();
});

it('returns false when deleting non-existent model', function () {
    $model = new UnitTestModel(['name' => 'John']);
    $result = $model->delete();
    expect($result)->toBeFalse();
});

it('handles scope methods', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('posts')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('status', 'published')
        ->andReturn($builder);
    
    UnitPostModel::setConnection($connection);
    
    $query = UnitPostModel::published();
    expect($query)->toBe($builder);
});

it('handles custom static methods', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('posts')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('slug', 'test-slug')
        ->andReturn($builder);
    
    $builder->shouldReceive('first')
        ->andReturn(['id' => 1, 'slug' => 'test-slug']);
    
    UnitPostModel::setConnection($connection);
    
    $post = UnitPostModel::findBySlug('test-slug');
    expect($post)->toBeInstanceOf(UnitPostModel::class);
    expect($post->getAttribute('slug'))->toBe('test-slug');
});

it('forwards static calls to query builder', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('active', true)
        ->andReturn($builder);
    
    UnitTestModel::setConnection($connection);
    
    $result = UnitTestModel::where('active', true);
    expect($result)->toBe($builder);
});

it('forwards instance calls to query builder with constraints', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 1)
        ->andReturn($builder);
    
    $builder->shouldReceive('update')
        ->with(['status' => 'active'])
        ->andReturn(1);
    
    UnitTestModel::setConnection($connection);
    
    $model = new UnitTestModel(['id' => 1]);
    $reflection = new ReflectionClass($model);
    $originalProp = $reflection->getProperty('original');
    $originalProp->setAccessible(true);
    $originalProp->setValue($model, ['id' => 1]);
    
    $result = $model->update(['status' => 'active']);
    expect($result)->toBe(1);
});

it('handles models without timestamps', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('unit_user_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('insertGetId')
        ->with(Mockery::on(function ($attrs) {
            return $attrs === ['name' => 'John']; // No timestamps
        }))
        ->andReturn(1);
    
    UnitUserModel::setConnection($connection);
    
    $model = UnitUserModel::create(['name' => 'John']);
    expect($model)->toBeInstanceOf(UnitUserModel::class);
});

it('returns false when insert fails', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('insertGetId')
        ->andReturn(null);
    
    UnitTestModel::setConnection($connection);
    
    $model = new UnitTestModel(['name' => 'John']);
    $result = $model->save();
    
    expect($result)->toBeFalse();
});

it('returns false when update fails', function () {
    $connection = Mockery::mock(Connection::class);
    $builder = Mockery::mock(Builder::class);
    
    $connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 1)
        ->andReturn($builder);
    
    $builder->shouldReceive('update')
        ->andReturn(0);
    
    UnitTestModel::setConnection($connection);
    
    $model = new UnitTestModel(['id' => 1, 'name' => 'John']);
    $reflection = new ReflectionClass($model);
    $originalProp = $reflection->getProperty('original');
    $originalProp->setAccessible(true);
    $originalProp->setValue($model, ['id' => 1, 'name' => 'John']);
    
    $model->setAttribute('name', 'Jane');
    $result = $model->save();
    
    expect($result)->toBeFalse();
});