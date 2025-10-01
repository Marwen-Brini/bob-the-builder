<?php

use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Query\Grammars\MySQLGrammar;
use Mockery as m;

describe('MySQLGrammar Tests', function () {

    beforeEach(function () {
        $this->grammar = new MySQLGrammar;
        $this->connection = m::mock(Connection::class);
        $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
        $this->connection->shouldReceive('getPostProcessor')->andReturn(m::mock(\Bob\Query\Processor::class));
        $this->builder = new Builder($this->connection);
        $this->builder->from('users');
    });

    afterEach(function () {
        m::close();
    });

    test('MySQLGrammar operators property', function () {
        $grammar = new MySQLGrammar;
        $reflection = new ReflectionClass($grammar);
        $operatorsProperty = $reflection->getProperty('operators');
        $operatorsProperty->setAccessible(true);
        $operators = $operatorsProperty->getValue($grammar);

        expect($operators)->toContain('=', '<', '>', '<=', '>=', '<>', '!=', '<=>');
        expect($operators)->toContain('like', 'like binary', 'not like', 'ilike');
        expect($operators)->toContain('&', '|', '^', '<<', '>>');
        expect($operators)->toContain('rlike', 'not rlike', 'regexp', 'not regexp');
    });

    test('wrapValue method with regular value (lines 18-25)', function () {
        $grammar = new class extends MySQLGrammar
        {
            public function testWrapValue(string $value): string
            {
                return $this->wrapValue($value);
            }
        };

        $result = $grammar->testWrapValue('name');
        expect($result)->toBe('`name`');

        // Test with backticks that need escaping
        $result = $grammar->testWrapValue('my`column');
        expect($result)->toBe('`my``column`');
    });

    test('wrapValue method with asterisk (lines 18-25)', function () {
        $grammar = new class extends MySQLGrammar
        {
            public function testWrapValue(string $value): string
            {
                return $this->wrapValue($value);
            }
        };

        $result = $grammar->testWrapValue('*');
        expect($result)->toBe('*');
    });

    test('compileInsertOrIgnore method (lines 27-30)', function () {
        $values = ['name' => 'John', 'email' => 'john@example.com'];

        $result = $this->grammar->compileInsertOrIgnore($this->builder, $values);

        expect($result)->toContain('insert ignore');
        expect($result)->toContain('`users`');
    });

    test('compileJsonLength method (lines 32-37)', function () {
        $grammar = new class extends MySQLGrammar
        {
            public function testCompileJsonLength(string $column, string $operator, string $value): string
            {
                return $this->compileJsonLength($column, $operator, $value);
            }
        };

        $result = $grammar->testCompileJsonLength('data->items', '>', '5');
        expect($result)->toContain('json_length');
        expect($result)->toContain('`data`');
        expect($result)->toContain('$.items');
    });

    test('wrapJsonFieldAndPath method (lines 39-47)', function () {
        $grammar = new class extends MySQLGrammar
        {
            public function testWrapJsonFieldAndPath(string $column): array
            {
                return $this->wrapJsonFieldAndPath($column);
            }
        };

        // Test with path
        $result = $grammar->testWrapJsonFieldAndPath('data->items->count');
        expect($result[0])->toBe('`data`');
        expect($result[1])->toContain('$.items.count');

        // Test without path
        $result = $grammar->testWrapJsonFieldAndPath('data');
        expect($result[0])->toBe('`data`');
        expect($result[1])->toBe('');
    });

    test('wrapJsonPath method (lines 49-52)', function () {
        $grammar = new class extends MySQLGrammar
        {
            public function testWrapJsonPath(string $value): string
            {
                return $this->wrapJsonPath($value);
            }
        };

        $result = $grammar->testWrapJsonPath('items->count');
        expect($result)->toBe('\'$.items.count\'');

        $result = $grammar->testWrapJsonPath('simple');
        expect($result)->toBe('\'$.simple\'');
    });

    test('compileUpsert method (lines 54-65)', function () {
        $values = [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ];
        $uniqueBy = ['email'];
        $update = ['name' => 'new_name', 'status' => 'updated'];

        $result = $this->grammar->compileUpsert($this->builder, $values, $uniqueBy, $update);

        expect($result)->toContain('insert into `users`');
        expect($result)->toContain('on duplicate key update');
        expect($result)->toContain('`name` = values(`name`)');
        expect($result)->toContain('`status` = values(`status`)');
    });

    test('compileLock method with boolean true (lines 67-74)', function () {
        $result = $this->grammar->compileLock($this->builder, true);
        expect($result)->toBe(' for update');
    });

    test('compileLock method with boolean false (lines 67-74)', function () {
        $result = $this->grammar->compileLock($this->builder, false);
        expect($result)->toBe(' lock in share mode');
    });

    test('compileLock method with string value (lines 67-74)', function () {
        $result = $this->grammar->compileLock($this->builder, 'for update nowait');
        expect($result)->toBe('for update nowait');

        $result = $this->grammar->compileLock($this->builder, 'lock in share mode');
        expect($result)->toBe('lock in share mode');
    });

    test('compileRandom method without seed (lines 76-79)', function () {
        $result = $this->grammar->compileRandom();
        expect($result)->toBe('RAND()');
    });

    test('compileRandom method with seed (lines 76-79)', function () {
        $result = $this->grammar->compileRandom('12345');
        expect($result)->toBe('RAND(12345)');
    });

    test('MySQLGrammar integration with Builder for complex queries', function () {
        $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
        $this->connection->shouldReceive('raw')->andReturnUsing(function ($value) {
            return new \Bob\Database\Expression($value);
        });

        // Test INSERT IGNORE
        $this->builder->from('users');
        $sql = $this->grammar->compileInsertOrIgnore($this->builder, ['name' => 'John', 'email' => 'john@test.com']);
        expect($sql)->toContain('insert ignore into `users`');

        // Test UPSERT with ON DUPLICATE KEY UPDATE
        $upsertSql = $this->grammar->compileUpsert($this->builder, [['name' => 'John']], ['email'], ['name' => 'updated']);
        expect($upsertSql)->toContain('on duplicate key update');

        // Test random function
        $randomSql = $this->grammar->compileRandom('seed123');
        expect($randomSql)->toBe('RAND(seed123)');

        // Test lock compilation
        $lockSql = $this->grammar->compileLock($this->builder, true);
        expect($lockSql)->toBe(' for update');
    });

    test('MySQLGrammar JSON operations comprehensive test', function () {
        $grammar = new class extends MySQLGrammar
        {
            public function testWrapJsonFieldAndPath(string $column): array
            {
                return $this->wrapJsonFieldAndPath($column);
            }

            public function testWrapJsonPath(string $value): string
            {
                return $this->wrapJsonPath($value);
            }

            public function testCompileJsonLength(string $column, string $operator, string $value): string
            {
                return $this->compileJsonLength($column, $operator, $value);
            }
        };

        // Test nested JSON paths
        $result = $grammar->testWrapJsonFieldAndPath('profile->settings->theme');
        expect($result[0])->toBe('`profile`');
        expect($result[1])->toBe(', \'$.settings.theme\'');

        // Test JSON path with arrays
        $path = $grammar->testWrapJsonPath('users[0]->name');
        expect($path)->toBe('\'$.users[0].name\'');

        // Test JSON length with complex path
        $jsonLength = $grammar->testCompileJsonLength('data->array->items', '>=', '10');
        expect($jsonLength)->toBe('json_length(`data`, \'$.array.items\') >= 10');
    });

    test('MySQLGrammar wrapping edge cases', function () {
        $grammar = new class extends MySQLGrammar
        {
            public function testWrapValue(string $value): string
            {
                return $this->wrapValue($value);
            }
        };

        // Test empty string (should still wrap)
        $result = $grammar->testWrapValue('');
        expect($result)->toBe('``');

        // Test value with multiple backticks
        $result = $grammar->testWrapValue('col`with``backticks');
        expect($result)->toBe('`col``with````backticks`');

        // Test special MySQL reserved word
        $result = $grammar->testWrapValue('select');
        expect($result)->toBe('`select`');
    });

    test('MySQLGrammar upsert with various column types', function () {
        // Test upsert with numeric keys
        $values = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];
        $uniqueBy = ['id'];
        $update = [0 => 'name', 1 => 'email']; // Using numeric keys

        $result = $this->grammar->compileUpsert($this->builder, $values, $uniqueBy, $update);
        expect($result)->toContain('on duplicate key update');
        expect($result)->toContain('`0` = values(`0`)');
        expect($result)->toContain('`1` = values(`1`)');
    });

});
