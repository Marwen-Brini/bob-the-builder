<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Connection;
use Bob\Schema\Blueprint;
use Bob\Schema\Fluent;
use Bob\Schema\Grammars\MySQLGrammar;

beforeEach(function () {
    $this->grammar = new MySQLGrammar;
    $this->connection = new Connection([
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test',
    ]);
});

test('compile create', function () {
    $blueprint = new Blueprint('users');
    $blueprint->create();
    $blueprint->id();
    $blueprint->string('name');

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    expect($sql)->toContain('create table `users`');
    expect($sql)->toContain('`id` bigint unsigned not null auto_increment primary key');
    expect($sql)->toContain('`name` varchar(255) not null');
});

test('compile create with engine', function () {
    $blueprint = new Blueprint('users');
    $blueprint->create();
    $blueprint->id();
    $blueprint->engine = 'InnoDB';
    $blueprint->charset = 'utf8mb4';
    $blueprint->collation = 'utf8mb4_unicode_ci';

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    expect($sql)->toContain('engine = InnoDB');
    expect($sql)->toContain('default character set utf8mb4');
    expect($sql)->toContain('collate \'utf8mb4_unicode_ci\'');
});

test('compile add', function () {
    $blueprint = new Blueprint('users');
    $blueprint->string('email');

    $command = new Fluent(['name' => 'add']);
    $sql = $this->grammar->compileAdd($blueprint, $command, $this->connection);

    expect($sql)->toContain('alter table `users` add `email` varchar(255) not null');
});

test('compile change', function () {
    $blueprint = new Blueprint('users');
    $blueprint->string('name', 100)->change();

    $command = new Fluent(['name' => 'change']);
    $sql = $this->grammar->compileChange($blueprint, $command, $this->connection);

    expect($sql)->toContain('alter table `users` change `name` `name` varchar(100) not null');
});

test('compile drop', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'drop']);
    $sql = $this->grammar->compileDrop($blueprint, $command, $this->connection);

    expect($sql)->toBe('drop table `users`');
});

test('compile drop if exists', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'dropIfExists']);
    $sql = $this->grammar->compileDropIfExists($blueprint, $command, $this->connection);

    expect($sql)->toBe('drop table if exists `users`');
});

test('compile rename', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'rename', 'to' => 'customers']);
    $sql = $this->grammar->compileRename($blueprint, $command, $this->connection);

    expect($sql)->toBe('rename table `users` to `customers`');
});

test('compile drop column', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'dropColumn', 'columns' => ['name', 'email']]);
    $sql = $this->grammar->compileDropColumn($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `users` drop `name`, drop `email`');
});

test('compile rename column', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'renameColumn', 'from' => 'name', 'to' => 'full_name']);
    $sql = $this->grammar->compileRenameColumn($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `users` change `name` `full_name` varchar(255)');
});

test('compile index', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent([
        'name' => 'index',
        'index' => 'users_email_index',
        'columns' => ['email'],
    ]);
    $sql = $this->grammar->compileIndex($blueprint, $command, $this->connection);

    expect($sql)->toBe('create index `users_email_index` on `users` (`email`)');
});

test('compile unique', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent([
        'name' => 'unique',
        'index' => 'users_email_unique',
        'columns' => ['email'],
    ]);
    $sql = $this->grammar->compileUnique($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `users` add unique `users_email_unique` (`email`)');
});

test('compile primary', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent([
        'name' => 'primary',
        'columns' => ['id'],
        'index' => 'users_id_primary',
    ]);
    $sql = $this->grammar->compilePrimary($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `users` add primary key constraint `users_id_primary` (`id`)');
});

test('compile fulltext', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent([
        'name' => 'fulltext',
        'index' => 'posts_content_fulltext',
        'columns' => ['title', 'content'],
    ]);
    $sql = $this->grammar->compileFulltext($blueprint, $command, $this->connection);

    expect($sql)->toBe('create fulltext index `posts_content_fulltext` on `posts` (`title`, `content`)');
});

test('compile spatial index', function () {
    $blueprint = new Blueprint('places');
    $command = new Fluent([
        'name' => 'spatialIndex',
        'index' => 'places_location_spatialindex',
        'columns' => ['location'],
    ]);
    $sql = $this->grammar->compileSpatialIndex($blueprint, $command, $this->connection);

    expect($sql)->toBe('create spatial index `places_location_spatialindex` on `places` (`location`)');
});

