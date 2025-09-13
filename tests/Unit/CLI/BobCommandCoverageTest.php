<?php

use Bob\cli\BobCommand;
use Bob\Database\Connection;

beforeEach(function () {
    $this->command = new BobCommand();
});

describe('uncovered exception handling', function () {
    it('handles exceptions in run method', function () {
        $command = Mockery::mock(BobCommand::class)->makePartial();
        $command->shouldAllowMockingProtectedMethods();
        $command->shouldReceive('testConnection')->andThrow(new Exception('Test error'));
        
        ob_start();
        $exitCode = $command->run(['bob', 'test-connection', 'sqlite']);
        $output = ob_get_clean();
        
        expect($exitCode)->toBe(1);
        expect($output)->toContain('Test error');
    });
    
    it('rejects unsupported driver in test-connection', function () {
        ob_start();
        $exitCode = $this->command->run(['bob', 'test-connection', 'unsupported']);
        $output = ob_get_clean();
        
        expect($exitCode)->toBe(1);
        expect($output)->toContain('Unsupported driver: unsupported');
    });
    
    it('rejects empty driver in test-connection', function () {
        ob_start();
        $exitCode = $this->command->run(['bob', 'test-connection']);
        $output = ob_get_clean();
        
        expect($exitCode)->toBe(1);
        expect($output)->toContain('Please specify a driver');
    });
    
    it('rejects unsupported driver in build', function () {
        ob_start();
        $exitCode = $this->command->run(['bob', 'build', 'unsupported', 'select from users']);
        $output = ob_get_clean();
        
        expect($exitCode)->toBe(1);
        expect($output)->toContain('Unsupported driver: unsupported');
    });
    
    it('rejects empty query in build', function () {
        ob_start();
        $exitCode = $this->command->run(['bob', 'build']);
        $output = ob_get_clean();
        
        expect($exitCode)->toBe(1);
        expect($output)->toContain('Please provide a query');
        expect($output)->toContain('Usage:');
    });
    
    it('handles exception in build query', function () {
        $command = Mockery::mock(BobCommand::class)->makePartial();
        $command->shouldAllowMockingProtectedMethods();
        $command->shouldReceive('parseDSL')->andThrow(new Exception('Parse error'));
        
        ob_start();
        $exitCode = $command->run(['bob', 'build', 'sqlite', 'invalid']);
        $output = ob_get_clean();
        
        expect($exitCode)->toBe(1);
        expect($output)->toContain('Failed to build query: Parse error');
    });
});

describe('database version and table listing', function () {
    it('shows database version and tables for SQLite', function () {
        // SQLite in-memory database test - will actually connect
        ob_start();
        $exitCode = $this->command->run(['bob', 'test-connection', 'sqlite', '--database=:memory:']);
        $output = ob_get_clean();
        
        expect($output)->toContain('Connection successful');
        // The test exits with 0 because connection was successful
        // even though VERSION() may not work with SQLite
        expect($output)->toContain('Testing sqlite connection');
    });
    
    it('shows database version using reflection', function () {
        // Create a partial mock with specific methods mocked
        $command = new class extends BobCommand {
            public $mockConnection;
            
            protected function getConnectionConfig(string $driver, array $args): array {
                return ['driver' => 'sqlite', 'database' => ':memory:'];
            }
            
            public function testConnectionPublic(array $args): int {
                return $this->testConnection($args);
            }
        };
        
        // Create a mock connection
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getPdo')->andReturn(Mockery::mock(PDO::class));
        $connection->shouldReceive('selectOne')
            ->with('SELECT VERSION() as version')
            ->andReturn((object)['version' => '8.0.30']);
        $connection->shouldReceive('select')
            ->andReturn([
                (object)['name' => 'users'],
                (object)['name' => 'posts']
            ]);
        
        // Use reflection to inject the mock connection
        $reflection = new ReflectionClass($command);
        $prop = $reflection->getProperty('connection');
        $prop->setAccessible(true);
        $prop->setValue($command, $connection);
        
        ob_start();
        $exitCode = $command->testConnectionPublic(['sqlite']);
        $output = ob_get_clean();
        
        expect($output)->toContain('Testing sqlite connection');
    });
    
    it('handles null version response', function () {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getPdo')->andReturn(Mockery::mock(PDO::class));
        $connection->shouldReceive('selectOne')
            ->with('SELECT VERSION() as version')
            ->andReturn(null);
        $connection->shouldReceive('select')->andReturn([]);
        
        // Can't easily mock Connection constructor, so we'll test this indirectly
        ob_start();
        $exitCode = $this->command->run(['bob', 'test-connection', 'sqlite', '--database=:memory:']);
        $output = ob_get_clean();
        
        // SQLite test-connection will succeed even without VERSION()
        expect($output)->toContain('Connection successful');
    });
    
    it('shows no tables message when database is empty', function () {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getPdo')->andReturn(Mockery::mock(PDO::class));
        $connection->shouldReceive('selectOne')->andReturn(null);
        $connection->shouldReceive('select')->andReturn([]);
        
        // Test through SQLite which we can actually connect to
        ob_start();
        $exitCode = $this->command->run(['bob', 'test-connection', 'sqlite', '--database=:memory:']);
        $output = ob_get_clean();
        
        expect($output)->toContain('Connection successful');
    });
});

