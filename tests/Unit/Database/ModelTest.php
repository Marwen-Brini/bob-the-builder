<?php

use Bob\Database\Model;
use Bob\Database\Connection;
use Bob\Contracts\ConnectionInterface;

class TestModel extends Model
{
    protected string $table = 'test_models';
    protected string $primaryKey = 'id';
}

beforeEach(function () {
    $this->connection = Mockery::mock(ConnectionInterface::class);
    TestModel::setConnection($this->connection);
});

it('can set and get connection', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    TestModel::setConnection($connection);
    
    expect(TestModel::getConnection())->toBe($connection);
});

it('creates new query builder instances', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $result = TestModel::query();
    expect($result)->toBe($builder);
});

it('forwards static calls to query builder', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('active', true)
        ->andReturnSelf();
        
    $result = TestModel::where('active', true);
    expect($result)->toBe($builder);
});

it('can find records by id', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 123)
        ->andReturnSelf();
    $builder->shouldReceive('first')
        ->andReturn(['id' => 123, 'name' => 'Test']);
        
    $result = TestModel::find(123);
    expect($result)->toBeInstanceOf(TestModel::class);
    expect($result->id)->toBe(123);
    expect($result->name)->toBe('Test');
});

it('can get all records', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('get')
        ->andReturn([['id' => 1], ['id' => 2]]);
        
    $result = TestModel::all();
    expect($result)->toHaveCount(2);
    expect($result[0])->toBeInstanceOf(TestModel::class);
    expect($result[1])->toBeInstanceOf(TestModel::class);
    expect($result[0]->id)->toBe(1);
    expect($result[1]->id)->toBe(2);
});

it('can create new records', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('insertGetId')
        ->with(Mockery::type('array'))
        ->andReturn(123);
        
    $attributes = ['name' => 'Test', 'email' => 'test@example.com'];
    $result = TestModel::create($attributes);
    expect($result)->toBeInstanceOf(TestModel::class);
    expect($result->id)->toBe(123);
});

it('can update records', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 123)
        ->andReturnSelf();
    $builder->shouldReceive('update')
        ->with(['name' => 'Updated'])
        ->andReturn(1);
        
    $result = TestModel::where('id', 123)->update(['name' => 'Updated']);
    expect($result)->toBe(1);
});

it('can delete records', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('id', 123)
        ->andReturnSelf();
    $builder->shouldReceive('delete')
        ->andReturn(1);
        
    $result = TestModel::where('id', 123)->delete();
    expect($result)->toBe(1);
});

it('can count records', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('count')
        ->andReturn(42);
        
    $result = TestModel::count();
    expect($result)->toBe(42);
});

it('can get table name', function () {
    $model = new TestModel();
    expect($model->getTable())->toBe('test_models');
});

it('can get primary key name', function () {
    $model = new TestModel();
    expect($model->getPrimaryKey())->toBe('id');
});

it('throws exception when no connection set', function () {
    // Reset connection to null using reflection since setConnection requires ConnectionInterface
    $reflection = new ReflectionClass(TestModel::class);
    $property = $reflection->getProperty('connection');
    $property->setAccessible(true);
    $property->setValue(null, null);
    
    expect(fn() => TestModel::query())
        ->toThrow(RuntimeException::class, 'No database connection');
    
    // Restore connection for other tests
    TestModel::setConnection($this->connection);
});

it('handles dynamic method calls', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('orderBy')
        ->with('created_at', 'desc')
        ->andReturnSelf();
        
    $result = TestModel::orderBy('created_at', 'desc');
    expect($result)->toBe($builder);
});

it('can handle aggregate methods', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('max')
        ->with('score')
        ->andReturn(100);
        
    $result = TestModel::max('score');
    expect($result)->toBe(100);
});

it('can handle scoped queries', function () {
    $builder = Mockery::mock(Bob\Query\Builder::class);
    $this->connection->shouldReceive('table')
        ->with('test_models')
        ->andReturn($builder);
    
    $builder->shouldReceive('where')
        ->with('active', true)
        ->andReturnSelf();
    $builder->shouldReceive('where')
        ->with('verified', true)
        ->andReturnSelf();
        
    $result = TestModel::where('active', true)->where('verified', true);
    expect($result)->toBe($builder);
});