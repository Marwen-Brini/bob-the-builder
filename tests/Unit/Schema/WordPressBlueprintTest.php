<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\WordPressBlueprint;

beforeEach(function () {
    $this->blueprint = new WordPressBlueprint('test_table');
});

test('wpId column', function () {
    $column = $this->blueprint->wpId();

    expect($column->type)->toBe('bigInteger');
    expect($column->name)->toBe('ID');
    expect($column->autoIncrement)->toBeTrue();
    expect($column->unsigned)->toBeTrue();
});

test('wpAuthor column', function () {
    $column = $this->blueprint->wpAuthor();

    expect($column->type)->toBe('bigInteger');
    expect($column->name)->toBe('post_author');
    expect($column->unsigned)->toBeTrue();
    expect($column->default)->toBe(0);
    expect($column->index)->toBeTrue();
});

test('wpDate columns', function () {
    $this->blueprint->wpDates();

    $columns = $this->blueprint->getColumns();
    expect($columns)->toHaveCount(4);

    $columnNames = array_map(fn ($col) => $col->name, $columns);
    expect($columnNames)->toContain('post_date');
    expect($columnNames)->toContain('post_date_gmt');
    expect($columnNames)->toContain('post_modified');
    expect($columnNames)->toContain('post_modified_gmt');

    foreach ($columns as $column) {
        expect($column->type)->toBe('dateTime');
        expect($column->default)->toBe('0000-00-00 00:00:00');
    }
});

test('wpTitle column', function () {
    $column = $this->blueprint->wpTitle();

    expect($column->type)->toBe('text');
    expect($column->name)->toBe('post_title');
});

test('wpContent column', function () {
    $column = $this->blueprint->wpContent();

    expect($column->type)->toBe('longText');
    expect($column->name)->toBe('post_content');
});

test('wpStatus column', function () {
    $column = $this->blueprint->wpStatus();

    expect($column->type)->toBe('string');
    expect($column->name)->toBe('post_status');
    expect($column->length)->toBe(20);
    expect($column->default)->toBe('publish');
    expect($column->index)->toBeTrue();
});

test('wpSlug column', function () {
    $column = $this->blueprint->wpSlug();

    expect($column->type)->toBe('string');
    expect($column->name)->toBe('post_name');
    expect($column->length)->toBe(200);
    expect($column->default)->toBe('');
    expect($column->index)->toBeTrue();
});

test('wpPost complete', function () {
    $this->blueprint->wpPost();

    $columns = $this->blueprint->getColumns();

    // WordPress post table has many columns
    expect(count($columns))->toBeGreaterThan(20);

    $columnNames = array_map(fn ($col) => $col->name, $columns);

    // Check essential columns exist
    expect($columnNames)->toContain('ID');
    expect($columnNames)->toContain('post_author');
    expect($columnNames)->toContain('post_date');
    expect($columnNames)->toContain('post_content');
    expect($columnNames)->toContain('post_title');
    expect($columnNames)->toContain('post_status');
    expect($columnNames)->toContain('post_name');
    expect($columnNames)->toContain('post_type');
});

test('wpUser complete', function () {
    $this->blueprint->wpUser();

    $columns = $this->blueprint->getColumns();

    $columnNames = array_map(fn ($col) => $col->name, $columns);

    expect($columnNames)->toContain('ID');
    expect($columnNames)->toContain('user_login');
    expect($columnNames)->toContain('user_pass');
    expect($columnNames)->toContain('user_email');
    expect($columnNames)->toContain('user_registered');
    expect($columnNames)->toContain('display_name');
});

test('wpMeta columns', function () {
    $this->blueprint->wpMeta('post');

    $columns = $this->blueprint->getColumns();
    expect($columns)->toHaveCount(4);

    $columnNames = array_map(fn ($col) => $col->name, $columns);
    expect($columnNames)->toContain('meta_id');
    expect($columnNames)->toContain('post_id');
    expect($columnNames)->toContain('meta_key');
    expect($columnNames)->toContain('meta_value');
});

test('wpTaxonomy columns', function () {
    $this->blueprint->wpTaxonomy();

    $columns = $this->blueprint->getColumns();

    $columnNames = array_map(fn ($col) => $col->name, $columns);
    expect($columnNames)->toContain('term_taxonomy_id');
    expect($columnNames)->toContain('term_id');
    expect($columnNames)->toContain('taxonomy');
    expect($columnNames)->toContain('description');
    expect($columnNames)->toContain('parent');
    expect($columnNames)->toContain('count');

    // Check for unique constraint
    $commands = $this->blueprint->getCommands();
    $uniqueCommands = array_filter($commands, fn ($cmd) => $cmd->name === 'unique');
    expect($uniqueCommands)->toHaveCount(1);
});

test('wpTerm columns', function () {
    $this->blueprint->wpTerm();

    $columns = $this->blueprint->getColumns();

    $columnNames = array_map(fn ($col) => $col->name, $columns);
    expect($columnNames)->toContain('term_id');
    expect($columnNames)->toContain('name');
    expect($columnNames)->toContain('slug');
    expect($columnNames)->toContain('term_group');
});

