<?php

namespace Bob\WordPress;

use Bob\Contracts\ConnectionInterface;
use Bob\Database\Connection;
use Bob\Query\Builder;

/**
 * WordPress Adapter for Bob Query Builder
 *
 * Provides compatibility layer for WordPress database operations
 * and integration with the global $wpdb object.
 */
class WordPressAdapter
{
    /**
     * The Bob connection instance
     */
    protected ConnectionInterface $connection;

    /**
     * WordPress database prefix
     */
    protected string $prefix = '';

    /**
     * Map of wpdb methods to Bob methods
     */
    protected array $methodMap = [
        'get_results' => 'select',
        'get_row' => 'selectOne',
        'get_var' => 'scalar',
        'query' => 'statement',
        'insert' => 'insert',
        'update' => 'update',
        'delete' => 'delete',
        'replace' => 'insertOrReplace',
    ];

    /**
     * Create a new WordPress adapter instance
     */
    public function __construct(?ConnectionInterface $connection = null)
    {
        if ($connection) {
            $this->connection = $connection;
        } else {
            $this->connection = $this->createConnectionFromWordPress();
        }

        $this->detectTablePrefix();
    }

    /**
     * Create a connection from WordPress configuration
     */
    protected function createConnectionFromWordPress(): ConnectionInterface
    {
        // Check if WordPress constants are defined
        if (! defined('DB_HOST') || ! defined('DB_NAME') || ! defined('DB_USER')) {
            throw new \RuntimeException('WordPress database constants are not defined');
        }

        $config = [
            'driver' => 'mysql',
            'host' => $this->parseHost(DB_HOST),
            'port' => $this->parsePort(DB_HOST),
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => defined('DB_PASSWORD') ? DB_PASSWORD : '',
            'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
            'collation' => defined('DB_COLLATE') && DB_COLLATE ? DB_COLLATE : 'utf8mb4_unicode_ci',
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ];

        // Handle socket connections
        if (strpos(DB_HOST, '.sock') !== false) {
            $config['unix_socket'] = DB_HOST;
            unset($config['host'], $config['port']);
        }

        return new Connection($config);
    }

    /**
     * Parse host from WordPress DB_HOST constant
     */
    protected function parseHost(string $dbHost): string
    {
        if (strpos($dbHost, ':') !== false) {
            [$host] = explode(':', $dbHost);

            return $host;
        }

        return $dbHost;
    }

    /**
     * Parse port from WordPress DB_HOST constant
     */
    protected function parsePort(string $dbHost): ?int
    {
        if (strpos($dbHost, ':') !== false) {
            [, $port] = explode(':', $dbHost);

            return is_numeric($port) ? (int) $port : null;
        }

        return null;
    }

    /**
     * Detect table prefix from global $wpdb or use default
     */
    protected function detectTablePrefix(): void
    {
        global $wpdb;

        if (isset($wpdb) && isset($wpdb->prefix)) {
            $this->prefix = $wpdb->prefix;
        } elseif (defined('DB_PREFIX')) {
            $this->prefix = DB_PREFIX;
        } else {
            $this->prefix = 'wp_';
        }

        $this->connection->setTablePrefix($this->prefix);
    }

    /**
     * Get the underlying connection instance
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Get a query builder instance
     */
    public function table(string $table): Builder
    {
        // Automatically add prefix if not already present
        if (! str_starts_with($table, $this->prefix)) {
            $table = $this->prefix.$table;
        }

        return $this->connection->table($table);
    }

    /**
     * wpdb-compatible get_results method
     *
     * @param  string  $query  The SQL query
     * @param  array  $bindings  Query bindings
     * @param  string  $output  Output type (OBJECT, ARRAY_A, ARRAY_N)
     */
    public function get_results(string $query, array $bindings = [], string $output = 'OBJECT'): array
    {
        $results = $this->connection->select($query, $bindings);

        return $this->formatResults($results, $output);
    }

    /**
     * wpdb-compatible get_row method
     *
     * @param  string  $query  The SQL query
     * @param  array  $bindings  Query bindings
     * @param  string  $output  Output type (OBJECT, ARRAY_A, ARRAY_N)
     * @param  int  $row  Row number to return
     * @return mixed
     */
    public function get_row(string $query, array $bindings = [], string $output = 'OBJECT', int $row = 0)
    {
        $results = $this->connection->select($query, $bindings);

        if (! isset($results[$row])) {
            return null;
        }

        $result = $results[$row];

        return $this->formatResult($result, $output);
    }

    /**
     * wpdb-compatible get_var method
     *
     * @param  string  $query  The SQL query
     * @param  array  $bindings  Query bindings
     * @param  int  $column  Column number
     * @param  int  $row  Row number
     * @return mixed
     */
    public function get_var(string $query, array $bindings = [], int $column = 0, int $row = 0)
    {
        $results = $this->connection->select($query, $bindings);

        if (! isset($results[$row])) {
            return null;
        }

        $result = (array) $results[$row];
        $values = array_values($result);

        return $values[$column] ?? null;
    }

