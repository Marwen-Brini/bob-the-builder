<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

beforeEach(function () {
    // Check if MySQL configuration exists
    if (!file_exists(__DIR__ . '/../../config/database.php')) {
        $this->skipTests = true;
        return;
    }

    $config = require __DIR__ . '/../../config/database.php';

    if (!isset($config['mysql'])) {
        $this->skipTests = true;
        return;
    }

    try {
        $this->connection = new Connection($config['mysql']);
        Schema::setConnection($this->connection);
        dropMySQLTestTables();
        $this->skipTests = false;
    } catch (Exception $e) {
        $this->skipTests = true;
    }
});

afterEach(function () {
    if (!$this->skipTests) {
        dropMySQLTestTables();
    }
});

function dropMySQLTestTables(): void
{
    Schema::disableForeignKeyConstraints();

    $tables = ['posts', 'users', 'test_table', 'products', 'orders',
              'categories', 'tags', 'comments', 'renamed_table'];

    foreach ($tables as $table) {
        try {
            Schema::dropIfExists($table);
        } catch (Exception $e) {
            // Ignore errors during cleanup
        }
    }

    Schema::enableForeignKeyConstraints();
}

test('create table with engine', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_unicode_ci';
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();

    // Check table properties
    $result = $this->connection->select("SHOW TABLE STATUS LIKE 'test_table'");
    expect($result)->not->toBeEmpty();
    expect($result[0]->Engine)->toBe('InnoDB');
})->group('mysql', 'integration-mysql');

test('all mysql column types', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();

        // String types
        $table->char('char_col', 10);
        $table->string('varchar_col');
        $table->tinyText('tinytext_col');
        $table->text('text_col');
        $table->mediumText('mediumtext_col');
        $table->longText('longtext_col');

        // Numeric types
        $table->tinyInteger('tinyint_col');
        $table->smallInteger('smallint_col');
        $table->mediumInteger('mediumint_col');
        $table->integer('int_col');
        $table->bigInteger('bigint_col');
        $table->unsignedTinyInteger('unsigned_tinyint');
        $table->unsignedInteger('unsigned_int');
        $table->unsignedBigInteger('unsigned_bigint');
        $table->float('float_col', 8, 2);
        $table->double('double_col', 15, 4);
        $table->decimal('decimal_col', 10, 2);

        // Date/Time types
        $table->date('date_col');
        $table->dateTime('datetime_col');
        $table->timestamp('timestamp_col');
        $table->time('time_col');
        $table->year('year_col');

        // Other types
        $table->boolean('bool_col');
        $table->enum('enum_col', ['option1', 'option2', 'option3']);
        $table->set('set_col', ['read', 'write', 'delete']);
        $table->json('json_col');
        $table->binary('binary_col');
        $table->uuid('uuid_col');
        $table->ipAddress('ip_col');
        $table->macAddress('mac_col');
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();

    $columns = Schema::getColumnListing('test_table');
    expect(count($columns))->toBeGreaterThanOrEqual(31);
})->group('mysql', 'integration-mysql');

test('mysql specific modifiers', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name')->charset('utf8')->collation('utf8_general_ci');
        $table->string('email')->comment('User email address');
        $table->integer('views')->unsigned()->zerofill();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrentOnUpdate();
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();
})->group('mysql', 'integration-mysql');

test('virtual and stored columns', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    // Skip for MySQL < 5.7
    $version = $this->connection->select('SELECT VERSION() as version')[0]->version;
    if (version_compare($version, '5.7.0', '<')) {
        $this->markTestSkipped('Virtual columns require MySQL 5.7+');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->decimal('price', 10, 2);
        $table->integer('quantity');
        $table->decimal('total', 10, 2)->storedAs('price * quantity');
        $table->string('price_display')->virtualAs("CONCAT('$', price)");
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();

    // Insert test data
    $this->connection->table('test_table')->insert([
        'price' => 10.50,
        'quantity' => 3,
    ]);

    $result = $this->connection->table('test_table')->first();
    expect((float) $result->total)->toBe(31.50);
    expect($result->price_display)->toBe('$10.50');
})->group('mysql', 'integration-mysql');

test('fulltext index', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->fulltext(['title', 'content']);
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();

    // Test fulltext search
    $this->connection->table('test_table')->insert([
        ['title' => 'MySQL Tutorial', 'content' => 'Learn about MySQL database'],
        ['title' => 'PHP Guide', 'content' => 'PHP programming with MySQL'],
        ['title' => 'Laravel Docs', 'content' => 'Laravel framework documentation'],
    ]);

    $results = $this->connection->select(
        "SELECT * FROM test_table WHERE MATCH(title, content) AGAINST('MySQL' IN NATURAL LANGUAGE MODE)"
    );

    expect($results)->toHaveCount(2);
})->group('mysql', 'integration-mysql');

test('spatial types', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    // Skip for MySQL < 5.7
    $version = $this->connection->select('SELECT VERSION() as version')[0]->version;
    if (version_compare($version, '5.7.0', '<')) {
        $this->markTestSkipped('Spatial types require MySQL 5.7+');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->geometry('location');
        $table->point('coordinates');
        $table->lineString('route');
        $table->polygon('area');
        $table->spatialIndex('location');
    });

    expect(Schema::hasTable('test_table'))->toBeTrue();
})->group('mysql', 'integration-mysql');

