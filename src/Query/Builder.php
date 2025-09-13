<?php

namespace Bob\Query;

use BadMethodCallException;
use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ConnectionInterface;
use Bob\Contracts\ExpressionInterface;
use Bob\Contracts\GrammarInterface;
use Bob\Contracts\ProcessorInterface;
use Bob\Database\Expression;
use Closure;

class Builder implements BuilderInterface
{
    use DynamicFinder, Macroable, Scopeable;

    public ConnectionInterface $connection;

    public GrammarInterface $grammar;

    public ProcessorInterface $processor;

    public $aggregate;

    public $columns;

    public $distinct = false;

    public $from;

    public $joins;

    public $wheres = [];

    public $groups;

    public $havings;

    public $orders;

    public $limit;

    public $offset;

    public $unions;

    public $unionLimit;

    public $unionOffset;

    public $unionOrders;

    public $lock;

    protected array $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'having' => [],
        'order' => [],
        'union' => [],
    ];

    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->grammar = $connection->getQueryGrammar();
        $this->processor = $connection->getPostProcessor();
    }

    public function select($columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    public function addSelect($column): self
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->columns = array_merge((array) $this->columns, $column);

        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;

        return $this;
    }

    public function from($table, $as = null): self
    {
        $this->from = $as ? "{$table} as {$as}" : $table;

        return $this;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if ($this->invalidOperator($operator)) {
            $value = $operator;
            $operator = '=';
        }

        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        $type = 'Basic';
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (! $value instanceof ExpressionInterface) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    protected function addArrayOfWheres(array $column, string $boolean): self
    {
        foreach ($column as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                $this->where(...$value);
            } else {
                $this->where($key, '=', $value, $boolean);
            }
        }

        return $this;
    }

    protected function prepareValueAndOperator($value, $operator, bool $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new \InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    protected function invalidOperatorAndValue($operator, $value): bool
    {
        return is_null($value) && in_array($operator, $this->operators) &&
               ! in_array($operator, ['=', '<>', '!=']);
    }

    protected function invalidOperator($operator): bool
    {
        return ! in_array(strtolower($operator), $this->operators, true);
    }

    protected function whereNested(Closure $callback, string $boolean = 'and'): self
    {
        $query = $this->newQuery();
        $callback($query);

        return $this->addNestedWhereQuery($query, $boolean);
    }

    protected function addNestedWhereQuery(self $query, string $boolean = 'and'): self
    {
        if (count($query->wheres)) {
            $type = 'Nested';
            $this->wheres[] = compact('type', 'query', 'boolean');
            $this->addBinding($query->getBindings()['where'], 'where');
        }

        return $this;
    }

    protected function whereSub(string $column, string $operator, Closure $callback, string $boolean): self
    {
        $type = 'Sub';
        $query = $this->newQuery();
        $callback($query);

        $this->wheres[] = compact('type', 'column', 'operator', 'query', 'boolean');
        $this->addBinding($query->getBindings()['where'], 'where');

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false): self
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, $boolean, $not);
        }

        if ($values instanceof BuilderInterface) {
            $type = $not ? 'NotInSub' : 'InSub';
            $this->wheres[] = ['type' => $type, 'column' => $column, 'query' => $values, 'boolean' => $boolean];
            $this->addBinding($values->getBindings()['where'] ?? [], 'where');

            return $this;
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        if (is_array($values)) {
            $this->addBinding($values, 'where');
        }

        return $this;
    }

    protected function whereInSub(string $column, Closure $callback, string $boolean, bool $not): self
    {
        $type = $not ? 'NotInSub' : 'InSub';
        $query = $this->newQuery();
        $callback($query);

        $this->wheres[] = compact('type', 'column', 'query', 'boolean');
        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    public function whereNotIn($column, $values, $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull($column, $boolean = 'and', $not = false): self
    {
        $type = $not ? 'NotNull' : 'Null';
        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    public function whereNotNull($column, $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNull($column): self
    {
        return $this->whereNull($column, 'or');
    }

    public function orWhereNotNull($column): self
    {
        return $this->whereNotNull($column, 'or');
    }

    public function whereBetween($column, array $values, $boolean = 'and', $not = false): self
    {
        $type = $not ? 'NotBetween' : 'Between';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');
        $this->addBinding($values, 'where');

        return $this;
    }

    public function whereNotBetween($column, array $values, $boolean = 'and'): self
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function whereExists(Closure $callback, $boolean = 'and', $not = false): self
    {
        $query = $this->newQuery();
        $callback($query);

        return $this->addWhereExistsQuery($query, $boolean, $not);
    }

    protected function addWhereExistsQuery(self $query, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? 'NotExists' : 'Exists';
        $this->wheres[] = compact('type', 'query', 'boolean');
        $this->addBinding($query->getBindings()['where'], 'where');

        return $this;
    }

    public function whereNotExists(Closure $callback, $boolean = 'and'): self
    {
        return $this->whereExists($callback, $boolean, true);
    }

    public function whereRaw($sql, $bindings = [], $boolean = 'and'): self
    {
        $this->wheres[] = ['type' => 'Raw', 'sql' => $sql, 'boolean' => $boolean];
        $this->addBinding((array) $bindings, 'where');

        return $this;
    }

    public function orWhereRaw($sql, $bindings = []): self
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    public function whereDate($column, $operator, $value = null, $boolean = 'and'): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->wheres[] = [
            'type' => 'Date',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function whereTime($column, $operator, $value = null, $boolean = 'and'): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->wheres[] = [
            'type' => 'Time',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function whereDay($column, $operator, $value = null, $boolean = 'and'): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->wheres[] = [
            'type' => 'Day',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function whereMonth($column, $operator, $value = null, $boolean = 'and'): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->wheres[] = [
            'type' => 'Month',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function whereYear($column, $operator, $value = null, $boolean = 'and'): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->wheres[] = [
            'type' => 'Year',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and'): self
    {
        if (is_array($first)) {
            foreach ($first as $conditions) {
                $this->whereColumn(...$conditions);
            }
            return $this;
        }

        // If only two arguments were passed, the second is the column name
        if (is_null($second)) {
            $second = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'Column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereColumn($first, $operator = null, $second = null): self
    {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false): self
    {
        $join = new JoinClause($this, $type, $table);

        if ($first instanceof Closure) {
            $first($join);
            $this->joins[] = $join;
            $this->addBinding($join->getBindings(), 'join');
        } else {
            $method = $where ? 'where' : 'on';
            $this->joins[] = $join->$method($first, $operator, $second);
            $this->addBinding($join->getBindings(), 'join');
        }

        return $this;
    }

    public function leftJoin($table, $first, $operator = null, $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin($table, $first, $operator = null, $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function crossJoin($table, $first = null, $operator = null, $second = null): self
    {
        if ($first) {
            return $this->join($table, $first, $operator, $second, 'cross');
        }

        $this->joins[] = new JoinClause($this, 'cross', $table);

        return $this;
    }

    public function groupBy(...$groups): self
    {
        foreach ($groups as $group) {
            $this->groups = array_merge(
                (array) $this->groups,
                is_array($group) ? $group : [$group]
            );
        }

        return $this;
    }

    public function having($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        $type = 'Basic';
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (! $value instanceof ExpressionInterface) {
            $this->addBinding($value, 'having');
        }

        return $this;
    }

    public function orHaving($column, $operator = null, $value = null): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->having($column, $operator, $value, 'or');
    }

    public function havingRaw($sql, $bindings = [], $boolean = 'and'): self
    {
        $type = 'Raw';
        $this->havings[] = compact('type', 'sql', 'boolean');
        $this->addBinding($bindings, 'having');

        return $this;
    }

    public function orderBy($column, $direction = 'asc'): self
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $this->orders[] = compact('column', 'direction');

        return $this;
    }

    public function orderByDesc($column): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function orderByRaw($sql, $bindings = []): self
    {
        $type = 'Raw';
        $this->orders[] = compact('type', 'sql');
        $this->addBinding($bindings, 'order');

        return $this;
    }

    public function latest($column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest($column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    public function inRandomOrder($seed = ''): self
    {
        return $this->orderByRaw($this->grammar->compileRandom($seed));
    }

    public function limit($value): self
    {
        if ($value >= 0) {
            $this->limit = $value;
        }

        return $this;
    }

    public function offset($value): self
    {
        if ($value >= 0) {
            $this->offset = $value;
        }

        return $this;
    }

    public function skip($value): self
    {
        return $this->offset($value);
    }

    public function take($value): self
    {
        return $this->limit($value);
    }

    public function page($page, $perPage = 15): self
    {
        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    public function get($columns = ['*']): array
    {
        $original = $this->columns;

        if (is_null($this->columns)) {
            $this->columns = $columns;
        }

        $results = $this->processor->processSelect(
            $this, $this->runSelect()
        );

        $this->columns = $original;

        return $results;
    }

    protected function runSelect(): array
    {
        return $this->connection->select(
            $this->toSql(), $this->getBindings()
        );
    }

    public function first($columns = ['*'])
    {
        $results = $this->limit(1)->get($columns);
        return $results[0] ?? null;
    }

    public function find($id, $columns = ['*'])
    {
        return $this->where('id', '=', $id)->first($columns);
    }

    public function value($column)
    {
        $result = $this->first([$column]);

        return $result ? ($result->$column ?? null) : null;
    }

    public function pluck($column, $key = null): array
    {
        $results = $this->get(is_null($key) ? [$column] : [$column, $key]);

        return $this->pluckFromArrayColumn($results, $column, $key);
    }

    protected function pluckFromArrayColumn(array $results, string $column, ?string $key): array
    {
        $plucked = [];

        foreach ($results as $row) {
            $itemValue = is_object($row) ? ($row->$column ?? null) : ($row[$column] ?? null);

            if (is_null($key)) {
                $plucked[] = $itemValue;
            } else {
                $keyValue = is_object($row) ? ($row->$key ?? null) : ($row[$key] ?? null);
                $plucked[$keyValue] = $itemValue;
            }
        }

        return $plucked;
    }

    public function exists(): bool
    {
        $results = $this->connection->select(
            $this->grammar->compileExists($this), $this->getBindings()
        );

        if (isset($results[0])) {
            $results = (array) $results[0];

            return (bool) $results['exists'];
        }

        return false;
    }

    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    public function chunk($count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->page($page, $count)->get();

            if (count($results) == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $hasMore = count($results) == $count;
            unset($results);
            $page++;
        } while ($hasMore);

        return true;
    }

    public function cursor(): \Generator
    {
        foreach ($this->get() as $record) {
            yield $record;
        }
    }

    public function count($columns = '*'): int
    {
        return (int) $this->aggregate(__FUNCTION__, [$columns]);
    }

    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    public function sum($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]) ?: 0;
    }

    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    public function average($column)
    {
        return $this->avg($column);
    }

    protected function aggregate(string $function, array $columns = ['*'])
    {
        $results = $this->cloneWithout(['columns'])
            ->cloneWithoutBindings(['select'])
            ->setAggregate($function, $columns)
            ->get($columns);

        // @codeCoverageIgnoreStart
        if (! $results) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $firstResult = $results[0];

        return $firstResult ? ($firstResult->aggregate ?? null) : null;
    }

    protected function setAggregate(string $function, array $columns): self
    {
        $this->aggregate = compact('function', 'columns');

        if (empty($this->groups)) {
            $this->orders = null;
            $this->bindings['order'] = [];
        }

        return $this;
    }

    protected function cloneWithout(array $properties): self
    {
        $clone = clone $this;

        foreach ($properties as $property) {
            $clone->$property = null;
        }

        return $clone;
    }

    protected function cloneWithoutBindings(array $except): self
    {
        $clone = clone $this;

        foreach ($except as $type) {
            $clone->bindings[$type] = [];
        }

        return $clone;
    }

    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        foreach ($values as $key => $value) {
            ksort($value);
            $values[$key] = $value;
        }

        return $this->connection->insert(
            $this->grammar->compileInsert($this, $values),
            $this->cleanBindings(array_flatten($values))
        );
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        $values = $this->cleanBindings($values);

        return $this->processor->processInsertGetId($this, $sql, $values, $sequence);
    }

    public function insertOrIgnore(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        foreach ($values as $key => $value) {
            ksort($value);
            $values[$key] = $value;
        }

        return $this->connection->affectingStatement(
            $this->grammar->compileInsertOrIgnore($this, $values),
            $this->cleanBindings(array_flatten($values))
        );
    }

    public function update(array $values): int
    {
        $sql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->update($sql, $this->cleanBindings(
            array_flatten(array_merge($values, $this->getBindings()))
        ));
    }

    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        if (! $this->where($attributes)->exists()) {
            return $this->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return (bool) $this->limit(1)->update($values);
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("$wrapped + $amount")], $extra);

        return $this->update($columns);
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("$wrapped - $amount")], $extra);

        return $this->update($columns);
    }

    public function delete($id = null): int
    {
        if (! is_null($id)) {
            $this->where('id', '=', $id);
        }

        return $this->connection->delete(
            $this->grammar->compileDelete($this), $this->getBindings()
        );
    }

    public function truncate(): void
    {
        foreach ($this->grammar->compileTruncate($this) as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
    }

    public function raw($value): ExpressionInterface
    {
        return $this->connection->raw($value);
    }

    public function selectRaw($expression, array $bindings = []): self
    {
        $this->addSelect(new Expression($expression));

        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    public function when($value, callable $callback, ?callable $default = null): self
    {
        if ($value) {
            return $callback($this, $value) ?? $this;
        } elseif ($default) {
            return $default($this, $value) ?? $this;
        }

        return $this;
    }

    public function unless($value, callable $callback, ?callable $default = null): self
    {
        if (! $value) {
            return $callback($this, $value) ?? $this;
        } elseif ($default) {
            return $default($this, $value) ?? $this;
        }

        return $this;
    }

    public function whereJsonContains($column, $value, $boolean = 'and', $not = false): self
    {
        $type = $not ? 'JsonNotContains' : 'JsonContains';
        $this->wheres[] = compact('type', 'column', 'value', 'boolean');

        if (! $value instanceof ExpressionInterface) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    public function whereJsonLength($column, $operator, $value = null, $boolean = 'and'): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->wheres[] = [
            'type' => 'JsonLength',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        if (! $value instanceof ExpressionInterface) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    public function whereFullText($columns, $value, $boolean = 'and'): self
    {
        $columns = (array) $columns;

        $this->wheres[] = [
            'type' => 'Fulltext',
            'columns' => $columns,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner'): self
    {
        if ($query instanceof Closure) {
            $subQuery = $this->newQuery();
            $query($subQuery);
            $query = $subQuery;
        }

        $expression = '(' . $query->toSql() . ') as ' . $this->grammar->wrap($as);

        $this->addBinding($query->getBindings(), 'join');

        return $this->join(new Expression($expression), $first, $operator, $second, $type);
    }

    public function leftJoinSub($query, $as, $first, $operator = null, $second = null): self
    {
        return $this->joinSub($query, $as, $first, $operator, $second, 'left');
    }

    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }

    public function getBindings(): array
    {
        return array_flatten($this->bindings);
    }

    protected function addBinding($value, string $type = 'where'): self
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new \InvalidArgumentException("Invalid binding type: {$type}.");
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_merge($this->bindings[$type], $value);
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    protected function cleanBindings(array $bindings): array
    {
        return array_values(array_filter($bindings, function ($binding) {
            return ! $binding instanceof ExpressionInterface;
        }));
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function newQuery(): self
    {
        return new static($this->connection);
    }

    public function clone(): self
    {
        return clone $this;
    }
    
    // Getter methods for interface compliance
    public function getAggregate(): ?array
    {
        return $this->aggregate;
    }
    
    public function getColumns(): ?array
    {
        return $this->columns;
    }
    
    public function getDistinct(): bool
    {
        return $this->distinct;
    }
    
    public function getFrom(): ?string
    {
        return $this->from;
    }
    
    public function getJoins(): ?array
    {
        return $this->joins;
    }
    
    public function getWheres(): array
    {
        return $this->wheres;
    }
    
    public function getGroups(): ?array
    {
        return $this->groups;
    }
    
    public function getHavings(): ?array
    {
        return $this->havings;
    }
    
    public function getOrders(): ?array
    {
        return $this->orders;
    }
    
    public function getLimit(): ?int
    {
        return $this->limit;
    }
    
    public function getOffset(): ?int
    {
        return $this->offset;
    }
    
    public function getUnions(): ?array
    {
        return $this->unions;
    }
    
    public function getUnionLimit(): ?int
    {
        return $this->unionLimit;
    }
    
    public function getUnionOffset(): ?int
    {
        return $this->unionOffset;
    }
    
    public function getUnionOrders(): ?array
    {
        return $this->unionOrders;
    }
    
    public function getLock()
    {
        return $this->lock;
    }

    /**
     * Handle dynamic method calls.
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        // First, check for dynamic finders (findBySlug, whereByStatus, etc.)
        $result = $this->handleDynamicFinder($method, $parameters);
        if ($result !== null) {
            return $result;
        }

        // Then check for local scopes
        // @codeCoverageIgnoreStart
        if (static::hasScope($method)) {
            return $this->withScope($method, ...$parameters);
        }
        // @codeCoverageIgnoreEnd

        // Then check for macros
        if (static::hasMacro($method)) {
            $macro = static::$macros[$method];
            if ($macro instanceof Closure) {
                $macro = $macro->bindTo($this, static::class);
            }

            return $macro(...$parameters);
        }

        // Finally, throw an exception
        throw new BadMethodCallException(sprintf(
            'Method %s::%s does not exist.',
            static::class,
            $method
        ));
    }

    /**
     * Create a new query instance with global scopes applied.
     */
    public function withGlobalScopes(): self
    {
        $query = clone $this;
        $query->applyGlobalScopes();

        return $query;
    }
}

function array_flatten(array $array): array
{
    $result = [];

    array_walk_recursive($array, function ($value) use (&$result) {
        $result[] = $value;
    });

    return $result;
}

function collect(array $items): Collection
{
    return new Collection($items);
}

class Collection
{
    protected array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function map(callable $callback): self
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    public function implode(string $glue): string
    {
        return implode($glue, $this->items);
    }
}

class JoinClause extends Builder
{
    public string $type;

    public string $table;

    public function __construct(Builder $parentQuery, string $type, string $table)
    {
        parent::__construct($parentQuery->connection);

        $this->type = $type;
        $this->table = $table;
    }

    public function on($first, $operator = null, $second = null, $boolean = 'and'): self
    {
        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    public function orOn($first, $operator = null, $second = null): self
    {
        return $this->on($first, $operator, $second, 'or');
    }

}