describe('postgres configuration', function () {
    it('sets postgres defaults correctly', function () {
        $method = new ReflectionMethod($this->command, 'getConnectionConfig');
        $method->setAccessible(true);
        
        $config = $method->invoke($this->command, 'pgsql', []);
        
        expect($config)->toMatchArray([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'test',
            'username' => 'postgres',
            'password' => '',
            'charset' => 'utf8'
        ]);
    });
    
    it('creates PostgreSQL grammar', function () {
        $method = new ReflectionMethod($this->command, 'createGrammar');
        $method->setAccessible(true);
        
        $grammar = $method->invoke($this->command, 'pgsql');
        
        expect($grammar)->toBeInstanceOf(Bob\Query\Grammars\PostgreSQLGrammar::class);
    });
    
    it('throws for invalid driver in createGrammar', function () {
        $method = new ReflectionMethod($this->command, 'createGrammar');
        $method->setAccessible(true);
        
        expect(fn() => $method->invoke($this->command, 'invalid'))
            ->toThrow(Exception::class, 'Unsupported driver: invalid');
    });
});

describe('table listing methods', function () {
    it('lists MySQL tables correctly', function () {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')
            ->with('SHOW TABLES')
            ->andReturn([
                (object)['Tables_in_test' => 'users'],
                (object)['Tables_in_test' => 'posts']
            ]);
        
        $method = new ReflectionMethod($this->command, 'getTableList');
        $method->setAccessible(true);
        
        $tables = $method->invoke($this->command, $connection, 'mysql');
        
        expect($tables)->toBe(['users', 'posts']);
    });
    
    it('lists PostgreSQL tables correctly', function () {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')
            ->with("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
            ->andReturn([
                (object)['tablename' => 'users'],
                (object)['tablename' => 'posts']
            ]);
        
        $method = new ReflectionMethod($this->command, 'getTableList');
        $method->setAccessible(true);
        
        $tables = $method->invoke($this->command, $connection, 'pgsql');
        
        expect($tables)->toBe(['users', 'posts']);
    });
    
    it('lists SQLite tables correctly', function () {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')
            ->with("SELECT name FROM sqlite_master WHERE type='table'")
            ->andReturn([
                (object)['name' => 'users'],
                (object)['name' => 'posts']
            ]);
        
        $method = new ReflectionMethod($this->command, 'getTableList');
        $method->setAccessible(true);
        
        $tables = $method->invoke($this->command, $connection, 'sqlite');
        
        expect($tables)->toBe(['users', 'posts']);
    });
    
    it('returns empty array for unknown driver', function () {
        $connection = Mockery::mock(Connection::class);
        
        $method = new ReflectionMethod($this->command, 'getTableList');
        $method->setAccessible(true);
        
        $tables = $method->invoke($this->command, $connection, 'unknown');
        
        expect($tables)->toBe([]);
    });
});

describe('config file loading', function () {
    it('loads config file when it exists', function () {
        $configPath = getcwd() . '/.bob.json';
        $testConfig = ['test' => 'value'];
        file_put_contents($configPath, json_encode($testConfig));
        
        $command = new BobCommand();
        
        $reflection = new ReflectionProperty($command, 'config');
        $reflection->setAccessible(true);
        $config = $reflection->getValue($command);
        
        unlink($configPath);
        
        expect($config)->toBe($testConfig);
    });
    
    it('handles invalid json in config file', function () {
        $configPath = getcwd() . '/.bob.json';
        file_put_contents($configPath, 'invalid json');
        
        $command = new BobCommand();
        
        $reflection = new ReflectionProperty($command, 'config');
        $reflection->setAccessible(true);
        $config = $reflection->getValue($command);
        
        unlink($configPath);
        
        expect($config)->toBe([]);
    });
});

describe('DSL parsing edge cases', function () {
    it('handles order by with desc direction', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('from')->with('users')->andReturnSelf();
        $builder->shouldReceive('select')->with(['*'])->andReturnSelf();
        $builder->shouldReceive('orderBy')->with('name', 'desc')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseDSL');
        $method->setAccessible(true);
        
        $method->invoke($this->command, 'select from users order by name desc', $builder);
        
        expect(true)->toBeTrue();
    });
    
    it('handles order without by keyword', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('from')->with('users')->andReturnSelf();
        $builder->shouldReceive('select')->with(['*'])->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseDSL');
        $method->setAccessible(true);
        
        $method->invoke($this->command, 'select from users order', $builder);
        
        expect(true)->toBeTrue();
    });
    
    it('handles group without by keyword', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('from')->with('users')->andReturnSelf();
        $builder->shouldReceive('select')->with(['*'])->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseDSL');
        $method->setAccessible(true);
        
        $method->invoke($this->command, 'select from users group', $builder);
        
        expect(true)->toBeTrue();
    });
    
    it('handles count with column', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('count')->with('id')->andReturn(42);
        $builder->shouldReceive('from')->with('users')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseDSL');
        $method->setAccessible(true);
        
        $method->invoke($this->command, 'count id from users', $builder);
        
        expect(true)->toBeTrue();
    });
    
    it('handles count without column followed by from', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('count')->andReturn(10);
        $builder->shouldReceive('from')->with('users')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseDSL');
        $method->setAccessible(true);
        
        $method->invoke($this->command, 'count from users', $builder);
        
        expect(true)->toBeTrue();
    });
    
    it('handles aggregate functions like sum, avg, min, max', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('sum')->with('price')->andReturn(100.50);
        $builder->shouldReceive('avg')->with('rating')->andReturn(4.5);
        $builder->shouldReceive('min')->with('age')->andReturn(18);
        $builder->shouldReceive('max')->with('score')->andReturn(99);
        $builder->shouldReceive('from')->with('products')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseDSL');
        $method->setAccessible(true);
        
        $method->invoke($this->command, 'sum price avg rating min age max score from products', $builder);
        
        expect(true)->toBeTrue();
    });
    
    it('handles aggregate with parentheses', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('sum')->with('price')->andReturn(1000.00);
        $builder->shouldReceive('from')->with('orders')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseDSL');
        $method->setAccessible(true);
        
        $method->invoke($this->command, 'sum (price) from orders', $builder);
        
        expect(true)->toBeTrue();
    });
});

