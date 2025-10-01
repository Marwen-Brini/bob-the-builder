<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\Fluent;

test('constructor with attributes', function () {
    $fluent = new Fluent(['name' => 'test', 'value' => 123]);

    expect($fluent->name)->toBe('test');
    expect($fluent->value)->toBe(123);
});

test('constructor empty', function () {
    $fluent = new Fluent;

    expect($fluent->nonexistent)->toBeNull();
});

test('magic get', function () {
    $fluent = new Fluent(['existing' => 'value']);

    expect($fluent->existing)->toBe('value');
    expect($fluent->nonexistent)->toBeNull();
});

test('magic set', function () {
    $fluent = new Fluent;

    $fluent->newProperty = 'newValue';

    expect($fluent->newProperty)->toBe('newValue');
});

test('magic isset', function () {
    $fluent = new Fluent(['existing' => 'value', 'null_value' => null]);

    expect(isset($fluent->existing))->toBeTrue();
    expect(isset($fluent->null_value))->toBeFalse();
    expect(isset($fluent->nonexistent))->toBeFalse();
});

test('magic unset', function () {
    $fluent = new Fluent(['test' => 'value']);

    expect($fluent->test)->toBe('value');

    unset($fluent->test);

    expect($fluent->test)->toBeNull();
});

test('get method', function () {
    $fluent = new Fluent([
        'simple' => 'value',
        'nested' => ['deep' => 'nested_value'],
    ]);

    expect($fluent->get('simple'))->toBe('value');
    expect($fluent->get('nested'))->toBe(['deep' => 'nested_value']);
    expect($fluent->get('nonexistent', 'default'))->toBe('default');
    expect($fluent->get('nonexistent'))->toBeNull();
});

test('get attributes', function () {
    $attributes = ['name' => 'test', 'value' => 123];
    $fluent = new Fluent($attributes);

    expect($fluent->getAttributes())->toBe($attributes);
});

test('to array', function () {
    $attributes = ['name' => 'test', 'value' => 123];
    $fluent = new Fluent($attributes);

    expect($fluent->toArray())->toBe($attributes);
});

test('json serialize', function () {
    $attributes = ['name' => 'test', 'value' => 123];
    $fluent = new Fluent($attributes);

    expect($fluent->jsonSerialize())->toBe($attributes);
});

test('to json', function () {
    $attributes = ['name' => 'test', 'value' => 123];
    $fluent = new Fluent($attributes);

    $json = $fluent->toJson();

    expect($json)->toBeString();
    expect($json)->toBe(json_encode($attributes));
});

test('to json with options', function () {
    $attributes = ['name' => 'test', 'value' => 123];
    $fluent = new Fluent($attributes);

    $json = $fluent->toJson(JSON_PRETTY_PRINT);

    expect($json)->toBeString();
    expect($json)->toBe(json_encode($attributes, JSON_PRETTY_PRINT));
});

test('magic call', function () {
    $fluent = new Fluent;

    $result = $fluent->customMethod('value');
    expect($result)->toBe($fluent);
    expect($fluent->customMethod)->toBe('value');

    $result2 = $fluent->booleanMethod();
    expect($result2)->toBe($fluent);
    expect($fluent->booleanMethod)->toBeTrue();
});

test('chainability', function () {
    $fluent = new Fluent;

    // Setting properties should return the instance for chaining
    $result = $fluent->name = 'test';

    expect($fluent->name)->toBe('test');
});

test('complex nested get', function () {
    $fluent = new Fluent([
        'level1' => [
            'level2' => [
                'level3' => 'deep_value',
            ],
        ],
    ]);

    $level1 = $fluent->get('level1');
    expect($level1)->toBe(['level2' => ['level3' => 'deep_value']]);
    expect($fluent->get('nonexistent'))->toBeNull();
    expect($fluent->get('nonexistent', 'default'))->toBe('default');
});

test('array access', function () {
    $fluent = new Fluent(['test' => 'value']);

    // Should work like array access through magic methods
    expect($fluent->test)->toBe('value');
    $fluent->newKey = 'newValue';
    expect($fluent->newKey)->toBe('newValue');
});

test('modification after construction', function () {
    $fluent = new Fluent(['original' => 'value']);

    // Add new properties
    $fluent->added = 'new_value';

    // Modify existing
    $fluent->original = 'modified_value';

    $expected = ['original' => 'modified_value', 'added' => 'new_value'];
    expect($fluent->getAttributes())->toBe($expected);
});

test('with various data types', function () {
    $fluent = new Fluent([
        'string' => 'text',
        'integer' => 42,
        'float' => 3.14,
        'boolean' => true,
        'null' => null,
        'array' => [1, 2, 3],
        'object' => (object) ['key' => 'value'],
    ]);

    expect($fluent->string)->toBe('text');
    expect($fluent->integer)->toBe(42);
    expect($fluent->float)->toBe(3.14);
    expect($fluent->boolean)->toBeTrue();
    expect($fluent->null)->toBeNull();
    expect($fluent->array)->toBe([1, 2, 3]);
    expect($fluent->object)->toEqual((object) ['key' => 'value']);
});
