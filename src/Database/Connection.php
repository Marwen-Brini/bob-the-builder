<?php

namespace Bob\Database;

use Bob\Cache\QueryCache;
use Bob\Concerns\LogsQueries;
use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ConnectionInterface;
use Bob\Contracts\ExpressionInterface;
use Bob\Contracts\GrammarInterface;
use Bob\Contracts\ProcessorInterface;
use Bob\Logging\Log;
use Bob\Query\Builder;
use Bob\Query\Grammars\MySQLGrammar;
use Bob\Query\Grammars\PostgreSQLGrammar;
use Bob\Query\Grammars\SQLiteGrammar;
use Bob\Query\Processor;
use Closure;
use PDO;
use Psr\Log\LoggerAwareInterface;
use Throwable;

class Connection implements ConnectionInterface, LoggerAwareInterface
{
    use LogsQueries;

    protected ?PDO $pdo = null;

    protected ?PDO $readPdo = null;

    protected array $config = [];

    protected GrammarInterface $queryGrammar;

    protected ProcessorInterface $postProcessor;

    protected string $tablePrefix = '';

    protected int $transactions = 0;

    protected bool $pretending = false;

    protected array $preparedStatements = [];

    protected bool $cachePreparedStatements = true;

    protected int $maxCachedStatements = 100;

    protected ?QueryCache $queryCache = null;

    protected ?QueryProfiler $profiler = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->tablePrefix = $config['prefix'] ?? '';

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();

        // Register with global Log facade
        Log::registerConnection($this);

        // Initialize logging if configured locally or globally
        if (($config['logging'] ?? false) || Log::isEnabled()) {
            $this->enableQueryLog();
        }

