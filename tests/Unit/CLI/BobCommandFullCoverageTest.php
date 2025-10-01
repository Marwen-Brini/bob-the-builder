<?php

use Bob\cli\BobCommand;
use Bob\Database\Connection;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Grammars\SQLiteGrammar;

describe('BobCommand Full Coverage Tests', function () {

    test('BobCommand exception handling in run method (lines 57-60)', function () {
        $command = new class(['bob']) extends BobCommand
        {
            protected array $commands = [
                'test-exception' => 'testException',
                'test-connection' => 'testConnection',
                'build' => 'buildQuery',
                'help' => 'showHelp',
                'version' => 'showVersion',
            ];

            protected function testException(): int
            {
                throw new Exception('Test exception');
            }
        };

        ob_start();
        $result = $command->run(['bob', 'test-exception']);
        $output = ob_get_clean();

        expect($result)->toBe(1);
        expect($output)->toContain('Test exception');
    });

    test('BobCommand testConnection with invalid driver (lines 74-78)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'test-connection', 'invalid']);
        $output = ob_get_clean();

        expect($result)->toBe(1);
        expect($output)->toContain('Unsupported driver: invalid');
    });

    test('BobCommand testConnection shows info message (lines 80-81)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'test-connection', 'sqlite']);
        $output = ob_get_clean();

        expect($output)->toContain('Testing sqlite connection...');
    });

    test('BobCommand testConnection successful with SQLite (lines 84-109)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'test-connection', 'sqlite']);
        $output = ob_get_clean();

        expect($result)->toBe(0);
        expect($output)->toContain('Connection successful!');
        expect($output)->toContain('No tables found in database');
    });

    test('BobCommand testConnection handles connection failure (lines 110-113)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'test-connection', 'mysql', '--host=nonexistent']);
        $output = ob_get_clean();

        expect($result)->toBe(1);
        expect($output)->toContain('Connection failed');
    });

    test('BobCommand buildQuery with empty args (lines 120-124)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'build']);
        $output = ob_get_clean();

        expect($result)->toBe(1);
        expect($output)->toContain('Please provide a query to build');
        expect($output)->toContain('Usage: bob build <driver> <query>');
    });

    test('BobCommand buildQuery successful execution (lines 137-168)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'SELECT * FROM users WHERE id = 1']);
        $output = ob_get_clean();

        expect($result)->toBe(0);
        expect($output)->toContain('Generated SQL:');
        expect($output)->toContain('select * from `users` where `id` = ?');
        expect($output)->toContain('Bindings:');
        expect($output)->toContain('Formatted query:');
    });

    test('BobCommand buildQuery handles invalid driver (lines 165-168)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'build', 'invalid', 'SELECT * FROM users']);
        $output = ob_get_clean();

        expect($result)->toBe(1);
        expect($output)->toContain('Unsupported driver: invalid');
    });

    test('BobCommand showVersion method (lines 203-206)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'version']);
        $output = ob_get_clean();

        expect($result)->toBe(0);
        expect($output)->toContain('Bob Query Builder v1.0.0');
        expect($output)->toContain('PHP '.PHP_VERSION);
    });

    test('BobCommand parseAndBuildQuery with complex DSL (lines 209-308)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'select:* from:users where:id,=,1 orderBy:name,asc limit:10']);
        $output = ob_get_clean();

        expect($result)->toBe(0);
        expect($output)->toContain('Generated SQL:');
    });

    test('BobCommand getConnectionConfig for MySQL (lines 327-335)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'test-connection', 'mysql', '--host=testhost', '--database=testdb']);
        $output = ob_get_clean();

        expect($output)->toContain('Testing mysql connection');
    });

    test('BobCommand getConnectionConfig for PostgreSQL (lines 337-344)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'test-connection', 'pgsql', '--host=testhost']);
        $output = ob_get_clean();

        expect($output)->toContain('Testing pgsql connection');
    });

    test('BobCommand getConnectionConfig for SQLite (lines 346-348)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'test-connection', 'sqlite', '--path=/tmp/test.db']);
        $output = ob_get_clean();

        expect($output)->toContain('Testing sqlite connection');
    });

    test('BobCommand createGrammar method (lines 354-361)', function () {
        $command = new class(['bob']) extends BobCommand
        {
            public function testCreateGrammar(string $driver)
            {
                return $this->createGrammar($driver);
            }
        };

        expect($command->testCreateGrammar('mysql'))->toBeInstanceOf(MySQLGrammar::class);
        expect($command->testCreateGrammar('pgsql'))->toBeInstanceOf(PostgreSQLGrammar::class);
        expect($command->testCreateGrammar('sqlite'))->toBeInstanceOf(SQLiteGrammar::class);

        expect(fn () => $command->testCreateGrammar('invalid'))->toThrow(Exception::class);
    });

    test('BobCommand createMockConnection method (lines 364-372)', function () {
        $command = new class(['bob']) extends BobCommand
        {
            public function testCreateMockConnection()
            {
                $grammar = $this->createGrammar('mysql');
                $processor = new \Bob\Query\Processor;

                return $this->createMockConnection($grammar, $processor);
            }
        };

        $connection = $command->testCreateMockConnection();
        expect($connection)->toBeInstanceOf(Connection::class);
    });

    test('BobCommand getTableList for MySQL (lines 378-381)', function () {
        $command = new class(['bob']) extends BobCommand
        {
            public function testGetTableList($driver)
            {
                $mockConnection = \Mockery::mock(Connection::class);

                if ($driver === 'mysql') {
                    $mockConnection->shouldReceive('select')
                        ->with('SHOW TABLES')
                        ->andReturn([
                            (object) ['Tables_in_test' => 'users'],
                            (object) ['Tables_in_test' => 'posts'],
                        ]);
                } elseif ($driver === 'pgsql') {
                    $mockConnection->shouldReceive('select')
                        ->with("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
                        ->andReturn([
                            (object) ['tablename' => 'users'],
                            (object) ['tablename' => 'posts'],
                        ]);
                } elseif ($driver === 'sqlite') {
                    $mockConnection->shouldReceive('select')
                        ->with("SELECT name FROM sqlite_master WHERE type='table'")
                        ->andReturn([
                            (object) ['name' => 'users'],
                            (object) ['name' => 'posts'],
                        ]);
                }

                return $this->getTableList($mockConnection, $driver);
            }
        };

        $mysqlTables = $command->testGetTableList('mysql');
        expect($mysqlTables)->toBe(['users', 'posts']);

        $pgsqlTables = $command->testGetTableList('pgsql');
        expect($pgsqlTables)->toBe(['users', 'posts']);

        $sqliteTables = $command->testGetTableList('sqlite');
        expect($sqliteTables)->toBe(['users', 'posts']);

        $unknownTables = $command->testGetTableList('unknown');
        expect($unknownTables)->toBe([]);
    });

    test('BobCommand formatQueryWithBindings method (lines 398-405)', function () {
        $command = new class(['bob']) extends BobCommand
        {
            public function testFormatQueryWithBindings($sql, $bindings)
            {
                return $this->formatQueryWithBindings($sql, $bindings);
            }
        };

        $sql = 'SELECT * FROM users WHERE id = ? AND name = ?';
        $bindings = [1, 'John'];

        $formatted = $command->testFormatQueryWithBindings($sql, $bindings);
        expect($formatted)->toBe("SELECT * FROM users WHERE id = 1 AND name = 'John'");
    });

    test('BobCommand output methods (lines 423-434)', function () {
        $command = new class(['bob']) extends BobCommand
        {
            public function testInfo($message)
            {
                $this->info($message);
            }

            public function testSuccess($message)
            {
                $this->success($message);
            }

            public function testError($message)
            {
                $this->error($message);
            }
        };

        ob_start();
        $command->testInfo('Info message');
        $output = ob_get_clean();
        expect($output)->toContain('Info message');

        ob_start();
        $command->testSuccess('Success message');
        $output = ob_get_clean();
        expect($output)->toContain('Success message');

        ob_start();
        $command->testError('Error message');
        $output = ob_get_clean();
        expect($output)->toContain('Error message');
    });

    test('BobCommand parseDSL with all operations (lines 436-end)', function () {
        $command = new BobCommand(['bob']);

        // Test SELECT with columns
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'SELECT id, name FROM users']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('select `id`, `name` from `users`');

        // Test complex query with WHERE
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'SELECT * FROM users WHERE id = 1 AND name = "John"']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('where');

        // Test ORDER BY
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'SELECT * FROM users ORDER BY name ASC']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('order by');

        // Test GROUP BY and HAVING
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'SELECT COUNT(*) FROM users GROUP BY status HAVING COUNT(*) > 5']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('group by');

        // Test LIMIT
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'SELECT * FROM users LIMIT 10']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('limit');

        // Test JOIN
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'SELECT * FROM users JOIN posts ON users.id = posts.user_id']);
        $output = ob_get_clean();
        expect($result)->toBe(0);
        expect($output)->toContain('join');
    });

    test('BobCommand testConnection with MySQL options (lines 328-335)', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'test-connection', 'mysql', '--host=localhost', '--username=test', '--password=secret', '--database=testdb']);
        $output = ob_get_clean();

        expect($output)->toContain('Testing mysql connection');
        // Connection will likely fail, but config parsing should work
    });

    test('BobCommand help command shows usage', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'help']);
        $output = ob_get_clean();

        expect($result)->toBe(0);
        expect($output)->toContain('Bob Query Builder CLI');
        expect($output)->toContain('Usage:');
        expect($output)->toContain('Commands:');
    });

    test('BobCommand unknown command shows error', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob', 'unknown-command']);
        $output = ob_get_clean();

        expect($result)->toBe(1);
        expect($output)->toContain('Unknown command');
    });

    test('BobCommand with no arguments shows help', function () {
        $command = new BobCommand(['bob']);

        ob_start();
        $result = $command->run(['bob']);
        $output = ob_get_clean();

        expect($result)->toBe(0);
        expect($output)->toContain('Bob Query Builder CLI');
    });

    test('BobCommand parseDSL covers SQL operations', function () {
        $command = new BobCommand(['bob']);

        // Test with natural SQL syntax that parseDSL can actually parse
        // Note: parseDSL doesn't support 'offset' keyword
        ob_start();
        $result = $command->run([
            'bob', 'build', 'mysql',
            'select id, name from users where age > 18 order by created_at desc limit 10',
        ]);
        $output = ob_get_clean();

        expect($result)->toBe(0);
        expect($output)->toContain('Generated SQL:');
        expect($output)->toContain('from `users`');
        expect($output)->toContain('where `age` > ?');
        expect($output)->toContain('order by `created_at` desc');
        expect($output)->toContain('limit 10');
    });

    test('BobCommand parseAndBuildQuery with ALL colon syntax operations', function () {
        // Create a test command that calls parseAndBuildQuery directly
        $command = new class(['bob']) extends BobCommand
        {
            public function testParseAndBuildQuery($queryString)
            {
                $grammar = $this->createGrammar('mysql');
                $processor = new \Bob\Query\Processor;
                $connection = $this->createMockConnection($grammar, $processor);
                $builder = new \Bob\Query\Builder($connection);

                // Call the protected method
                $this->parseAndBuildQuery($builder, $queryString);

                return [
                    'sql' => $builder->toSql(),
                    'bindings' => $builder->getBindings(),
                ];
            }
        };

        // Test basic select, from, where (already covered but for completeness)
        $result = $command->testParseAndBuildQuery('select:id,name from:users where:age,>,18');
        expect($result['sql'])->toContain('select `id`, `name` from `users`');
        expect($result['sql'])->toContain('where `age` > ?');
        expect($result['bindings'])->toBe(['18']); // Values from string parsing are strings

        // Test orWhere (lines 241-248)
        $result = $command->testParseAndBuildQuery('from:users where:age,>,18 orWhere:status,active');
        expect($result['sql'])->toContain('or `status` = ?');
        expect($result['bindings'])->toContain('active');

        // Test orWhere with operator
        $result = $command->testParseAndBuildQuery('from:users where:id,1 orWhere:age,<,25');
        expect($result['sql'])->toContain('or `age` < ?');

        // Test whereIn (lines 251-254)
        $result = $command->testParseAndBuildQuery('from:users whereIn:role,admin,user,moderator');
        expect($result['sql'])->toContain('`role` in (?, ?, ?)');
        expect($result['bindings'])->toBe(['admin', 'user', 'moderator']);

        // Test whereNull (lines 257-258)
        $result = $command->testParseAndBuildQuery('from:users whereNull:deleted_at');
        expect($result['sql'])->toContain('`deleted_at` is null');

        // Test whereNotNull (lines 261-262)
        $result = $command->testParseAndBuildQuery('from:users whereNotNull:email');
        expect($result['sql'])->toContain('`email` is not null');

        // Test join (lines 265-269)
        $result = $command->testParseAndBuildQuery('from:users join:posts,posts.user_id,users.id');
        expect($result['sql'])->toContain('inner join `posts` on `posts`.`user_id` = `users`.`id`');

        // Test leftJoin (lines 272-276)
        $result = $command->testParseAndBuildQuery('from:users leftJoin:comments,comments.user_id,users.id');
        expect($result['sql'])->toContain('left join `comments` on `comments`.`user_id` = `users`.`id`');

        // Test groupBy (lines 286-288)
        $result = $command->testParseAndBuildQuery('from:users groupBy:department,role');
        expect($result['sql'])->toContain('group by `department`, `role`');

        // Test having (lines 291-298)
        $result = $command->testParseAndBuildQuery('from:orders groupBy:user_id having:total,>,100');
        expect($result['sql'])->toContain('having `total` > ?');
        expect($result['bindings'])->toContain('100'); // String from parsing

        // Test having with default operator
        $result = $command->testParseAndBuildQuery('from:orders groupBy:status having:count,5');
        expect($result['sql'])->toContain('having `count` = ?');

        // Test limit (line 301)
        $result = $command->testParseAndBuildQuery('from:users limit:10');
        expect($result['sql'])->toContain('limit 10');

        // Test offset (lines 304-306)
        $result = $command->testParseAndBuildQuery('from:users limit:10 offset:20');
        expect($result['sql'])->toContain('limit 10 offset 20');

        // Test combination of all operations
        $result = $command->testParseAndBuildQuery(
            'select:id,name,email from:users where:age,>,18 orWhere:status,active '.
            'whereIn:role,admin,user whereNull:deleted_at whereNotNull:verified_at '.
            'join:posts,posts.user_id,users.id leftJoin:comments,comments.user_id,users.id '.
            'groupBy:department,role having:count,>,5 orderBy:created_at,desc limit:10 offset:20'
        );

        expect($result['sql'])->toContain('select `id`, `name`, `email`');
        expect($result['sql'])->toContain('from `users`');
        expect($result['sql'])->toContain('inner join `posts`');
        expect($result['sql'])->toContain('left join `comments`');
        expect($result['sql'])->toContain('where `age` > ?');
        expect($result['sql'])->toContain('or `status` = ?');
        expect($result['sql'])->toContain('`role` in (?, ?)');
        expect($result['sql'])->toContain('`deleted_at` is null');
        expect($result['sql'])->toContain('`verified_at` is not null');
        expect($result['sql'])->toContain('group by `department`, `role`');
        expect($result['sql'])->toContain('having `count` > ?');
        expect($result['sql'])->toContain('order by `created_at` desc');
        expect($result['sql'])->toContain('limit 10 offset 20');
    });

    test('BobCommand buildQuery exception handling (lines 165-168)', function () {
        $command = new class(['bob']) extends BobCommand
        {
            protected function createGrammar(string $driver)
            {
                throw new Exception('Grammar creation failed');
            }
        };

        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'select:*']);
        $output = ob_get_clean();

        expect($result)->toBe(1);
        expect($output)->toContain('Failed to build query: Grammar creation failed');
    });

    test('BobCommand testConnection with tables (lines 101-103)', function () {
        // Create a mock command that returns tables
        $command = new class(['bob']) extends BobCommand
        {
            protected function getTableList($connection, string $driver): array
            {
                return ['users', 'posts', 'comments'];
            }
        };

        ob_start();
        $result = $command->run(['bob', 'test-connection', 'sqlite']);
        $output = ob_get_clean();

        expect($result)->toBe(0);
        expect($output)->toContain('Available tables:');
        expect($output)->toContain('- users');
        expect($output)->toContain('- posts');
        expect($output)->toContain('- comments');
    });

    test('BobCommand parseDSL with all edge cases and aggregates', function () {
        $command = new BobCommand(['bob']);

        // Test line 455 - SELECT without columns
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'select from users']);
        $output = ob_get_clean();
        expect($output)->toContain('select * from `users`');

        // Test line 511 - order without 'by' (just increments i)
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'from users order']);
        $output = ob_get_clean();
        expect($result)->toBe(0);

        // Test line 523 - group without 'by' (just increments i)
        ob_start();
        $result = $command->run(['bob', 'build', 'mysql', 'from users group']);
        $output = ob_get_clean();
        expect($result)->toBe(0);

        // Aggregate functions will try to execute, which fails without real tables
        // So we just verify the command runs without crashing the parser
    });

    test('BobCommand parseDSL aggregate functions coverage', function () {
        // Create a simple class that exercises parseDSL paths
        $command = new class(['bob']) extends BobCommand
        {
            public function getBuilderState(string $queryString)
            {
                // Use SQLite in-memory database that actually works
                $grammar = $this->createGrammar('sqlite');
                $processor = new \Bob\Query\Processor;
                $connection = $this->createMockConnection($grammar, $processor);
                $builder = new \Bob\Query\Builder($connection);

                // Create a test table for aggregates to work
                try {
                    $connection->statement('CREATE TABLE users (id INTEGER, user_id INTEGER)');
                    $connection->statement('CREATE TABLE orders (total INTEGER)');
                    $connection->statement('CREATE TABLE reviews (rating INTEGER)');
                    $connection->statement('CREATE TABLE games (score INTEGER)');
                } catch (\Exception $e) {
                    // Tables might already exist
                }

                // Parse the query
                $this->parseDSL($queryString, $builder);

                // Return the aggregate state
                return $builder->aggregate;
            }
        };

        // Test COUNT with 'from' keyword check (line 538-540)
        // This tests the case where COUNT is followed by FROM - the count() is called without column
        $aggregate = $command->getBuilderState('from users count');
        expect($aggregate)->toHaveKey('function');
        expect($aggregate['function'])->toBe('count');

        // Test COUNT with column (lines 542-546)
        $aggregate = $command->getBuilderState('from users count user_id');
        expect($aggregate)->toHaveKey('function');
        expect($aggregate['function'])->toBe('count');
        expect($aggregate['columns'])->toContain('user_id');

        // Test SUM with column (lines 553-558)
        $aggregate = $command->getBuilderState('from orders sum total');
        expect($aggregate)->toHaveKey('function');
        expect($aggregate['function'])->toBe('sum');
        expect($aggregate['columns'])->toContain('total');

        // Test AVG with parentheses (line 555 - trim)
        $aggregate = $command->getBuilderState('from reviews avg (rating)');
        expect($aggregate)->toHaveKey('function');
        expect($aggregate['function'])->toBe('avg');
        expect($aggregate['columns'])->toContain('rating');

        // Test MIN (uses default column which is id)
        $aggregate = $command->getBuilderState('from users min id');
        expect($aggregate)->toHaveKey('function');
        expect($aggregate['function'])->toBe('min');

        // Test MAX with parentheses
        $aggregate = $command->getBuilderState('from games max (score)');
        expect($aggregate)->toHaveKey('function');
        expect($aggregate['function'])->toBe('max');

        // Test aggregate without column (line 558 - no column case)
        $aggregate = $command->getBuilderState('from users sum id');
        expect($aggregate)->toHaveKey('function');

        // Test COUNT with more parts after column (line 545)
        $aggregate = $command->getBuilderState('from users count id order by id');
        expect($aggregate)->toHaveKey('function');
    });

    test('BobCommand displayDatabaseVersion with version object (lines 575-577)', function () {
        $command = new class(['bob']) extends BobCommand
        {
            public function testDisplayVersion()
            {
                // Test with version object
                $version = new stdClass;
                $version->version = 'MySQL 8.0.33';
                ob_start();
                $this->displayDatabaseVersion($version);
                $output = ob_get_clean();
                expect($output)->toContain('Database version: MySQL 8.0.33');

                // Test with version array
                $version = ['version' => 'PostgreSQL 14.5'];
                ob_start();
                $this->displayDatabaseVersion($version);
                $output = ob_get_clean();
                expect($output)->toContain('Database version: PostgreSQL 14.5');

                // Test with array without version key
                $version = ['other' => 'value'];
                ob_start();
                $this->displayDatabaseVersion($version);
                $output = ob_get_clean();
                expect($output)->toContain('Database version: Unknown');
            }
        };

        $command->testDisplayVersion();
    });

});
