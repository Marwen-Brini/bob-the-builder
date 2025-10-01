<?php

use Bob\cli\BobCommand;

describe('BobCommand 100% Coverage Tests', function () {

    test('BobCommand build with --execute shows actual results (line 209)', function () {
        // Create a test database with data
        $dbFile = sys_get_temp_dir().'/test_bob_exec_'.uniqid().'.db';
        $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => $dbFile]);
        $connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $connection->statement('INSERT INTO users (name) VALUES ("Alice"), ("Bob")');

        $command = new class(['bob']) extends BobCommand
        {
            public static $testDb;

            protected function getConnectionConfig($driver, $args): array
            {
                return ['driver' => 'sqlite', 'database' => self::$testDb];
            }
        };

        $command::$testDb = $dbFile;

        ob_start();
        $result = $command->run(['bob', 'build', 'sqlite', 'select * from users', '--execute']);
        $output = ob_get_clean();

        expect($output)->toContain('Executing query');
        expect($output)->toContain('Results (2 rows)');
        expect($output)->toContain('Alice');
        expect($output)->toContain('Bob');

        // Clean up
        @unlink($dbFile);
    });

    test('BobCommand execute with colon syntax (line 239)', function () {
        $dbFile = sys_get_temp_dir().'/test_bob_colon_'.uniqid().'.db';
        $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => $dbFile]);
        $connection->statement('CREATE TABLE products (id INTEGER, name TEXT)');
        $connection->statement('INSERT INTO products VALUES (1, "Widget")');

        $command = new class(['bob']) extends BobCommand
        {
            public static $testDb;

            protected function getConnectionConfigWithDefaults($driver): array
            {
                return ['driver' => 'sqlite', 'database' => self::$testDb];
            }
        };

        $command::$testDb = $dbFile;

        ob_start();
        $command->run(['bob', 'execute', 'sqlite', 'select:* from:products']);
        $output = ob_get_clean();

        expect($output)->toContain('Results');
        expect($output)->toContain('Widget');

        @unlink($dbFile);
    });

    test('BobCommand schema lists tables (line 287)', function () {
        $dbFile = sys_get_temp_dir().'/test_bob_schema_'.uniqid().'.db';
        $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => $dbFile]);
        $connection->statement('CREATE TABLE table1 (id INTEGER)');
        $connection->statement('CREATE TABLE table2 (id INTEGER)');
        $connection->statement('CREATE TABLE table3 (id INTEGER)');

        $command = new class(['bob']) extends BobCommand
        {
            public static $testDb;

            protected function getConnectionConfigWithDefaults($driver): array
            {
                return ['driver' => 'sqlite', 'database' => self::$testDb];
            }
        };

        $command::$testDb = $dbFile;

        ob_start();
        $command->run(['bob', 'schema', 'sqlite']);
        $output = ob_get_clean();

        expect($output)->toContain('Available tables');
        expect($output)->toContain('- table1');
        expect($output)->toContain('- table2');
        expect($output)->toContain('- table3');

        @unlink($dbFile);
    });

    test('BobCommand export CSV with real data (lines 338-346)', function () {
        $dbFile = sys_get_temp_dir().'/test_bob_csv_'.uniqid().'.db';
        $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => $dbFile]);
        $connection->statement('CREATE TABLE employees (id INTEGER, name TEXT, salary REAL)');
        $connection->statement('INSERT INTO employees VALUES (1, "John Doe", 50000.50)');
        $connection->statement("INSERT INTO employees VALUES (2, 'Jane \"Smith\"', 60000.75)");
        $connection->statement('INSERT INTO employees VALUES (3, "Bob", NULL)');

        $command = new class(['bob']) extends BobCommand
        {
            public static $testDb;

            protected function getConnectionConfigWithDefaults($driver): array
            {
                return ['driver' => 'sqlite', 'database' => self::$testDb];
            }
        };

        $command::$testDb = $dbFile;

        ob_start();
        $command->run(['bob', 'export', 'sqlite', 'select * from employees', '--format=csv']);
        $output = ob_get_clean();

        // Check CSV headers
        expect($output)->toContain('id,name,salary');
        // Check data with proper CSV escaping
        expect($output)->toContain('"John Doe"');
        expect($output)->toContain('"Jane ""Smith"""'); // Double quotes escaped
        expect($output)->toContain('50000.5');
        expect($output)->toContain('Bob');

        @unlink($dbFile);
    });

    test('BobCommand parseAndBuildQuery with parts without colon (line 409)', function () {
        $command = new class(['bob']) extends BobCommand
        {
            public function testParseAndBuild()
            {
                $grammar = $this->createGrammar('mysql');
                $processor = new \Bob\Query\Processor;
                $connection = $this->createMockConnection($grammar, $processor);
                $builder = new \Bob\Query\Builder($connection);

                // Mix parts with and without colons
                $this->parseAndBuildQuery($builder, 'select:* from:users sometext where:id,1 moretext');

                return $builder->toSql();
            }
        };

        $sql = $command->testParseAndBuild();
        expect($sql)->toContain('select * from `users`');
        expect($sql)->toContain('where `id` = ?');
    });

    test('BobCommand export CSV with empty result set', function () {
        $dbFile = sys_get_temp_dir().'/test_bob_empty_'.uniqid().'.db';
        $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => $dbFile]);
        $connection->statement('CREATE TABLE empty_table (id INTEGER, name TEXT)');

        $command = new class(['bob']) extends BobCommand
        {
            public static $testDb;

            protected function getConnectionConfigWithDefaults($driver): array
            {
                return ['driver' => 'sqlite', 'database' => self::$testDb];
            }
        };

        $command::$testDb = $dbFile;

        ob_start();
        $command->run(['bob', 'export', 'sqlite', 'select * from empty_table', '--format=csv']);
        $output = ob_get_clean();

        // Should be empty or just newline
        expect(trim($output))->toBe('');

        @unlink($dbFile);
    });

    test('BobCommand export JSON format', function () {
        $dbFile = sys_get_temp_dir().'/test_bob_json_'.uniqid().'.db';
        $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => $dbFile]);
        $connection->statement('CREATE TABLE data (id INTEGER, value TEXT)');
        $connection->statement('INSERT INTO data VALUES (1, "test")');

        $command = new class(['bob']) extends BobCommand
        {
            public static $testDb;

            protected function getConnectionConfigWithDefaults($driver): array
            {
                return ['driver' => 'sqlite', 'database' => self::$testDb];
            }
        };

        $command::$testDb = $dbFile;

        ob_start();
        $command->run(['bob', 'export', 'sqlite', 'select * from data']);
        $output = ob_get_clean();

        $json = json_decode($output, true);
        expect($json)->toBeArray();
        expect($json[0]['value'])->toBe('test');

        @unlink($dbFile);
    });

    test('BobCommand schema with no tables', function () {
        $dbFile = sys_get_temp_dir().'/test_bob_notables_'.uniqid().'.db';
        $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => $dbFile]);
        // Don't create any tables

        $command = new class(['bob']) extends BobCommand
        {
            public static $testDb;

            protected function getConnectionConfigWithDefaults($driver): array
            {
                return ['driver' => 'sqlite', 'database' => self::$testDb];
            }
        };

        $command::$testDb = $dbFile;

        ob_start();
        $command->run(['bob', 'schema', 'sqlite']);
        $output = ob_get_clean();

        expect($output)->toContain('Available tables');
        // No table listings

        @unlink($dbFile);
    });

    test('BobCommand build without --execute and with results', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'select * from users']);
        $output = ob_get_clean();

        expect($output)->toContain('Generated SQL');
        expect($output)->not->toContain('Executing query');
        expect($output)->not->toContain('Results (');
    });

    test('BobCommand parseAndBuildQuery handles all missing cases', function () {
        $command = new class(['bob']) extends BobCommand
        {
            public function testAllCases()
            {
                $grammar = $this->createGrammar('mysql');
                $processor = new \Bob\Query\Processor;
                $connection = $this->createMockConnection($grammar, $processor);
                $builder = new \Bob\Query\Builder($connection);

                // Test with only parts without colons (should continue through)
                $this->parseAndBuildQuery($builder, 'just text without colons');

                // Test with mixed content
                $this->parseAndBuildQuery($builder, 'some text select:id,name more text from:table even more');

                return $builder->toSql();
            }
        };

        $sql = $command->testAllCases();
        expect($sql)->toContain('select `id`, `name` from `table`');
    });

});
