# Models

Bob provides an elegant ActiveRecord implementation through its Model class. Models provide an object-oriented interface to your database tables, combining the power of the query builder with convenient object manipulation.

## Introduction

Models in Bob represent database tables as PHP classes. Each instance of a model represents a row in the database. This provides an intuitive way to work with your data:

```php
use Bob\Database\Model;

class User extends Model
{
    protected string $table = 'users';
}

// Find a user and update their name
$user = User::find(1);
$user->name = 'John Doe';
$user->save();
```

## Defining Models

### Basic Model Definition

Create a model by extending the `Bob\Database\Model` class:

```php
<?php

namespace App\Models;

use Bob\Database\Model;

class Post extends Model
{
    /**
     * The table associated with the model.
     */
    protected string $table = 'posts';
    
    /**
     * The primary key for the model.
     */
    protected string $primaryKey = 'id';
    
    /**
     * Indicates if the model should be timestamped.
     */
    protected bool $timestamps = true;
    
    /**
     * The name of the "created at" column.
     */
    protected string $createdAt = 'created_at';
    
    /**
     * The name of the "updated at" column.
     */
    protected string $updatedAt = 'updated_at';
}
```

### Table Name Convention

If you don't specify a table name, Bob will attempt to guess it by converting the class name to snake_case and pluralizing it:

```php
class User extends Model {} // Table: users
class BlogPost extends Model {} // Table: blog_posts
class Category extends Model {} // Table: categories
```

## Configuration

### Setting the Connection

Before using models, configure the database connection:

```php
use Bob\Database\Connection;
use Bob\Database\Model;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

Model::setConnection($connection);
```

## Retrieving Models

### Retrieving All Models

```php
$users = User::all();

foreach ($users as $user) {
    echo $user->name;
}
```

### Finding a Single Model

```php
// Find by primary key
$user = User::find(1);

// Find or throw exception
$user = User::findOrFail(1);

// Get the first model
$user = User::first();
```

### Using Query Builder Methods

Models provide access to all query builder methods:

```php
$activeUsers = User::where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

$admin = User::where('email', 'admin@example.com')->first();

$count = User::where('status', 'active')->count();
```

## Creating and Updating Models

### Creating New Models

```php
// Method 1: Create and save separately
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// Method 2: Mass assignment
$user = new User([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);
$user->save();

// Method 3: Create method
$user = User::create([
    'name' => 'Bob Smith',
    'email' => 'bob@example.com'
]);
```

### Updating Models

```php
$user = User::find(1);
$user->name = 'Updated Name';
$user->email = 'newemail@example.com';
$user->save();

// Update multiple attributes
$user->fill([
    'name' => 'New Name',
    'email' => 'new@example.com'
])->save();
```

### Checking for Changes

```php
$user = User::find(1);
$user->name = 'New Name';

// Get changed attributes
$dirty = $user->getDirty();
// ['name' => 'New Name']

// Check if model exists in database
if ($user->exists()) {
    $user->save();
}
```

## Deleting Models

```php
// Delete a model
$user = User::find(1);
$user->delete();

// Delete by primary key
User::destroy(1);
User::destroy([1, 2, 3]);

// Delete using query
User::where('status', 'inactive')
    ->where('created_at', '<', '2023-01-01')
    ->delete();
```

## Custom Model Methods

### Defining Custom Finders

Add model-specific finder methods:

```php
class User extends Model
{
    protected string $table = 'users';
    
    /**
     * Find a user by email address
     */
    public static function findByEmail(string $email): ?self
    {
        $result = static::query()
            ->where('email', $email)
            ->first();
        
        return $result ? static::hydrate($result) : null;
    }
    
    /**
     * Get all active users
     */
    public static function findActive(): array
    {
        $results = static::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        
        return static::hydrateMany($results);
    }
}

// Usage
$user = User::findByEmail('john@example.com');
$activeUsers = User::findActive();
```

### Adding Business Logic

```php
class Post extends Model
{
    protected string $table = 'posts';
    
    /**
     * Publish the post
     */
    public function publish(): bool
    {
        $this->status = 'published';
        $this->published_at = date('Y-m-d H:i:s');
        return $this->save();
    }
    
    /**
     * Check if the post is published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' 
            && $this->published_at !== null;
    }
    
    /**
     * Get the URL for the post
     */
    public function getUrl(): string
    {
        return '/posts/' . $this->slug;
    }
    
    /**
     * Get related posts
     */
    public function getRelatedPosts(int $limit = 5): array
    {
        return static::query()
            ->where('category_id', $this->category_id)
            ->where('id', '!=', $this->id)
            ->where('status', 'published')
            ->limit($limit)
            ->get();
    }
}
```

