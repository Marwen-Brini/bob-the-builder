<?php

namespace Bob\Contracts;

interface BuilderInterface
{
    // Getter methods for properties that Grammar needs
    public function getAggregate(): ?array;
    
    public function getColumns(): ?array;
    
    public function getDistinct(): bool;
    
    public function getFrom(): ?string;
    
    public function getJoins(): ?array;
    
    public function getWheres(): array;
    
    public function getGroups(): ?array;
    
    public function getHavings(): ?array;
    
    public function getOrders(): ?array;
    
    public function getLimit(): ?int;
    
    public function getOffset(): ?int;
    
    public function getUnions(): ?array;
    
    public function getUnionLimit(): ?int;
    
    public function getUnionOffset(): ?int;
    
    public function getUnionOrders(): ?array;
    
    public function getLock();
    
    // Query building methods
    public function select($columns = ['*']): self;

    public function addSelect($column): self;

    public function distinct(bool $value = true): self;

    public function from($table, $as = null): self;

    public function where($column, $operator = null, $value = null, $boolean = 'and'): self;

    public function orWhere($column, $operator = null, $value = null): self;

    public function whereIn($column, $values, $boolean = 'and', $not = false): self;

    public function whereNotIn($column, $values, $boolean = 'and'): self;

    public function whereNull($column, $boolean = 'and', $not = false): self;

    public function whereNotNull($column, $boolean = 'and'): self;

    public function whereBetween($column, array $values, $boolean = 'and', $not = false): self;

    public function whereNotBetween($column, array $values, $boolean = 'and'): self;

    public function whereExists(\Closure $callback, $boolean = 'and', $not = false): self;

    public function whereNotExists(\Closure $callback, $boolean = 'and'): self;

    public function whereRaw($sql, $bindings = [], $boolean = 'and'): self;

    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false): self;

    public function leftJoin($table, $first, $operator = null, $second = null): self;

    public function rightJoin($table, $first, $operator = null, $second = null): self;

    public function crossJoin($table, $first = null, $operator = null, $second = null): self;

    public function groupBy(...$groups): self;

    public function having($column, $operator = null, $value = null, $boolean = 'and'): self;

    public function orHaving($column, $operator = null, $value = null): self;

    public function havingRaw($sql, $bindings = [], $boolean = 'and'): self;

    public function orderBy($column, $direction = 'asc'): self;

    public function orderByDesc($column): self;

    public function orderByRaw($sql, $bindings = []): self;

    public function latest($column = 'created_at'): self;

    public function oldest($column = 'created_at'): self;

    public function inRandomOrder($seed = ''): self;

    public function limit($value): self;

    public function offset($value): self;

    public function skip($value): self;

    public function take($value): self;

    public function page($page, $perPage = 15): self;

    public function get($columns = ['*']): array;

    public function first($columns = ['*']);

    public function find($id, $columns = ['*']);

    public function value($column);

    public function pluck($column, $key = null): array;

    public function chunk($count, callable $callback): bool;

    public function cursor(): \Generator;

    public function exists(): bool;

    public function doesntExist(): bool;

    public function count($columns = '*'): int;

    public function min($column);

    public function max($column);

    public function sum($column);

    public function avg($column);

    public function average($column);

    public function insert(array $values): bool;

    public function insertGetId(array $values, $sequence = null);

    public function insertOrIgnore(array $values): int;

    public function update(array $values): int;

    public function updateOrInsert(array $attributes, array $values = []): bool;

    public function increment($column, $amount = 1, array $extra = []): int;

    public function decrement($column, $amount = 1, array $extra = []): int;

    public function delete($id = null): int;

    /**
     * Truncate the table.
     *
     * @return bool Returns true if truncation was successful
     */
    public function truncate(): bool;

    public function raw($value): ExpressionInterface;

    public function selectRaw($expression, array $bindings = []): self;

    public function toSql(): string;

    public function getBindings(): array;

    public function getConnection(): ConnectionInterface;

    public function newQuery(): self;

    public function clone(): self;
}
