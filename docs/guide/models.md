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

## Summary

Bob's Model class provides:

- **ActiveRecord Pattern**: Work with database rows as objects
- **Query Builder Integration**: Full access to query builder methods
- **Custom Methods**: Define model-specific business logic
- **Scopes**: Reusable query constraints
- **Timestamps**: Automatic created_at/updated_at handling
- **Extensibility**: Easy to extend and customize

The Model class bridges the gap between object-oriented programming and relational databases, making it easy to work with your data in a natural, intuitive way.