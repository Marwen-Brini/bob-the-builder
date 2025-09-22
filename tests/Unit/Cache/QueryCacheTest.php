<?php

use Bob\Cache\QueryCache;

beforeEach(function () {
    $this->cache = new QueryCache();
});

test('QueryCache constructor sets default values', function () {
    $cache = new QueryCache();
    expect($cache->getMaxItems())->toBe(1000);
    expect($cache->getTtl())->toBe(3600);
    expect($cache->isEnabled())->toBeTrue();
    expect($cache->size())->toBe(0);
});

test('QueryCache constructor with custom values', function () {
    $cache = new QueryCache(500, 1800);
    expect($cache->getMaxItems())->toBe(500);
    expect($cache->getTtl())->toBe(1800);
});

test('put and get work correctly', function () {
    $this->cache->put('key1', 'value1');
    expect($this->cache->get('key1'))->toBe('value1');
});

test('put with custom TTL', function () {
    $this->cache->setCurrentTime(1000);
    $this->cache->put('key1', 'value1', 60);

    // Should be valid at time 1050
    $this->cache->setCurrentTime(1050);
    expect($this->cache->get('key1'))->toBe('value1');

    // Should expire at time 1061
    $this->cache->setCurrentTime(1061);
    expect($this->cache->get('key1'))->toBeNull();
});

test('get returns null for non-existent key', function () {
    expect($this->cache->get('nonexistent'))->toBeNull();
});

test('get returns null when cache is disabled', function () {
    $this->cache->put('key1', 'value1');
    $this->cache->disable();
    expect($this->cache->get('key1'))->toBeNull();
});

test('put does nothing when cache is disabled', function () {
    $this->cache->disable();
    $this->cache->put('key1', 'value1');
    expect($this->cache->size())->toBe(0);
});

test('has returns true for existing key', function () {
    $this->cache->put('key1', 'value1');
    expect($this->cache->has('key1'))->toBeTrue();
    expect($this->cache->has('key2'))->toBeFalse();
});

test('forget removes item and returns true', function () {
    $this->cache->put('key1', 'value1');
    expect($this->cache->forget('key1'))->toBeTrue();
    expect($this->cache->has('key1'))->toBeFalse();
});

test('forget returns false for non-existent key', function () {
    expect($this->cache->forget('nonexistent'))->toBeFalse();
});

test('flush clears all cache', function () {
    $this->cache->put('key1', 'value1');
    $this->cache->put('key2', 'value2');
    expect($this->cache->size())->toBe(2);

    $this->cache->flush();
    expect($this->cache->size())->toBe(0);
});

test('enable and disable toggle cache state', function () {
    expect($this->cache->isEnabled())->toBeTrue();

    $this->cache->disable();
    expect($this->cache->isEnabled())->toBeFalse();

    $this->cache->enable();
    expect($this->cache->isEnabled())->toBeTrue();
});

test('size returns number of items', function () {
    expect($this->cache->size())->toBe(0);

    $this->cache->put('key1', 'value1');
    expect($this->cache->size())->toBe(1);

    $this->cache->put('key2', 'value2');
    expect($this->cache->size())->toBe(2);
});

test('setMaxItems and getMaxItems work correctly', function () {
    $this->cache->setMaxItems(100);
    expect($this->cache->getMaxItems())->toBe(100);
});

test('setTtl and getTtl work correctly', function () {
    $this->cache->setTtl(7200);
    expect($this->cache->getTtl())->toBe(7200);
});

test('keys returns all cache keys', function () {
    $this->cache->put('key1', 'value1');
    $this->cache->put('key2', 'value2');
    $this->cache->put('key3', 'value3');

    $keys = $this->cache->keys();
    expect($keys)->toContain('key1');
    expect($keys)->toContain('key2');
    expect($keys)->toContain('key3');
    expect($keys)->toHaveCount(3);
});

test('eviction removes oldest item when max reached', function () {
    $cache = new QueryCache(3);
    $cache->setCurrentTime(1000);

    $cache->put('key1', 'value1');
    $cache->setCurrentTime(1001);
    $cache->put('key2', 'value2');
    $cache->setCurrentTime(1002);
    $cache->put('key3', 'value3');

    // Now at max, next put should evict oldest (key1)
    $cache->setCurrentTime(1003);
    $cache->put('key4', 'value4');

    expect($cache->has('key1'))->toBeFalse();
    expect($cache->has('key2'))->toBeTrue();
    expect($cache->has('key3'))->toBeTrue();
    expect($cache->has('key4'))->toBeTrue();
});

test('expired items are removed on get', function () {
    $this->cache->setCurrentTime(1000);
    $this->cache->put('key1', 'value1', 10); // Expires at 1010

    // Valid at time 1005
    $this->cache->setCurrentTime(1005);
    expect($this->cache->get('key1'))->toBe('value1');
    expect($this->cache->has('key1'))->toBeTrue();

    // Expired at time 1011
    $this->cache->setCurrentTime(1011);
    expect($this->cache->get('key1'))->toBeNull();
    expect($this->cache->has('key1'))->toBeFalse(); // Should be removed
});

test('generateKey creates consistent hash', function () {
    $query = 'SELECT * FROM users WHERE id = ?';
    $bindings = [1];

    $key1 = $this->cache->generateKey($query, $bindings);
    $key2 = $this->cache->generateKey($query, $bindings);

    expect($key1)->toBe($key2);
    expect($key1)->toBeString();
    expect(strlen($key1))->toBe(32); // MD5 hash length
});

