<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\Blueprint;
use Bob\Schema\ColumnDefinition;

test('unsignedDecimal method (covers line 432)', function () {
    $blueprint = new Blueprint('test_table');

    // Test unsignedDecimal method
    $result = $blueprint->unsignedDecimal('price', 10, 2);
    expect($result)->toBeInstanceOf(ColumnDefinition::class);

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(1);

    expect($columns[0]['name'])->toBe('price');
    expect($columns[0]['type'])->toBe('decimal');
    expect($columns[0]['precision'])->toBe(10);
    expect($columns[0]['scale'])->toBe(2);
    expect($columns[0]['unsigned'])->toBeTrue();
});

test('other column methods', function () {
    $blueprint = new Blueprint('test_table');

    // Test methods that might be uncovered
    $blueprint->unsignedTinyInteger('tiny_col');
    $blueprint->unsignedSmallInteger('small_col');
    $blueprint->unsignedMediumInteger('medium_col');
    $blueprint->unsignedBigInteger('big_col');

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(4);

    // Verify unsigned property is set
    foreach ($columns as $column) {
        expect($column['unsigned'])->toBeTrue();
    }
});

test('additional column methods', function () {
    $blueprint = new Blueprint('test_table');

    // Test various column types
    $blueprint->year('birth_year');
    $blueprint->ipAddress('ip');
    $blueprint->macAddress('mac');
    $blueprint->uuid('uuid_col');

    $columns = $blueprint->getColumns();
    expect($columns)->toHaveCount(4);

    $expectedTypes = ['year', 'ipAddress', 'macAddress', 'uuid'];
    for ($i = 0; $i < 4; $i++) {
        expect($columns[$i]['type'])->toBe($expectedTypes[$i]);
    }
});

test('special blueprint methods', function () {
    $blueprint = new Blueprint('test_table');

    // Test special properties that might be uncovered
    $blueprint->charset = 'utf8mb4';
    $blueprint->collation = 'utf8mb4_unicode_ci';
    $blueprint->engine = 'InnoDB';
    $blueprint->temporary = true;

    // Test properties exist and can be set
    expect($blueprint->charset)->toBe('utf8mb4');
    expect($blueprint->collation)->toBe('utf8mb4_unicode_ci');
    expect($blueprint->engine)->toBe('InnoDB');
    expect($blueprint->temporary)->toBeTrue();
});

test('table manipulation methods', function () {
    $blueprint = new Blueprint('test_table');

    // Test table creation methods
    $result1 = $blueprint->create();
    expect($result1)->not->toBeNull();

    $result2 = $blueprint->drop();
    expect($result2)->not->toBeNull();

    $commands = $blueprint->getCommands();
    expect($commands)->toHaveCount(2);
    expect($commands[0]['name'])->toBe('create');
    expect($commands[1]['name'])->toBe('drop');
});

test('index manipulation methods', function () {
    $blueprint = new Blueprint('test_table');

    // Test various index methods
    $blueprint->primary(['id']);
    $blueprint->unique(['email']);
    $blueprint->index(['name']);

    $commands = $blueprint->getCommands();
    expect($commands)->toHaveCount(3);

    $commandNames = array_column($commands, 'name');
    expect($commandNames)->toContain('primary');
    expect($commandNames)->toContain('unique');
    expect($commandNames)->toContain('index');
});

test('column modification methods', function () {
    $blueprint = new Blueprint('test_table');

    // Test column change/rename methods
    $blueprint->renameColumn('old_name', 'new_name');
    $blueprint->dropColumn(['unwanted_col']);

    $commands = $blueprint->getCommands();
    expect($commands)->toHaveCount(2);

    expect($commands[0]['name'])->toBe('renameColumn');
    expect($commands[0]['from'])->toBe('old_name');
    expect($commands[0]['to'])->toBe('new_name');

    expect($commands[1]['name'])->toBe('dropColumn');
    expect($commands[1]['columns'])->toBe(['unwanted_col']);
});

test('foreign key methods', function () {
    $blueprint = new Blueprint('test_table');

    // Test foreign key creation
    $result = $blueprint->foreign(['user_id'], 'fk_user');
    expect($result)->not->toBeNull();

    // Test foreign key dropping
    $blueprint->dropForeign(['user_id']);

    $commands = $blueprint->getCommands();
    expect($commands)->toHaveCount(2);

    expect($commands[0]['name'])->toBe('foreign');
    expect($commands[0]['columns'])->toBe(['user_id']);
    expect($commands[0]['index'])->toBe('fk_user');

    expect($commands[1]['name'])->toBe('dropForeign');
});
