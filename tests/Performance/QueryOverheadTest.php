<?php

declare(strict_types=1);

use Bob\Database\Connection;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create simple test table
    $this->connection->statement('
        CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            name TEXT,
            value INTEGER
        )
    ');
});

test('query building overhead is less than 10ms for simple queries', function () {
    $iterations = 100;
    $times = [];

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);

        $builder = $this->connection->table('test_table');
        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $elapsed = (microtime(true) - $start) * 1000; // Convert to ms
        $times[] = $elapsed;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);

    expect($avgTime)->toBeLessThan(10.0);
    expect($maxTime)->toBeLessThan(10.0);

    echo "\nSimple SELECT: Avg: ".round($avgTime, 3).'ms, Max: '.round($maxTime, 3)."ms\n";
});

test('query building overhead is less than 10ms for complex queries', function () {
    $iterations = 100;
    $times = [];

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);

        $builder = $this->connection->table('test_table')
            ->select(['id', 'name', 'value'])
            ->where('value', '>', 100)
            ->where('name', 'like', '%test%')
            ->whereIn('id', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
            ->whereBetween('value', [50, 150])
            ->orderBy('value', 'desc')
            ->orderBy('name', 'asc')
            ->limit(50)
            ->offset(100);

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $elapsed = (microtime(true) - $start) * 1000;
        $times[] = $elapsed;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);

    expect($avgTime)->toBeLessThan(10.0);
    expect($maxTime)->toBeLessThan(10.0);

    echo 'Complex SELECT: Avg: '.round($avgTime, 3).'ms, Max: '.round($maxTime, 3)."ms\n";
});

test('query building overhead for INSERT queries', function () {
    $iterations = 100;
    $times = [];
    $data = [
        'name' => 'Test Name',
        'value' => 123,
    ];

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);

        $builder = $this->connection->table('test_table');
        $sql = $builder->grammar->compileInsert($builder, $data);

        $elapsed = (microtime(true) - $start) * 1000;
        $times[] = $elapsed;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);

    expect($avgTime)->toBeLessThan(10.0);
    expect($maxTime)->toBeLessThan(10.0);

    echo 'INSERT: Avg: '.round($avgTime, 3).'ms, Max: '.round($maxTime, 3)."ms\n";
});

test('query building overhead for UPDATE queries', function () {
    $iterations = 100;
    $times = [];
    $data = ['name' => 'Updated Name', 'value' => 456];

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);

        $builder = $this->connection->table('test_table')
            ->where('id', '>', 10)
            ->where('value', '<', 1000);
        $sql = $builder->grammar->compileUpdate($builder, $data);

        $elapsed = (microtime(true) - $start) * 1000;
        $times[] = $elapsed;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);

    expect($avgTime)->toBeLessThan(10.0);
    expect($maxTime)->toBeLessThan(10.0);

    echo 'UPDATE: Avg: '.round($avgTime, 3).'ms, Max: '.round($maxTime, 3)."ms\n";
});

test('query building overhead for DELETE queries', function () {
    $iterations = 100;
    $times = [];

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);

        $builder = $this->connection->table('test_table')
            ->where('value', '<', 0)
            ->orWhere('name', 'like', '%delete%');
        $sql = $builder->grammar->compileDelete($builder);

        $elapsed = (microtime(true) - $start) * 1000;
        $times[] = $elapsed;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);

    expect($avgTime)->toBeLessThan(10.0);
    expect($maxTime)->toBeLessThan(10.0);

    echo 'DELETE: Avg: '.round($avgTime, 3).'ms, Max: '.round($maxTime, 3)."ms\n";
});

test('query building overhead for JOIN queries', function () {
    // Create second table for joins
    $this->connection->statement('
        CREATE TABLE related_table (
            id INTEGER PRIMARY KEY,
            test_id INTEGER,
            description TEXT
        )
    ');

    $iterations = 100;
    $times = [];

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);

        $builder = $this->connection->table('test_table')
            ->join('related_table', 'test_table.id', '=', 'related_table.test_id')
            ->select(['test_table.*', 'related_table.description'])
            ->where('test_table.value', '>', 50)
            ->orderBy('test_table.id');

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $elapsed = (microtime(true) - $start) * 1000;
        $times[] = $elapsed;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);

    expect($avgTime)->toBeLessThan(10.0);
    expect($maxTime)->toBeLessThan(10.0);

    echo 'JOIN: Avg: '.round($avgTime, 3).'ms, Max: '.round($maxTime, 3)."ms\n";
});

test('query building overhead for aggregate queries', function () {
    $iterations = 100;
    $times = [];

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);

        $builder = $this->connection->table('test_table')
            ->where('value', '>', 0);
        $builder->aggregate = ['function' => 'count', 'columns' => ['*']];
        $sql = $builder->grammar->compileSelect($builder);

        $elapsed = (microtime(true) - $start) * 1000;
        $times[] = $elapsed;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);

    expect($avgTime)->toBeLessThan(10.0);
    expect($maxTime)->toBeLessThan(10.0);

    echo 'AGGREGATE: Avg: '.round($avgTime, 3).'ms, Max: '.round($maxTime, 3)."ms\n";
});

test('query building overhead for subqueries', function () {
    $iterations = 100;
    $times = [];

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);

        $subquery = $this->connection->table('test_table')
            ->select('id')
            ->where('value', '>', 100);

        $builder = $this->connection->table('test_table')
            ->whereIn('id', $subquery)
            ->orderBy('name');

        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $elapsed = (microtime(true) - $start) * 1000;
        $times[] = $elapsed;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);

    expect($avgTime)->toBeLessThan(10.0);
    expect($maxTime)->toBeLessThan(10.0);

    echo 'SUBQUERY: Avg: '.round($avgTime, 3).'ms, Max: '.round($maxTime, 3)."ms\n";
});

test('overall query building performance meets requirements', function () {
    $results = [];
    $queryTypes = [
        'simple_select' => function () {
            return $this->connection->table('test_table')->toSql();
        },
        'where_clause' => function () {
            return $this->connection->table('test_table')
                ->where('value', '>', 100)
                ->toSql();
        },
        'multiple_wheres' => function () {
            return $this->connection->table('test_table')
                ->where('value', '>', 100)
                ->where('name', 'like', '%test%')
                ->whereIn('id', [1, 2, 3])
                ->toSql();
        },
        'order_limit' => function () {
            return $this->connection->table('test_table')
                ->orderBy('value', 'desc')
                ->limit(10)
                ->offset(20)
                ->toSql();
        },
    ];

    foreach ($queryTypes as $type => $queryBuilder) {
        $times = [];
        for ($i = 0; $i < 50; $i++) {
            $start = microtime(true);
            $queryBuilder();
            $times[] = (microtime(true) - $start) * 1000;
        }

        $avg = array_sum($times) / count($times);
        $results[$type] = $avg;

        expect($avg)->toBeLessThan(10.0, "Query type '$type' exceeded 10ms threshold");
    }

    echo "\n=== QUERY BUILDING OVERHEAD SUMMARY ===\n";
    foreach ($results as $type => $avgTime) {
        echo str_pad($type, 20).': '.round($avgTime, 3)."ms\n";
    }

    $overallAvg = array_sum($results) / count($results);
    echo 'Overall Average: '.round($overallAvg, 3)."ms\n";

    expect($overallAvg)->toBeLessThan(10.0);
});
