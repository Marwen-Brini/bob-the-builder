<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\Blueprint;
use Bob\Schema\ColumnDefinition;
use Bob\Schema\Fluent;

beforeEach(function () {
    $this->blueprint = new Blueprint('test_table');
});

test('table name', function () {
    expect($this->blueprint->getTable())->toBe('test_table');
});

test('adding columns', function () {
    $this->blueprint->string('name');
    $this->blueprint->integer('age');
    $this->blueprint->boolean('active');

    $columns = $this->blueprint->getColumns();

    expect($columns)->toHaveCount(3);
    expect($columns[0]->type)->toBe('string');
    expect($columns[0]->name)->toBe('name');
    expect($columns[1]->type)->toBe('integer');
    expect($columns[1]->name)->toBe('age');
    expect($columns[2]->type)->toBe('boolean');
    expect($columns[2]->name)->toBe('active');
});

test('column modifiers', function () {
    $column = $this->blueprint->string('email')
        ->nullable()
        ->unique()
        ->default('test@example.com')
        ->comment('User email address');

    expect($column)->toBeInstanceOf(ColumnDefinition::class);
    expect($column->nullable)->toBeTrue();
    expect($column->unique)->toBeTrue();
    expect($column->default)->toBe('test@example.com');
    expect($column->comment)->toBe('User email address');
});

test('id column', function () {
    $column = $this->blueprint->id();

    expect($column->type)->toBe('bigInteger');
    expect($column->name)->toBe('id');
    expect($column->autoIncrement)->toBeTrue();
    expect($column->unsigned)->toBeTrue();
});

test('timestamps', function () {
    $this->blueprint->timestamps();

    $columns = $this->blueprint->getColumns();
    expect($columns)->toHaveCount(2);
    expect($columns[0]->name)->toBe('created_at');
    expect($columns[1]->name)->toBe('updated_at');
    expect($columns[0]->type)->toBe('timestamp');
    expect($columns[1]->type)->toBe('timestamp');
    expect($columns[0]->nullable)->toBeTrue();
    expect($columns[1]->nullable)->toBeTrue();
});

test('soft deletes', function () {
    $column = $this->blueprint->softDeletes();

    expect($column->type)->toBe('timestamp');
    expect($column->name)->toBe('deleted_at');
    expect($column->nullable)->toBeTrue();
});

test('json column', function () {
    $column = $this->blueprint->json('settings');

    expect($column->type)->toBe('json');
    expect($column->name)->toBe('settings');
});

test('enum column', function () {
    $column = $this->blueprint->enum('status', ['active', 'inactive', 'pending']);

    expect($column->type)->toBe('enum');
    expect($column->name)->toBe('status');
    expect($column->allowed)->toBe(['active', 'inactive', 'pending']);
});

test('foreign id column', function () {
    $column = $this->blueprint->foreignId('user_id');

    expect($column->type)->toBe('bigInteger');
    expect($column->name)->toBe('user_id');
    expect($column->unsigned)->toBeTrue();
});

test('adding indexes', function () {
    $this->blueprint->string('email')->index();
    $this->blueprint->index(['first_name', 'last_name']);

    $commands = $this->blueprint->getCommands();

    // Find index commands
    $indexCommands = array_filter($commands, fn($cmd) => $cmd->name === 'index');
    expect($indexCommands)->toHaveCount(2);
});

test('adding primary key', function () {
    $this->blueprint->primary(['id']);

    $commands = $this->blueprint->getCommands();
    $primaryCommand = array_filter($commands, fn($cmd) => $cmd->name === 'primary');

    expect($primaryCommand)->toHaveCount(1);
    $primary = array_values($primaryCommand)[0];
    expect($primary->columns)->toBe(['id']);
});

test('adding unique index', function () {
    $this->blueprint->string('email')->unique();
    $this->blueprint->unique(['username']);

    $commands = $this->blueprint->getCommands();
    $uniqueCommands = array_filter($commands, fn($cmd) => $cmd->name === 'unique');

    expect($uniqueCommands)->toHaveCount(2);
});

test('adding foreign key', function () {
    $foreign = $this->blueprint->foreign('user_id')
        ->references('id')
        ->on('users')
        ->cascadeOnDelete();

    $commands = $this->blueprint->getCommands();
    $foreignCommand = array_filter($commands, fn($cmd) => $cmd->name === 'foreign');

    expect($foreignCommand)->toHaveCount(1);
    $fk = array_values($foreignCommand)[0];
    expect($fk->columns)->toBe(['user_id']);
    expect($fk->references)->toBe(['id']);
    expect($fk->on)->toBe('users');
    expect($fk->onDelete)->toBe('cascade');
});

test('drop column', function () {
    $this->blueprint->dropColumn('old_column');
    $this->blueprint->dropColumn(['col1', 'col2']);

    $commands = $this->blueprint->getCommands();
    $dropCommands = array_filter($commands, fn($cmd) => $cmd->name === 'dropColumn');

    expect($dropCommands)->toHaveCount(2);
});

