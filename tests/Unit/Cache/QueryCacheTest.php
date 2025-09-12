<?php

use Bob\Cache\QueryCache;

describe('QueryCache basic functionality', function () {
    it('stores and retrieves values', function () {
        $cache = new QueryCache();
        
        $cache->put('key1', 'value1');
        expect($cache->get('key1'))->toBe('value1');
    });
    
    it('returns null for non-existent keys', function () {
        $cache = new QueryCache();
        
        expect($cache->get('non-existent'))->toBeNull();
    });
    
    it('respects TTL on items', function () {
        $cache = new QueryCache();
        
        // Put item with 1 second TTL
        $cache->put('key1', 'value1', 1);
        expect($cache->get('key1'))->toBe('value1');
        
        // Wait for expiration
        sleep(2);
        expect($cache->get('key1'))->toBeNull();
    });
    
    it('removes expired items and returns null', function () {
        $cache = new QueryCache();
        
        // Use reflection to set expired item directly
        $reflection = new ReflectionClass($cache);
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        
        $cacheProperty->setValue($cache, [
            'expired_key' => [
                'value' => 'expired_value',
                'expires' => time() - 10, // Expired 10 seconds ago
                'created' => time() - 20
            ]
        ]);
        
        // Getting expired item should return null and remove it
        expect($cache->get('expired_key'))->toBeNull();
        expect($cache->size())->toBe(0);
    });
    
    it('forgets specific keys', function () {
        $cache = new QueryCache();
        
        $cache->put('key1', 'value1');
        $cache->put('key2', 'value2');
        
        expect($cache->size())->toBe(2);
        
        $cache->forget('key1');
        
        expect($cache->get('key1'))->toBeNull();
        expect($cache->get('key2'))->toBe('value2');
        expect($cache->size())->toBe(1);
    });
    
    it('flushes all cache', function () {
        $cache = new QueryCache();
        
        $cache->put('key1', 'value1');
        $cache->put('key2', 'value2');
        $cache->put('key3', 'value3');
        
        expect($cache->size())->toBe(3);
        
        $cache->flush();
        
        expect($cache->size())->toBe(0);
        expect($cache->get('key1'))->toBeNull();
    });
});

describe('QueryCache enable/disable functionality', function () {
    it('can be disabled and enabled', function () {
        $cache = new QueryCache();
        
        expect($cache->isEnabled())->toBeTrue();
        
        $cache->disable();
        expect($cache->isEnabled())->toBeFalse();
        
        $cache->enable();
        expect($cache->isEnabled())->toBeTrue();
    });
    
    it('does not store when disabled', function () {
        $cache = new QueryCache();
        
        $cache->disable();
        $cache->put('key1', 'value1');
        
        expect($cache->size())->toBe(0);
        expect($cache->get('key1'))->toBeNull();
    });
    
    it('does not retrieve when disabled', function () {
        $cache = new QueryCache();
        
        $cache->put('key1', 'value1');
        expect($cache->get('key1'))->toBe('value1');
        
        $cache->disable();
        expect($cache->get('key1'))->toBeNull();
        
        $cache->enable();
        expect($cache->get('key1'))->toBe('value1');
    });
});

describe('QueryCache eviction policy', function () {
    it('evicts oldest item when cache is full', function () {
        $cache = new QueryCache(3); // Max 3 items
        
        // Add 3 items
        $cache->put('key1', 'value1');
        sleep(1); // Ensure different timestamps
        $cache->put('key2', 'value2');
        $cache->put('key3', 'value3');
        
        expect($cache->size())->toBe(3);
        
        // Adding 4th item should evict the oldest (key1)
        $cache->put('key4', 'value4');
        
        expect($cache->size())->toBe(3);
        expect($cache->get('key1'))->toBeNull(); // Oldest was evicted
        expect($cache->get('key2'))->toBe('value2');
        expect($cache->get('key3'))->toBe('value3');
        expect($cache->get('key4'))->toBe('value4');
    });
    
    it('evicts multiple items correctly', function () {
        $cache = new QueryCache(2); // Max 2 items
        
        // Fill cache
        $cache->put('key1', 'value1');
        usleep(100000); // 0.1 second delay
        $cache->put('key2', 'value2');
        
        // Add more items, causing evictions
        usleep(100000);
        $cache->put('key3', 'value3');
        expect($cache->get('key1'))->toBeNull(); // Evicted
        expect($cache->get('key2'))->toBe('value2');
        expect($cache->get('key3'))->toBe('value3');
        
        usleep(100000);
        $cache->put('key4', 'value4');
        expect($cache->get('key2'))->toBeNull(); // Evicted
        expect($cache->get('key3'))->toBe('value3');
        expect($cache->get('key4'))->toBe('value4');
    });
    
    it('handles eviction when all items have same timestamp', function () {
        $cache = new QueryCache(2);
        
        // Use reflection to set items with same timestamp
        $reflection = new ReflectionClass($cache);
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        
        $timestamp = time();
        $cacheProperty->setValue($cache, [
            'key1' => ['value' => 'value1', 'expires' => $timestamp + 3600, 'created' => $timestamp],
            'key2' => ['value' => 'value2', 'expires' => $timestamp + 3600, 'created' => $timestamp]
        ]);
        
        // Adding a new item should evict one of them
        $cache->put('key3', 'value3');
        
        expect($cache->size())->toBe(2);
        expect($cache->get('key3'))->toBe('value3');
        
        // Either key1 or key2 should be evicted, but not both
        $key1Exists = $cache->get('key1') !== null;
        $key2Exists = $cache->get('key2') !== null;
        expect($key1Exists || $key2Exists)->toBeTrue();
        expect($key1Exists && $key2Exists)->toBeFalse();
    });
});

