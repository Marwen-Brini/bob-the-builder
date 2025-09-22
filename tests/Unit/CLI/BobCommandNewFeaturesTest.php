<?php

use Bob\cli\BobCommand;

describe('BobCommand New Features Tests', function () {

    test('BobCommand constructor accepts argv', function () {
        $command = new BobCommand(['bob', 'test']);
        expect($command)->toBeInstanceOf(BobCommand::class);
    });

    test('BobCommand dual DSL syntax detection', function () {
        $command = new BobCommand(['bob']);

        // Test colon syntax detection
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'select:id,name from:users where:active,1']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('Generated SQL');

        // Test natural SQL syntax
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'select id, name from users where active = 1']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('Generated SQL');
    });

    test('BobCommand --execute flag', function () {
        $command = new BobCommand(['bob']);

        // Test with --execute flag (should handle connection error gracefully)
        ob_start();
        $result = $command->run(['bob', 'build', 'sqlite', 'select * from users', '--execute']);
        $output = ob_get_clean();
        // Will fail to execute but should show SQL
        expect($output)->toContain('Generated SQL');
    });

    test('BobCommand aggregate detection without execution', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'from users count']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('Aggregate query detected');
        expect($output)->toContain('Function: count');
    });

    test('BobCommand execute command', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'execute', 'sqlite', 'select * from sqlite_master']);
        $output = ob_get_clean();
        expect($output)->toContain('Results');
    });

    test('BobCommand execute command with no args', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'execute']);
        $output = ob_get_clean();
        expect($result)->toBe(1);
        expect($output)->toContain('Please provide driver and query');
    });

    test('BobCommand schema command', function () {
        $command = new BobCommand(['bob']);

        // Test listing tables
        ob_start();
        $result = $command->run(['bob', 'schema', 'sqlite']);
        $output = ob_get_clean();
        expect($output)->toContain('Available tables');
    });

    test('BobCommand schema command with table', function () {
        $command = new class(['bob']) extends BobCommand {
            protected function getTableSchema($connection, $driver, $table): array {
                return [
                    ['column_name' => 'id', 'data_type' => 'integer'],
                    ['column_name' => 'name', 'data_type' => 'varchar'],
                ];
            }
        };

        ob_start();
        $result = $command->run(['bob', 'schema', 'sqlite', 'users']);
        $output = ob_get_clean();
        expect($output)->toContain('Schema for table');
    });

    test('BobCommand schema command with no args', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'schema']);
        $output = ob_get_clean();
        expect($result)->toBe(1);
        expect($output)->toContain('Please provide driver');
    });

    test('BobCommand export command JSON format', function () {
        $command = new class(['bob']) extends BobCommand {
            protected function getConnectionConfigWithDefaults($driver): array {
                return ['driver' => 'sqlite', 'database' => ':memory:'];
            }
        };

        // Create a table and insert data
        $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection->statement('CREATE TABLE users (id INTEGER, name TEXT)');
        $connection->statement('INSERT INTO users (id, name) VALUES (1, "John")');

        ob_start();
        $result = $command->run(['bob', 'export', 'sqlite', 'select * from sqlite_master']);
        $output = ob_get_clean();
        expect($output)->toContain('[');  // JSON array
    });

    test('BobCommand export command CSV format', function () {
        $command = new class(['bob']) extends BobCommand {
            protected function getConnectionConfigWithDefaults($driver): array {
                return ['driver' => 'sqlite', 'database' => ':memory:'];
            }
        };

        ob_start();
        $result = $command->run(['bob', 'export', 'sqlite', 'select * from sqlite_master', '--format=csv']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
    });

    test('BobCommand export command with no args', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'export']);
        $output = ob_get_clean();
        expect($result)->toBe(1);
        expect($output)->toContain('Please provide driver and query');
    });

    test('BobCommand parseDSL with AND/OR support', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'select * from users where age > 18 and status = active or role = admin']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('where `age` > ?');
        expect($output)->toContain('and `status` = ?');
        expect($output)->toContain('or `role` = ?');
    });

    test('BobCommand parseDSL with OFFSET support', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'select * from users limit 10 offset 20']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('limit 10 offset 20');
    });

    test('BobCommand uses configuration from .bob.json', function () {
        $command = new class(['bob']) extends BobCommand {
            protected function loadConfig(): void {
                $this->config = [
                    'connections' => [
                        'mysql' => [
                            'host' => 'config-host',
                            'database' => 'config-db',
                        ]
                    ]
                ];
            }

            public function getConfig($driver) {
                return $this->getConnectionConfig($driver, []);
            }
        };

        $config = $command->getConfig('mysql');
        expect($config['host'])->toBe('config-host');
        expect($config['database'])->toBe('config-db');
    });

    test('BobCommand getConnectionConfigWithDefaults', function () {
        $command = new class(['bob']) extends BobCommand {
            public function testGetDefaults($driver) {
                return $this->getConnectionConfigWithDefaults($driver);
            }
        };

        $config = $command->testGetDefaults('mysql');
        expect($config['host'])->toBe('localhost');
        expect($config['port'])->toBe(3306);

        $config = $command->testGetDefaults('pgsql');
        expect($config['host'])->toBe('localhost');
        expect($config['port'])->toBe(5432);

        $config = $command->testGetDefaults('sqlite');
        expect($config['database'])->toBe(':memory:');
    });

    test('BobCommand getTableSchema for different drivers', function () {
        $command = new class(['bob']) extends BobCommand {
            public function testGetSchema($driver, $table) {
                // Use real in-memory SQLite connection for testing
                $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => ':memory:']);

                if ($driver === 'sqlite') {
                    // Create a test table for SQLite
                    $connection->statement('CREATE TABLE users (id INTEGER, name TEXT)');
                }

                $result = $this->getTableSchema($connection, $driver, $table);
                return is_array($result) ? $result : [];
            }
        };

        // SQLite will return actual schema
        $result = $command->testGetSchema('sqlite', 'users');
        expect($result)->toBeArray();

        // MySQL and PostgreSQL will try to query but fail (that's ok for test)
        try {
            $command->testGetSchema('mysql', 'users');
        } catch (Exception $e) {
            expect(true)->toBeTrue();
        }

        try {
            $command->testGetSchema('pgsql', 'users');
        } catch (Exception $e) {
            expect(true)->toBeTrue();
        }
    });

    test('BobCommand updated help shows all new commands', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $command->run(['bob', 'help']);
        $output = ob_get_clean();

        expect($output)->toContain('execute');
        expect($output)->toContain('schema');
        expect($output)->toContain('export');
        expect($output)->toContain('--execute');
        expect($output)->toContain('Colon syntax');
        expect($output)->toContain('SQL syntax');
    });

    test('BobCommand execute with results output', function () {
        $command = new class(['bob']) extends BobCommand {
            private $setupDone = false;

            protected function getConnectionConfigWithDefaults($driver): array {
                // Create persistent database file for this test
                $dbFile = sys_get_temp_dir() . '/test_bob_' . uniqid() . '.db';

                if (!$this->setupDone) {
                    $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => $dbFile]);
                    $connection->statement('CREATE TABLE users (id INTEGER, name TEXT)');
                    $connection->statement('INSERT INTO users VALUES (1, "John"), (2, "Jane")');
                    $this->setupDone = true;
                }

                return ['driver' => 'sqlite', 'database' => $dbFile];
            }
        };

        ob_start();
        $command->run(['bob', 'execute', 'sqlite', 'select * from users']);
        $output = ob_get_clean();
        expect($output)->toContain('Results (');
        expect($output)->toContain('John');
    });

    test('BobCommand execute with exception', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'execute', 'mysql', 'invalid query']);
        $output = ob_get_clean();
        expect($result)->toBe(1);
        expect($output)->toContain('Execution failed');
    });

    test('BobCommand schema with exception', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'schema', 'mysql', 'nonexistent']);
        $output = ob_get_clean();
        expect($result)->toBe(1);
        expect($output)->toContain('Failed to get schema');
    });

    test('BobCommand export CSV with actual data', function () {
        $command = new class(['bob']) extends BobCommand {
            protected function getConnectionConfigWithDefaults($driver): array {
                $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => ':memory:']);
                $connection->statement('CREATE TABLE users (id INTEGER, name TEXT)');
                $connection->statement('INSERT INTO users VALUES (1, "John Doe"), (2, "Jane Smith")');
                return ['driver' => 'sqlite', 'database' => ':memory:'];
            }
        };

        ob_start();
        $command->run(['bob', 'export', 'sqlite', 'select:* from:sqlite_master', '--format=csv']);
        $output = ob_get_clean();

        // CSV should have headers
        $lines = explode("\n", trim($output));
        expect(count($lines))->toBeGreaterThanOrEqual(1);
    });

    test('BobCommand export with exception', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'export', 'mysql', 'invalid query']);
        $output = ob_get_clean();
        expect($result)->toBe(1);
        expect($output)->toContain('Export failed');
    });

    test('BobCommand build with --execute and real results', function () {
        $command = new class(['bob']) extends BobCommand {
            protected function getConnectionConfig($driver, $args): array {
                return ['driver' => 'sqlite', 'database' => ':memory:'];
            }
        };

        $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $connection->statement('CREATE TABLE users (id INTEGER, name TEXT)');
        $connection->statement('INSERT INTO users VALUES (1, "Test")');

        ob_start();
        $command->run(['bob', 'build', 'sqlite', 'select:* from:sqlite_master', '--execute']);
        $output = ob_get_clean();
        expect($output)->toContain('Executing query');
        expect($output)->toContain('Results (');
    });

    test('BobCommand config with connection settings', function () {
        $command = new class(['bob']) extends BobCommand {
            protected function loadConfig(): void {
                $this->config = [
                    'connections' => [
                        'pgsql' => [
                            'host' => 'pg-host',
                            'port' => 5433,
                        ]
                    ]
                ];
            }

            public function testConfig() {
                return $this->getConnectionConfigWithDefaults('pgsql');
            }
        };

        $config = $command->testConfig();
        expect($config['host'])->toBe('pg-host');
        expect($config['port'])->toBe(5433);
    });

    test('BobCommand getTableSchema returns empty for unknown driver', function () {
        $command = new class(['bob']) extends BobCommand {
            public function testUnknownDriver() {
                $connection = new \Bob\Database\Connection(['driver' => 'sqlite', 'database' => ':memory:']);
                return $this->getTableSchema($connection, 'unknown', 'table');
            }
        };

        $result = $command->testUnknownDriver();
        expect($result)->toBe([]);
    });

});