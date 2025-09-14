<?php

namespace Bob\Contracts;

interface ConnectionInterface
{
    public function getPdo(): \PDO;

    public function setPdo(\PDO $pdo): self;

    public function getName(): string;

    public function reconnect(): void;

    public function disconnect(): void;

    public function getDatabaseName(): string;

    public function getQueryGrammar(): GrammarInterface;

    public function setQueryGrammar(GrammarInterface $grammar): self;

    public function getPostProcessor(): ProcessorInterface;

    public function setPostProcessor(ProcessorInterface $processor): self;

    public function getTablePrefix(): string;

    public function setTablePrefix(string $prefix): self;

    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array;

    public function selectOne(string $query, array $bindings = [], bool $useReadPdo = true): mixed;

    public function insert(string $query, array $bindings = []): bool;

    public function update(string $query, array $bindings = []): int;

    public function delete(string $query, array $bindings = []): int;

    public function statement(string $query, array $bindings = []): bool;

    public function affectingStatement(string $query, array $bindings = []): int;

    public function unprepared(string $query): bool;

    public function prepareBindings(array $bindings): array;

    public function transaction(\Closure $callback, int $attempts = 1);

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function transactionLevel(): int;

    public function pretend(\Closure $callback): array;

    public function pretending(): bool;

    public function logQuery(string $query, array $bindings, ?float $time = null): void;

    public function enableQueryLog(): void;

    public function disableQueryLog(): void;

    public function logging(): bool;

    public function getQueryLog(): array;

    public function flushQueryLog(): void;

    public function table(string $table): BuilderInterface;

    public function raw($value): ExpressionInterface;
}