test('compile foreign', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent([
        'name' => 'foreign',
        'columns' => ['user_id'],
        'on' => 'users',
        'references' => ['id'],
        'onDelete' => 'cascade',
        'onUpdate' => 'restrict',
    ]);
    $sql = $this->grammar->compileForeign($blueprint, $command, $this->connection);

    expect($sql)->toContain('alter table `posts` add constraint');
    expect($sql)->toContain('foreign key (`user_id`) references `users` (`id`)');
    expect($sql)->toContain('on delete cascade');
    expect($sql)->toContain('on update restrict');
});

test('compile drop index', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'dropIndex', 'index' => 'users_email_index']);
    $sql = $this->grammar->compileDropIndex($blueprint, $command, $this->connection);

    expect($sql)->toBe('drop index `users_email_index` on `users`');
});

test('compile drop unique', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'dropUnique', 'index' => 'users_email_unique']);
    $sql = $this->grammar->compileDropUnique($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `users` drop index `users_email_unique`');
});

test('compile drop primary', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent(['name' => 'dropPrimary']);
    $sql = $this->grammar->compileDropPrimary($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `users` drop primary key');
});

test('compile drop foreign', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent(['name' => 'dropForeign', 'index' => 'posts_user_id_foreign']);
    $sql = $this->grammar->compileDropForeign($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `posts` drop foreign key `posts_user_id_foreign`');
});

test('compile drop fulltext', function () {
    $blueprint = new Blueprint('posts');
    $command = new Fluent(['name' => 'dropFulltext', 'index' => 'posts_content_fulltext']);
    $sql = $this->grammar->compileDropFulltext($blueprint, $command, $this->connection);

    expect($sql)->toBe('drop index `posts_content_fulltext` on `posts`');
});

test('compile drop spatial index', function () {
    $blueprint = new Blueprint('places');
    $command = new Fluent(['name' => 'dropSpatialIndex', 'index' => 'places_location_spatialindex']);
    $sql = $this->grammar->compileDropSpatialIndex($blueprint, $command, $this->connection);

    expect($sql)->toBe('drop index `places_location_spatialindex` on `places`');
});

test('compile table exists', function () {
    $sql = $this->grammar->compileTableExists();

    expect($sql)->toBe("select * from information_schema.tables where table_schema = ? and table_name = ? and table_type = 'BASE TABLE'");
});

test('compile column listing', function () {
    $sql = $this->grammar->compileColumnListing('users');

    expect($sql)->toBe('select column_name as `column_name` from information_schema.columns where table_schema = ? and table_name = ?');
});

test('compile enable foreign key constraints', function () {
    $sql = $this->grammar->compileEnableForeignKeyConstraints();

    expect($sql)->toBe('SET FOREIGN_KEY_CHECKS=1');
});

test('compile disable foreign key constraints', function () {
    $sql = $this->grammar->compileDisableForeignKeyConstraints();

    expect($sql)->toBe('SET FOREIGN_KEY_CHECKS=0');
});

test('all column types', function () {
    $blueprint = new Blueprint('test_table');

    // Test all column types to get coverage
    $blueprint->char('char_col', 10);
    $blueprint->string('string_col');
    $blueprint->tinyText('tinytext_col');
    $blueprint->text('text_col');
    $blueprint->mediumText('mediumtext_col');
    $blueprint->longText('longtext_col');
    $blueprint->integer('int_col');
    $blueprint->bigInteger('bigint_col');
    $blueprint->mediumInteger('mediumint_col');
    $blueprint->tinyInteger('tinyint_col');
    $blueprint->smallInteger('smallint_col');
    $blueprint->unsignedInteger('uint_col');
    $blueprint->unsignedBigInteger('ubigint_col');
    $blueprint->unsignedMediumInteger('umediumint_col');
    $blueprint->unsignedTinyInteger('utinyint_col');
    $blueprint->unsignedSmallInteger('usmallint_col');
    $blueprint->float('float_col', 8, 2);
    $blueprint->double('double_col', 15, 4);
    $blueprint->decimal('decimal_col', 10, 2);
    $blueprint->unsignedDecimal('udecimal_col', 8, 2);
    $blueprint->boolean('bool_col');
    $blueprint->enum('enum_col', ['yes', 'no']);
    $blueprint->set('set_col', ['read', 'write']);
    $blueprint->json('json_col');
    $blueprint->jsonb('jsonb_col');
    $blueprint->date('date_col');
    $blueprint->dateTime('datetime_col');
    $blueprint->dateTimeTz('datetimetz_col');
    $blueprint->time('time_col');
    $blueprint->timeTz('timetz_col');
    $blueprint->timestamp('timestamp_col');
    $blueprint->timestampTz('timestamptz_col');
    $blueprint->year('year_col');
    $blueprint->binary('binary_col');
    $blueprint->uuid('uuid_col');
    $blueprint->ipAddress('ip_col');
    $blueprint->macAddress('mac_col');
    $blueprint->geometry('geometry_col');
    $blueprint->point('point_col');
    $blueprint->lineString('linestring_col');
    $blueprint->polygon('polygon_col');
    $blueprint->geometryCollection('geomcollection_col');
    $blueprint->multiPoint('multipoint_col');
    $blueprint->multiLineString('multilinestring_col');
    $blueprint->multiPolygon('multipolygon_col');
    $blueprint->computed('computed_col', 'price * quantity');

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    // Just verify it compiles without error
    expect($sql)->toBeString();
    expect($sql)->toContain('create table');
});

