<?php

use Bob\Query\Grammars\SQLiteGrammar;
use Bob\Query\Builder;
use Bob\Database\Connection;
use Mockery as m;

describe('SQLiteGrammar Tests', function () {

    beforeEach(function () {
        $this->grammar = new SQLiteGrammar();
        $this->connection = m::mock(Connection::class);
        $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
        $this->connection->shouldReceive('getPostProcessor')->andReturn(m::mock(\Bob\Query\Processor::class));
        $this->builder = new Builder($this->connection);
        $this->builder->from('users');
    });

    afterEach(function () {
        m::close();
    });

    test('SQLiteGrammar operators property', function () {
        $grammar = new SQLiteGrammar();
        $reflection = new ReflectionClass($grammar);
        $operatorsProperty = $reflection->getProperty('operators');
        $operatorsProperty->setAccessible(true);
        $operators = $operatorsProperty->getValue($grammar);

        expect($operators)->toContain('=', '<', '>', '<=', '>=', '<>', '!=');
        expect($operators)->toContain('like', 'not like', 'ilike');
        expect($operators)->toContain('&', '|', '<<', '>>');
    });

    test('compileInsertOrIgnore method (lines 16-19)', function () {
        $this->builder->select(['name', 'email']);
        $values = ['name' => 'John', 'email' => 'john@example.com'];

        $result = $this->grammar->compileInsertOrIgnore($this->builder, $values);

        expect($result)->toContain('insert or ignore');
        expect($result)->toContain('users');
    });

    test('compileTruncate method (lines 21-27)', function () {
        $result = $this->grammar->compileTruncate($this->builder);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('delete from sqlite_sequence where name = ?');
        expect($result['delete from sqlite_sequence where name = ?'])->toBe(['users']);
        expect($result)->toHaveKey('delete from "users"');
    });

    test('compileLock method returns empty string (lines 29-32)', function () {
        $result = $this->grammar->compileLock($this->builder, true);
        expect($result)->toBe('');

        $result = $this->grammar->compileLock($this->builder, false);
        expect($result)->toBe('');

        $result = $this->grammar->compileLock($this->builder, 'for update');
        expect($result)->toBe('');
    });

    test('wrapUnion method (lines 34-37)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testWrapUnion(string $sql): string {
                return $this->wrapUnion($sql);
            }
        };

        $sql = 'select * from users union select * from posts';
        $result = $grammar->testWrapUnion($sql);

        expect($result)->toBe('select * from (select * from users union select * from posts)');
    });

    test('compileUpsert method (lines 39-46)', function () {
        $values = [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com']
        ];
        $uniqueBy = ['email'];
        $update = ['name'];

        $result = $this->grammar->compileUpsert($this->builder, $values, $uniqueBy, $update);

        expect($result)->toContain('insert or replace');
        expect($result)->toContain('users');
    });

    test('supportsSavepoints method (lines 48-51)', function () {
        $result = $this->grammar->supportsSavepoints();
        expect($result)->toBeTrue();
    });

    test('compileDateBasedWhere method with Day (lines 53-65)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testCompileDateBasedWhere(string $type, $query, array $where): string {
                return $this->compileDateBasedWhere($type, $query, $where);
            }
        };

        $where = [
            'column' => 'created_at',
            'operator' => '=',
            'value' => '15'
        ];

        $result = $grammar->testCompileDateBasedWhere('Day', $this->builder, $where);
        expect($result)->toContain('strftime(\'%d\'');
        expect($result)->toContain('"created_at"');
    });

    test('compileDateBasedWhere method with Month (lines 53-65)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testCompileDateBasedWhere(string $type, $query, array $where): string {
                return $this->compileDateBasedWhere($type, $query, $where);
            }
        };

        $where = [
            'column' => 'created_at',
            'operator' => '=',
            'value' => '12'
        ];

        $result = $grammar->testCompileDateBasedWhere('Month', $this->builder, $where);
        expect($result)->toContain('strftime(\'%m\'');
    });

    test('compileDateBasedWhere method with Year (lines 53-65)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testCompileDateBasedWhere(string $type, $query, array $where): string {
                return $this->compileDateBasedWhere($type, $query, $where);
            }
        };

        $where = [
            'column' => 'created_at',
            'operator' => '=',
            'value' => '2023'
        ];

        $result = $grammar->testCompileDateBasedWhere('Year', $this->builder, $where);
        expect($result)->toContain('strftime(\'%Y\'');
    });

    test('compileDateBasedWhere method with Date (lines 53-65)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testCompileDateBasedWhere(string $type, $query, array $where): string {
                return $this->compileDateBasedWhere($type, $query, $where);
            }
        };

        $where = [
            'column' => 'created_at',
            'operator' => '=',
            'value' => '2023-12-15'
        ];

        $result = $grammar->testCompileDateBasedWhere('Date', $this->builder, $where);
        expect($result)->toContain('date(');
    });

    test('compileDateBasedWhere method with Time (lines 53-65)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testCompileDateBasedWhere(string $type, $query, array $where): string {
                return $this->compileDateBasedWhere($type, $query, $where);
            }
        };

        $where = [
            'column' => 'created_at',
            'operator' => '=',
            'value' => '14:30:00'
        ];

        $result = $grammar->testCompileDateBasedWhere('Time', $this->builder, $where);
        expect($result)->toContain('time(');
    });

    test('compileJsonLength method (lines 67-72)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testCompileJsonLength(string $column, string $operator, string $value): string {
                return $this->compileJsonLength($column, $operator, $value);
            }
        };

        $result = $grammar->testCompileJsonLength('data->items', '>', '5');
        expect($result)->toContain('json_array_length');
        expect($result)->toContain('"data"');
        expect($result)->toContain('$.items');
    });

    test('wrapJsonFieldAndPath method (lines 74-82)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testWrapJsonFieldAndPath(string $column): array {
                return $this->wrapJsonFieldAndPath($column);
            }
        };

        // Test with path
        $result = $grammar->testWrapJsonFieldAndPath('data->items->count');
        expect($result[0])->toBe('"data"');
        expect($result[1])->toContain('$.items.count');

        // Test without path
        $result = $grammar->testWrapJsonFieldAndPath('data');
        expect($result[0])->toBe('"data"');
        expect($result[1])->toBe('');
    });

    test('wrapJsonPath method (lines 84-87)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testWrapJsonPath(string $value): string {
                return $this->wrapJsonPath($value);
            }
        };

        $result = $grammar->testWrapJsonPath('items->count');
        expect($result)->toBe('\'$.items.count\'');

        $result = $grammar->testWrapJsonPath('simple');
        expect($result)->toBe('\'$.simple\'');
    });

    test('compileRandom method without seed (lines 89-92)', function () {
        $result = $this->grammar->compileRandom();
        expect($result)->toBe('random()');
    });

    test('compileRandom method with seed (lines 89-92)', function () {
        $result = $this->grammar->compileRandom('12345');
        expect($result)->toBe('abs(random() / 12345)');
    });

    test('whereDate method (lines 94-97)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testWhereDate($query, array $where): string {
                return $this->whereDate($query, $where);
            }
        };

        $where = ['column' => 'created_at', 'operator' => '='];
        $result = $grammar->testWhereDate($this->builder, $where);

        expect($result)->toBe('date("created_at") = ?');
    });

    test('whereTime method (lines 99-102)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testWhereTime($query, array $where): string {
                return $this->whereTime($query, $where);
            }
        };

        $where = ['column' => 'created_at', 'operator' => '>='];
        $result = $grammar->testWhereTime($this->builder, $where);

        expect($result)->toBe('time("created_at") >= ?');
    });

    test('whereDay method (lines 104-107)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testWhereDay($query, array $where): string {
                return $this->whereDay($query, $where);
            }
        };

        $where = ['column' => 'created_at', 'operator' => '='];
        $result = $grammar->testWhereDay($this->builder, $where);

        expect($result)->toBe('cast(strftime(\'%d\', "created_at") as integer) = ?');
    });

    test('whereMonth method (lines 109-112)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testWhereMonth($query, array $where): string {
                return $this->whereMonth($query, $where);
            }
        };

        $where = ['column' => 'created_at', 'operator' => '<='];
        $result = $grammar->testWhereMonth($this->builder, $where);

        expect($result)->toBe('cast(strftime(\'%m\', "created_at") as integer) <= ?');
    });

    test('whereYear method (lines 114-117)', function () {
        $grammar = new class extends SQLiteGrammar {
            public function testWhereYear($query, array $where): string {
                return $this->whereYear($query, $where);
            }
        };

        $where = ['column' => 'created_at', 'operator' => '>'];
        $result = $grammar->testWhereYear($this->builder, $where);

        expect($result)->toBe('cast(strftime(\'%Y\', "created_at") as integer) > ?');
    });

    test('SQLiteGrammar integration with Builder for complex queries', function () {
        $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
        $this->connection->shouldReceive('raw')->andReturnUsing(function ($value) {
            return new \Bob\Database\Expression($value);
        });

        // Test INSERT OR IGNORE
        $this->builder->from('users');
        $sql = $this->grammar->compileInsertOrIgnore($this->builder, ['name' => 'John', 'email' => 'john@test.com']);
        expect($sql)->toContain('insert or ignore into "users"');

        // Test UPSERT
        $upsertSql = $this->grammar->compileUpsert($this->builder, [['name' => 'John']], ['email'], ['name']);
        expect($upsertSql)->toContain('insert or replace');

        // Test random function
        $randomSql = $this->grammar->compileRandom();
        expect($randomSql)->toBe('random()');

        // Test savepoints support
        $supportsSevepoints = $this->grammar->supportsSavepoints();
        expect($supportsSevepoints)->toBeTrue();
    });

});