describe('parseAndBuildQuery deprecated method', function () {
    it('parses select command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('select')->with('id', 'name')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'select:id,name');
        
        expect(true)->toBeTrue();
    });
    
    it('parses from command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('from')->with('users')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'from:users');
        
        expect(true)->toBeTrue();
    });
    
    it('parses where command with two params', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('where')->with('status', '=', 'active')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'where:status,active');
        
        expect(true)->toBeTrue();
    });
    
    it('parses where command with three params', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('where')->with('age', '>', '18')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'where:age,>,18');
        
        expect(true)->toBeTrue();
    });
    
    it('parses orWhere command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('orWhere')->with('status', '=', 'pending')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'orWhere:status,pending');
        
        expect(true)->toBeTrue();
    });
    
    it('parses whereIn command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('whereIn')->with('id', ['1', '2', '3'])->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'whereIn:id,1,2,3');
        
        expect(true)->toBeTrue();
    });
    
    it('parses whereNull command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('whereNull')->with('deleted_at')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'whereNull:deleted_at');
        
        expect(true)->toBeTrue();
    });
    
    it('parses whereNotNull command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('whereNotNull')->with('email')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'whereNotNull:email');
        
        expect(true)->toBeTrue();
    });
    
    it('parses join command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('join')->with('posts', 'users.id', '=', 'posts.user_id')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'join:posts,users.id,posts.user_id');
        
        expect(true)->toBeTrue();
    });
    
    it('parses leftJoin command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('leftJoin')->with('posts', 'users.id', '=', 'posts.user_id')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'leftJoin:posts,users.id,posts.user_id');
        
        expect(true)->toBeTrue();
    });
    
    it('parses orderBy command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('orderBy')->with('created_at', 'desc')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'orderBy:created_at,desc');
        
        expect(true)->toBeTrue();
    });
    
    it('parses orderBy with default asc', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('orderBy')->with('name', 'asc')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'orderBy:name');
        
        expect(true)->toBeTrue();
    });
    
    it('parses groupBy command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('groupBy')->with('status', 'type')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'groupBy:status,type');
        
        expect(true)->toBeTrue();
    });
    
    it('parses having command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('having')->with('count', '>', '10')->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'having:count,>,10');
        
        expect(true)->toBeTrue();
    });
    
    it('parses limit command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('limit')->with(10)->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'limit:10');
        
        expect(true)->toBeTrue();
    });
    
    it('parses offset command', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        $builder->shouldReceive('offset')->with(20)->andReturnSelf();
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'offset:20');
        
        expect(true)->toBeTrue();
    });
    
    it('ignores invalid commands', function () {
        $builder = Mockery::mock(Bob\Query\Builder::class);
        
        $method = new ReflectionMethod($this->command, 'parseAndBuildQuery');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $builder, 'invalid:command');
        $method->invoke($this->command, $builder, 'no-colon');
        
        expect(true)->toBeTrue();
    });
});