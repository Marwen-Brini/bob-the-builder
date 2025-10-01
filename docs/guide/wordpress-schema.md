# WordPress Schema Helpers

Bob includes specialized helpers for creating WordPress and WooCommerce compatible database tables. The `WordPressBlueprint` class extends the standard `Blueprint` with WordPress-specific column types and table presets.

## Introduction

WordPress has specific conventions for table structures, especially for:

- Custom post types
- User meta tables
- Taxonomy tables
- WooCommerce HPOS (High-Performance Order Storage) tables

Bob's WordPress helpers make it easy to create tables that follow these conventions perfectly.

## Getting Started

### Basic Usage

Use `Schema::createWordPress()` instead of `Schema::create()`:

```php
use Bob\Schema\Schema;
use Bob\Schema\WordPressBlueprint;

Schema::createWordPress('custom_posts', function (WordPressBlueprint $table) {
    $table->wpPost();  // Adds all standard WordPress post columns
});
```

### Setting Up Connection

For WordPress, use the global `$wpdb` object for configuration:

```php
use Bob\Database\Connection;
use Bob\Schema\Schema;

global $wpdb;

$connection = new Connection([
    'driver' => 'mysql',
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'prefix' => $wpdb->prefix,  // Uses wp_ or custom prefix
]);

Schema::setConnection($connection);
```

## WordPress Column Helpers

### ID Column

```php
$table->wpId();              // Creates: ID BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
$table->wpId('product_id');  // Custom column name
```

### Author Column

```php
$table->wpAuthor();               // Creates: post_author BIGINT UNSIGNED DEFAULT 0 with index
$table->wpAuthor('author_id');    // Custom column name
```

### Date Columns

```php
// Single datetime column
$table->wpDatetime('post_date');               // DATETIME DEFAULT '0000-00-00 00:00:00'
$table->wpDatetimeGmt('post_date_gmt');        // GMT version

// All four date columns at once
$table->wpDates();                              // Creates:
// - post_date
// - post_date_gmt
// - post_modified
// - post_modified_gmt

// Custom prefix
$table->wpDates('event');                      // Creates:
// - event_date
// - event_date_gmt
// - event_modified
// - event_modified_gmt

// Alias
$table->wpTimestamps();                        // Same as wpDates()
```

### Content Columns

```php
$table->wpTitle();              // TEXT for post_title
$table->wpContent();            // LONGTEXT for post_content
$table->wpExcerpt();            // TEXT for post_excerpt
```

### Status and Visibility

```php
$table->wpStatus();             // VARCHAR(20) DEFAULT 'publish' with index
$table->wpSlug();               // VARCHAR(200) DEFAULT '' for post_name, with index
$table->wpGuid();               // VARCHAR(255) DEFAULT '' for GUID
$table->wpCommentStatus();      // VARCHAR(20) DEFAULT 'open'
$table->wpPingStatus();         // VARCHAR(20) DEFAULT 'open'
$table->wpPassword();           // VARCHAR(255) DEFAULT '' for post_password
```

### Hierarchy

```php
$table->wpParent();             // BIGINT UNSIGNED DEFAULT 0 for post_parent, with index
$table->wpMenuOrder();          // INT DEFAULT 0 for menu_order
```

### Metadata

```php
$table->wpMimeType();           // VARCHAR(100) DEFAULT '' for post_mime_type
$table->wpCommentCount();       // BIGINT DEFAULT 0 for comment_count
$table->wpPostType();           // VARCHAR(20) DEFAULT 'post' for post_type
```

## Complete Table Presets

### WordPress Post Table

Creates all standard WordPress post columns:

```php
Schema::createWordPress('custom_posts', function (WordPressBlueprint $table) {
    $table->wpPost();
    $table->wpPostIndexes();  // Adds standard indexes
});
```

This creates:
- ID (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY)
- post_author (with index)
- post_date, post_date_gmt, post_modified, post_modified_gmt
- post_content (LONGTEXT)
- post_title (TEXT)
- post_excerpt (TEXT)
- post_status (with index)
- post_name (slug, with index)
- post_parent (with index)
- guid
- menu_order
- post_type
- post_mime_type
- comment_status, ping_status, post_password
- to_ping, pinged (TEXT)
- post_content_filtered (LONGTEXT)
- comment_count

### WordPress User Table

```php
Schema::createWordPress('custom_users', function (WordPressBlueprint $table) {
    $table->wpUser();
});
```

Creates:
- ID
- user_login (VARCHAR(60), indexed)
- user_pass
- user_nicename (VARCHAR(50), indexed)
- user_email (VARCHAR(100), indexed)
- user_url (VARCHAR(100))
- user_registered (DATETIME)
- user_activation_key
- user_status (INT)
- display_name (VARCHAR(250))

### WordPress Meta Table

```php
Schema::createWordPress('custom_meta', function (WordPressBlueprint $table) {
    $table->wpMeta('custom');    // Object type: custom
});
```

Creates:
- meta_id (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY)
- custom_id (BIGINT UNSIGNED, indexed) - Uses the object type parameter
- meta_key (VARCHAR(255) NULLABLE, indexed)
- meta_value (LONGTEXT NULLABLE)