## Query Scopes

Scopes allow you to define common query constraints:

```php
class Post extends Model
{
    /**
     * Scope for published posts
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')
              ->whereNotNull('published_at');
    }
    
    /**
     * Scope for posts by category
     */
    public function scopeInCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }
    
    /**
     * Scope for recent posts
     */
    public function scopeRecent(Builder $query, int $days = 7): void
    {
        $date = date('Y-m-d', strtotime("-{$days} days"));
        $query->where('created_at', '>=', $date);
    }
}

// Usage
$publishedPosts = Post::published()->get();
$techPosts = Post::published()->inCategory('technology')->get();
$recentPosts = Post::recent(30)->published()->get();
```

## Attributes and Casting

### Accessing Attributes

```php
$user = User::find(1);

// Access as properties
echo $user->name;
echo $user->email;

// Get attribute with default
$role = $user->getAttribute('role', 'user');

// Set attributes
$user->name = 'New Name';
$user->setAttribute('email', 'new@example.com');

// Convert to array
$array = $user->toArray();

// Convert to JSON
$json = $user->toJson();
```

### Computed Attributes

Add computed attributes to your models:

```php
class User extends Model
{
    /**
     * Get the user's full name
     */
    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    /**
     * Get the user's avatar URL
     */
    public function getAvatarUrl(): string
    {
        return $this->avatar 
            ? '/storage/avatars/' . $this->avatar
            : '/images/default-avatar.png';
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

// Usage
$user = User::find(1);
echo $user->getFullName();
echo "<img src='{$user->getAvatarUrl()}'>";
```

## Timestamps

Bob automatically manages created_at and updated_at timestamps:

```php
class Post extends Model
{
    // Enable timestamps (default: true)
    protected bool $timestamps = true;
    
    // Customize column names
    protected string $createdAt = 'created_at';
    protected string $updatedAt = 'modified_at';
}

// Disable timestamps for a specific model
class Setting extends Model
{
    protected bool $timestamps = false;
}
```

## Model Events

While Bob doesn't have built-in events, you can implement them:

```php
class Post extends Model
{
    public function save(): bool
    {
        // Before save logic
        if (!$this->validate()) {
            return false;
        }
        
        // Call parent save
        $result = parent::save();
        
        // After save logic
        if ($result) {
            $this->clearCache();
            $this->notifySubscribers();
        }
        
        return $result;
    }
    
    protected function validate(): bool
    {
        // Validation logic
        return !empty($this->title) && !empty($this->content);
    }
    
    protected function clearCache(): void
    {
        // Clear cache logic
    }
    
    protected function notifySubscribers(): void
    {
        // Notification logic
    }
}
```

## Best Practices

### 1. Use Type Declarations

```php
class Product extends Model
{
    protected string $table = 'products';
    
    public function getPrice(): float
    {
        return (float) $this->price;
    }
    
    public function setPrice(float $price): void
    {
        $this->price = $price;
    }
}
```

### 2. Separate Business Logic

```php
class Order extends Model
{
    public function calculateTotal(): float
    {
        $subtotal = $this->getSubtotal();
        $tax = $this->calculateTax($subtotal);
        $shipping = $this->calculateShipping();
        
        return $subtotal + $tax + $shipping;
    }
    
    protected function getSubtotal(): float
    {
        // Calculate from order items
    }
    
    protected function calculateTax(float $amount): float
    {
        return $amount * 0.1; // 10% tax
    }
    
    protected function calculateShipping(): float
    {
        return $this->shipping_method === 'express' ? 20 : 10;
    }
}
```

### 3. Use Repository Pattern for Complex Queries

```php
class UserRepository
{
    public function findActiveWithPosts(): array
    {
        return User::query()
            ->where('status', 'active')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->select('users.*', 'COUNT(posts.id) as post_count')
            ->groupBy('users.id')
            ->having('post_count', '>', 0)
            ->get();
    }
}
```

## WordPress Integration

Create a base model for WordPress:

```php
abstract class WordPressModel extends Model
{
    protected function getTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . parent::getTable();
    }
}

class WPPost extends WordPressModel
{
    protected string $table = 'posts';
    
    public static function findBySlug(string $slug): ?self
    {
        $result = static::query()
            ->where('post_name', $slug)
            ->where('post_status', 'publish')
            ->first();
        
        return $result ? static::hydrate($result) : null;
    }
    
    public function scopePublished(Builder $query): void
    {
        $query->where('post_status', 'publish')
              ->where('post_type', 'post');
    }
}
```

## Relationships

Bob v2.0 introduces a powerful relationship system that makes it easy to work with related models.