test('column modifiers', function () {
    $blueprint = new Blueprint('test_table');

    // Test columns with various modifiers
    $blueprint->string('name')->nullable()->default('test')->comment('Test comment');
    $blueprint->integer('count')->unsigned()->autoIncrement();
    $blueprint->string('email')->unique();
    $blueprint->string('slug')->index();
    $blueprint->text('content')->fulltext();
    $blueprint->timestamp('created_at')->useCurrent();
    $blueprint->timestamp('updated_at')->useCurrentOnUpdate();
    $blueprint->string('title')->charset('utf8')->collation('utf8_general_ci');
    $blueprint->string('after_col')->after('name');
    $blueprint->string('first_col')->first();
    $blueprint->decimal('price', 10, 2)->virtualAs('base_price * tax_rate');
    $blueprint->decimal('total', 10, 2)->storedAs('price + tax');

    $command = new Fluent(['name' => 'create']);
    $sql = $this->grammar->compileCreate($blueprint, $command, $this->connection);

    expect($sql)->toContain('`name` varchar(255) null default \'test\' comment \'Test comment\'');
    expect($sql)->toContain('unsigned');
    expect($sql)->toContain('auto_increment');
    expect($sql)->toContain('current_timestamp');
    expect($sql)->toContain('on update current_timestamp');
    expect($sql)->toContain('character set utf8');
    expect($sql)->toContain('collate utf8_general_ci');
    expect($sql)->toContain('as (base_price * tax_rate) virtual');
    expect($sql)->toContain('as (price + tax) stored');
});

test('rename index', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent([
        'name' => 'renameIndex',
        'from' => 'old_index',
        'to' => 'new_index',
    ]);
    $sql = $this->grammar->compileRenameIndex($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `users` rename index `old_index` to `new_index`');
});

test('auto increment starting values', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent([
        'name' => 'autoIncrement',
        'column' => new Fluent(['name' => 'id', 'from' => 1000]),
    ]);
    $sql = $this->grammar->compileAutoIncrementStartingValues($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `users` auto_increment = 1000');
});

test('comment', function () {
    $blueprint = new Blueprint('users');
    $command = new Fluent([
        'name' => 'comment',
        'column' => new Fluent(['name' => 'name', 'comment' => 'User full name']),
        'value' => 'User full name',
    ]);
    $sql = $this->grammar->compileComment($blueprint, $command, $this->connection);

    expect($sql)->toBe('alter table `users` modify `name` varchar(255) not null comment \'User full name\'');
});

test('supports schema transactions', function () {
    expect($this->grammar->supportsSchemaTransactions())->toBeFalse();
});

test('wrap methods', function () {
    expect($this->grammar->wrap('column'))->toBe('`column`');
    expect($this->grammar->wrapTable('table'))->toBe('`table`');
    expect($this->grammar->wrap('table.column'))->toBe('`table`.`column`');
});

test('table prefix', function () {
    $this->grammar->setTablePrefix('wp_');
    expect($this->grammar->getTablePrefix())->toBe('wp_');
    expect($this->grammar->wrapTable('users'))->toBe('`wp_users`');
});

test('get fluent commands', function () {
    $commands = $this->grammar->getFluentCommands();
    expect($commands)->toBeArray();
    expect($commands)->toContain('Comment');
    expect($commands)->toContain('Charset');
    expect($commands)->toContain('Collation');
});