test('wpOption columns', function () {
    $this->blueprint->wpOption();

    $columns = $this->blueprint->getColumns();

    $columnNames = array_map(fn ($col) => $col->name, $columns);
    expect($columnNames)->toContain('option_id');
    expect($columnNames)->toContain('option_name');
    expect($columnNames)->toContain('option_value');
    expect($columnNames)->toContain('autoload');

    // Check for unique constraint on option_name
    $optionNameColumn = array_filter($columns, fn ($col) => $col->name === 'option_name');
    $optionNameColumn = array_values($optionNameColumn)[0];
    expect($optionNameColumn->unique)->toBeTrue();
});

test('wpComment columns', function () {
    $this->blueprint->wpComment();

    $columns = $this->blueprint->getColumns();

    $columnNames = array_map(fn ($col) => $col->name, $columns);
    expect($columnNames)->toContain('comment_ID');
    expect($columnNames)->toContain('comment_post_ID');
    expect($columnNames)->toContain('comment_author');
    expect($columnNames)->toContain('comment_author_email');
    expect($columnNames)->toContain('comment_content');
    expect($columnNames)->toContain('comment_date');
    expect($columnNames)->toContain('comment_approved');
});

test('wpForeign keys', function () {
    $postCol = $this->blueprint->wpForeignPost();
    expect($postCol->type)->toBe('bigInteger');
    expect($postCol->name)->toBe('post_id');
    expect($postCol->unsigned)->toBeTrue();

    $userCol = $this->blueprint->wpForeignUser();
    expect($userCol->type)->toBe('bigInteger');
    expect($userCol->name)->toBe('user_id');
    expect($userCol->unsigned)->toBeTrue();

    $termCol = $this->blueprint->wpForeignTerm();
    expect($termCol->type)->toBe('bigInteger');
    expect($termCol->name)->toBe('term_id');
    expect($termCol->unsigned)->toBeTrue();

    // Check foreign key commands were added
    $commands = $this->blueprint->getCommands();
    $foreignCommands = array_filter($commands, fn ($cmd) => $cmd->name === 'foreign');
    expect($foreignCommands)->toHaveCount(3);
});

test('wooCommerce order columns', function () {
    $this->blueprint->wcOrder();

    $columns = $this->blueprint->getColumns();

    $columnNames = array_map(fn ($col) => $col->name, $columns);

    // Check essential WooCommerce order columns
    expect($columnNames)->toContain('order_id');
    expect($columnNames)->toContain('order_key');
    expect($columnNames)->toContain('customer_id');
    expect($columnNames)->toContain('billing_first_name');
    expect($columnNames)->toContain('billing_email');
    expect($columnNames)->toContain('shipping_city');
    expect($columnNames)->toContain('payment_method');
    expect($columnNames)->toContain('total');
    expect($columnNames)->toContain('status');
    expect($columnNames)->toContain('currency');
    expect($columnNames)->toContain('date_created');
});

test('wooCommerce order item columns', function () {
    $this->blueprint->wcOrderItem();

    $columns = $this->blueprint->getColumns();

    $columnNames = array_map(fn ($col) => $col->name, $columns);
    expect($columnNames)->toContain('order_item_id');
    expect($columnNames)->toContain('order_item_name');
    expect($columnNames)->toContain('order_item_type');
    expect($columnNames)->toContain('order_id');

    // Check foreign key to orders table
    $commands = $this->blueprint->getCommands();
    $foreignCommands = array_filter($commands, fn ($cmd) => $cmd->name === 'foreign');
    expect($foreignCommands)->toHaveCount(1);
});

test('wooCommerce hpos structure', function () {
    $this->blueprint->wcHposStructure();

    $columns = $this->blueprint->getColumns();

    // HPOS includes all order columns plus additional indexes
    $columnNames = array_map(fn ($col) => $col->name, $columns);
    expect($columnNames)->toContain('order_id');
    expect($columnNames)->toContain('customer_id');
    expect($columnNames)->toContain('status');
    expect($columnNames)->toContain('total');

    // Check that indexes are added
    $commands = $this->blueprint->getCommands();
    $indexCommands = array_filter($commands, fn ($cmd) => $cmd->name === 'index');
    expect(count($indexCommands))->toBeGreaterThan(3);
});

test('custom prefix for wpDates', function () {
    $this->blueprint->wpDates('comment');

    $columns = $this->blueprint->getColumns();
    $columnNames = array_map(fn ($col) => $col->name, $columns);

    expect($columnNames)->toContain('comment_date');
    expect($columnNames)->toContain('comment_date_gmt');
    expect($columnNames)->toContain('comment_modified');
    expect($columnNames)->toContain('comment_modified_gmt');
});

test('inheritance from blueprint', function () {
    // Ensure WordPressBlueprint still has all Blueprint functionality
    $this->blueprint->string('custom_field');
    $this->blueprint->integer('custom_number');
    $this->blueprint->timestamps();

    $columns = $this->blueprint->getColumns();
    $columnNames = array_map(fn ($col) => $col->name, $columns);

    expect($columnNames)->toContain('custom_field');
    expect($columnNames)->toContain('custom_number');
    expect($columnNames)->toContain('created_at');
    expect($columnNames)->toContain('updated_at');
});