### Defining Relationships

#### One-to-One (HasOne)

A one-to-one relationship links two models with a single association:

```php
class User extends Model
{
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}

class Profile extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// Usage
$user = User::find(1);
$profile = $user->profile; // Automatically loads the related profile
```

#### One-to-Many (HasMany)

A one-to-many relationship is used for parent-child relationships:

```php
class Post extends Model
{
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}

class Comment extends Model
{
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}

// Usage
$post = Post::find(1);
foreach ($post->comments as $comment) {
    echo $comment->content;
}
```

#### Many-to-Many (BelongsToMany)

Many-to-many relationships require a pivot table:

```php
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
}

class Role extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }
}

// Usage
$user = User::find(1);
$roles = $user->roles;

// Working with pivot table
$user->roles()->attach($roleId);
$user->roles()->detach($roleId);
$user->roles()->sync([1, 2, 3]);
```

### Eager Loading

Prevent N+1 query problems with eager loading:

```php
// Load users with their posts and comments
$users = User::with('posts.comments')->get();

// Multiple relationships
$users = User::with(['posts', 'profile', 'roles'])->get();

// Eager loading with constraints
$users = User::with(['posts' => function($query) {
    $query->where('published', true)->orderBy('created_at', 'desc');
}])->get();
```

### Relationship Methods

#### Querying Relations

```php
// Check if relationship exists
if ($user->posts()->exists()) {
    // User has posts
}

// Count related models
$postCount = $user->posts()->count();

// Get specific related models
$recentPosts = $user->posts()
    ->where('created_at', '>=', now()->subDays(7))
    ->get();
```

#### Creating Related Models

```php
// Create a new related model
$comment = $post->comments()->create([
    'content' => 'Great post!',
    'user_id' => auth()->id()
]);

// Save an existing model
$comment = new Comment(['content' => 'Nice!']);
$post->comments()->save($comment);
```

#### Updating Relations

```php
// Update all related models
$user->posts()->update(['published' => true]);

// Delete related models
$post->comments()->delete();
```

### Working with Pivot Tables

For many-to-many relationships, you can work with pivot table data:

```php
// Attach with additional pivot data
$user->roles()->attach($roleId, [
    'assigned_at' => now(),
    'assigned_by' => auth()->id()
]);

// Update pivot data
$user->roles()->updateExistingPivot($roleId, [
    'updated_at' => now()
]);

// Detach roles
$user->roles()->detach([$role1, $role2]);

// Sync roles (detaches all others)
$user->roles()->sync([1, 2, 3]);

// Sync without detaching
$user->roles()->syncWithoutDetaching([1, 2, 3]);
```

### Custom Foreign Keys

You can specify custom foreign keys and local keys:

```php
class Post extends Model
{
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }
}
```

### Relationship Existence Queries

Query models based on relationship existence:

```php
// Get all posts that have comments
$posts = Post::has('comments')->get();

// Get posts with at least 3 comments
$posts = Post::has('comments', '>=', 3)->get();

// Get posts with active comments
$posts = Post::whereHas('comments', function ($query) {
    $query->where('approved', true);
})->get();

// Get posts without comments
$posts = Post::doesntHave('comments')->get();
```

## Collections

Model queries return `Bob\Support\Collection` instances, which provide powerful methods for working with result sets:

```php
$users = User::all();

// Filter users
$admins = $users->filter(function ($user) {
    return $user->role === 'admin';
});

// Map over users
$names = $users->map(function ($user) {
    return $user->name;
});

// Pluck a single column
$emails = $users->pluck('email');

// Sort by a field
$sorted = $users->sortBy('created_at');

// Group by a field
$grouped = $users->groupBy('role');

// Check if collection contains an item
if ($users->contains('email', 'john@example.com')) {
    // User exists
}

// Get first/last items
$first = $users->first();
$last = $users->last();

// Convert to array
$array = $users->toArray();

// Convert to JSON
$json = $users->toJson();
```

## Summary

Bob's Model class in v2.0 provides:

- **ActiveRecord Pattern**: Work with database rows as objects
- **Powerful Relationships**: HasOne, HasMany, BelongsTo, BelongsToMany
- **Eager Loading**: Prevent N+1 queries with intelligent loading
- **Collections**: Rich collection class for working with result sets
- **Query Builder Integration**: Full access to query builder methods
- **Custom Methods**: Define model-specific business logic
- **Scopes**: Reusable query constraints
- **Timestamps**: Automatic created_at/updated_at handling
- **Extensibility**: Easy to extend and customize

The Model class bridges the gap between object-oriented programming and relational databases, making it easy to work with your data in a natural, intuitive way.