test('rename column', function () {
    $this->blueprint->renameColumn('old_name', 'new_name');

    $commands = $this->blueprint->getCommands();
    $renameCommand = array_filter($commands, fn($cmd) => $cmd->name === 'renameColumn');

    expect($renameCommand)->toHaveCount(1);
    $rename = array_values($renameCommand)[0];
    expect($rename->from)->toBe('old_name');
    expect($rename->to)->toBe('new_name');
});

test('table creation', function () {
    $this->blueprint->create();

    $commands = $this->blueprint->getCommands();
    $createCommand = array_filter($commands, fn($cmd) => $cmd->name === 'create');

    expect($createCommand)->toHaveCount(1);
});

test('table drop', function () {
    $this->blueprint->drop();

    $commands = $this->blueprint->getCommands();
    $dropCommand = array_filter($commands, fn($cmd) => $cmd->name === 'drop');

    expect($dropCommand)->toHaveCount(1);
});

test('after column', function () {
    $this->blueprint->string('email');
    $this->blueprint->after('email', function($table) {
        $table->string('phone');
        $table->string('address');
    });

    $columns = $this->blueprint->getColumns();
    expect($columns)->toHaveCount(3);
    expect($columns[1]->name)->toBe('phone');
    expect($columns[1]->after)->toBe('email');
    expect($columns[2]->name)->toBe('address');
    expect($columns[2]->after)->toBe('phone');
});

test('temporary table', function () {
    $this->blueprint->temporary = true;
    expect($this->blueprint->temporary)->toBeTrue();
});

test('engine specification', function () {
    $this->blueprint->engine = 'InnoDB';
    expect($this->blueprint->engine)->toBe('InnoDB');
});

test('charset and collation', function () {
    $this->blueprint->charset = 'utf8mb4';
    $this->blueprint->collation = 'utf8mb4_unicode_ci';

    expect($this->blueprint->charset)->toBe('utf8mb4');
    expect($this->blueprint->collation)->toBe('utf8mb4_unicode_ci');
});

test('all numeric types', function () {
    $this->blueprint->tinyInteger('tiny');
    $this->blueprint->smallInteger('small');
    $this->blueprint->mediumInteger('medium');
    $this->blueprint->integer('int');
    $this->blueprint->bigInteger('big');
    $this->blueprint->float('float_col');
    $this->blueprint->double('double_col');
    $this->blueprint->decimal('decimal_col', 10, 2);

    $columns = $this->blueprint->getColumns();
    expect($columns)->toHaveCount(8);

    $types = array_map(fn($col) => $col->type, $columns);
    expect($types)->toBe(['tinyInteger', 'smallInteger', 'mediumInteger', 'integer',
                       'bigInteger', 'float', 'double', 'decimal']);
});

test('all date types', function () {
    $this->blueprint->date('date_col');
    $this->blueprint->dateTime('datetime_col');
    $this->blueprint->dateTimeTz('datetime_tz');
    $this->blueprint->time('time_col');
    $this->blueprint->timeTz('time_tz');
    $this->blueprint->timestamp('timestamp_col');
    $this->blueprint->timestampTz('timestamp_tz');
    $this->blueprint->year('year_col');

    $columns = $this->blueprint->getColumns();
    expect($columns)->toHaveCount(8);

    $types = array_map(fn($col) => $col->type, $columns);
    expect($types)->toBe(['date', 'dateTime', 'dateTimeTz', 'time',
                       'timeTz', 'timestamp', 'timestampTz', 'year']);
});

test('all text types', function () {
    $this->blueprint->char('char_col', 10);
    $this->blueprint->string('string_col', 100);
    $this->blueprint->tinyText('tinytext_col');
    $this->blueprint->text('text_col');
    $this->blueprint->mediumText('mediumtext_col');
    $this->blueprint->longText('longtext_col');

    $columns = $this->blueprint->getColumns();
    expect($columns)->toHaveCount(6);

    $types = array_map(fn($col) => $col->type, $columns);
    expect($types)->toBe(['char', 'string', 'tinyText', 'text', 'mediumText', 'longText']);
});

test('special types', function () {
    $this->blueprint->binary('binary_col');
    $this->blueprint->uuid('uuid_col');
    $this->blueprint->ipAddress('ip_col');
    $this->blueprint->macAddress('mac_col');
    $this->blueprint->geometry('geo_col');
    $this->blueprint->point('point_col');

    $columns = $this->blueprint->getColumns();
    expect($columns)->toHaveCount(6);

    $types = array_map(fn($col) => $col->type, $columns);
    expect($types)->toBe(['binary', 'uuid', 'ipAddress', 'macAddress', 'geometry', 'point']);
});