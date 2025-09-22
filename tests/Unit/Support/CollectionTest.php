<?php

use Bob\Support\Collection;

test('Collection can be created from array', function () {
    $collection = new Collection([1, 2, 3]);
    expect($collection)->toBeInstanceOf(Collection::class);
    expect($collection->all())->toBe([1, 2, 3]);
});

test('Collection make static method', function () {
    $collection = Collection::make([1, 2, 3]);
    expect($collection)->toBeInstanceOf(Collection::class);
    expect($collection->all())->toBe([1, 2, 3]);
});

test('Collection count returns number of items', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);
    expect($collection->count())->toBe(5);
    expect(count($collection))->toBe(5);  // Countable interface
});

test('Collection first returns first item', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);
    expect($collection->first())->toBe(1);

    $empty = new Collection([]);
    expect($empty->first())->toBeNull();
    expect($empty->first(null, 'default'))->toBe('default');
});

test('Collection first with callback', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $result = $collection->first(function ($item) {
        return $item > 3;
    });

    expect($result)->toBe(4);

    $notFound = $collection->first(function ($item) {
        return $item > 10;
    });

    expect($notFound)->toBeNull();
});

test('Collection map transforms items', function () {
    $collection = new Collection([1, 2, 3]);

    $mapped = $collection->map(function ($item) {
        return $item * 2;
    });

    expect($mapped)->toBeInstanceOf(Collection::class);
    expect($mapped->all())->toBe([2, 4, 6]);
});

test('Collection filter removes items', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $filtered = $collection->filter(function ($item) {
        return $item % 2 === 0;
    });

    expect($filtered->values()->all())->toBe([2, 4]);
});

test('Collection reject removes items matching callback', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $rejected = $collection->reject(function ($item) {
        return $item % 2 === 0;
    });

    expect($rejected->values()->all())->toBe([1, 3, 5]);
});

test('Collection pluck extracts column', function () {
    $collection = new Collection([
        ['name' => 'John', 'age' => 30],
        ['name' => 'Jane', 'age' => 25],
    ]);

    $names = $collection->pluck('name');
    expect($names->all())->toBe(['John', 'Jane']);

    $keyed = $collection->pluck('name', 'age');
    expect($keyed->all())->toBe([30 => 'John', 25 => 'Jane']);
});

test('Collection values reindexes array', function () {
    $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

    $values = $collection->values();
    expect($values->all())->toBe([1, 2, 3]);
});

test('Collection push and pop', function () {
    $collection = new Collection([1, 2]);

    $collection->push(3);
    expect($collection->all())->toBe([1, 2, 3]);

    $popped = $collection->pop();
    expect($popped)->toBe(3);
    expect($collection->all())->toBe([1, 2]);
});

test('Collection shift removes first item', function () {
    $collection = new Collection([1, 2, 3]);

    $shifted = $collection->shift();
    expect($shifted)->toBe(1);
    expect($collection->all())->toBe([2, 3]);
});

test('Collection slice returns subset', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $slice = $collection->slice(1, 3);
    expect($slice->values()->all())->toBe([2, 3, 4]);
});

test('Collection take returns first n items', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $taken = $collection->take(3);
    expect($taken->all())->toBe([1, 2, 3]);

    // Negative take gets last N items
    $negative = $collection->take(-2);
    expect($negative->values()->all())->toBe([4, 5]);
});

test('Collection sort orders items', function () {
    $collection = new Collection([3, 1, 2]);

    $sorted = $collection->sort();
    expect($sorted->values()->all())->toBe([1, 2, 3]);

    // With callback
    $collection = new Collection([3, 1, 2]);
    $sorted = $collection->sort(function ($a, $b) {
        return $b <=> $a; // Descending
    });
    expect($sorted->values()->all())->toBe([3, 2, 1]);
});

test('Collection sortBy orders items by key', function () {
    $collection = new Collection([
        ['name' => 'Charlie', 'age' => 35],
        ['name' => 'Alice', 'age' => 25],
        ['name' => 'Bob', 'age' => 30],
    ]);

    $sorted = $collection->sortBy('age');
    expect($sorted->values()->pluck('name')->all())->toBe(['Alice', 'Bob', 'Charlie']);

    $sortedDesc = $collection->sortByDesc('age');
    expect($sortedDesc->values()->pluck('name')->all())->toBe(['Charlie', 'Bob', 'Alice']);
});

