<?php

use Bob\Cache\QueryCache;
use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Query\Processor;

beforeEach(function () {
    $this->connection = Mockery::mock(Connection::class);
    $this->grammar = Mockery::mock(Grammar::class);
    $this->processor = Mockery::mock(Processor::class);
    $this->queryCache = Mockery::mock(QueryCache::class);

    // Mock the connection methods that Builder constructor calls
    $this->connection->shouldReceive('getQueryGrammar')
        ->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')
        ->andReturn($this->processor);

    $this->builder = new Builder($this->connection);
});

describe('Exists Query Caching', function () {
    test('exists() uses cache when caching is enabled', function () {
        // Enable caching
        $this->builder->enableExistsCache(120);

        // Set up the query
        $this->builder->from('users')->where('id', 1);

        $sql = 'select exists(select * from users where id = ?) as `exists`';
        $bindings = [1];
        $cacheKey = 'exists_'.md5($sql.serialize($bindings));

        // Mock the cache and connection
        $this->connection->shouldReceive('getQueryCache')
            ->andReturn($this->queryCache);

        $this->grammar->shouldReceive('compileExists')
            ->once()
            ->andReturn($sql);

        $this->queryCache->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(null); // Cache miss

        $this->connection->shouldReceive('select')
            ->with($sql, $bindings)
            ->once()
            ->andReturn([['exists' => 1]]);

        $this->processor->shouldReceive('processSelect')
            ->once()
            ->andReturn([['exists' => 1]]);

        $this->queryCache->shouldReceive('put')
            ->with($cacheKey, true, 120)
            ->once();

        // Execute
        $result = $this->builder->exists();

        expect($result)->toBeTrue();
    });

    test('exists() returns cached result on second call', function () {
        // Enable caching
        $this->builder->enableExistsCache(60);

        // Set up the query
        $this->builder->from('posts')->where('status', 'published');

        $sql = 'select exists(select * from posts where status = ?) as `exists`';
        $bindings = ['published'];
        $cacheKey = 'exists_'.md5($sql.serialize($bindings));

        // Mock the cache and connection
        $this->connection->shouldReceive('getQueryCache')
            ->andReturn($this->queryCache);

        $this->grammar->shouldReceive('compileExists')
            ->once()
            ->andReturn($sql);

        $this->queryCache->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(true); // Cache hit!

        // Should NOT call select since we have a cached result
        $this->connection->shouldNotReceive('select');
        $this->processor->shouldNotReceive('processSelect');
        $this->queryCache->shouldNotReceive('put');

        // Execute
        $result = $this->builder->exists();

        expect($result)->toBeTrue();
    });

    test('exists() does not use cache when caching is disabled', function () {
        // Caching is disabled by default
        expect($this->builder->isExistsCachingEnabled())->toBeFalse();

        // Set up the query
        $this->builder->from('products');

        $sql = 'select exists(select * from products) as `exists`';

        $this->grammar->shouldReceive('compileExists')
            ->once()
            ->andReturn($sql);

        // Should NOT interact with cache at all
        $this->connection->shouldNotReceive('getQueryCache');

        $this->connection->shouldReceive('select')
            ->with($sql, [])
            ->once()
            ->andReturn([['exists' => 0]]);

        $this->processor->shouldReceive('processSelect')
            ->once()
            ->andReturn([['exists' => 0]]);

        // Execute
        $result = $this->builder->exists();

        expect($result)->toBeFalse();
    });

    test('enableExistsCache() and disableExistsCache() work correctly', function () {
        // Initially disabled
        expect($this->builder->isExistsCachingEnabled())->toBeFalse();

        // Enable with custom TTL
        $this->builder->enableExistsCache(300);
        expect($this->builder->isExistsCachingEnabled())->toBeTrue();

        // Disable again
        $this->builder->disableExistsCache();
        expect($this->builder->isExistsCachingEnabled())->toBeFalse();
    });

    test('exists() handles cache miss and stores result', function () {
        // Enable caching with default TTL
        $this->builder->enableExistsCache();

        // Set up the query
        $this->builder->from('orders')->where('status', 'pending');

        $sql = 'select exists(select * from orders where status = ?) as `exists`';
        $bindings = ['pending'];
        $cacheKey = 'exists_'.md5($sql.serialize($bindings));

        // Mock the cache and connection
        $this->connection->shouldReceive('getQueryCache')
            ->andReturn($this->queryCache);

        $this->grammar->shouldReceive('compileExists')
            ->once()
            ->andReturn($sql);

        // First call - cache miss
        $this->queryCache->shouldReceive('get')
            ->with($cacheKey)
            ->once()
            ->andReturn(null);

        $this->connection->shouldReceive('select')
            ->with($sql, $bindings)
            ->once()
            ->andReturn([['exists' => 1]]);

        $this->processor->shouldReceive('processSelect')
            ->once()
            ->andReturn([['exists' => 1]]);

        // Should store in cache with default TTL (60 seconds)
        $this->queryCache->shouldReceive('put')
            ->with($cacheKey, true, 60)
            ->once();

        // Execute
        $result = $this->builder->exists();

        expect($result)->toBeTrue();
    });

    test('exists() correctly handles various result formats', function () {
        // Set up the query
        $this->builder->from('test_table');

        $sql = 'select exists(select * from test_table) as `exists`';

        $this->grammar->shouldReceive('compileExists')
            ->times(6)
            ->andReturn($sql);

        // Test with array result returning true
        $this->connection->shouldReceive('select')
            ->once()
            ->andReturn([['exists' => 1]]);
        $this->processor->shouldReceive('processSelect')
            ->once()
            ->andReturn([['exists' => 1]]);
        expect($this->builder->exists())->toBeTrue();

        // Test with array result returning false
        $this->connection->shouldReceive('select')
            ->once()
            ->andReturn([['exists' => 0]]);
        $this->processor->shouldReceive('processSelect')
            ->once()
            ->andReturn([['exists' => 0]]);
        expect($this->builder->exists())->toBeFalse();

        // Test with object result returning true
        $obj = new stdClass;
        $obj->exists = 1;
        $this->connection->shouldReceive('select')
            ->once()
            ->andReturn([$obj]);
        $this->processor->shouldReceive('processSelect')
            ->once()
            ->andReturn([$obj]);
        expect($this->builder->exists())->toBeTrue();

        // Test with object result returning false
        $obj->exists = 0;
        $this->connection->shouldReceive('select')
            ->once()
            ->andReturn([$obj]);
        $this->processor->shouldReceive('processSelect')
            ->once()
            ->andReturn([$obj]);
        expect($this->builder->exists())->toBeFalse();

        // Test with empty result
        $this->connection->shouldReceive('select')
            ->once()
            ->andReturn([]);
        $this->processor->shouldReceive('processSelect')
            ->once()
            ->andReturn([]);
        expect($this->builder->exists())->toBeFalse();

        // Test with missing exists key
        $this->connection->shouldReceive('select')
            ->once()
            ->andReturn([['other' => 1]]);
        $this->processor->shouldReceive('processSelect')
            ->once()
            ->andReturn([['other' => 1]]);
        expect($this->builder->exists())->toBeFalse();
    });
});
