<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Database\Eloquent\Scope;
use Bob\Database\Eloquent\SoftDeletes;
use Bob\Query\Builder;

// Test Models
class GlobalScopeTestCategory extends Model
{
    protected string $table = 'categories';
    protected array $fillable = ['name', 'taxonomy'];

    protected static function boot(): void
    {
        parent::boot();

        // Add a global scope to filter by taxonomy
        static::addGlobalScope('taxonomy', function (Builder $builder) {
            $builder->where('taxonomy', 'category');
        });
    }
}

class GlobalScopeTestPost extends Model
{
    protected string $table = 'posts';
    protected array $fillable = ['title', 'status', 'author_id'];

    protected static function boot(): void
    {
        parent::boot();

        // Add multiple global scopes
        static::addGlobalScope('published', function (Builder $builder) {
            $builder->where('status', 'published');
        });

        static::addGlobalScope('recent', function (Builder $builder) {
            $builder->orderBy('created_at', 'desc');
        });
    }
}

// Custom Scope Class
class ActiveScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('active', true);
    }
}

class GlobalScopeTestUser extends Model
{
    use SoftDeletes;

    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'active'];

    protected static function boot(): void
    {
        parent::boot();

        // Add scope class
        static::addGlobalScope(new ActiveScope);
    }
}

class GlobalScopeTestProduct extends Model
{
    protected string $table = 'products';
    protected array $fillable = ['name', 'tenant_id'];

    protected static function boot(): void
    {
        parent::boot();

        // Multi-tenant scope
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Simulate getting current tenant ID
            $tenantId = $_SESSION['tenant_id'] ?? 1;
            $builder->where('tenant_id', $tenantId);
        });
    }
}

beforeEach(function () {
    // Create an in-memory SQLite database for testing
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Set connection for all models
    Model::setConnection($this->connection);

    // Create test tables
    $this->connection->statement('
        CREATE TABLE categories (
            id INTEGER PRIMARY KEY,
            name TEXT,
            taxonomy TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )
    ');

    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY,
            title TEXT,
            status TEXT,
            author_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP
        )
    ');

    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            email TEXT,
            active BOOLEAN DEFAULT 1,
            deleted_at TIMESTAMP NULL,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )
    ');

    $this->connection->statement('
        CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            name TEXT,
            tenant_id INTEGER,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )
    ');

    // Seed test data
    $this->connection->table('categories')->insert([
        ['name' => 'Technology', 'taxonomy' => 'category'],
        ['name' => 'Sports', 'taxonomy' => 'category'],
        ['name' => 'PHP', 'taxonomy' => 'tag'],
        ['name' => 'Laravel', 'taxonomy' => 'tag'],
    ]);

    $this->connection->table('posts')->insert([
        ['title' => 'Published Post 1', 'status' => 'published', 'author_id' => 1],
        ['title' => 'Published Post 2', 'status' => 'published', 'author_id' => 2],
        ['title' => 'Draft Post', 'status' => 'draft', 'author_id' => 1],
        ['title' => 'Scheduled Post', 'status' => 'scheduled', 'author_id' => 2],
    ]);

    $this->connection->table('users')->insert([
        ['name' => 'Active User 1', 'email' => 'user1@example.com', 'active' => 1, 'deleted_at' => null],
        ['name' => 'Active User 2', 'email' => 'user2@example.com', 'active' => 1, 'deleted_at' => null],
        ['name' => 'Inactive User', 'email' => 'inactive@example.com', 'active' => 0, 'deleted_at' => null],
        ['name' => 'Deleted User', 'email' => 'deleted@example.com', 'active' => 1, 'deleted_at' => '2024-01-01 00:00:00'],
    ]);

    $this->connection->table('products')->insert([
        ['name' => 'Product 1 - Tenant 1', 'tenant_id' => 1],
        ['name' => 'Product 2 - Tenant 1', 'tenant_id' => 1],
        ['name' => 'Product 1 - Tenant 2', 'tenant_id' => 2],
        ['name' => 'Product 2 - Tenant 2', 'tenant_id' => 2],
    ]);

    // Clear any previously booted models
    $reflection = new ReflectionClass(Model::class);

    $bootedProperty = $reflection->getProperty('booted');
    $bootedProperty->setAccessible(true);
    $bootedProperty->setValue(null, []);

    $globalScopesProperty = $reflection->getProperty('globalScopes');
    $globalScopesProperty->setAccessible(true);
    $globalScopesProperty->setValue(null, []);

    $bootedMethodsProperty = $reflection->getProperty('bootedMethods');
    $bootedMethodsProperty->setAccessible(true);
    $bootedMethodsProperty->setValue(null, []);
});

afterEach(function () {
    Model::clearConnection();
});

test('closure global scope filters results', function () {
    $categories = GlobalScopeTestCategory::all();

    // Should only return categories, not tags
    expect($categories)->toHaveCount(2);
    expect($categories[0]->name)->toBe('Technology');
    expect($categories[1]->name)->toBe('Sports');
});

