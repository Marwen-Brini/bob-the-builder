<?php

namespace Bob\Database;

use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ConnectionInterface;
use Bob\Contracts\ExpressionInterface;
use Bob\Contracts\GrammarInterface;
use Bob\Contracts\ProcessorInterface;
use Bob\Query\Builder;
use Bob\Query\Grammar;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Grammars\SQLiteGrammar;
use Bob\Query\Processor;
use Closure;
use PDO;
use Throwable;

class Connection implements ConnectionInterface
{
    protected ?PDO $pdo = null;
    protected ?PDO $readPdo = null;
    protected array $config = [];
    protected GrammarInterface $queryGrammar;
    protected ProcessorInterface $postProcessor;
    protected string $tablePrefix = '';
    protected int $transactions = 0;
    protected array $queryLog = [];
    protected bool $loggingQueries = false;
    protected bool $pretending = false;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->tablePrefix = $config['prefix'] ?? '';
        
        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    protected function useDefaultQueryGrammar(): void
    {
        $driver = $this->config['driver'] ?? 'mysql';
        
        $this->queryGrammar = match ($driver) {
            'mysql' => new MySQLGrammar(),
            'pgsql', 'postgres', 'postgresql' => new PostgreSQLGrammar(),
            'sqlite' => new SQLiteGrammar(),
            default => new MySQLGrammar(),
        };
        
        $this->queryGrammar->setTablePrefix($this->tablePrefix);
    }

    protected function useDefaultPostProcessor(): void
    {
        $this->postProcessor = new Processor();
    }

    public function getPdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        return $this->pdo = $this->createConnection();
    }

    protected function createConnection(): PDO
    {
        $driver = $this->config['driver'] ?? 'mysql';
        
        $dsn = match ($driver) {
            'mysql' => $this->getMySQLDsn(),
            'pgsql', 'postgres', 'postgresql' => $this->getPostgresDsn(),
            'sqlite' => $this->getSQLiteDsn(),
            default => throw new \InvalidArgumentException("Unsupported driver: {$driver}"),
        };

        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;
        $options = $this->config['options'] ?? [];

        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

        return new PDO($dsn, $username, $password, $options);
    }

    protected function getMySQLDsn(): string
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';

        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    protected function getPostgresDsn(): string
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 5432;
        $database = $this->config['database'] ?? '';

        return "pgsql:host={$host};port={$port};dbname={$database}";
    }

    protected function getSQLiteDsn(): string
    {
        $database = $this->config['database'] ?? ':memory:';
        
        return "sqlite:{$database}";
    }

    public function setPdo(PDO $pdo): self
    {
        $this->pdo = $pdo;
        
        return $this;
    }

    public function getName(): string
    {
        return $this->config['name'] ?? 'default';
    }

    public function reconnect(): void
    {
        $this->disconnect();
        $this->pdo = null;
        $this->readPdo = null;
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->readPdo = null;
    }

    public function getDatabaseName(): string
    {
        return $this->config['database'] ?? '';
    }

    public function getQueryGrammar(): GrammarInterface
    {
        return $this->queryGrammar;
    }

    public function setQueryGrammar(GrammarInterface $grammar): self
    {
        $this->queryGrammar = $grammar;
        
        return $this;
    }

    public function getPostProcessor(): ProcessorInterface
    {
        return $this->postProcessor;
    }

    public function setPostProcessor(ProcessorInterface $processor): self
    {
        $this->postProcessor = $processor;
        
        return $this;
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    public function setTablePrefix(string $prefix): self
    {
        $this->tablePrefix = $prefix;
        $this->queryGrammar->setTablePrefix($prefix);
        
        return $this;
    }

    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending) {
                return [];
            }

            $statement = $this->getPdoForSelect($useReadPdo)->prepare($query);
            $statement->execute($bindings);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    protected function getPdoForSelect(bool $useReadPdo = true): PDO
    {
        return $useReadPdo && $this->readPdo ? $this->readPdo : $this->getPdo();
    }

    public function selectOne(string $query, array $bindings = [], bool $useReadPdo = true): ?array
    {
        $records = $this->select($query, $bindings, $useReadPdo);

        return array_shift($records);
    }

    public function insert(string $query, array $bindings = []): bool
    {
        return $this->statement($query, $bindings);
    }

    public function update(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    public function delete(string $query, array $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    public function statement(string $query, array $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending) {
                return true;
            }

            $statement = $this->getPdo()->prepare($query);

            return $statement->execute($bindings);
        });
    }

    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending) {
                return 0;
            }

            $statement = $this->getPdo()->prepare($query);
            $statement->execute($bindings);

            return $statement->rowCount();
        });
    }

    public function unprepared(string $query): bool
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending) {
                return true;
            }

            return (bool) $this->getPdo()->exec($query);
        });
    }

    protected function run(string $query, array $bindings, Closure $callback)
    {
        $start = microtime(true);

        try {
            $result = $callback($query, $bindings);
        } catch (Throwable $e) {
            throw $e;
        }

        $time = microtime(true) - $start;

        if ($this->loggingQueries) {
            $this->logQuery($query, $bindings, $time);
        }

        return $result;
    }

    public function prepareBindings(array $bindings): array
    {
        foreach ($bindings as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $bindings[$key] = $value->format($this->queryGrammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }

    public function logQuery(string $query, array $bindings, float $time = null): void
    {
        $this->queryLog[] = compact('query', 'bindings', 'time');
    }

    public function transaction(Closure $callback, int $attempts = 1)
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            try {
                $callbackResult = $callback($this);
                $this->commit();
                
                return $callbackResult;
            } catch (Throwable $e) {
                $this->rollBack();

                if ($currentAttempt >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactions == 0) {
            $this->getPdo()->beginTransaction();
        } elseif ($this->transactions >= 1 && $this->queryGrammar->supportsSavepoints()) {
            $this->getPdo()->exec(
                $this->queryGrammar->compileSavepoint('trans' . ($this->transactions + 1))
            );
        }

        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions == 1) {
            $this->getPdo()->commit();
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactions == 1) {
            $this->getPdo()->rollBack();
        } elseif ($this->transactions > 1 && $this->queryGrammar->supportsSavepoints()) {
            $this->getPdo()->exec(
                $this->queryGrammar->compileSavepointRollBack('trans' . $this->transactions)
            );
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function transactionLevel(): int
    {
        return $this->transactions;
    }

    public function pretend(Closure $callback): array
    {
        $this->pretending = true;
        $this->queryLog = [];
        $this->loggingQueries = true;

        $callback($this);

        $this->pretending = false;
        $this->loggingQueries = false;

        return $this->queryLog;
    }

    public function pretending(): bool
    {
        return $this->pretending;
    }

    public function enableQueryLog(): void
    {
        $this->loggingQueries = true;
    }

    public function disableQueryLog(): void
    {
        $this->loggingQueries = false;
    }

    public function logging(): bool
    {
        return $this->loggingQueries;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }

    public function table(string $table): BuilderInterface
    {
        return (new Builder($this))->from($table);
    }

    public function raw($value): ExpressionInterface
    {
        return new Expression($value);
    }
}