test('foreign key constraints', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->foreignId('user_id')
            ->constrained('users')
            ->cascadeOnUpdate()
            ->cascadeOnDelete();
        $table->timestamps();
    });

    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('posts'))->toBeTrue();

    // Test cascade delete
    $userId = $this->connection->table('users')->insertGetId([
        'name' => 'John Doe',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $postId = $this->connection->table('posts')->insertGetId([
        'title' => 'Test Post',
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Delete user should cascade delete posts
    $this->connection->table('users')->where('id', $userId)->delete();

    $postCount = $this->connection->table('posts')->count();
    expect($postCount)->toBe(0);
})->group('mysql', 'integration-mysql');

test('modify table', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::table('test_table', function (Blueprint $table) {
        $table->string('email')->after('name');
        $table->integer('age')->nullable();
        $table->boolean('active')->default(true)->first();
    });

    $columns = Schema::getColumnListing('test_table');
    expect($columns)->toContain('email');
    expect($columns)->toContain('age');
    expect($columns)->toContain('active');
})->group('mysql', 'integration-mysql');

test('drop column', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->integer('age');
    });

    Schema::table('test_table', function (Blueprint $table) {
        $table->dropColumn('age');
        $table->dropColumn(['email']);
    });

    expect(Schema::hasColumn('test_table', 'age'))->toBeFalse();
    expect(Schema::hasColumn('test_table', 'email'))->toBeFalse();
    expect(Schema::hasColumn('test_table', 'name'))->toBeTrue();
})->group('mysql', 'integration-mysql');

test('rename column', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('old_name');
    });

    Schema::table('test_table', function (Blueprint $table) {
        $table->renameColumn('old_name', 'new_name');
    });

    expect(Schema::hasColumn('test_table', 'old_name'))->toBeFalse();
    expect(Schema::hasColumn('test_table', 'new_name'))->toBeTrue();
})->group('mysql', 'integration-mysql');

test('index management', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id();
        $table->string('email');
        $table->string('username');
        $table->integer('age');
    });

    Schema::table('test_table', function (Blueprint $table) {
        $table->unique('email', 'unique_email');
        $table->index('username', 'idx_username');
        $table->index(['username', 'age'], 'composite_idx');
    });

    // Check indexes exist
    $indexes = $this->connection->select("SHOW INDEX FROM test_table");
    $indexNames = array_map(fn($idx) => $idx->Key_name, $indexes);

    expect($indexNames)->toContain('unique_email');
    expect($indexNames)->toContain('idx_username');
    expect($indexNames)->toContain('composite_idx');

    // Drop indexes
    Schema::table('test_table', function (Blueprint $table) {
        $table->dropUnique('unique_email');
        $table->dropIndex('idx_username');
        $table->dropIndex('composite_idx');
    });
})->group('mysql', 'integration-mysql');

test('auto increment start value', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('test_table', function (Blueprint $table) {
        $table->id()->from(1000);
        $table->string('name');
    });

    $this->connection->table('test_table')->insert(['name' => 'Test']);
    $result = $this->connection->table('test_table')->first();

    expect($result->id)->toBeGreaterThanOrEqual(1000);
})->group('mysql', 'integration-mysql');

test('complex wordpress like table', function () {
    if ($this->skipTests ?? true) {
        $this->markTestSkipped('MySQL configuration not available or connection failed');
    }

    Schema::create('posts', function (Blueprint $table) {
        $table->bigIncrements('ID');
        $table->unsignedBigInteger('post_author')->default(0)->index();
        $table->dateTime('post_date')->nullable();
        $table->dateTime('post_date_gmt')->nullable();
        $table->longText('post_content');
        $table->text('post_title');
        $table->text('post_excerpt');
        $table->string('post_status', 20)->default('publish');
        $table->string('comment_status', 20)->default('open');
        $table->string('ping_status', 20)->default('open');
        $table->string('post_password')->default('');
        $table->string('post_name', 200)->default('')->index();
        $table->text('to_ping');
        $table->text('pinged');
        $table->dateTime('post_modified')->nullable();
        $table->dateTime('post_modified_gmt')->nullable();
        $table->longText('post_content_filtered');
        $table->unsignedBigInteger('post_parent')->default(0)->index();
        $table->string('guid')->default('');
        $table->integer('menu_order')->default(0);
        $table->string('post_type', 20)->default('post');
        $table->string('post_mime_type', 100)->default('');
        $table->bigInteger('comment_count')->default(0);

        // Composite indexes
        $table->index(['post_type', 'post_status', 'post_date', 'ID']);
    });

    expect(Schema::hasTable('posts'))->toBeTrue();

    // Test inserting a post
    $postId = $this->connection->table('posts')->insertGetId([
        'post_author' => 1,
        'post_date' => now(),
        'post_date_gmt' => now(),
        'post_content' => 'Test content',
        'post_title' => 'Test Post',
        'post_excerpt' => 'Test excerpt',
        'post_status' => 'publish',
        'comment_status' => 'open',
        'ping_status' => 'open',
        'post_name' => 'test-post',
        'to_ping' => '',
        'pinged' => '',
        'post_modified' => now(),
        'post_modified_gmt' => now(),
        'post_content_filtered' => '',
        'guid' => 'http://example.com/?p=1',
    ]);

    expect($postId)->toBeGreaterThan(0);

    $post = $this->connection->table('posts')->find($postId);
    expect($post->post_title)->toBe('Test Post');
    expect($post->post_status)->toBe('publish');
})->group('mysql', 'integration-mysql');

/**
 * Helper function to get current timestamp
 */
function now(): string
{
    return date('Y-m-d H:i:s');
}