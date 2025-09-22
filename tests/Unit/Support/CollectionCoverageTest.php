<?php

namespace Tests\Unit\Support;

use Bob\Support\Collection;
use JsonSerializable;

it('converts nested collections to arrays in toArray method', function () {
    $innerCollection = new Collection(['a' => 1, 'b' => 2]);
    $collection = new Collection([
        'first' => $innerCollection,
        'second' => 'value'
    ]);

    $result = $collection->toArray();

    expect($result)->toBe([
        'first' => ['a' => 1, 'b' => 2],
        'second' => 'value'
    ]);
});

it('converts objects with toArray method in toArray', function () {
    $object = new class {
        public function toArray() {
            return ['converted' => true];
        }
    };

    $collection = new Collection([
        'obj' => $object,
        'normal' => 'value'
    ]);

    $result = $collection->toArray();

    expect($result)->toBe([
        'obj' => ['converted' => true],
        'normal' => 'value'
    ]);
});

it('serializes JsonSerializable objects in jsonSerialize', function () {
    $jsonObject = new class implements JsonSerializable {
        public function jsonSerialize(): mixed {
            return ['json' => 'serialized'];
        }
    };

    $collection = new Collection([
        'item' => $jsonObject,
        'other' => 'value'
    ]);

    $result = $collection->jsonSerialize();

    expect($result)->toBe([
        'item' => ['json' => 'serialized'],
        'other' => 'value'
    ]);
});

it('serializes nested collections in jsonSerialize', function () {
    $innerCollection = new Collection(['nested' => 'data']);
    $collection = new Collection([
        'inner' => $innerCollection,
        'outer' => 'value'
    ]);

    $result = $collection->jsonSerialize();

    expect($result)->toBe([
        'inner' => ['nested' => 'data'],
        'outer' => 'value'
    ]);
});

it('calls toJson which uses jsonSerialize for nested collections', function () {
    $innerCollection = new Collection(['key' => 'value']);
    $collection = new Collection([
        'nested' => $innerCollection,
        'simple' => 'text'
    ]);

    $json = $collection->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toBe([
        'nested' => ['key' => 'value'],
        'simple' => 'text'
    ]);
});

it('filters items without callback to remove falsy values', function () {
    $collection = new Collection([
        'a' => 1,
        'b' => 0,
        'c' => 'text',
        'd' => '',
        'e' => false,
        'f' => null,
        'g' => [],
        'h' => 'valid'
    ]);

    $filtered = $collection->filter();

    expect($filtered->all())->toBe([
        'a' => 1,
        'c' => 'text',
        'h' => 'valid'
    ]);
});

it('filters array with numeric keys without callback', function () {
    $collection = new Collection([1, 0, 2, false, 3, null, 4]);

    $filtered = $collection->filter();

    expect($filtered->all())->toBe([
        0 => 1,
        2 => 2,
        4 => 3,
        6 => 4
    ]);
});