Examples for different meta types:
```php
$table->wpMeta('post');      // Creates post_id column
$table->wpMeta('user');      // Creates user_id column
$table->wpMeta('comment');   // Creates comment_id column
$table->wpMeta('product');   // Creates product_id column
```

### WordPress Taxonomy Tables

#### Terms Table

```php
Schema::createWordPress('custom_terms', function (WordPressBlueprint $table) {
    $table->wpTerm();
});
```

Creates:
- term_id (BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY)
- name (VARCHAR(200), indexed)
- slug (VARCHAR(200), indexed)
- term_group (BIGINT)

#### Term Taxonomy Table

```php
Schema::createWordPress('custom_term_taxonomy', function (WordPressBlueprint $table) {
    $table->wpTaxonomy();
});
```

Creates:
- term_taxonomy_id (PRIMARY KEY)
- term_id
- taxonomy (VARCHAR(32), indexed)
- description (LONGTEXT)
- parent (BIGINT UNSIGNED)
- count (BIGINT)
- Unique constraint on (term_id, taxonomy)

#### Term Relationships Table

```php
Schema::createWordPress('custom_term_relationships', function (WordPressBlueprint $table) {
    $table->wpTermRelationship();
});
```

Creates:
- object_id
- term_taxonomy_id
- term_order
- Primary key on (object_id, term_taxonomy_id)
- Index on term_taxonomy_id

### WordPress Options Table

```php
Schema::createWordPress('custom_options', function (WordPressBlueprint $table) {
    $table->wpOption();
});
```

Creates:
- option_id (PRIMARY KEY)
- option_name (VARCHAR(191), unique)
- option_value (LONGTEXT)
- autoload (VARCHAR(20), indexed, default 'yes')

### WordPress Comments Table

```php
Schema::createWordPress('custom_comments', function (WordPressBlueprint $table) {
    $table->wpComment();
});
```

Creates all standard comment columns with appropriate indexes.

## Foreign Key Helpers

### Post Foreign Key

```php
Schema::createWordPress('custom_meta', function (WordPressBlueprint $table) {
    $table->wpMeta('custom');
    $table->wpForeignPost('post_id', 'posts');  // Links to wp_posts
});
```

Creates an unsigned BIGINT column with a foreign key constraint:
- References: posts.ID
- On Delete: CASCADE

### User Foreign Key

```php
Schema::createWordPress('user_subscriptions', function (WordPressBlueprint $table) {
    $table->id();
    $table->wpForeignUser('user_id', 'users');  // Links to wp_users
    $table->string('plan');
    $table->timestamps();
});
```

### Term Foreign Key

```php
Schema::createWordPress('product_terms', function (WordPressBlueprint $table) {
    $table->id();
    $table->wpForeignTerm('term_id', 'terms');  // Links to wp_terms
    $table->foreignId('product_id')->constrained();
});
```

## WooCommerce Helpers

### WooCommerce Order Table (HPOS)

```php
Schema::createWordPress('wc_custom_orders', function (WordPressBlueprint $table) {
    $table->wcOrder();
});
```

Creates all WooCommerce HPOS order columns:
- order_id (PRIMARY KEY)
- order_key (VARCHAR(100), unique)
- customer_id (indexed)
- Billing fields (first_name, last_name, company, address, city, state, postcode, country, email, phone)
- Shipping fields (same as billing)
- Payment fields (method, method_title, transaction_id)
- Customer info (ip_address, user_agent, created_via, customer_note)
- Dates (created, updated, completed, paid - all indexed)
- Financial data (discount_total, discount_tax, shipping_total, shipping_tax, cart_tax, total - all DECIMAL(12,2))
- status (VARCHAR(20), indexed)
- currency (VARCHAR(10))
- And more...

### WooCommerce Order Items

```php
Schema::createWordPress('wc_custom_order_items', function (WordPressBlueprint $table) {
    $table->wcOrderItem();
});
```

Creates:
- order_item_id (PRIMARY KEY)
- order_item_name
- order_item_type
- order_id (with foreign key to wc_orders)

### HPOS Structure

For a complete HPOS-compatible structure:

```php
Schema::createWordPress('wc_orders', function (WordPressBlueprint $table) {
    $table->wcHposStructure();
});
```

This includes all order columns plus HPOS-specific indexes.

## Complete Examples

### Custom Post Type with Meta

```php
// Main posts table
Schema::createWordPress('products', function (WordPressBlueprint $table) {
    $table->wpPost();
    $table->wpPostIndexes();
});

// Meta table
Schema::createWordPress('productmeta', function (WordPressBlueprint $table) {
    $table->wpMeta('product');
    $table->wpForeignPost('product_id', 'products');
});
```

### Custom User System

