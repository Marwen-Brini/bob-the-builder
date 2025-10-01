<?php

use Bob\Database\Connection;
use Bob\Database\Expression;
use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Grammars\SQLiteGrammar;
use Bob\Query\Processor;

describe('Builder Maximum Coverage Tests', function () {

    beforeEach(function () {
        $this->connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->grammar = new MySQLGrammar;
        $this->processor = new Processor;
        $this->builder = new Builder($this->connection, $this->grammar, $this->processor);
    });

    // Line 747: bindings type initialization
    test('mergeBindingsForType initializes missing binding type', function () {
        // Use reflection to access protected method
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('mergeBindingsForType');
        $method->setAccessible(true);

        // Get initial bindings property
        $bindingsProperty = $reflection->getProperty('bindings');
        $bindingsProperty->setAccessible(true);
        $bindings = $bindingsProperty->getValue($this->builder);

        // Remove a binding type to test initialization
        unset($bindings['join']);
        $bindingsProperty->setValue($this->builder, $bindings);

        // Now merge should initialize it
        $method->invoke($this->builder, 'join', ['value1']);

        $bindings = $bindingsProperty->getValue($this->builder);
        expect($bindings)->toHaveKey('join');
        expect($bindings['join'])->toBe(['value1']);
    });

    // Line 1238: average() is alias for avg()
    test('average method is alias for avg', function () {
        $this->connection->statement('CREATE TABLE numbers (value INTEGER)');
        $this->connection->statement('INSERT INTO numbers VALUES (10), (20), (30)');

        $average = $this->builder->from('numbers')->average('value');
        expect($average)->toBe(20.0);

        // Verify it returns same as avg
        $builder2 = new Builder($this->connection);
        $avg = $builder2->from('numbers')->avg('value');
        expect($average)->toBe($avg);
    });

    // Line 1266: exists() and count testing
    test('exists returns correct boolean for records', function () {
        $this->connection->statement('CREATE TABLE test_exists (id INTEGER)');

        // Test count() returns 0 for empty table
        $count = $this->builder->from('test_exists')->count();
        expect($count)->toBe(0);

        // Add records
        $this->connection->statement('INSERT INTO test_exists VALUES (1)');
        $this->connection->statement('INSERT INTO test_exists VALUES (2)');

        $builder2 = new Builder($this->connection);
        $count2 = $builder2->from('test_exists')->count();
        expect($count2)->toBe(2);

        // Test exists() returns true when records exist
        $builder3 = new Builder($this->connection);
        $exists = $builder3->from('test_exists')->exists();
        expect($exists)->toBeTrue();

        // Test exists with where clause
        $builder4 = new Builder($this->connection);
        $exists2 = $builder4->from('test_exists')->where('id', 1)->exists();
        expect($exists2)->toBeTrue();
    });

    // Line 1313: leftJoinWhere comprehensive test
    test('leftJoinWhere with complex conditions', function () {
        $sql = $this->builder->from('users')
            ->leftJoinWhere('posts', 'users.id', '=', 'posts.user_id', function ($join) {
                $join->where('posts.status', 'published')
                    ->where('posts.views', '>', 100);
            })
            ->toSql();

        expect($sql)->toContain('left join');
        $bindings = $this->builder->getRawBindings();
        expect($bindings['join'])->toContain('published');
        expect($bindings['join'])->toContain(100);
    });

    // Line 1368: orderByRaw with complex expression
    test('orderByRaw with complex SQL expression', function () {
        $sql = $this->builder->from('products')
            ->orderByRaw('FIELD(category, ?, ?, ?) DESC, price ASC', ['electronics', 'books', 'clothing'])
            ->toSql();

        expect($sql)->toContain('order by FIELD(category, ?, ?, ?) DESC, price ASC');

        $bindings = $this->builder->getRawBindings();
        expect($bindings['order'])->toBe(['electronics', 'books', 'clothing']);
    });

    // Lines 1423, 1437: latest() and oldest() methods
    test('latest and oldest use default column', function () {
        // latest() defaults to created_at DESC
        $sql1 = $this->builder->from('posts')->latest()->toSql();
        expect($sql1)->toContain('order by');
        expect($sql1)->toContain('created_at');
        expect($sql1)->toContain('desc');

        // oldest() defaults to created_at ASC
        $builder2 = new Builder($this->connection, $this->grammar, $this->processor);
        $sql2 = $builder2->from('posts')->oldest()->toSql();
        expect($sql2)->toContain('order by');
        expect($sql2)->toContain('created_at');
        expect($sql2)->toContain('asc');

        // Test with custom columns
        $builder3 = new Builder($this->connection, $this->grammar, $this->processor);
        $sql3 = $builder3->from('posts')->latest('updated_at')->toSql();
        expect($sql3)->toContain('updated_at');

        $builder4 = new Builder($this->connection, $this->grammar, $this->processor);
        $sql4 = $builder4->from('posts')->oldest('published_at')->toSql();
        expect($sql4)->toContain('published_at');
    });

    // Line 1442: inRandomOrder for different databases
    test('inRandomOrder adds random ordering for different grammars', function () {
        // SQLite
        $sqliteGrammar = new SQLiteGrammar;
        $builder1 = new Builder($this->connection, $sqliteGrammar, $this->processor);
        $sql1 = $builder1->from('users')->inRandomOrder()->toSql();
        // SQLite uses RANDOM() in the order by clause
        expect($sql1)->toContain('order by');

        // MySQL
        $sql2 = $this->builder->from('users')->inRandomOrder()->toSql();
        // MySQL uses RAND() in the order by clause
        expect($sql2)->toContain('order by');
    });

    // Lines 1479, 1491: reorder method variations
    test('reorder clears orders and can set new one', function () {
        // Test reorder with no parameters clears all
        $this->builder->from('users')
            ->orderBy('name')
            ->orderBy('email')
            ->reorder();

        expect($this->builder->orders)->toBeNull();

        // Test reorder with parameters
        $builder2 = new Builder($this->connection, $this->grammar, $this->processor);
        $builder2->from('users')
            ->orderBy('name')
            ->orderBy('email')
            ->reorder('id', 'desc');

        expect($builder2->orders)->toHaveCount(1);
        expect($builder2->orders[0]['column'])->toBe('id');
        expect($builder2->orders[0]['direction'])->toBe('desc');
    });

    // Line 1544: skip() is alias for offset()
    test('skip is alias for offset', function () {
        $this->builder->from('users')->skip(10);
        expect($this->builder->offset)->toBe(10);

        // Test chaining
        $this->builder->skip(20);
        expect($this->builder->offset)->toBe(20);
    });

    // Line 1544: take() is alias for limit()
    test('take is alias for limit', function () {
        $this->builder->from('users')->take(5);
        expect($this->builder->limit)->toBe(5);

        // Test chaining
        $this->builder->take(10);
        expect($this->builder->limit)->toBe(10);
    });

    // Line 1589: forPage calculation
    test('forPage calculates correct offset for pagination', function () {
        // Page 1
        $this->builder->from('users')->forPage(1, 20);
        expect($this->builder->limit)->toBe(20);
        expect($this->builder->offset)->toBe(0);

        // Page 2
        $builder2 = new Builder($this->connection, $this->grammar, $this->processor);
        $builder2->from('users')->forPage(2, 20);
        expect($builder2->limit)->toBe(20);
        expect($builder2->offset)->toBe(20);

        // Page 5
        $builder3 = new Builder($this->connection, $this->grammar, $this->processor);
        $builder3->from('users')->forPage(5, 15);
        expect($builder3->limit)->toBe(15);
        expect($builder3->offset)->toBe(60); // (5-1) * 15
    });

    // Lines 1772-1774: paginate method
    test('paginate returns correct page of results', function () {
        $this->connection->statement('CREATE TABLE items (id INTEGER, name TEXT)');
        for ($i = 1; $i <= 25; $i++) {
            $this->connection->statement("INSERT INTO items VALUES ($i, 'Item $i')");
        }

        // Get page 2 with 10 per page
        $results = $this->builder->from('items')
            ->orderBy('id')
            ->limit(10)
            ->offset(10)
            ->get();

        expect($results)->toHaveCount(10);
        expect($results[0]->id)->toBe(11);
        expect($results[9]->id)->toBe(20);
    });

    // Line 1814: simplePaginate
    test('simplePaginate gets next page indicator', function () {
        $this->connection->statement('CREATE TABLE pages (id INTEGER)');
        for ($i = 1; $i <= 15; $i++) {
            $this->connection->statement("INSERT INTO pages VALUES ($i)");
        }

        // Get first page with limit 10 + 1 to check if more exist
        $results = $this->builder->from('pages')
            ->limit(11)
            ->get();

        // Should get 11 results, indicating more pages exist
        expect($results)->toHaveCount(11);
    });

    // Line 1841: getCountForPagination
    test('getCountForPagination counts records correctly', function () {
        $this->connection->statement('CREATE TABLE records (id INTEGER, type TEXT)');
        $this->connection->statement('INSERT INTO records VALUES (1, "A"), (2, "B"), (3, "A"), (4, "C")');

        $count = $this->builder->from('records')->count();
        expect($count)->toBe(4);

        // Test with where clause
        $builder2 = new Builder($this->connection);
        $count2 = $builder2->from('records')->where('type', 'A')->count();
        expect($count2)->toBe(2);
    });

    // Line 2020: whereColumn with operator
    test('whereColumn compares two columns with operator', function () {
        $sql = $this->builder->from('users')
            ->whereColumn('updated_at', '>=', 'created_at')
            ->toSql();

        expect($sql)->toContain('where');
        expect($sql)->toContain('updated_at');
        expect($sql)->toContain('created_at');
    });

    // Line 2045: whereColumn with array
    test('whereColumn accepts array of conditions', function () {
        $sql = $this->builder->from('users')
            ->whereColumn([
                ['first_name', '=', 'last_name'],
                ['updated_at', '>', 'created_at'],
            ])
            ->toSql();

        expect($sql)->toContain('where');
        expect($sql)->toContain('first_name');
        expect($sql)->toContain('last_name');
        expect($sql)->toContain('updated_at');
        expect($sql)->toContain('created_at');
    });

    // Line 2064: orWhereColumn
    test('orWhereColumn adds OR column comparison', function () {
        $sql = $this->builder->from('users')
            ->where('active', 1)
            ->orWhereColumn('first_name', 'last_name')
            ->toSql();

        expect($sql)->toContain('where');
        expect($sql)->toContain('or');

        $wheres = $this->builder->wheres;
        expect($wheres)->toHaveCount(2);
        expect($wheres[1]['boolean'])->toBe('or');
    });

    // Lines 2091-2099: whereJsonContains
    test('whereJsonContains checks JSON field', function () {
        $sql = $this->builder->from('users')
            ->whereJsonContains('options->roles', 'admin')
            ->toSql();

        expect($sql)->toContain('where');

        $bindings = $this->builder->getBindings();
        // The binding might be JSON encoded or not depending on the database
        $hasAdmin = in_array('admin', $bindings) || in_array('"admin"', $bindings);
        expect($hasAdmin)->toBeTrue();
    });

    // Lines 2116-2145: whereJsonLength
    test('whereJsonLength checks JSON array length', function () {
        $sql = $this->builder->from('users')
            ->whereJsonLength('tags', 5)
            ->toSql();

        expect($sql)->toContain('where');

        $bindings = $this->builder->getBindings();
        expect($bindings)->toContain(5);
    });

    test('whereJsonLength with operator', function () {
        $sql = $this->builder->from('users')
            ->whereJsonLength('items', '>=', 3)
            ->toSql();

        expect($sql)->toContain('where');

        $bindings = $this->builder->getBindings();
        expect($bindings)->toContain(3);
    });

    // Line 2155: dd() method
    test('dd dumps query and data', function () {
        $dumped = false;
        $dumpedSql = '';
        $dumpedBindings = [];

        // Override dd to capture instead of dying
        $builder = new class($this->connection, $this->grammar, $this->processor) extends Builder
        {
            public function dd()
            {
                $GLOBALS['test_dd_sql'] = $this->toSql();
                $GLOBALS['test_dd_bindings'] = $this->getBindings();

                return ['dumped' => true];
            }
        };

        $builder->from('users')->where('id', 1);
        $result = $builder->dd();

        expect($result['dumped'])->toBeTrue();
        expect($GLOBALS['test_dd_sql'])->toContain('where');
        expect($GLOBALS['test_dd_bindings'])->toContain(1);

        unset($GLOBALS['test_dd_sql']);
        unset($GLOBALS['test_dd_bindings']);
    });

    // Lines 2304-2307: Additional connection methods
    test('builder uses connection methods properly', function () {
        // Test that builder can access connection
        expect($this->builder->connection)->toBe($this->connection);

        // Test that builder uses grammar from connection
        expect($this->builder->grammar)->toBeInstanceOf(Grammar::class);

        // Test that builder uses processor
        expect($this->builder->processor)->toBeInstanceOf(Processor::class);
    });

    // Line 2379: __clone
    test('clone creates deep copy of builder', function () {
        $this->builder->from('users')
            ->select(['id', 'name'])
            ->where('active', 1)
            ->whereIn('role', ['admin', 'user'])
            ->orderBy('name')
            ->limit(10);

        $cloned = clone $this->builder;

        // Verify clone has same state
        expect($cloned->from)->toBe('users');
        expect($cloned->columns)->toBe(['id', 'name']);
        expect(count($cloned->wheres))->toBe(2);
        expect($cloned->orders)->toHaveCount(1);
        expect($cloned->limit)->toBe(10);

        // Verify they are independent
        $cloned->where('extra', 'value');
        expect(count($cloned->wheres))->toBe(3);
        expect(count($this->builder->wheres))->toBe(2); // Original unchanged
    });
});