        // Set logger if provided locally or use global logger
        if (isset($config['logger'])) {
            $this->setLogger($config['logger']);
        } elseif (Log::getLogger()) {
            $this->setLogger(Log::getLogger());
        }
    }

    protected function useDefaultQueryGrammar(): void
    {
        $driver = $this->config['driver'] ?? 'mysql';

        $this->queryGrammar = match ($driver) {
            'mysql' => new MySQLGrammar,
            'pgsql', 'postgres', 'postgresql' => new PostgreSQLGrammar,
            'sqlite' => new SQLiteGrammar,
            default => new MySQLGrammar,
        };

        $this->queryGrammar->setTablePrefix($this->tablePrefix);
    }

    protected function useDefaultPostProcessor(): void
    {
        $this->postProcessor = new Processor;
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

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            $this->logConnection('established', $this->config);

            return $pdo;
        } catch (\PDOException $e) {
            $this->logConnection('failed', $this->config);
            throw $e;
        }
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

    public function getConfig(?string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? null;
    }

    public function setTablePrefix(string $prefix): self
    {
        $this->tablePrefix = $prefix;
        $this->queryGrammar->setTablePrefix($prefix);

        return $this;
    }

    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array
    {
        // Check cache first
        if ($this->queryCache && $this->queryCache->isEnabled()) {
            $cacheKey = $this->queryCache->generateKey($query, $bindings);
            $cached = $this->queryCache->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending) {
                return [];
            }

            $pdo = $this->getPdoForSelect($useReadPdo);
            $statement = $this->getCachedStatement($query, $pdo);
            $statement->execute($bindings);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        });

        // Cache the result
        if ($this->queryCache && $this->queryCache->isEnabled()) {
            $cacheKey = $this->queryCache->generateKey($query, $bindings);
            $this->queryCache->put($cacheKey, $result);
        }

        return $result;
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

            $statement = $this->getCachedStatement($query, $this->getPdo());

            return $statement->execute($bindings);
        });
    }

    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending) {
                return 0;
            }

            $statement = $this->getCachedStatement($query, $this->getPdo());
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

            return $this->getPdo()->exec($query) !== false;
        });
    }

    protected function run(string $query, array $bindings, Closure $callback)
    {
        $start = microtime(true);

        // Start profiling
        $profileId = '';
        if ($this->profiler && $this->profiler->isEnabled()) {
            $profileId = $this->profiler->start($query, $bindings);
        }

        try {
            $result = $callback($query, $bindings);
        } catch (Throwable $e) {
            if ($profileId) {
                $this->profiler->end($profileId);
            }
            throw $e;
        }

        $time = microtime(true) - $start;

        // End profiling
        if ($profileId) {
            $this->profiler->end($profileId);
        }

        if ($this->isLoggingEnabled()) {
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
            $this->logTransaction('started');
        } elseif ($this->transactions >= 1 && $this->queryGrammar->supportsSavepoints()) {
            $savepoint = 'trans'.($this->transactions + 1);
            $this->getPdo()->exec(
                $this->queryGrammar->compileSavepoint($savepoint)
            );
            $this->logTransaction('savepoint', $savepoint);
        }

        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions == 1) {
            $this->getPdo()->commit();
            $this->logTransaction('committed');
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactions == 1) {
            $this->getPdo()->rollBack();
            $this->logTransaction('rolled back');
        } elseif ($this->transactions > 1 && $this->queryGrammar->supportsSavepoints()) {
            $savepoint = 'trans'.$this->transactions;
            $this->getPdo()->exec(
                $this->queryGrammar->compileSavepointRollBack($savepoint)
            );
            $this->logTransaction('savepoint rolled back', $savepoint);
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
        $this->clearQueryLog();
        $this->enableQueryLog();

        $callback($this);

        $this->pretending = false;
        $this->disableQueryLog();

        return $this->getQueryLog();
    }

    public function pretending(): bool
    {
        return $this->pretending;
    }

    public function logging(): bool
    {
        return $this->isLoggingEnabled();
    }

    public function flushQueryLog(): void
    {
        $this->clearQueryLog();
    }

    public function table(string $table): BuilderInterface
    {
        return (new Builder($this))->from($table);
    }

    public function raw($value): ExpressionInterface
    {
        return new Expression($value);
    }

    protected function getCachedStatement(string $query, PDO $pdo): \PDOStatement
    {
        if (! $this->cachePreparedStatements) {
            return $pdo->prepare($query);
        }

        $key = spl_object_hash($pdo).':'.$query;

        if (! isset($this->preparedStatements[$key])) {
            if (count($this->preparedStatements) >= $this->maxCachedStatements) {
                array_shift($this->preparedStatements);
            }

            $this->preparedStatements[$key] = $pdo->prepare($query);
        }

        return $this->preparedStatements[$key];
    }

    public function enableStatementCaching(): void
    {
        $this->cachePreparedStatements = true;
    }

    public function disableStatementCaching(): void
    {
        $this->cachePreparedStatements = false;
        $this->clearStatementCache();
    }

    public function clearStatementCache(): void
    {
        $this->preparedStatements = [];
    }

    public function getStatementCacheSize(): int
    {
        return count($this->preparedStatements);
    }

    public function setMaxCachedStatements(int $max): void
    {
        $this->maxCachedStatements = max(1, $max);

        while (count($this->preparedStatements) > $this->maxCachedStatements) {
            array_shift($this->preparedStatements);
        }
    }

    public function enableQueryCache(int $maxItems = 1000, int $ttl = 3600): void
    {
        $this->queryCache = new QueryCache($maxItems, $ttl);
    }

    public function disableQueryCache(): void
    {
        if ($this->queryCache) {
            $this->queryCache->disable();
        }
    }

    public function flushQueryCache(): void
    {
        if ($this->queryCache) {
            $this->queryCache->flush();
        }
    }

    public function getQueryCache(): ?QueryCache
    {
        return $this->queryCache;
    }

    public function enableProfiling(): void
    {
        if (! $this->profiler) {
            $this->profiler = new QueryProfiler;
        }
        $this->profiler->enable();
    }

    public function disableProfiling(): void
    {
        if ($this->profiler) {
            $this->profiler->disable();
        }
    }

    public function getProfiler(): ?QueryProfiler
    {
        return $this->profiler;
    }

    public function getProfilingReport(): array
    {
        if (! $this->profiler) {
            return ['enabled' => false, 'message' => 'Profiling not initialized'];
        }

        return $this->profiler->getReport();
    }
}