test('Collection reverse reverses items', function () {
    $collection = new Collection([1, 2, 3]);

    $reversed = $collection->reverse();
    expect($reversed->all())->toBe([2 => 3, 1 => 2, 0 => 1]);
    expect($reversed->values()->all())->toBe([3, 2, 1]);
});

test('Collection shuffle randomizes items', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    // With seed for predictable test
    $shuffled = $collection->shuffle(123);
    expect($shuffled->count())->toBe(5);
    expect($shuffled->all())->toContain(1);
    expect($shuffled->all())->toContain(5);
});

test('Collection random returns random items', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $random = $collection->random();
    expect($random)->toBeIn([1, 2, 3, 4, 5]);

    $randomMultiple = $collection->random(2);
    expect($randomMultiple->count())->toBe(2);
});

test('Collection search finds item index', function () {
    $collection = new Collection(['foo', 'bar', 'baz']);

    expect($collection->search('bar'))->toBe(1);
    expect($collection->search('missing'))->toBeFalse();

    // Strict search
    $collection = new Collection([1, '1', 2]);
    expect($collection->search('1', true))->toBe(1);
    expect($collection->search(1, true))->toBe(0);
});

test('Collection unique removes duplicates', function () {
    $collection = new Collection([1, 2, 2, 3, 3, 3, 4]);

    $unique = $collection->unique();
    expect($unique->values()->all())->toBe([1, 2, 3, 4]);
});

test('Collection toArray converts to array', function () {
    $collection = new Collection([1, 2, 3]);
    expect($collection->toArray())->toBe([1, 2, 3]);
});

test('Collection toJson converts to JSON', function () {
    $collection = new Collection(['name' => 'John', 'age' => 30]);
    expect($collection->toJson())->toBe('{"name":"John","age":30}');
});

test('Collection is iterable', function () {
    $collection = new Collection([1, 2, 3]);
    $result = [];

    foreach ($collection as $item) {
        $result[] = $item;
    }

    expect($result)->toBe([1, 2, 3]);
});

test('Collection offsetGet and offsetSet work', function () {
    $collection = new Collection(['a' => 1, 'b' => 2]);

    expect($collection['a'])->toBe(1);

    $collection['c'] = 3;
    expect($collection['c'])->toBe(3);

    expect(isset($collection['b']))->toBeTrue();
    expect(isset($collection['d']))->toBeFalse();

    unset($collection['b']);
    expect(isset($collection['b']))->toBeFalse();
});

// Tests for uncovered lines
test('Collection random throws exception when requesting more than available', function () {
    $collection = new Collection([1, 2, 3]);

    expect(function () use ($collection) {
        $collection->random(5);
    })->toThrow(\InvalidArgumentException::class, 'You requested 5 items, but there are only 3 items available.');
});

test('Collection random returns empty collection when requesting zero', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $result = $collection->random(0);
    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->count())->toBe(0);
});

test('Collection search with callback function', function () {
    $collection = new Collection([
        ['id' => 1, 'name' => 'John'],
        ['id' => 2, 'name' => 'Jane'],
        ['id' => 3, 'name' => 'Bob']
    ]);

    $result = $collection->search(function ($item) {
        return $item['name'] === 'Jane';
    });

    expect($result)->toBe(1);

    $notFound = $collection->search(function ($item) {
        return $item['name'] === 'Alice';
    });

    expect($notFound)->toBeFalse();
});

test('Collection shuffle without seed', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $shuffled = $collection->shuffle();
    expect($shuffled)->toBeInstanceOf(Collection::class);
    expect($shuffled->count())->toBe(5);
    expect($shuffled->all())->toContain(1);
    expect($shuffled->all())->toContain(2);
    expect($shuffled->all())->toContain(3);
    expect($shuffled->all())->toContain(4);
    expect($shuffled->all())->toContain(5);
});

test('Collection getArrayableItems with Collection instance', function () {
    $original = new Collection([1, 2, 3]);
    $collection = new Collection($original);

    expect($collection->all())->toBe([1, 2, 3]);
});

