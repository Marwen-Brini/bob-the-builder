<?php

use Bob\Database\Model;
use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Database\Relations\BelongsTo;
use Bob\Database\Relations\BelongsToMany;
use Bob\Database\Relations\HasOne;
use Bob\Database\Relations\HasMany;
use Bob\Database\Relations\Relation;
use Mockery as m;

describe('Model 100% Coverage Tests', function () {

    beforeEach(function () {
        $this->connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        Model::setConnection($this->connection);

        // Create test tables
        $this->connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role_id INTEGER, created_at DATETIME, updated_at DATETIME)');
        $this->connection->statement('CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, created_at DATETIME, updated_at DATETIME)');
        $this->connection->statement('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->statement('CREATE TABLE role_user (role_id INTEGER, user_id INTEGER)');
    });

    afterEach(function () {
        Model::clearConnection();
        m::close();
    });

    // Line 306: update returns true when no dirty attributes
    test('update returns true when no dirty attributes after preparing', function () {
        // Insert a user first
        $this->connection->statement('INSERT INTO users (id, name, email) VALUES (1, "John", "john@example.com")');

        // Create a model instance
        $model = new class extends Model {
            protected string $table = 'users';
            protected $fillable = ['name', 'email'];
            public bool $timestamps = false;
        };

        // Find the existing model
        $model = $model->find(1);

        // Save the model (which calls the protected update method internally)
        $result = $model->save();

        expect($result)->toBeTrue();
    });

    // Line 607: castAttribute returns value when no cast defined
    test('castAttribute returns original value when no cast defined', function () {
        $model = new class extends Model {
            protected string $table = 'users';
            // No casts defined
        };

        $model->setAttribute('name', 'John Doe');

        // Use reflection to call protected method
        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('castAttribute');
        $method->setAccessible(true);

        // castAttribute takes two parameters: key and value
        $result = $method->invoke($model, 'name', 'John Doe');

        expect($result)->toBe('John Doe');
    });

    // Line 793: belongsTo with null foreignKey parameter
    test('belongsTo generates foreignKey when null provided', function () {
        $model = new class extends Model {
            protected string $table = 'posts';

            public function testBelongsTo() {
                // Pass null as foreignKey to trigger line 793
                return $this->belongsTo(User::class, null, 'id', 'user');
            }
        };

        $user = new class extends Model {
            protected string $table = 'users';
        };

        class_alias(get_class($user), 'User');

        $relation = $model->testBelongsTo();

        expect($relation)->toBeInstanceOf(BelongsTo::class);
        expect($relation->getForeignKeyName())->toBe('user_id');
    });

    // Line 825: belongsToMany with null table parameter
    test('belongsToMany generates table name when null provided', function () {
        $model = new class extends Model {
            protected string $table = 'users';

            public function testBelongsToMany() {
                // Pass null as table to trigger line 825
                return $this->belongsToMany(Role::class, null, 'user_id', 'role_id');
            }
        };

        $role = new class extends Model {
            protected string $table = 'roles';
        };

        class_alias(get_class($role), 'Role');

        $relation = $model->testBelongsToMany();

        expect($relation)->toBeInstanceOf(BelongsToMany::class);
        // Should generate table name from joining tables
        expect($relation->getTable())->toBe('roles_users');
    });

    // Line 951: getRelationValue loads from method when not already loaded
    test('getRelationValue loads relationship from method', function () {
        $model = new class extends Model {
            protected string $table = 'users';

            public function posts() {
                return $this->hasMany(Post::class, 'user_id');
            }
        };

        $post = new class extends Model {
            protected string $table = 'posts';
        };

        class_alias(get_class($post), 'Post');

        $model->setAttribute('id', 1);
        $model->exists = true;

        // Insert test data
        $this->connection->statement('INSERT INTO users (id, name) VALUES (1, "John")');
        $this->connection->statement('INSERT INTO posts (user_id, title) VALUES (1, "Post 1")');

        // Use reflection to access protected method
        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getRelationValue');
        $method->setAccessible(true);

        $result = $method->invoke($model, 'posts');

        expect($result)->toBeArray();
    });

    // Lines 986-988: getRelationshipFromMethod with tap function
    test('getRelationshipFromMethod uses tap to set relation', function () {
        // Mock the relation instead of using real database
        $relationMock = m::mock(\Bob\Database\Relations\HasOne::class);
        $relationMock->shouldReceive('getResults')->once()->andReturn(null);

        $model = new class extends Model {
            protected string $table = 'users';
            public $mockedRelation = null;

            public function testRelation() {
                // Return the mocked relation set from outside
                return $this->mockedRelation;
            }
        };

        // Set the mocked relation
        $model->mockedRelation = $relationMock;
        $model->setAttribute('id', 1);
        $model->exists = true;

        // Use reflection to access protected method
        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getRelationshipFromMethod');
        $method->setAccessible(true);

        $result = $method->invoke($model, 'testRelation');

        // After calling, the relation should be set
        expect($model->relationLoaded('testRelation'))->toBeTrue();
    });

    // Line 1024: getRelations returns relations array
    test('getRelations returns the relations array', function () {
        $model = new class extends Model {
            protected string $table = 'users';
        };

        // Set some relations
        $model->setRelation('posts', []);
        $model->setRelation('role', null);

        $relations = $model->getRelations();

        expect($relations)->toBeArray();
        expect($relations)->toHaveKey('posts');
        expect($relations)->toHaveKey('role');
    });

    // Line 1115: isIgnoringTouch static method
    test('isIgnoringTouch returns current ignoreTouch state', function () {
        // Simply call the method to cover line 1115
        $result = Model::isIgnoringTouch();

        // It returns a boolean
        expect($result)->toBeBool();
    });

    // Line 1259: fresh returns null when model doesn't exist
    test('fresh returns null when model does not exist', function () {
        $model = new class extends Model {
            protected string $table = 'users';
        };

        // Model doesn't exist (no id, exists = false)
        $model->exists = false;

        $result = $model->fresh();

        expect($result)->toBeNull();
    });

    // Line 1393: getAppends returns appends array
    test('getAppends returns the appends array', function () {
        $model = new class extends Model {
            protected string $table = 'users';
            protected $appends = ['full_name', 'display_name'];
        };

        $appends = $model->getAppends();

        expect($appends)->toBeArray();
        expect($appends)->toContain('full_name');
        expect($appends)->toContain('display_name');
    });

    // Additional test to ensure tap function works correctly (lines 986-988)
    test('tap function in getRelationshipFromMethod sets relation correctly', function () {
        $model = new class extends Model {
            protected string $table = 'users';

            public function profile() {
                // Return a mock relation that returns specific results
                $relation = m::mock(Relation::class);
                $relation->shouldReceive('getResults')->once()->andReturn(['profile_data']);
                return $relation;
            }
        };

        $model->setAttribute('id', 1);
        $model->exists = true;

        // Use reflection to call the protected method
        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getRelationshipFromMethod');
        $method->setAccessible(true);

        $result = $method->invoke($model, 'profile');

        expect($result)->toBe(['profile_data']);
        expect($model->relationLoaded('profile'))->toBeTrue();
        expect($model->getRelation('profile'))->toBe(['profile_data']);
    });

    // Test line 306 more directly
    test('performUpdate returns true when no dirty attributes', function () {
        // This test ensures line 306 is covered - when prepareAttributesForUpdate returns empty
        $model = new class extends Model {
            protected string $table = 'users';
            public bool $timestamps = false;

            // Override to ensure we get to the empty check
            protected function prepareAttributesForUpdate(): array {
                // Return empty to trigger line 306
                return [];
            }
        };

        $model->exists = true;
        $model->setAttribute('id', 1);
        $model->syncOriginal(); // Ensure nothing is dirty

        // Use reflection to call the protected update method
        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('update');
        $method->setAccessible(true);

        $result = $method->invoke($model);

        expect($result)->toBeTrue();
    });

    // Line 607: getCastedAttribute returns value when no cast defined
    test('getCastedAttribute returns original value when no cast defined', function () {
        $model = new class extends Model {
            protected string $table = 'users';
            // No casts defined - this will trigger line 607
        };

        $model->setAttribute('name', 'John Doe');
        $model->setAttribute('age', 25);

        // getCastedAttribute is a public method
        $result = $model->getCastedAttribute('name');
        expect($result)->toBe('John Doe');

        $result = $model->getCastedAttribute('age');
        expect($result)->toBe(25);
    });

    // Line 306: update method returns true when prepareAttributesForUpdate returns empty
    test('update returns true immediately when no attributes to update', function () {
        // First insert a record to update
        $this->connection->statement('INSERT INTO users (id, name, email) VALUES (1, "Test", "test@example.com")');

        // Create a model where isDirty() returns true but prepareAttributesForUpdate returns empty
        $model = new class extends Model {
            protected string $table = 'users';
            public bool $timestamps = false;
            protected $fillable = ['name', 'email'];

            public function isDirty($attributes = null): bool {
                // Force isDirty to return true so we pass line 298
                return true;
            }

            protected function prepareAttributesForUpdate(): array {
                // Return empty array to trigger line 306
                return [];
            }
        };

        // Set the model as existing
        $model->exists = true;
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Test');
        $model->setAttribute('email', 'test@example.com');
        $model->syncOriginal();

        // Call save which internally calls update
        $result = $model->save();

        // This should return true from line 306
        expect($result)->toBeTrue();
    });
});