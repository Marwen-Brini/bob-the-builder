<?php

use Bob\Database\Model;
use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Database\Relations\HasOne;
use Bob\Database\Relations\HasMany;
use Bob\Database\Relations\BelongsTo;
use Bob\Database\Relations\BelongsToMany;

class TestModel extends Model
{
    protected string $table = 'test_models';
    protected $fillable = ['name', 'email'];
    protected $guarded = ['password'];
    protected $hidden = ['secret'];
    protected array $casts = [
        'is_active' => 'boolean',
        'age' => 'integer',
        'metadata' => 'array'
    ];
}

class UserModel extends Model 
{
    protected string $table = 'users';
    
    public function profile()
    {
        return $this->hasOne(ProfileModel::class, 'user_id', 'id');
    }
    
    public function posts()
    {
        return $this->hasMany(PostModel::class, 'user_id', 'id');
    }
}

class PostModel extends Model
{
    protected string $table = 'posts';
    
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }
    
    public function tags()
    {
        return $this->belongsToMany(TagModel::class, 'post_tags', 'post_id', 'tag_id');
    }
}

class ProfileModel extends Model
{
    protected string $table = 'profiles';
}

class TagModel extends Model
{
    protected string $table = 'tags';
}

beforeEach(function () {
    // Set up a mock connection for all tests
    $this->connection = Mockery::mock(Connection::class);
    Model::setConnection($this->connection);
});

afterEach(function () {
    // Clean up
    Mockery::close();
});

describe('Model', function () {
    
    test('basic attributes', function () {
        $model = new TestModel();

        $model->setAttribute('name', 'John Doe');
        expect($model->getAttribute('name'))->toBe('John Doe');

        $model->name = 'Jane Doe';
        expect($model->name)->toBe('Jane Doe');

        // Test using setAttribute for email
        $model->setAttribute('email', 'jane@example.com');
        expect($model->getAttribute('email'))->toBe('jane@example.com');
    });
    
    test('mass assignment with fillable', function () {
        $model = new TestModel();
        $model->fill([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret' // This should be ignored due to guarded
        ]);
        
        expect($model->name)->toBe('John');
        expect($model->email)->toBe('john@example.com');
        expect($model->getAttribute('password'))->toBeNull();
    });
    
    test('hidden attributes', function () {
        $model = new TestModel();
        $model->fill([
            'name' => 'John',
            'secret' => 'hidden_value'
        ]);
        
        $array = $model->toArray();
        expect($array)->toHaveKey('name');
        expect($array)->not->toHaveKey('secret');
        
        $json = $model->toJson();
        expect($json)->toContain('John');
        expect($json)->not->toContain('hidden_value');
    });
    
    test('attribute casting', function () {
        $model = new TestModel();
        
        $model->setAttribute('is_active', 1);
        expect($model->getCastedAttribute('is_active'))->toBe(true);
        
        $model->setAttribute('age', '25');
        expect($model->getCastedAttribute('age'))->toBe(25);
        
        $model->setAttribute('metadata', ['key' => 'value']);
        $retrieved = $model->getCastedAttribute('metadata');
        expect($retrieved)->toBe(['key' => 'value']);
    });
    
    test('dirty tracking', function () {
        $model = new TestModel();
        $model->syncOriginal();
        
        expect($model->isDirty())->toBeFalse();
        
        $model->name = 'John';
        expect($model->isDirty())->toBeTrue();
        expect($model->isDirty('name'))->toBeTrue();
        expect($model->isDirty('email'))->toBeFalse();
        
        expect($model->getDirty())->toBe(['name' => 'John']);
        
        $model->syncOriginal();
        expect($model->isDirty())->toBeFalse();
    });
    
    test('original attributes', function () {
        $model = new TestModel();
        $model->name = 'John';
        $model->syncOriginal();
        
        $model->name = 'Jane';
        expect($model->getOriginal('name'))->toBe('John');
        expect($model->getAttribute('name'))->toBe('Jane');
    });
    
    test('array and JSON serialization', function () {
        $model = new TestModel();
        $model->name = 'John';
        $model->email = 'john@example.com';
        $model->secret = 'hidden';
        
        $array = $model->toArray();
        expect($array)->toHaveKeys(['name', 'email']);
        expect($array)->not->toHaveKey('secret');
        
        $json = $model->toJson();
        $decoded = json_decode($json, true);
        expect($decoded)->toHaveKeys(['name', 'email']);
        expect($decoded)->not->toHaveKey('secret');
    });
    
    test('hasOne relationship', function () {
        $builder = Mockery::mock(Builder::class);
        $related = new ProfileModel();

        $this->connection->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('setModel')->andReturn($builder);
        $builder->shouldReceive('getModel')->andReturn($related);
        $builder->shouldReceive('where')->andReturn($builder);
        $builder->shouldReceive('whereNotNull')->andReturn($builder);

        $user = new UserModel();
        $user->id = 1;

        $relation = $user->profile();
        expect($relation)->toBeInstanceOf(HasOne::class);
    });
    
    test('hasMany relationship', function () {
        $builder = Mockery::mock(Builder::class);
        $related = new PostModel();

        $this->connection->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('setModel')->andReturn($builder);
        $builder->shouldReceive('getModel')->andReturn($related);
        $builder->shouldReceive('where')->andReturn($builder);
        $builder->shouldReceive('whereNotNull')->andReturn($builder);

        $user = new UserModel();
        $user->id = 1;

        $relation = $user->posts();
        expect($relation)->toBeInstanceOf(HasMany::class);
    });
    
    test('belongsTo relationship', function () {
        $builder = Mockery::mock(Builder::class);
        $related = new UserModel();

        $this->connection->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('setModel')->andReturn($builder);
        $builder->shouldReceive('getModel')->andReturn($related);
        $builder->shouldReceive('where')->andReturn($builder);

        $post = new PostModel();
        $post->user_id = 1;

        $relation = $post->user();
        expect($relation)->toBeInstanceOf(BelongsTo::class);
    });
    
    test('belongsToMany relationship', function () {
        $builder = Mockery::mock(Builder::class);
        $related = new TagModel();

        $this->connection->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('setModel')->andReturn($builder);
        $builder->shouldReceive('getModel')->andReturn($related);
        $builder->shouldReceive('join')->andReturn($builder);
        $builder->shouldReceive('where')->andReturn($builder);

        $post = new PostModel();
        $post->id = 1;

        $relation = $post->tags();
        expect($relation)->toBeInstanceOf(BelongsToMany::class);
    });
    
    test('timestamps', function () {
        $model = new TestModel();
        $model->timestamps = true;
        
        // Test that timestamps would be set
        expect($model->timestamps)->toBeTrue();
        expect($model->getCreatedAtColumn())->toBe('created_at');
        expect($model->getUpdatedAtColumn())->toBe('updated_at');
    });
    
    test('query builder creation', function () {
        $builder = Mockery::mock(Builder::class);

        $this->connection->shouldReceive('table')
            ->with('test_models')
            ->andReturn($builder);
        $builder->shouldReceive('setModel')->andReturn($builder);

        $query = TestModel::query();
        expect($query)->toBe($builder);
    });
});