test('Collection getArrayableItems with JsonSerializable', function () {
    $jsonable = new class implements JsonSerializable {
        public function jsonSerialize(): array {
            return ['foo' => 'bar', 'baz' => 'qux'];
        }
    };

    $collection = new Collection($jsonable);
    expect($collection->all())->toBe(['foo' => 'bar', 'baz' => 'qux']);
});

test('Collection getArrayableItems with object', function () {
    $obj = (object) ['a' => 1, 'b' => 2];
    $collection = new Collection($obj);

    expect($collection->all())->toBe(['a' => 1, 'b' => 2]);
});

test('Collection collapse static method', function () {
    $result = Collection::collapse([
        [1, 2, 3],
        [4, 5, 6],
        [7, 8, 9]
    ]);

    expect($result)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9]);
});

test('Collection collapse with non-array values', function () {
    $result = Collection::collapse([
        [1, 2],
        'string',
        [3, 4],
        5
    ]);

    expect($result)->toBe([1, 2, 'string', 3, 4, 5]);
});

test('Collection dataGet with null key returns target', function () {
    $target = ['foo' => 'bar'];
    $result = Collection::dataGet($target, null);

    expect($result)->toBe($target);
});

test('Collection dataGet with nested dot notation', function () {
    $target = [
        'user' => [
            'profile' => [
                'name' => 'John Doe'
            ]
        ]
    ];

    $result = Collection::dataGet($target, 'user.profile.name');
    expect($result)->toBe('John Doe');

    $missing = Collection::dataGet($target, 'user.profile.age', 'default');
    expect($missing)->toBe('default');
});

test('Collection dataGet with array key', function () {
    $target = [
        'user' => [
            'profile' => [
                'name' => 'John Doe'
            ]
        ]
    ];

    $result = Collection::dataGet($target, ['user', 'profile', 'name']);
    expect($result)->toBe('John Doe');
});

test('Collection dataGet with wildcard', function () {
    $target = [
        'users' => [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35]
        ]
    ];

    $names = Collection::dataGet($target, 'users.*.name');
    expect($names)->toBe(['John', 'Jane', 'Bob']);
});

test('Collection dataGet with wildcard on non-array returns default', function () {
    $target = ['users' => 'string'];

    $result = Collection::dataGet($target, 'users.*.name', 'default');
    expect($result)->toBe('default');
});

test('Collection dataGet with nested wildcards', function () {
    $target = [
        'groups' => [
            ['users' => [['name' => 'John'], ['name' => 'Jane']]],
            ['users' => [['name' => 'Bob'], ['name' => 'Alice']]]
        ]
    ];

    $names = Collection::dataGet($target, 'groups.*.users.*.name');
    expect($names)->toBe(['John', 'Jane', 'Bob', 'Alice']);
});

test('Collection dataGet with object properties', function () {
    $target = (object) [
        'user' => (object) [
            'name' => 'John Doe'
        ]
    ];

    $result = Collection::dataGet($target, 'user.name');
    expect($result)->toBe('John Doe');

    $missing = Collection::dataGet($target, 'user.age', 'default');
    expect($missing)->toBe('default');
});

test('Collection unique with key', function () {
    $collection = new Collection([
        ['id' => 1, 'name' => 'John'],
        ['id' => 2, 'name' => 'Jane'],
        ['id' => 3, 'name' => 'John'],
        ['id' => 4, 'name' => 'Bob']
    ]);

    $unique = $collection->unique('name');
    expect($unique->values()->all())->toHaveCount(3);
    expect($unique->values()->pluck('name')->all())->toBe(['John', 'Jane', 'Bob']);
});

test('Collection unique with callback', function () {
    $collection = new Collection([
        ['id' => 1, 'category' => ['name' => 'A']],
        ['id' => 2, 'category' => ['name' => 'B']],
        ['id' => 3, 'category' => ['name' => 'A']],
        ['id' => 4, 'category' => ['name' => 'C']]
    ]);

    $unique = $collection->unique(function ($item) {
        return $item['category']['name'];
    });

    expect($unique->values()->all())->toHaveCount(3);
    expect($unique->values()->pluck('id')->all())->toBe([1, 2, 4]);
});

test('Collection reject with non-callable value', function () {
    $collection = new Collection([1, 2, 3, 2, 1, 4]);

    $rejected = $collection->reject(2);
    expect($rejected->values()->all())->toBe([1, 3, 1, 4]);
});