```php
// Extended users table
Schema::createWordPress('vendors', function (WordPressBlueprint $table) {
    $table->id();
    $table->wpForeignUser('user_id', 'users');
    $table->string('company_name');
    $table->string('tax_id')->nullable();
    $table->decimal('commission_rate', 5, 2)->default(10.00);
    $table->boolean('is_verified')->default(false);
    $table->timestamps();
});

// Vendor meta table
Schema::createWordPress('vendormeta', function (WordPressBlueprint $table) {
    $table->wpMeta('vendor');
});
```

### Custom Taxonomy System

```php
// Terms
Schema::createWordPress('product_terms', function (WordPressBlueprint $table) {
    $table->wpTerm();
});

// Term taxonomy
Schema::createWordPress('product_term_taxonomy', function (WordPressBlueprint $table) {
    $table->wpTaxonomy();
});

// Term relationships
Schema::createWordPress('product_term_relationships', function (WordPressBlueprint $table) {
    $table->wpTermRelationship();
});
```

### WooCommerce Extension

```php
// Custom order table
Schema::createWordPress('wc_subscriptions', function (WordPressBlueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained('wc_orders');
    $table->foreignId('product_id')->constrained('posts');  // WooCommerce products
    $table->enum('status', ['active', 'cancelled', 'expired'])->default('active');
    $table->timestamp('next_payment_date')->nullable();
    $table->integer('billing_period')->default(1);
    $table->enum('billing_interval', ['day', 'week', 'month', 'year'])->default('month');
    $table->timestamps();

    $table->index(['order_id', 'status']);
});
```

## WordPress Integration in Plugins

### Plugin Activation Hook

```php
// my-plugin.php

use Bob\Database\Connection;
use Bob\Schema\Schema;
use Bob\Schema\WordPressBlueprint;

function my_plugin_create_tables() {
    global $wpdb;

    $connection = new Connection([
        'driver' => 'mysql',
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASSWORD,
        'prefix' => $wpdb->prefix,
    ]);

    Schema::setConnection($connection);

    // Create custom tables
    Schema::createWordPress('my_plugin_data', function (WordPressBlueprint $table) {
        $table->id();
        $table->wpForeignUser('user_id');
        $table->wpForeignPost('post_id');
        $table->string('custom_field');
        $table->timestamps();

        $table->index(['user_id', 'post_id']);
    });
}

register_activation_hook(__FILE__, 'my_plugin_create_tables');
```

### With Migrations

```php
use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\WordPressBlueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWordPress('my_plugin_posts', function (WordPressBlueprint $table) {
            $table->wpPost();
            $table->wpPostIndexes();
        });

        Schema::createWordPress('my_plugin_postmeta', function (WordPressBlueprint $table) {
            $table->wpMeta('my_plugin_post');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_plugin_postmeta');
        Schema::dropIfExists('my_plugin_posts');
    }
};
```

## Combining Standard and WordPress Helpers

You can mix standard Blueprint methods with WordPress helpers:

```php
Schema::createWordPress('events', function (WordPressBlueprint $table) {
    // WordPress columns
    $table->wpId();
    $table->wpTitle();
    $table->wpContent();
    $table->wpStatus();
    $table->wpDates();

    // Custom columns
    $table->dateTime('event_start');
    $table->dateTime('event_end');
    $table->string('venue');
    $table->decimal('price', 8, 2)->default(0);
    $table->integer('capacity')->nullable();
    $table->json('metadata')->nullable();

    // Indexes
    $table->index('event_start');
    $table->index(['status', 'event_start']);
});
```

## Best Practices

### 1. Use Table Prefixes

Always use the WordPress table prefix:

```php
global $wpdb;
$connection = new Connection([
    'prefix' => $wpdb->prefix,  // Essential for WordPress compatibility
]);
```

### 2. Follow WordPress Naming Conventions

```php
// Good
Schema::createWordPress('products', ...);        // Singular or plural
Schema::createWordPress('productmeta', ...);     // No underscore for meta

// Avoid
Schema::createWordPress('product_data', ...);    // WordPress typically doesn't use underscores
```

### 3. Use Foreign Keys for Data Integrity

```php
Schema::createWordPress('bookings', function (WordPressBlueprint $table) {
    $table->id();
    $table->wpForeignUser('user_id');           // Links to users
    $table->wpForeignPost('event_id', 'events'); // Links to custom post type
    $table->timestamps();
});
```

### 4. Add Appropriate Indexes

WordPress relies heavily on indexes for performance:

```php
Schema::createWordPress('logs', function (WordPressBlueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users');
    $table->string('action');
    $table->timestamps();

    // Add indexes for common queries
    $table->index('user_id');
    $table->index('action');
    $table->index('created_at');
    $table->index(['user_id', 'action']);
});
```

### 5. Consider Multisite

If supporting WordPress Multisite, tables can be site-specific or global:

```php
// Site-specific table (uses prefix like wp_2_)
global $wpdb;
$table_name = $wpdb->prefix . 'custom_data';

// Global table (uses base prefix like wp_)
$global_table = $wpdb->base_prefix . 'global_data';
```

## Next Steps

- Learn about [Schema Builder](schema-builder.md) for standard tables
- Explore [Database Migrations](migrations.md) for version control
- Check out [Schema Inspector](schema-inspector.md) for reverse engineering
