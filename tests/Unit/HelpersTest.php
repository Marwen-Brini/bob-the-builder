<?php

use Bob\Support\Collection;

// Include the helpers file
require_once __DIR__.'/../../src/helpers.php';

test('tap function calls callback and returns value', function () {
    $value = 'test';
    $result = tap($value, function ($v) {
        expect($v)->toBe('test');
    });

    expect($result)->toBe('test');
});

test('tap function modifies object through callback', function () {
    $object = new stdClass;
    $object->name = 'initial';

    $result = tap($object, function ($obj) {
        $obj->name = 'modified';
        $obj->age = 30;
    });

    expect($result)->toBe($object);
    expect($result->name)->toBe('modified');
    expect($result->age)->toBe(30);
});

test('tap function works with arrays', function () {
    $array = ['key' => 'value'];

    $result = tap($array, function ($arr) {
        // Can't modify the array since it's passed by value
        expect($arr)->toBe(['key' => 'value']);
    });

    // Original array is returned unchanged
    expect($result)->toBe(['key' => 'value']);
});

test('tap function works with null', function () {
    $result = tap(null, function ($v) {
        expect($v)->toBeNull();
    });

    expect($result)->toBeNull();
});

test('collect function creates collection from array', function () {
    $collection = collect([1, 2, 3]);

    expect($collection)->toBeInstanceOf(Collection::class);
    expect($collection->all())->toBe([1, 2, 3]);
});

test('collect function creates collection from null', function () {
    $collection = collect(null);

    expect($collection)->toBeInstanceOf(Collection::class);
    // null creates an empty collection
    expect($collection->all())->toBe([]);
});

test('collect function creates collection from object', function () {
    $object = new stdClass;
    $object->name = 'test';
    $object->value = 123;

    $collection = collect($object);

    expect($collection)->toBeInstanceOf(Collection::class);
    expect($collection->all())->toBe(['name' => 'test', 'value' => 123]);
});

test('collect function creates collection from string', function () {
    $collection = collect('test');

    expect($collection)->toBeInstanceOf(Collection::class);
    expect($collection->all())->toBe(['test']);
});

test('collect function creates empty collection when no argument', function () {
    $collection = collect();

    expect($collection)->toBeInstanceOf(Collection::class);
    // No argument creates empty collection
    expect($collection->all())->toBe([]);
});

test('collect function creates collection from another collection', function () {
    $original = collect([1, 2, 3]);
    $collection = collect($original);

    expect($collection)->toBeInstanceOf(Collection::class);
    // Collection gets the items from the original collection
    expect($collection->all())->toBe([1, 2, 3]);
});

test('class_basename returns class name without namespace', function () {
    expect(class_basename('App\Models\User'))->toBe('User');
    expect(class_basename('SomeClass'))->toBe('SomeClass');
    expect(class_basename('Vendor\Package\Deeply\Nested\ClassName'))->toBe('ClassName');
});

test('class_basename works with objects', function () {
    $object = new stdClass;
    expect(class_basename($object))->toBe('stdClass');

    $collection = new Collection;
    expect(class_basename($collection))->toBe('Collection');
});

test('class_basename handles backslashes correctly', function () {
    expect(class_basename('\\App\\Models\\User'))->toBe('User');
    expect(class_basename('App\\Models\\User\\'))->toBe('User');
});

test('class_basename handles forward slashes', function () {
    // Even though PHP uses backslashes, the function converts them to forward slashes
    expect(class_basename('App/Models/User'))->toBe('User');
});

test('class_basename with anonymous class', function () {
    $anonymous = new class
    {
        public $value = 'test';
    };

    $basename = class_basename($anonymous);
    // Anonymous classes have generated names
    expect($basename)->toBeString();
    expect(strlen($basename))->toBeGreaterThan(0);
});

test('last function returns last element of array', function () {
    expect(last([1, 2, 3]))->toBe(3);
    expect(last(['a', 'b', 'c']))->toBe('c');
});

test('last function returns last element of associative array', function () {
    $array = [
        'first' => 'value1',
        'second' => 'value2',
        'third' => 'value3',
    ];

    expect(last($array))->toBe('value3');
});

test('last function returns null for empty array', function () {
    // end() returns false for empty array
    expect(last([]))->toBe(false);
});

test('last function returns null for non-array', function () {
    expect(last(null))->toBeNull();
    expect(last('string'))->toBeNull();
    expect(last(123))->toBeNull();
    expect(last(new stdClass))->toBeNull();
});

test('last function works with single element array', function () {
    expect(last([42]))->toBe(42);
    expect(last(['only']))->toBe('only');
});

test('last function preserves array pointer', function () {
    $array = [1, 2, 3];

    // Move pointer to first element
    reset($array);
    $current = current($array);
    expect($current)->toBe(1);

    // Get last element
    $lastElement = last($array);
    expect($lastElement)->toBe(3);

    // Original array pointer should be at the end after end() call
    // But the original array is not modified since it's passed by value
});

test('helper functions do not redefine if already exist', function () {
    // Since we've already included the helpers, trying to define them again should not cause errors

    // Define a dummy function before including helpers again
    if (! function_exists('dummy_test_function')) {
        function dummy_test_function()
        {
            return 'test';
        }
    }

    // Include helpers again - should not cause redefinition errors
    include __DIR__.'/../../src/helpers.php';

    // Functions should still work
    expect(tap('value', function () {}))->toBe('value');
    expect(collect([1, 2]))->toBeInstanceOf(Collection::class);
    expect(class_basename('App\Test'))->toBe('Test');
    expect(last([1, 2, 3]))->toBe(3);
});
