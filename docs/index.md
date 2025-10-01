---
layout: home

hero:
  name: Bob ORM v3.0.0
  text: Complete Database Toolkit for PHP
  tagline: Full-featured ORM with models, relationships, migrations, and schema builder - Your complete database solution for PHP
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
  - icon: 🗄️
    title: Database Migrations
    details: Version control for your database with dependency resolution, rollbacks, and batch tracking
  - icon: 🏗️
    title: Schema Builder
    details: Fluent interface for creating and modifying tables across MySQL, PostgreSQL, and SQLite
  - icon: 🌐
    title: WordPress Schema Helpers
    details: Pre-built helpers for WordPress and WooCommerce table structures - instant compatibility
  - icon: 🔍
    title: Schema Inspector
    details: Reverse engineer existing databases and auto-generate migration files
  - icon: 🎯
    title: Full ORM with Models
    details: ActiveRecord pattern with Model base class, CRUD operations, and attribute handling
  - icon: 🔗
    title: Powerful Relationships
    details: HasOne, HasMany, BelongsTo, BelongsToMany with eager loading and N+1 prevention
  - icon: ⚡
    title: Blazing Fast
    details: Optimized for performance with prepared statement caching, connection pooling, and minimal overhead
  - icon: 🎪
    title: Event System
    details: Hook into migration lifecycle for logging, monitoring, and custom workflows
  - icon: 📦
    title: Collections
    details: Powerful collection class for working with arrays of models and data
  - icon: 🔧
    title: Framework Agnostic
    details: Works with any PHP project - WordPress, Laravel, Symfony, or standalone applications
  - icon: 🧪
    title: 100% Test Coverage
    details: Over 1773 tests ensuring reliability and stability
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