    /**
     * wpdb-compatible query method
     *
     * @param  string  $query  The SQL query
     * @param  array  $bindings  Query bindings
     * @return int|bool Number of rows affected or false on error
     */
    public function query(string $query, array $bindings = [])
    {
        try {
            return $this->connection->affectingStatement($query, $bindings);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * wpdb-compatible insert method
     *
     * @param  string  $table  Table name
     * @param  array  $data  Data to insert
     * @param  array|null  $format  Format strings for data
     * @return int|false The insert ID or false on error
     */
    public function insert(string $table, array $data, ?array $format = null)
    {
        try {
            // Add prefix if not present
            if (! str_starts_with($table, $this->prefix)) {
                $table = $this->prefix.$table;
            }

            return $this->connection->table($table)->insertGetId($data);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * wpdb-compatible update method
     *
     * @param  string  $table  Table name
     * @param  array  $data  Data to update
     * @param  array  $where  WHERE conditions
     * @param  array|null  $format  Format strings for data
     * @param  array|null  $whereFormat  Format strings for WHERE
     * @return int|false Number of rows updated or false on error
     */
    public function update(string $table, array $data, array $where, ?array $format = null, ?array $whereFormat = null)
    {
        try {
            // Add prefix if not present
            if (! str_starts_with($table, $this->prefix)) {
                $table = $this->prefix.$table;
            }

            $query = $this->connection->table($table);

            foreach ($where as $column => $value) {
                $query->where($column, '=', $value);
            }

            return $query->update($data);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * wpdb-compatible delete method
     *
     * @param  string  $table  Table name
     * @param  array  $where  WHERE conditions
     * @param  array|null  $whereFormat  Format strings for WHERE
     * @return int|false Number of rows deleted or false on error
     */
    public function delete(string $table, array $where, ?array $whereFormat = null)
    {
        try {
            // Add prefix if not present
            if (! str_starts_with($table, $this->prefix)) {
                $table = $this->prefix.$table;
            }

            $query = $this->connection->table($table);

            foreach ($where as $column => $value) {
                $query->where($column, '=', $value);
            }

            return $query->delete();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * wpdb-compatible replace method
     *
     * @param  string  $table  Table name
     * @param  array  $data  Data to insert or replace
     * @param  array|null  $format  Format strings for data
     * @return int|false The insert/replace ID or false on error
     */
    public function replace(string $table, array $data, ?array $format = null)
    {
        try {
            // Add prefix if not present
            if (! str_starts_with($table, $this->prefix)) {
                $table = $this->prefix.$table;
            }

            // MySQL REPLACE syntax
            $columns = array_keys($data);
            $values = array_values($data);
            $placeholders = array_fill(0, count($values), '?');

            $sql = sprintf(
                'REPLACE INTO %s (%s) VALUES (%s)',
                $this->connection->getTablePrefix().$table,
                implode(', ', array_map(fn ($c) => "`$c`", $columns)),
                implode(', ', $placeholders)
            );

            $this->connection->statement($sql, $values);

            return $this->connection->getPdo()->lastInsertId();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Prepare a SQL query with placeholders
     *
     * @param  string  $query  Query with sprintf-like placeholders
     * @param  mixed  ...$args  Arguments to replace placeholders
     */
    public function prepare(string $query, ...$args): string
    {
        // Handle WordPress-style placeholders (%s, %d, %f)
        $query = str_replace('%s', '?', $query);
        $query = str_replace('%d', '?', $query);
        $query = str_replace('%f', '?', $query);

        // If we have arguments, we need to return a bound query
        if (! empty($args)) {
            // This is a simplified version - in production you'd want proper escaping
            foreach ($args as $arg) {
                $pos = strpos($query, '?');
                if ($pos !== false) {
                    $value = is_string($arg) ? "'".addslashes($arg)."'" : $arg;
                    $query = substr_replace($query, $value, $pos, 1);
                }
            }
        }

        return $query;
    }

    /**
     * Get the last insert ID
     */
    public function insert_id(): int
    {
        return (int) $this->connection->getPdo()->lastInsertId();
    }

    /**
     * Get the last error message
     */
    public function last_error(): ?string
    {
        $errorInfo = $this->connection->getPdo()->errorInfo();

        return $errorInfo[2] ?? null;
    }

    /**
     * Format results based on output type
     */
    protected function formatResults(array $results, string $output): array
    {
        return array_map(fn ($row) => $this->formatResult($row, $output), $results);
    }

    /**
     * Format a single result based on output type
     */
    protected function formatResult($result, string $output)
    {
        switch ($output) {
            case 'ARRAY_A':
                return (array) $result;
            case 'ARRAY_N':
                return array_values((array) $result);
            case 'OBJECT':
            default:
                return is_array($result) ? (object) $result : $result;
        }
    }

    /**
     * Begin a database transaction
     */
    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    /**
     * Commit a database transaction
     */
    public function commit(): void
    {
        $this->connection->commit();
    }

    /**
     * Rollback a database transaction
     */
    public function rollback(): void
    {
        $this->connection->rollBack();
    }

    /**
     * Enable query logging
     */
    public function enableQueryLog(): void
    {
        $this->connection->enableQueryLog();
    }

    /**
     * Get the query log
     */
    public function getQueryLog(): array
    {
        return $this->connection->getQueryLog();
    }

    /**
     * Get table prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Set table prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
        $this->connection->setTablePrefix($prefix);
    }

    /**
     * Magic method to handle wpdb property access
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'prefix':
                return $this->prefix;
            case 'insert_id':
                return $this->insert_id();
            case 'last_error':
                return $this->last_error();
            default:
                return null;
        }
    }

    /**
     * Magic method to handle wpdb method calls
     */
    public function __call(string $method, array $arguments)
    {
        // Check if it's a mapped wpdb method
        if (isset($this->methodMap[$method])) {
            $bobMethod = $this->methodMap[$method];

            return $this->connection->$bobMethod(...$arguments);
        }

        // Otherwise, proxy to connection
        if (method_exists($this->connection, $method)) {
            return $this->connection->$method(...$arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist");
    }
}
