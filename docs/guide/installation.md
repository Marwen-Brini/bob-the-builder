# Installation

## Requirements

Before installing Bob Query Builder, ensure your environment meets these requirements:

- **PHP**: 8.1 or higher
- **PDO Extension**: Required for database connectivity
- **Database**: One of the following:
  - MySQL 5.7+ or MariaDB 10.2+
  - PostgreSQL 9.6+
  - SQLite 3.8.8+

## Installation via Composer

Bob Query Builder is available on Packagist and can be installed using Composer:

```bash
composer require marwen-brini/bob-the-builder
```

## Manual Installation

If you prefer to install manually or are working in an environment without Composer:

1. Download the latest release from [GitHub](https://github.com/Marwen-Brini/bob-the-builder/releases)
2. Extract the files to your project
3. Include the autoloader:

```php
require_once 'path/to/bob-the-builder/vendor/autoload.php';
```

## Verify Installation

After installation, verify everything is working:

```php
<?php
require 'vendor/autoload.php';

use Bob\Database\Connection;

// Test with SQLite (no server required)
$connection = new Connection([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);

// Create a test table
$connection->statement('CREATE TABLE test (id INTEGER, name TEXT)');

// Insert test data
$connection->table('test')->insert(['id' => 1, 'name' => 'Bob']);

// Query the data
$result = $connection->table('test')->first();
echo "Success! Found: " . $result->name; // Outputs: Success! Found: Bob
```

## Database Driver Installation

### MySQL/MariaDB

Ensure the PDO MySQL extension is installed:

```bash
# Ubuntu/Debian
sudo apt-get install php8.1-mysql

# CentOS/RHEL/Fedora
sudo yum install php-mysqlnd

# macOS with Homebrew
brew install php@8.1
```

Verify installation:
```php
if (extension_loaded('pdo_mysql')) {
    echo "PDO MySQL is installed";
}
```

### PostgreSQL

Ensure the PDO PostgreSQL extension is installed:

```bash
# Ubuntu/Debian
sudo apt-get install php8.1-pgsql

# CentOS/RHEL/Fedora
sudo yum install php-pgsql

# macOS with Homebrew
brew install php@8.1 --with-postgresql
```

Verify installation:
```php
if (extension_loaded('pdo_pgsql')) {
    echo "PDO PostgreSQL is installed";
}
```

### SQLite

SQLite support is usually included with PHP, but if not:

```bash
# Ubuntu/Debian
sudo apt-get install php8.1-sqlite3

# CentOS/RHEL/Fedora
sudo yum install php-pdo

# macOS - usually included
```

Verify installation:
```php
if (extension_loaded('pdo_sqlite')) {
    echo "PDO SQLite is installed";
}
```

## Development Installation

For contributing or testing:

```bash
# Clone the repository
git clone https://github.com/Marwen-Brini/bob-the-builder.git
cd bob-the-builder

# Install dependencies
composer install

# Run tests
composer test

# Build documentation
npm install
npm run docs:build
```

## Docker Installation

If you're using Docker, here's a sample Dockerfile:

```dockerfile
FROM php:8.1-cli

# Install PDO extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Bob Query Builder
RUN composer require marwen-brini/bob-the-builder

WORKDIR /app
```

## WordPress Installation

For WordPress projects, install Bob in your plugin or theme:

```bash
# Navigate to your plugin directory
cd wp-content/plugins/your-plugin

# Install Bob
composer require marwen-brini/bob-the-builder
```

Then in your plugin:

```php
// Load Composer autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use Bob\Database\Connection;

// Use WordPress database credentials
global $wpdb;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'charset' => DB_CHARSET,
    'collation' => DB_COLLATE ?: 'utf8mb4_unicode_ci',
    'prefix' => $wpdb->prefix,
]);
```

## Troubleshooting Installation

### Common Issues

**1. Class not found errors**
```bash
# Regenerate autoloader
composer dump-autoload
```

**2. PDO not available**
```bash
# Check PHP configuration
php -m | grep pdo
```

**3. Permission errors**
```bash
# Fix permissions
chmod -R 755 vendor/
```

**4. Memory limit errors during installation**
```bash
# Increase memory limit
php -d memory_limit=-1 $(which composer) require marwen-brini/bob-the-builder
```

## Next Steps

After installation, proceed to:
- [Configuration](/guide/configuration) - Set up your database connection
- [Quick Start](/guide/quick-start) - Build your first queries
- [Query Builder](/guide/query-builder) - Learn the basics