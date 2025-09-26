---
layout: home

hero:
  name: Bob ORM v2.1.1
  text: Complete ORM & Query Builder for PHP
  tagline: A full-featured ORM with models, relationships, and Laravel-like query builder - now with enhanced table prefix handling and forceFill() method
  image:
    src: /logo.svg
    alt: Bob Query Builder
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/Marwen-Brini/bob-the-builder

features:
  - icon: 🏗️
    title: Full ORM with Models
    details: ActiveRecord pattern with Model base class, CRUD operations, and attribute handling
  - icon: 🔗
    title: Powerful Relationships
    details: HasOne, HasMany, BelongsTo, BelongsToMany with eager loading and N+1 prevention
  - icon: ⚡
    title: Blazing Fast
    details: Optimized for performance with prepared statement caching, connection pooling, and minimal overhead
  - icon: 🎯
    title: Laravel-like Syntax
    details: Familiar fluent interface and Eloquent-style models that PHP developers already know and love
  - icon: 🗄️
    title: Multi-Database Support
    details: MySQL, PostgreSQL, and SQLite support out of the box with PDO
  - icon: 🔧
    title: Framework Agnostic
    details: Works with any PHP project - WordPress, Laravel, Symfony, or standalone applications
  - icon: 📦
    title: Collections
    details: Powerful collection class for working with arrays of models and data
  - icon: 🔌
    title: Highly Extensible
    details: Add custom methods, scopes, and finders through macros and model extensions
  - icon: 📊
    title: Query Profiling
    details: Built-in query profiling and logging for debugging and optimization
  - icon: 🚀
    title: Modern PHP
    details: Built for PHP 8.1+ with type hints, attributes, and modern best practices
  - icon: 🧪
    title: 100% Test Coverage
    details: Over 1400 tests ensuring reliability and stability
  - icon: 📚
    title: Great Documentation
    details: Comprehensive guides, API reference, and real-world examples
---

## Quick Start

```php
use Bob\Database\Connection;

// Configure your connection
$connection = new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password',
]);

// Start building queries
$users = $connection->table('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

## Model Example

```php
use Bob\Database\Model;

class Post extends Model
{
    protected string $table = 'posts';
    
    public static function findBySlug(string $slug): ?self
    {
        $result = static::query()
            ->where('slug', $slug)
            ->first();
        
        return $result ? static::hydrate($result) : null;
    }
}

// Use your custom methods
$post = Post::findBySlug('hello-world');
```

## Why Bob?

Bob Query Builder was born from the need for a powerful, standalone query builder that could enhance Quantum ORM's capabilities. But it quickly evolved into something more - a fully independent, framework-agnostic solution that brings Laravel's elegant query building to any PHP project.

### Perfect For:

- **WordPress Plugins** - Replace slow wpdb queries with optimized prepared statements
- **Microservices** - Lightweight database layer without framework overhead  
- **Legacy Modernization** - Gradually modernize database code without full rewrites
- **Performance Critical Apps** - Connection pooling, caching, and profiling built-in
- **Learning Projects** - Clean, well-documented code that's easy to understand

## Installation

Install Bob via Composer:

```bash
composer require marwen-brini/bob-the-builder
```

That's it! No complex configuration or bootstrapping required.

## Community

Join our growing community of developers using Bob Query Builder:

- 🐛 [Report Issues](https://github.com/Marwen-Brini/bob-the-builder/issues)
- 💡 [Request Features](https://github.com/Marwen-Brini/bob-the-builder/discussions)
- 🤝 [Contribute](https://github.com/Marwen-Brini/bob-the-builder/pulls)
- ⭐ [Star on GitHub](https://github.com/Marwen-Brini/bob-the-builder)