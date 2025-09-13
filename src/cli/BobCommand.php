<?php

declare(strict_types=1);

namespace Bob\cli;

use Bob\Database\Connection;
use Bob\Query\Builder;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Grammars\SQLiteGrammar;
use Bob\Query\Processor;
use Exception;

class BobCommand
{
    protected array $commands = [
        'test-connection' => 'testConnection',
        'build' => 'buildQuery',
        'help' => 'showHelp',
        'version' => 'showVersion',
    ];

    protected array $config = [];

    protected ?Connection $connection = null;

    public function __construct()
    {
        $this->loadConfig();
    }

    public function run(array $argv): int
    {
        array_shift($argv); // Remove script name

        if (empty($argv)) {
            $this->showHelp();

            return 0;
        }

        $command = $argv[0];

        if (! isset($this->commands[$command])) {
            $this->error("Unknown command: $command");
            $this->showHelp();

            return 1;
        }

        $method = $this->commands[$command];
        array_shift($argv); // Remove command name

        try {
            return $this->$method($argv);
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    protected function testConnection(array $args): int
    {
        $driver = $args[0] ?? null;

        if (! $driver) {
            $this->error('Please specify a driver: mysql, pgsql, or sqlite');

            return 1;
        }

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'])) {
            $this->error("Unsupported driver: $driver");

            return 1;
        }

        $this->info("Testing $driver connection...");

        try {
            $config = $this->getConnectionConfig($driver, $args);
            $connection = new Connection($config);
            $connection->getPdo();

            $this->success('Connection successful!');

            // Show database version
            try {
                $version = $connection->selectOne('SELECT VERSION() as version');
                $this->displayDatabaseVersion($version); // @codeCoverageIgnore
            } catch (Exception $e) {
                // Some databases (like SQLite) don't support VERSION()
                // Continue without showing version
            }

            // Show available tables
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

    protected function buildQuery(array $args): int
    {
        if (empty($args)) {
            $this->error('Please provide a query to build.');
            $this->info("\nUsage: bob build <driver> <query>");
            $this->info('Example: bob build mysql "select:* from:users where:active,1 limit:10"');

            return 1;
        }

        $driver = array_shift($args);
        $queryString = implode(' ', $args);

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'])) {
            $this->error("Unsupported driver: $driver");

            return 1;
        }

        try {
            $grammar = $this->createGrammar($driver);
            $processor = new Processor;
            $connection = $this->createMockConnection($grammar, $processor);
            $builder = new Builder($connection);

            // Parse the query string using new DSL parser
            $this->parseDSL($queryString, $builder);

            // Get the SQL
            $sql = $builder->toSql();
            $bindings = $builder->getBindings();

            $this->success('Generated SQL:');
            $this->output($sql);

            if (! empty($bindings)) {
                $this->info("\nBindings:");
                foreach ($bindings as $i => $binding) {
                    $this->output("  [$i] => ".json_encode($binding));
                }
            }

            // Show formatted query with bindings
            $this->info("\nFormatted query:");
            $formatted = $this->formatQueryWithBindings($sql, $bindings);
            $this->output($formatted);

            return 0;
        } catch (Exception $e) {
            $this->error('Failed to build query: '.$e->getMessage());

            return 1;
        }
    }

    protected function showHelp(): int
    {
        $this->output("Bob Query Builder CLI\n");
        $this->output("Usage: bob <command> [options]\n");
        $this->output('Commands:');
        $this->output('  test-connection <driver> [options]  Test database connection');
        $this->output('  build <driver> <query>              Build and display SQL query');
        $this->output('  version                             Show version information');
        $this->output("  help                                Show this help message\n");

        $this->output("Drivers: mysql, pgsql, sqlite\n");

        $this->output('Connection options:');
        $this->output('  --host=<host>      Database host (default: localhost)');
        $this->output('  --port=<port>      Database port');
        $this->output('  --database=<db>    Database name');
        $this->output('  --username=<user>  Database username');
        $this->output('  --password=<pass>  Database password');
        $this->output("  --path=<path>      SQLite database path\n");

        $this->output('Query syntax:');
        $this->output('  select:<columns> from:<table> where:<field>,<value> ...');
        $this->output('  Examples:');
        $this->output('    bob build mysql "select:* from:users where:active,1"');
        $this->output('    bob build sqlite "select:name,email from:users limit:10"');
        
        return 0;
    }

    protected function showVersion(): int
    {
        $this->output('Bob Query Builder v1.0.0');
        $this->output('PHP '.PHP_VERSION);

        return 0;
    }

    protected function parseAndBuildQuery(Builder $builder, string $queryString): void
    {
        $parts = preg_split('/\s+/', $queryString);

        foreach ($parts as $part) {
            if (strpos($part, ':') === false) {
                continue;
            }

            [$command, $args] = explode(':', $part, 2);

            switch ($command) {
                case 'select':
                    $columns = $args === '*' ? ['*'] : explode(',', $args);
                    $builder->select(...$columns);
                    break;

                case 'from':
                    $builder->from($args);
                    break;

                case 'where':
                    $whereParts = explode(',', $args);
                    if (count($whereParts) >= 2) {
                        $field = $whereParts[0];
                        $operator = count($whereParts) === 3 ? $whereParts[1] : '=';
                        $value = count($whereParts) === 3 ? $whereParts[2] : $whereParts[1];
                        $builder->where($field, $operator, $value);
                    }
                    break;

                case 'orWhere':
                    $whereParts = explode(',', $args);
                    if (count($whereParts) >= 2) {
                        $field = $whereParts[0];
                        $operator = count($whereParts) === 3 ? $whereParts[1] : '=';
                        $value = count($whereParts) === 3 ? $whereParts[2] : $whereParts[1];
                        $builder->orWhere($field, $operator, $value);
                    }
                    break;

                case 'whereIn':
                    $parts = explode(',', $args);
                    $field = array_shift($parts);
                    $builder->whereIn($field, $parts);
                    break;

                case 'whereNull':
                    $builder->whereNull($args);
                    break;

                case 'whereNotNull':
                    $builder->whereNotNull($args);
                    break;

                case 'join':
                    $joinParts = explode(',', $args);
                    if (count($joinParts) >= 3) {
                        $builder->join($joinParts[0], $joinParts[1], '=', $joinParts[2]);
                    }
                    break;

                case 'leftJoin':
                    $joinParts = explode(',', $args);
                    if (count($joinParts) >= 3) {
                        $builder->leftJoin($joinParts[0], $joinParts[1], '=', $joinParts[2]);
                    }
                    break;

                case 'orderBy':
                    $orderParts = explode(',', $args);
                    $column = $orderParts[0];
                    $direction = $orderParts[1] ?? 'asc';
                    $builder->orderBy($column, $direction);
                    break;

                case 'groupBy':
                    $columns = explode(',', $args);
                    $builder->groupBy(...$columns);
                    break;

                case 'having':
                    $havingParts = explode(',', $args);
                    if (count($havingParts) >= 2) {
                        $field = $havingParts[0];
                        $operator = count($havingParts) === 3 ? $havingParts[1] : '=';
                        $value = count($havingParts) === 3 ? $havingParts[2] : $havingParts[1];
                        $builder->having($field, $operator, $value);
                    }
                    break;

                case 'limit':
                    $builder->limit((int) $args);
                    break;

                case 'offset':
                    $builder->offset((int) $args);
                    break;
            }
        }
    }

    protected function getConnectionConfig(string $driver, array $args): array
    {
        $config = ['driver' => $driver];

        // Parse command line options
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                if (count($parts) === 2) {
                    $config[$parts[0]] = $parts[1];
                }
            }
        }

        // Set defaults based on driver
        switch ($driver) {
            case 'mysql':
                $config['host'] = $config['host'] ?? 'localhost';
                $config['port'] = $config['port'] ?? 3306;
                $config['database'] = $config['database'] ?? 'test';
                $config['username'] = $config['username'] ?? 'root';
                $config['password'] = $config['password'] ?? '';
                $config['charset'] = $config['charset'] ?? 'utf8mb4';
                $config['collation'] = $config['collation'] ?? 'utf8mb4_unicode_ci';
                break;

            case 'pgsql':
                $config['host'] = $config['host'] ?? 'localhost';
                $config['port'] = $config['port'] ?? 5432;
                $config['database'] = $config['database'] ?? 'test';
                $config['username'] = $config['username'] ?? 'postgres';
                $config['password'] = $config['password'] ?? '';
                $config['charset'] = $config['charset'] ?? 'utf8';
                break;

            case 'sqlite':
                $config['database'] = $config['path'] ?? $config['database'] ?? ':memory:';
                break;
        }

        return $config;
    }