test('multiple global scopes work', function () {
    $posts = GlobalScopeTestPost::all();

    // Should only return published posts
    expect($posts)->toHaveCount(2);

    // Check that both published posts are present (order may vary based on DB)
    $titles = array_map(fn($p) => $p->title, $posts);
    expect($titles)->toContain('Published Post 1');
    expect($titles)->toContain('Published Post 2');
});

test('scope class implementation works', function () {
    $users = GlobalScopeTestUser::all();

    // Should only return active, non-deleted users
    expect($users)->toHaveCount(2);
    expect($users[0]->name)->toBe('Active User 1');
    expect($users[1]->name)->toBe('Active User 2');
});

test('withoutGlobalScope removes single scope', function () {
    $categories = GlobalScopeTestCategory::query()
        ->withoutGlobalScope('taxonomy')
        ->get();

    // Should return all items including tags
    expect($categories)->toHaveCount(4);
});

test('withoutGlobalScopes removes all scopes', function () {
    $posts = GlobalScopeTestPost::query()
        ->withoutGlobalScopes()
        ->get();

    // Should return all posts regardless of status
    expect($posts)->toHaveCount(4);
});

test('without specific global scopes', function () {
    $posts = GlobalScopeTestPost::query()
        ->withoutGlobalScopes(['published'])
        ->get();

    // Should return all posts but still ordered (recent scope still active)
    expect($posts)->toHaveCount(4);
});

test('global scope with multi-tenancy', function () {
    // Set tenant ID
    $_SESSION['tenant_id'] = 1;
    $products = GlobalScopeTestProduct::all();

    // Should only return products for tenant 1
    expect($products)->toHaveCount(2);
    expect($products[0]->name)->toContain('Tenant 1');

    // Change tenant and clear booted models
    $_SESSION['tenant_id'] = 2;

    $reflection = new ReflectionClass(Model::class);
    $bootedProperty = $reflection->getProperty('booted');
    $bootedProperty->setAccessible(true);
    $bootedProperty->setValue(null, []);
    $globalScopesProperty = $reflection->getProperty('globalScopes');
    $globalScopesProperty->setAccessible(true);
    $globalScopesProperty->setValue(null, []);

    $products = GlobalScopeTestProduct::all();

    // Should only return products for tenant 2
    expect($products)->toHaveCount(2);
    expect($products[0]->name)->toContain('Tenant 2');
});

test('soft deletes global scope', function () {
    $users = GlobalScopeTestUser::all();

    // Should not include soft-deleted users (also filtered by active scope)
    expect($users)->toHaveCount(2);

    // With trashed should include deleted but still respect active scope
    $usersWithTrashed = GlobalScopeTestUser::query()
        ->withoutGlobalScope(\Bob\Database\Eloquent\SoftDeletingScope::class)
        ->get();

    // Should still filter by active=1 but include deleted
    expect($usersWithTrashed)->toHaveCount(3);
});

test('global scope on find method', function () {
    // Try to find a draft post (should fail due to global scope)
    $post = GlobalScopeTestPost::find(3); // Draft post ID

    expect($post)->toBeNull();

    // Without global scope, should find it
    $post = GlobalScopeTestPost::query()
        ->withoutGlobalScopes()
        ->where('id', 3)
        ->first();

    expect($post)->not->toBeNull();
    expect($post->title)->toBe('Draft Post');
});

test('adding global scope after boot', function () {
    // Create a new test model class
    $model = new class extends Model {
        protected string $table = 'test_table';
    };

    // Add global scope after instantiation
    $modelClass = get_class($model);
    $modelClass::addGlobalScope('test', function (Builder $builder) {
        $builder->where('test_field', 'test_value');
    });

    // Verify scope was added
    expect($modelClass::hasGlobalScope('test'))->toBeTrue();
    expect($modelClass::getGlobalScope('test'))->not->toBeNull();
});

test('global scopes persist across queries', function () {
    // First query
    $categories1 = GlobalScopeTestCategory::all();
    expect($categories1)->toHaveCount(2);

    // Second query should also have scope applied
    $categories2 = GlobalScopeTestCategory::query()->get();
    expect($categories2)->toHaveCount(2);

    // Third query with additional where
    $categories3 = GlobalScopeTestCategory::query()
        ->where('name', 'Technology')
        ->get();
    expect($categories3)->toHaveCount(1);
});

test('boot method only called once', function () {
    // Track boot calls
    $model = new class extends Model {
        protected string $table = 'test_table';
        public static $bootCalls = 0;

        protected static function boot(): void
        {
            parent::boot();
            static::$bootCalls++;
        }
    };

    // Create multiple instances
    new $model();
    new $model();
    new $model();

    // Boot should only be called once
    expect($model::$bootCalls)->toBe(1);
});