test('generateKey creates different hash for different inputs', function () {
    $key1 = $this->cache->generateKey('SELECT * FROM users', []);
    $key2 = $this->cache->generateKey('SELECT * FROM posts', []);
    $key3 = $this->cache->generateKey('SELECT * FROM users', [1]);

    expect($key1)->not->toBe($key2);
    expect($key1)->not->toBe($key3);
    expect($key2)->not->toBe($key3);
});

test('getStats returns cache statistics', function () {
    $this->cache->setCurrentTime(1000);

    // Add some valid items
    $this->cache->put('key1', 'value1', 100);
    $this->cache->put('key2', 'value2', 100);

    // Add an expired item
    $this->cache->put('key3', 'value3', 10);

    // Move time forward so key3 expires
    $this->cache->setCurrentTime(1020);

    $stats = $this->cache->getStats();

    expect($stats['total'])->toBe(3);
    expect($stats['valid'])->toBe(2);
    expect($stats['expired'])->toBe(1);
    expect($stats['max_items'])->toBe(1000);
    expect($stats['enabled'])->toBeTrue();
});

test('cleanExpired removes all expired items', function () {
    $this->cache->setCurrentTime(1000);

    // Add items with different expiration times
    $this->cache->put('key1', 'value1', 10); // Expires at 1010
    $this->cache->put('key2', 'value2', 20); // Expires at 1020
    $this->cache->put('key3', 'value3', 30); // Expires at 1030

    // Move time forward so key1 and key2 expire
    $this->cache->setCurrentTime(1025);

    $removed = $this->cache->cleanExpired();

    expect($removed)->toBe(2);
    expect($this->cache->has('key1'))->toBeFalse();
    expect($this->cache->has('key2'))->toBeFalse();
    expect($this->cache->has('key3'))->toBeTrue();
    expect($this->cache->size())->toBe(1);
});

test('cleanExpired returns 0 when no items expired', function () {
    $this->cache->setCurrentTime(1000);
    $this->cache->put('key1', 'value1', 100);
    $this->cache->put('key2', 'value2', 100);

    $removed = $this->cache->cleanExpired();
    expect($removed)->toBe(0);
    expect($this->cache->size())->toBe(2);
});

test('getRawCache returns internal cache array', function () {
    $this->cache->setCurrentTime(1000);
    $this->cache->put('key1', 'value1');

    $raw = $this->cache->getRawCache();

    expect($raw)->toBeArray();
    expect($raw)->toHaveKey('key1');
    expect($raw['key1']['value'])->toBe('value1');
    expect($raw['key1']['created'])->toBe(1000);
    expect($raw['key1']['expires'])->toBe(1000 + 3600);
});

test('cache handles various data types', function () {
    // String
    $this->cache->put('string', 'test');
    expect($this->cache->get('string'))->toBe('test');

    // Integer
    $this->cache->put('int', 42);
    expect($this->cache->get('int'))->toBe(42);

    // Float
    $this->cache->put('float', 3.14);
    expect($this->cache->get('float'))->toBe(3.14);

    // Array
    $this->cache->put('array', ['a', 'b', 'c']);
    expect($this->cache->get('array'))->toBe(['a', 'b', 'c']);

    // Object
    $obj = (object)['name' => 'test'];
    $this->cache->put('object', $obj);
    expect($this->cache->get('object'))->toEqual($obj);

    // Null
    $this->cache->put('null', null);
    expect($this->cache->get('null'))->toBeNull();

    // Boolean
    $this->cache->put('bool', true);
    expect($this->cache->get('bool'))->toBeTrue();
});

test('eviction works with empty cache', function () {
    $cache = new QueryCache(0);
    // Should not throw error even with maxItems = 0
    $cache->put('key1', 'value1');
    expect($cache->has('key1'))->toBeFalse(); // Immediately evicted
});

test('multiple items with same creation time evicts first one', function () {
    $cache = new QueryCache(3);
    $cache->setCurrentTime(1000);

    $cache->put('key1', 'value1');
    $cache->put('key2', 'value2');
    $cache->put('key3', 'value3');

    // All have same creation time, key1 should be evicted first
    $cache->put('key4', 'value4');

    expect($cache->has('key1'))->toBeFalse();
    expect($cache->has('key2'))->toBeTrue();
    expect($cache->has('key3'))->toBeTrue();
    expect($cache->has('key4'))->toBeTrue();
});

test('updating existing key updates value and expiration', function () {
    $this->cache->setCurrentTime(1000);
    $this->cache->put('key1', 'value1', 10); // Expires at 1010

    // Update the key with new value and TTL
    $this->cache->setCurrentTime(1005);
    $this->cache->put('key1', 'value2', 20); // Expires at 1025

    expect($this->cache->get('key1'))->toBe('value2');

    // Should still be valid at 1020
    $this->cache->setCurrentTime(1020);
    expect($this->cache->get('key1'))->toBe('value2');

    // Should expire at 1026
    $this->cache->setCurrentTime(1026);
    expect($this->cache->get('key1'))->toBeNull();
});

test('setCurrentTime resets to real time when set to null', function () {
    $this->cache->setCurrentTime(1000);
    $this->cache->put('key1', 'value1', 10);

    // Reset to real time
    $this->cache->setCurrentTime(null);

    // Should use real time() now - we can't test exact value but can verify it works
    $this->cache->put('key2', 'value2');
    expect($this->cache->has('key2'))->toBeTrue();
});

test('evictOldest handles empty cache gracefully', function () {
    // Create cache with 1 max item
    $cache = new QueryCache(1);

    // Get access to protected method via reflection
    $reflection = new ReflectionClass($cache);
    $method = $reflection->getMethod('evictOldest');
    $method->setAccessible(true);

    // Should not throw error when cache is empty
    $method->invoke($cache);

    expect($cache->size())->toBe(0);
});