describe('QueryCache key generation', function () {
    it('generates consistent keys for same query and bindings', function () {
        $cache = new QueryCache();
        
        $query = 'SELECT * FROM users WHERE id = ?';
        $bindings = [1];
        
        $key1 = $cache->generateKey($query, $bindings);
        $key2 = $cache->generateKey($query, $bindings);
        
        expect($key1)->toBe($key2);
    });
    
    it('generates different keys for different queries', function () {
        $cache = new QueryCache();
        
        $key1 = $cache->generateKey('SELECT * FROM users');
        $key2 = $cache->generateKey('SELECT * FROM posts');
        
        expect($key1)->not->toBe($key2);
    });
    
    it('generates different keys for different bindings', function () {
        $cache = new QueryCache();
        
        $query = 'SELECT * FROM users WHERE id = ?';
        
        $key1 = $cache->generateKey($query, [1]);
        $key2 = $cache->generateKey($query, [2]);
        
        expect($key1)->not->toBe($key2);
    });
    
    it('handles empty bindings', function () {
        $cache = new QueryCache();
        
        $key = $cache->generateKey('SELECT * FROM users');
        
        expect($key)->toBeString();
        expect(strlen($key))->toBe(32); // MD5 hash length
    });
});

describe('QueryCache configuration', function () {
    it('respects custom max items setting', function () {
        $cache = new QueryCache(5, 3600);
        
        for ($i = 1; $i <= 6; $i++) {
            $cache->put("key$i", "value$i");
            usleep(10000); // Small delay to ensure different timestamps
        }
        
        expect($cache->size())->toBe(5);
        expect($cache->get('key1'))->toBeNull(); // First one should be evicted
        expect($cache->get('key6'))->toBe('value6'); // Last one should exist
    });
    
    it('respects custom TTL setting', function () {
        $cache = new QueryCache(100, 1); // 1 second default TTL
        
        $cache->put('key1', 'value1'); // Uses default TTL
        $cache->put('key2', 'value2', 3); // Custom TTL
        
        sleep(2); // Sleep for 2 seconds to ensure key1 expires
        
        // After 2 seconds, key1 should be expired but key2 should still be valid
        expect($cache->get('key1'))->toBeNull();
        expect($cache->get('key2'))->toBe('value2');
        
        sleep(2); // Sleep another 2 seconds
        
        // After 4 seconds total, both should be expired
        expect($cache->get('key2'))->toBeNull();
    });
});

describe('QueryCache edge cases', function () {
    it('handles null values', function () {
        $cache = new QueryCache();
        
        $cache->put('null_key', null);
        expect($cache->get('null_key'))->toBeNull();
        
        // But we can verify it was stored by checking size
        expect($cache->size())->toBe(1);
    });
    
    it('handles array values', function () {
        $cache = new QueryCache();
        
        $data = ['user' => 'John', 'age' => 30];
        $cache->put('array_key', $data);
        
        expect($cache->get('array_key'))->toBe($data);
    });
    
    it('handles object values', function () {
        $cache = new QueryCache();
        
        $obj = (object) ['name' => 'Test'];
        $cache->put('object_key', $obj);
        
        $retrieved = $cache->get('object_key');
        expect($retrieved)->toEqual($obj);
    });
    
    it('handles forgetting non-existent keys', function () {
        $cache = new QueryCache();
        
        $cache->put('key1', 'value1');
        expect($cache->size())->toBe(1);
        
        $cache->forget('non-existent');
        expect($cache->size())->toBe(1);
        expect($cache->get('key1'))->toBe('value1');
    });
    
    it('handles zero TTL', function () {
        $cache = new QueryCache();
        
        // Use reflection to verify the item is stored with TTL 0
        $reflection = new ReflectionClass($cache);
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        
        $cache->put('key1', 'value1', 0);
        
        $cached = $cacheProperty->getValue($cache);
        expect($cached)->toHaveKey('key1');
        // With 0 TTL, expires timestamp is current time
        expect($cached['key1']['expires'])->toBe(time());
        
        // Sleep for 1 full second to ensure we're past the expiration time
        sleep(1);
        expect($cache->get('key1'))->toBeNull();
    });
    
    it('handles negative TTL', function () {
        $cache = new QueryCache();
        
        $cache->put('key1', 'value1', -10);
        
        // With negative TTL, item should be immediately expired
        usleep(1000); // Small delay to ensure we're past any timestamp
        expect($cache->get('key1'))->toBeNull();
    });
});