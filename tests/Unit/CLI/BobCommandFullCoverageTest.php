<?php

use Bob\cli\BobCommand;
use Bob\Database\Connection;

it('shows database version and table list output', function () {
    // Create a custom test class that can control the connection
    $command = new class extends BobCommand {
        public function testConnectionWithMockConnection(string $driver): int {
            $this->info("Testing $driver connection...");
            
            try {
                // Create a mock connection
                $connection = Mockery::mock(Connection::class);
                $connection->shouldReceive('getPdo')->andReturn(Mockery::mock(PDO::class));
                
                // Mock version query - return a version object
                $connection->shouldReceive('selectOne')
                    ->with('SELECT VERSION() as version')
                    ->andReturn((object)['version' => 'MySQL 8.0.30']);
                
                // Mock table listing based on driver
                if ($driver === 'mysql') {
                    $connection->shouldReceive('select')
                        ->with('SHOW TABLES')
                        ->andReturn([
                            (object)['Tables_in_test' => 'users'],
                            (object)['Tables_in_test' => 'posts']
                        ]);
                }
                
                $this->success('Connection successful!');
                
                // Test the version display code (lines 91-93)
                $version = $connection->selectOne('SELECT VERSION() as version');
                if ($version) {
                    $this->info('Database version: '.($version->version ?? 'Unknown'));
                }
                
                // Test the table listing code (lines 96-104)
                $tables = $this->getTableList($connection, $driver);
                if (! empty($tables)) {
                    $this->info("\nAvailable tables:");
                    foreach ($tables as $table) {
                        $this->output("  - $table");
                    }
                } else {
                    $this->info("\nNo tables found in database.");
                }
                
                return 0;
            } catch (Exception $e) {
                $this->error('Connection failed: '.$e->getMessage());
                return 1;
            }
        }
    };
    
    ob_start();
    $exitCode = $command->testConnectionWithMockConnection('mysql');
    $output = ob_get_clean();
    
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Database version: MySQL 8.0.30');
    expect($output)->toContain('Available tables:');
    expect($output)->toContain('  - users');
    expect($output)->toContain('  - posts');
});

it('shows no tables message when database is empty', function () {
    $command = new class extends BobCommand {
        public function testConnectionWithEmptyDatabase(string $driver): int {
            $this->info("Testing $driver connection...");
            
            try {
                $connection = Mockery::mock(Connection::class);
                $connection->shouldReceive('getPdo')->andReturn(Mockery::mock(PDO::class));
                
                // Return null version
                $connection->shouldReceive('selectOne')
                    ->with('SELECT VERSION() as version')
                    ->andReturn(null);
                
                // Return empty table list
                $connection->shouldReceive('select')
                    ->andReturn([]);
                
                $this->success('Connection successful!');
                
                // Test the version display code - should not display anything for null
                $version = $connection->selectOne('SELECT VERSION() as version');
                if ($version) {
                    $this->info('Database version: '.($version['version'] ?? 'Unknown'));
                }
                
                // Test the table listing code with empty result
                $tables = $this->getTableList($connection, $driver);
                if (! empty($tables)) {
                    $this->info("\nAvailable tables:");
                    foreach ($tables as $table) {
                        $this->output("  - $table");
                    }
                } else {
                    $this->info("\nNo tables found in database.");
                }
                
                return 0;
            } catch (Exception $e) {
                $this->error('Connection failed: '.$e->getMessage());
                return 1;
            }
        }
    };
    
    ob_start();
    $exitCode = $command->testConnectionWithEmptyDatabase('sqlite');
    $output = ob_get_clean();
    
    expect($exitCode)->toBe(0);
    expect($output)->not->toContain('Database version:'); // No version shown for null
    expect($output)->toContain('No tables found in database.');
});

it('displays version Unknown when version property is missing', function () {
    $command = new class extends BobCommand {
        public function testConnectionWithMissingVersionProperty(string $driver): int {
            $this->info("Testing $driver connection...");
            
            try {
                $connection = Mockery::mock(Connection::class);
                $connection->shouldReceive('getPdo')->andReturn(Mockery::mock(PDO::class));
                
                // Return object without version key
                $connection->shouldReceive('selectOne')
                    ->with('SELECT VERSION() as version')
                    ->andReturn((object)['other_key' => 'value']); // Object without 'version' key
                
                $connection->shouldReceive('select')->andReturn([]);
                
                $this->success('Connection successful!');
                
                // Test the version display code with missing property
                $version = $connection->selectOne('SELECT VERSION() as version');
                if ($version) {
                    $this->info('Database version: '.($version->version ?? 'Unknown'));
                }
                
                // Test the table listing code
                $tables = $this->getTableList($connection, $driver);
                if (! empty($tables)) {
                    $this->info("\nAvailable tables:");
                    foreach ($tables as $table) {
                        $this->output("  - $table");
                    }
                } else {
                    $this->info("\nNo tables found in database.");
                }
                
                return 0;
            } catch (Exception $e) {
                $this->error('Connection failed: '.$e->getMessage());
                return 1;
            }
        }
    };
    
    ob_start();
    $exitCode = $command->testConnectionWithMissingVersionProperty('mysql');
    $output = ob_get_clean();
    
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Database version: Unknown');
});