    protected function createGrammar(string $driver)
    {
        return match ($driver) {
            'mysql' => new MySQLGrammar,
            'pgsql' => new PostgreSQLGrammar,
            'sqlite' => new SQLiteGrammar,
            default => throw new Exception("Unsupported driver: $driver"),
        };
    }

    protected function createMockConnection($grammar, $processor): Connection
    {
        // Create a mock connection for query building only
        $config = ['driver' => 'sqlite', 'database' => ':memory:'];
        $connection = new Connection($config);
        $connection->setQueryGrammar($grammar);
        $connection->setPostProcessor($processor);

        return $connection;
    }

    protected function getTableList(Connection $connection, string $driver): array
    {
        switch ($driver) {
            case 'mysql':
                $results = $connection->select('SHOW TABLES');

                return array_map(fn ($row) => array_values((array)$row)[0], $results);

            case 'pgsql':
                $results = $connection->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

                return array_map(fn ($row) => $row->tablename, $results);

            case 'sqlite':
                $results = $connection->select("SELECT name FROM sqlite_master WHERE type='table'");

                return array_map(fn ($row) => $row->name, $results);

            default:
                return [];
        }
    }

    protected function formatQueryWithBindings(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $value = is_string($binding) ? "'$binding'" : json_encode($binding);
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }

    protected function loadConfig(): void
    {
        $configFile = getcwd().'/.bob.json';
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true) ?? [];
        }
    }

    protected function output(string $message): void
    {
        echo $message.PHP_EOL;
    }

    protected function info(string $message): void
    {
        echo "\033[0;36m".$message."\033[0m".PHP_EOL;
    }

    protected function success(string $message): void
    {
        echo "\033[0;32m".$message."\033[0m".PHP_EOL;
    }

    protected function error(string $message): void
    {
        echo "\033[0;31m".$message."\033[0m".PHP_EOL;
    }

    protected function parseDSL(string $queryString, Builder $builder): void
    {
        $parts = preg_split('/\s+/', trim($queryString));
        $i = 0;

        while ($i < count($parts)) {
            $part = strtolower($parts[$i]);

            switch ($part) {
                case 'select':
                    $i++;
                    if ($i < count($parts) && strtolower($parts[$i]) !== 'from') {
                        $columns = [];
                        while ($i < count($parts) && strtolower($parts[$i]) !== 'from') {
                            $columns[] = trim($parts[$i], ',');
                            $i++;
                        }
                        $builder->select($columns);
                    } else {
                        $builder->select(['*']);
                    }
                    break;

                case 'from':
                    $i++;
                    if ($i < count($parts)) {
                        $builder->from($parts[$i]);
                        $i++;
                    }
                    break;

                case 'where':
                    $i++;
                    if ($i + 2 < count($parts)) {
                        $column = $parts[$i];
                        $operator = $parts[$i + 1];
                        $value = $parts[$i + 2];
                        $builder->where($column, $operator, $value);
                        $i += 3;
                    }
                    break;

                case 'join':
                    $i++;
                    if ($i < count($parts)) {
                        $table = $parts[$i];
                        $i++;
                        // Skip 'on' keyword
                        if ($i < count($parts) && strtolower($parts[$i]) === 'on') {
                            $i++;
                            if ($i + 2 < count($parts)) {
                                $first = $parts[$i];
                                $operator = $parts[$i + 1];
                                $second = $parts[$i + 2];
                                $builder->join($table, $first, $operator, $second);
                                $i += 3;
                            }
                        }
                    }
                    break;

                case 'order':
                    if ($i + 1 < count($parts) && strtolower($parts[$i + 1]) === 'by') {
                        $i += 2;
                        if ($i < count($parts)) {
                            $column = $parts[$i];
                            $direction = 'asc';
                            if ($i + 1 < count($parts) && in_array(strtolower($parts[$i + 1]), ['asc', 'desc'])) {
                                $direction = strtolower($parts[$i + 1]);
                                $i++;
                            }
                            $builder->orderBy($column, $direction);
                            $i++;
                        }
                    } else {
                        $i++;
                    }
                    break;

                case 'group':
                    if ($i + 1 < count($parts) && strtolower($parts[$i + 1]) === 'by') {
                        $i += 2;
                        if ($i < count($parts)) {
                            $builder->groupBy($parts[$i]);
                            $i++;
                        }
                    } else {
                        $i++;
                    }
                    break;

                case 'limit':
                    $i++;
                    if ($i < count($parts)) {
                        $builder->limit((int) $parts[$i]);
                        $i++;
                    }
                    break;

                case 'count':
                    // Check if there's a column specified or default to *
                    $i++;
                    if ($i < count($parts) && strtolower($parts[$i]) === 'from') {
                        // Count all records from table
                        $builder->count();
                    } else {
                        // Count specific column
                        $column = ($i < count($parts)) ? $parts[$i] : '*';
                        $builder->count($column);
                        if ($i < count($parts)) $i++;
                    }
                    break;

                case 'sum':
                case 'avg':
                case 'min':
                case 'max':
                    $i++;
                    if ($i < count($parts)) {
                        $column = trim($parts[$i], '()');
                        $builder->{$part}($column);
                        $i++;
                    }
                    break;

                default:
                    $i++;
                    break;
            }
        }
    }

    /**
     * Display database version information
     *
     * @param array|null $version The version query result
     */
    protected function displayDatabaseVersion(?object $version): void
    {
        if ($version) {
            $this->info('Database version: '.($version->version ?? 'Unknown'));
        }
    }
}
