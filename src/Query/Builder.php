<?php

namespace Bob\Query;

use BadMethodCallException;
use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ConnectionInterface;
use Bob\Contracts\ExpressionInterface;
use Bob\Contracts\GrammarInterface;
use Bob\Contracts\ProcessorInterface;
use Bob\Database\Expression;
use Bob\Query\RelationshipLoader;
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

    /**
     * The model being queried.
     */
    protected $model;

    /**
     * The relationships that should be eager loaded.
     */
    protected array $eagerLoad = [];

    /**
     * The instance-level global scopes for this builder.
     */
    protected array $instanceGlobalScopes = [];

    /**
     * The removed global scopes for this builder instance.
     */
    protected array $removedScopes = [];

    public $lock;

    public bool $useWritePdo = false;

    /**
     * Enable caching for exists() queries to avoid repeated checks.
     */
    protected bool $existsCaching = false;

    /**
     * TTL for exists() query cache in seconds.
     */
    protected int $existsCacheTtl = 60;

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
        $columns = is_array($columns) ? $columns : func_get_args();

        // Process each column to detect aggregate functions
        $this->columns = array_map(function ($column) {
            // If it's already an Expression, leave it as is
            if ($column instanceof Expression) {
                return $column;
            }

            // Check if the column contains an aggregate function
            if (is_string($column) && $this->isAggregateFunction($column)) {
                return new Expression($column);
            }

            return $column;
        }, $columns);

        return $this;
    }

    /**
     * Add a subquery select expression to the query.
     *
     * @param \Closure|Builder|string $query
     * @param string $as
     * @return self
     */
    public function selectSub($query, $as): self
    {
        if ($query instanceof \Closure) {
            $callback = $query;
            $query = $this->newQuery();
            $callback($query);
        }

        if ($query instanceof self) {
            $bindings = $query->getBindings();
            $query = $query->toSql();

            // Add the bindings from the subquery
            $this->addBinding($bindings, 'select');
        }

        return $this->selectRaw('(' . $query . ') as ' . $this->grammar->wrap($as));
    }

    public function addSelect($column): self
    {
        $columns = is_array($column) ? $column : func_get_args();

        // Process each column to detect aggregate functions
        $processedColumns = array_map(function ($col) {
            // If it's already an Expression, leave it as is
            if ($col instanceof Expression) {
                return $col;
            }

            // Check if the column contains an aggregate function
            if (is_string($col) && $this->isAggregateFunction($col)) {
                return new Expression($col);
            }

            return $col;
        }, $columns);

        $this->columns = array_merge((array) $this->columns, $processedColumns);

        return $this;
    }

    /**
     * Set the distinct flag for the query.
     *
     * @param bool $value
     * @return self
     */
    public function distinct(bool $value = true): self
    {
        $this->distinct = $value;

        return $this;
    }

    public function from($table, ?string $as = null): self
    {
        if ($this->isQueryable($table)) {
            // Handle closures and subqueries
            if ($table instanceof \Closure) {
                $sub = $this->forSubQuery();
                $table($sub);
                $table = $sub;
            }
            $this->from = $table;
        } else {
            // Handle regular table names
            $this->from = $as ? "{$table} as {$as}" : $table;
        }

        return $this;
    }

    /**
     * Set a raw from clause.
     *
     * @param string $expression
     * @param array $bindings
     * @return self
     */
    public function fromRaw($expression, $bindings = []): self
    {
        $this->from = $this->raw($expression);

        $this->addBinding($bindings, 'from');

        return $this;
    }

    public function where($column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
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
        return is_null($operator) || ! in_array(strtolower($operator), $this->operators, true);
    }

    public function whereNested(Closure $callback, string $boolean = 'and'): self
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
            // Get the where bindings directly from the query's bindings array
            if (isset($query->bindings['where'])) {
                $this->addBinding($query->bindings['where'], 'where');
            }
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

    public function orWhere($column, mixed $operator = null, mixed $value = null): self
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
            // Get all bindings from the subquery
            $this->addBinding($values->getBindings(), 'where');

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

    /**
     * Add an "or where in" clause to the query.
     *
     * @param string $column
     * @param mixed $values
     * @return self
     */
    public function orWhereIn($column, $values): self
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param string $column
     * @param mixed $values
     * @return self
     */
    public function orWhereNotIn($column, $values): self
    {
        return $this->whereIn($column, $values, 'or', true);
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
        // Get the where bindings directly from the query's bindings array
        if (isset($query->bindings['where'])) {
            $this->addBinding($query->bindings['where'], 'where');
        }

        return $this;
    }

    public function whereNotExists(Closure $callback, $boolean = 'and'): self
    {
        return $this->whereExists($callback, $boolean, true);
    }

    /**
     * Add an "or where exists" clause to the query.
     *
     * @param Closure $callback
     * @return self
     */
    public function orWhereExists(Closure $callback): self
    {
        return $this->whereExists($callback, 'or');
    }

    /**
     * Add an "or where not exists" clause to the query.
     *
     * @param Closure $callback
     * @return self
     */
    public function orWhereNotExists(Closure $callback): self
    {
        return $this->whereExists($callback, 'or', true);
    }

    /**
     * Add a where in with integers clause to the query.
     *
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @param bool $not
     * @return self
     */
    public function whereIntegerInRaw($column, $values, $boolean = 'and', $not = false): self
    {
        $type = $not ? 'NotInRaw' : 'InRaw';

        // Ensure all values are integers
        $values = array_map('intval', $values);

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        return $this;
    }

    /**
     * Add a where not in with integers clause to the query.
     *
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @return self
     */
    public function whereIntegerNotInRaw($column, $values, $boolean = 'and'): self
    {
        return $this->whereIntegerInRaw($column, $values, $boolean, true);
    }

    /**
     * Add an or where in with integers clause to the query.
     *
     * @param string $column
     * @param array $values
     * @return self
     */
    public function orWhereIntegerInRaw($column, $values): self
    {
        return $this->whereIntegerInRaw($column, $values, 'or');
    }

    /**
     * Add an or where not in with integers clause to the query.
     *
     * @param string $column
     * @param array $values
     * @return self
     */
    public function orWhereIntegerNotInRaw($column, $values): self
    {
        return $this->whereIntegerInRaw($column, $values, 'or', true);
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

    public function whereDate($column, $operator, mixed $value = null, string $boolean = 'and'): self
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

    public function whereTime($column, $operator, mixed $value = null, string $boolean = 'and'): self
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

    public function whereDay($column, $operator, mixed $value = null, string $boolean = 'and'): self
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

    public function whereMonth($column, $operator, mixed $value = null, string $boolean = 'and'): self
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

    public function whereYear($column, $operator, mixed $value = null, string $boolean = 'and'): self
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

    public function join($table, $first, mixed $operator = null, mixed $second = null, string $type = 'inner', bool $where = false): self
    {
        $join = $this->newJoinClause($type, $table);

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

    public function leftJoin($table, $first, mixed $operator = null, mixed $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin($table, $first, mixed $operator = null, mixed $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function crossJoin($table, mixed $first = null, mixed $operator = null, mixed $second = null): self
    {
        if ($this->hasCrossJoinConditions($first)) {
            return $this->crossJoinWithConditions($table, $first, $operator, $second);
        }

        return $this->simpleCrossJoin($table);
    }

    /**
     * Check if cross join has conditions
     */
    protected function hasCrossJoinConditions($first): bool
    {
        return $first !== null;
    }

    /**
     * Add cross join with conditions
     */
    protected function crossJoinWithConditions($table, $first, $operator, $second): self
    {
        return $this->join($table, $first, $operator, $second, 'cross');
    }

    /**
     * Add simple cross join without conditions
     */
    protected function simpleCrossJoin($table): self
    {
        $this->joins[] = $this->newJoinClause('cross', $table);
        return $this;
    }

    /**
     * Create a new join clause instance.
     */
    public function newJoinClause($parentQuery, mixed $type = null, $table = null): JoinClause
    {
        // Handle both signatures for backward compatibility
        if (is_string($parentQuery) && $type !== null) {
            // Old signature: newJoinClause($type, $table)
            // $parentQuery is actually the type, $type is actually the table
            return new JoinClause($this, $parentQuery, $type);
        }
        // New signature: newJoinClause($parentQuery, $type, $table)
        return new JoinClause($parentQuery, $type, $table);
    }

    /**
     * Create a new query instance for a subquery.
     */
    public function forSubQuery(): self
    {
        return $this->newQuery();
    }

    /**
     * Merge an array of bindings into our bindings.
     */
    public function mergeBindings(self $query): self
    {
        foreach ($query->bindings as $type => $bindings) {
            $this->mergeBindingsForType($type, $bindings);
        }

        return $this;
    }

    /**
     * Merge bindings for a specific type
     */
    protected function mergeBindingsForType(string $type, array $bindings): void
    {
        if (!isset($this->bindings[$type])) {
            $this->bindings[$type] = [];
        }
        $this->bindings[$type] = array_merge($this->bindings[$type], $bindings);
    }

    /**
     * Add a "join where" clause to the query.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param \\Closure $where
     * @return self
     */
    public function joinWhere($table, $first, $operator, $second, $where): self
    {
        return $this->join($table, function($join) use ($first, $operator, $second, $where) {
            $join->on($first, $operator, $second);
            $where($join);
        });
    }

    /**
     * Add a "left join where" clause to the query.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param \\Closure $where
     * @return self
     */
    public function leftJoinWhere($table, $first, $operator, $second, $where): self
    {
        return $this->leftJoin($table, function($join) use ($first, $operator, $second, $where) {
            $join->on($first, $operator, $second);
            $where($join);
        });
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

    public function having($column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
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

    public function orHaving($column, mixed $operator = null, mixed $value = null): self
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

    /**
     * Add a raw or having clause to the query.
     *
     * @param string $sql
     * @param array $bindings
     * @return self
     */
    public function orHavingRaw($sql, $bindings = []): self
    {
        return $this->havingRaw($sql, $bindings, 'or');
    }

    /**
     * Add a having between clause to the query.
     */
    public function havingBetween($column, array $values, $boolean = 'and', $not = false): self
    {
        $type = 'Between';
        $this->havings[] = compact('type', 'column', 'values', 'boolean', 'not');
        $this->addBinding($values, 'having');
        return $this;
    }

    public function orderBy($column, $direction = 'asc'): self
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $order = compact('column', 'direction');

        // If we have unions, set unionOrders instead
        if ($this->unions) {
            $this->unionOrders[] = $order;
        } else {
            $this->orders[] = $order;
        }

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

    /**
     * Remove all existing orders and optionally add a new one.
     *
     * @param string|null $column
     * @param string $direction
     * @return self
     */
    public function reorder(mixed $column = null, string $direction = 'asc'): self
    {
        $this->orders = null;
        $this->bindings['order'] = [];

        if ($column !== null) {
            return $this->orderBy($column, $direction);
        }

        return $this;
    }

    public function limit($value): self
    {
        if ($value >= 0) {
            // If we have unions, set the unionLimit instead
            if ($this->unions) {
                $this->unionLimit = $value;
            } else {
                $this->limit = $value;
            }
        }

        return $this;
    }

    public function offset($value): self
    {
        if ($value >= 0) {
            // If we have unions, set the unionOffset instead
            if ($this->unions) {
                $this->unionOffset = $value;
            } else {
                $this->offset = $value;
            }
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

    /**
     * Alias for the "page" method
     */
    public function forPage($page, $perPage = 15): self
    {
        return $this->page($page, $perPage);
    }

    public function get($columns = ['*']): array
    {
        $original = $this->columns;

        if (is_null($this->columns)) {
            $this->columns = $columns;
        }

        $this->applyScopes();

        $results = $this->processor->processSelect(
            $this, $this->runSelect()
        );

        $this->columns = $original;

        // If we have a model, hydrate the results into model instances
        if ($this->model) {
            $models = [];

            foreach ($results as $result) {
                $models[] = $this->hydrateModel($result);
            }

            // Handle eager loading
            if (!empty($this->eagerLoad)) {
                $models = $this->eagerLoadRelations($models);
            }

            // Return model instances
            return $models;
        }

        return $results;
    }

    protected function runSelect(): array
    {
        return $this->connection->select(
            $this->toSql(), $this->getBindings(), ! $this->useWritePdo
        );
    }

    /**
     * Hydrate a result into a model instance
     */
    protected function hydrateModel($data)
    {
        if (!$this->shouldHydrateModel()) {
            return $data;
        }

        return $this->createModelFromData($data);
    }

    /**
     * Check if we should hydrate to model
     */
    protected function shouldHydrateModel(): bool
    {
        return $this->model !== null;
    }

    /**
     * Create a model instance from data
     */
    protected function createModelFromData($data)
    {
        $modelClass = get_class($this->model);
        $model = new $modelClass;

        // Convert stdClass to array if needed
        $attributes = is_object($data) ? (array) $data : $data;

        // Set the attributes directly
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }

        // Mark as existing from database
        $model->exists = true;
        $model->syncOriginal();

        return $model;
    }

    /**
     * Hydrate an array of data into model instances.
     */
    public function hydrate(array $items): array
    {
        $models = [];
        foreach ($items as $item) {
            $models[] = $this->hydrateModel($item);
        }
        return $models;
    }

    public function first($columns = ['*'])
    {
        // Only set columns if no columns have been explicitly set yet
        if (is_null($this->columns)) {
            $this->columns = is_array($columns) ? $columns : func_get_args();
        }
        $this->limit = 1;

        $this->applyScopes();

        $result = $this->connection->selectOne(
            $this->toSql(), $this->getBindings(), ! $this->useWritePdo
        );

        if ($this->processor) {
            $result = $this->processor->processSelect($this, $result ? [$result] : []);
            $result = $result[0] ?? null;
        }

        // If we have a model set, hydrate the result into a model instance
        if ($result && $this->model) {
            return $this->hydrateModel($result);
        }

        return $result;
    }

    public function find($id, $columns = ['*'])
    {
        if (is_array($id) || $id instanceof \Traversable) {
            return $this->whereIn('id', $id)->get($columns);
        }

        return $this->where('id', '=', $id)->first($columns);
    }

    public function value($column)
    {
        $result = $this->first([$column]);

        if (!$result) {
            return null;
        }

        return $this->extractValueFromResult($result, $column);
    }

    /**
     * Extract a column value from a result
     */
    protected function extractValueFromResult($result, string $column)
    {
        // Handle Model instances
        if ($this->isModel($result)) {
            return $result->getAttribute($column);
        }

        // Handle stdClass objects
        if (is_object($result)) {
            return $result->{$column} ?? null;
        }

        // Handle arrays
        return $result[$column] ?? null;
    }

    /**
     * Check if result is a Model instance
     */
    protected function isModel($result): bool
    {
        return is_object($result) && method_exists($result, 'getAttribute');
    }

    public function pluck($column, ?string $key = null): array
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
        $this->applyScopes();

        // Check if we should use caching
        if ($this->existsCaching && $this->connection->getQueryCache()) {
            $queryCache = $this->connection->getQueryCache();

            // Generate cache key from the SQL and bindings
            $sql = $this->grammar->compileExists($this);
            $bindings = $this->getBindings();
            $cacheKey = 'exists_' . md5($sql . serialize($bindings));

            // Check if we have a cached result
            $cached = $queryCache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            // Execute the query
            $results = $this->connection->select($sql, $bindings);

            // Process the results if we have a processor
            if ($this->processor) {
                $results = $this->processor->processSelect($this, $results);
            }

            // Determine the result
            $exists = $this->parseExistsResult($results);

            // Cache the result
            $queryCache->put($cacheKey, $exists, $this->existsCacheTtl);

            return $exists;
        }

        // No caching - execute normally
        $results = $this->connection->select(
            $this->grammar->compileExists($this), $this->getBindings()
        );

        // Process the results if we have a processor
        if ($this->processor) {
            $results = $this->processor->processSelect($this, $results);
        }

        return $this->parseExistsResult($results);
    }

    /**
     * Parse the result of an exists query.
     *
     * @param  array  $results
     * @return bool
     */
    protected function parseExistsResult(array $results): bool
    {
        // Handle empty result set
        if (empty($results)) {
            return false;
        }

        // Get the first row - it contains the EXISTS result
        $firstRow = $results[0];

        // Handle both array and object results
        if (is_array($firstRow)) {
            return (bool) ($firstRow['exists'] ?? false);
        } elseif (is_object($firstRow)) {
            return (bool) ($firstRow->exists ?? false);
        }

        // Fallback to false if unexpected format
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

    /**
     * Get the count for pagination.
     */
    public function getCountForPagination($columns = ['*']): int
    {
        return (int) $this->aggregate('count', $columns);
    }

    protected function aggregate(string $function, array $columns = ['*'])
    {
        $results = $this->cloneWithout(['columns'])
            ->cloneWithoutBindingsExcept(['select', 'where'])
            ->setAggregate($function, $columns)
            ->get($columns);

        // @codeCoverageIgnoreStart
        if (! $results) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $firstResult = $results[0];

        // Handle both array and object results
        if (is_array($firstResult)) {
            return $firstResult ? ($firstResult['aggregate'] ?? null) : null;
        } else {
            return $firstResult ? ($firstResult->aggregate ?? null) : null;
        }
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

    protected function cloneWithoutBindingsExcept(array $except): self
    {
        $clone = clone $this;

        $bindingTypes = ['select', 'from', 'join', 'where', 'groupBy', 'having', 'order', 'union', 'unionOrder'];

        foreach ($bindingTypes as $type) {
            if (!in_array($type, $except)) {
                $clone->bindings[$type] = [];
            }
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

    public function insertGetId(array $values, ?string $sequence = null)
    {
        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        // Convert associative array to indexed array of values only
        $values = $this->cleanBindings(array_values($values));

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

    /**
     * Insert new records from a subquery.
     */
    public function insertUsing(array $columns, $query): int
    {
        if ($query instanceof \Closure) {
            $query = $query($this->newQuery());
        }

        $sql = $this->grammar->compileInsertUsing($this, $columns, $query);

        return $this->connection->affectingStatement($sql, $this->getBindings());
    }

    public function update(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        $this->applyScopes();

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

    public function delete(mixed $id = null): int
    {
        if (! is_null($id)) {
            $this->where('id', '=', $id);
        }

        $this->applyScopes();

        // Delete only uses WHERE bindings, not all bindings
        return $this->connection->delete(
            $this->grammar->compileDelete($this), $this->getBindings('where')
        );
    }

    /**
     * Insert or update records using MySQL's ON DUPLICATE KEY UPDATE or PostgreSQL's ON CONFLICT.
     */
    public function upsert(array $values, mixed $uniqueBy = null, mixed $update = null): int
    {
        if (empty($values)) {
            return 0;
        }

        // Normalize values
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $sql = $this->grammar->compileUpsert($this, $values, $uniqueBy, $update);

        $bindings = [];
        foreach ($values as $record) {
            foreach ($record as $value) {
                $bindings[] = $value;
            }
        }

        if ($update !== null) {
            foreach ($values[0] as $key => $value) {
                if (in_array($key, (array) $update)) {
                    $bindings[] = $value;
                }
            }
        }

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /**
     * Concatenate values of a given column as a string.
     */
    public function implode(string $column, string $glue = ''): string
    {
        return implode($glue, $this->pluck($column));
    }

    /**
     * Determine if any rows exist for the current query.
     */
    public function existsOr(callable $callback)
    {
        if ($this->exists()) {
            return true;
        }

        return $callback($this);
    }

    /**
     * Determine if no rows exist for the current query.
     */
    public function doesntExistOr(callable $callback)
    {
        if ($this->doesntExist()) {
            return true;
        }

        return $callback($this);
    }

    /**
     * Get the raw array of bindings (structured by type).
     */
    public function getRawBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Constrain the query to the previous "page" of results before a given ID.
     */
    public function forPageBeforeId(int $perPage = 15, ?int $lastId = 0, string $column = 'id'): self
    {
        $this->orders = $this->removeExistingOrdersFor($column);

        if (! is_null($lastId)) {
            $this->where($column, '<', $lastId);
        }

        return $this->orderBy($column, 'desc')
                    ->limit($perPage);
    }

    /**
     * Constrain the query to the next "page" of results after a given ID.
     */
    public function forPageAfterId(int $perPage = 15, ?int $lastId = 0, string $column = 'id'): self
    {
        $this->orders = $this->removeExistingOrdersFor($column);

        if (! is_null($lastId)) {
            $this->where($column, '>', $lastId);
        }

        return $this->orderBy($column, 'asc')
                    ->limit($perPage);
    }

    /**
     * Remove an existing order by column from the query.
     */
    protected function removeExistingOrdersFor(string $column): array
    {
        if (!$this->hasOrders()) {
            return [];
        }

        // @codeCoverageIgnoreStart
        return $this->filterOrdersExcluding($column);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Check if query has orders
     */
    protected function hasOrders(): bool
    {
        return isset($this->orders) && !empty($this->orders);
    }

    /**
     * Filter orders excluding a column
     */
    protected function filterOrdersExcluding(string $column): array
    {
        return array_filter($this->orders ?? [], function ($order) use ($column) {
            return isset($order['column']) && $order['column'] !== $column;
        });
    }

    /**
     * Truncate the table
     *
     * @return bool Returns true if truncation was successful
     */
    public function truncate(): bool
    {
        $sql = $this->grammar->compileTruncate($this);

        $this->executeTruncateStatements($sql);

        return true;
    }

    /**
     * Execute truncate statements
     */
    protected function executeTruncateStatements($sql): void
    {
        if (is_array($sql)) {
            foreach ($sql as $statement => $bindings) {
                $this->connection->statement($statement, $bindings);
            }
        } else {
            $this->connection->statement($sql);
        }
    }

    public function raw($value): ExpressionInterface
    {
        return $this->connection->raw($value);
    }

    public function selectRaw($expression, array $bindings = []): self
    {
        $this->addSelect(new Expression($expression));

        $this->addRawBindings($bindings, 'select');

        return $this;
    }

    /**
     * Add raw bindings if they exist
     */
    protected function addRawBindings(array $bindings, string $type): void
    {
        if (!empty($bindings)) {
            $this->addBinding($bindings, $type);
        }
    }

    public function when($value, callable $callback, ?callable $default = null): self
    {
        return $this->conditionalCall((bool)$value, $value, $callback, $default);
    }

    public function unless($value, callable $callback, ?callable $default = null): self
    {
        return $this->conditionalCall(!$value, $value, $callback, $default);
    }

    /**
     * Execute conditional callback
     */
    protected function conditionalCall(bool $condition, $value, callable $callback, ?callable $default = null): self
    {
        if ($condition) {
            return $callback($this, $value) ?? $this;
        } elseif ($default) {
            return $default($this, $value) ?? $this;
        }

        return $this;
    }

    /**
     * Add a union statement to the query.
     *
     * @param Builder|\\Closure $query
     * @param bool $all
     * @return self
     */
    public function union($query, $all = false): self
    {
        if ($query instanceof \Closure) {
            $query($query = $this->newQuery());
        }

        $this->unions[] = compact('query', 'all');

        $this->addBinding($query->getBindings(), 'union');

        return $this;
    }

    /**
     * Add a union all statement to the query.
     *
     * @param Builder|\\Closure $query
     * @return self
     */
    public function unionAll($query): self
    {
        return $this->union($query, true);
    }

    /**
     * Set the lock value for the query.
     *
     * @param bool|string $value
     * @return self
     */
    public function lock($value = true): self
    {
        $this->lock = $value;

        return $this;
    }

    /**
     * Lock the selected rows in the table for updating.
     *
     * @return self
     */
    public function lockForUpdate(): self
    {
        return $this->lock(true);
    }

    /**
     * Share lock the selected rows in the table.
     *
     * @return self
     */
    public function sharedLock(): self
    {
        return $this->lock(false);
    }

    /**
     * Apply the callback if the given value is truthy.
     * Alias for the "when" method for better readability.
     *
     * @param mixed $value
     * @param callable $callback
     * @return self
     */
    public function tap($callback): self
    {
        $callback($this);

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

    public function whereJsonLength($column, $operator, mixed $value = null, string $boolean = 'and'): self
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

    public function joinSub($query, $as, $first, mixed $operator = null, mixed $second = null, string $type = 'inner'): self
    {
        if ($query instanceof Closure) {
            $subQuery = $this->newQuery();
            $query($subQuery);
            $query = $subQuery;
        }

        // Don't use wrap() on the alias as it might get prefixed
        $expression = '(' . $query->toSql() . ') as `' . str_replace('`', '', $as) . '`';

        $this->addBinding($query->getBindings(), 'join');

        return $this->join(new Expression($expression), $first, $operator, $second, $type);
    }

    public function leftJoinSub($query, $as, $first, mixed $operator = null, mixed $second = null): self
    {
        return $this->joinSub($query, $as, $first, $operator, $second, 'left');
    }

    /**
     * Add a subquery cross join to the query.
     */
    public function crossJoinSub($query, $as): self
    {
        if ($query instanceof Closure) {
            $subQuery = $this->newQuery();
            $query($subQuery);
            $query = $subQuery;
        }

        // Don't use wrap() on the alias as it might get prefixed
        $expression = '(' . $query->toSql() . ') as `' . str_replace('`', '', $as) . '`';

        $this->addBinding($query->getBindings(), 'join');

        $this->joins[] = $this->newJoinClause('cross', new Expression($expression));

        return $this;
    }

    /**
     * Add a subquery right join to the query.
     */
    public function rightJoinSub($query, $as, $first, mixed $operator = null, mixed $second = null): self
    {
        return $this->joinSub($query, $as, $first, $operator, $second, 'right');
    }

    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }

    public function getBindings(?string $type = null): array
    {
        if (is_null($type)) {
            return array_flatten($this->bindings);
        }

        return $this->bindings[$type] ?? [];
    }

    /**
     * Set the bindings on the query builder.
     *
     * @param array $bindings
     * @param string $type
     * @return self
     */
    public function setBindings(array $bindings, $type = 'where'): self
    {
        if (!isset($this->bindings[$type])) {
            $this->bindings[$type] = [];
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * Merge an array of wheres into the current wheres.
     *
     * @param array $wheres
     * @param array $bindings
     * @return self
     */
    public function mergeWheres($wheres, $bindings): self
    {
        $this->wheres = array_merge($this->wheres, (array) $wheres);
        $this->bindings['where'] = array_merge($this->bindings['where'], (array) $bindings);

        return $this;
    }

    /**
     * Get the query builder's grammar instance.
     *
     * @return Grammar
     */
    public function getGrammar()
    {
        return $this->grammar;
    }

    /**
     * Get the query builder's processor instance.
     *
     * @return Processor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * Use the write PDO connection for this query.
     *
     * @return self
     */
    public function useWritePdo(): self
    {
        $this->useWritePdo = true;

        return $this;
    }

    public function addBinding($value, string $type = 'where'): self
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

    public function cleanBindings(array $bindings): array
    {
        $cleaned = [];
        foreach ($bindings as $binding) {
            if (! $binding instanceof ExpressionInterface) {
                $cleaned[] = $binding;
            }
        }
        return $cleaned;
    }

    /**
     * Determine whether the value is a query builder instance or a closure.
     */
    public function isQueryable($value): bool
    {
        return $value instanceof BuilderInterface || $value instanceof \Closure;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Get the model instance being queried.
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set a model instance for the model being queried.
     */
    public function setModel($model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Enable caching for exists() queries.
     *
     * @param  int  $ttl  Cache TTL in seconds
     * @return $this
     */
    public function enableExistsCache(int $ttl = 60): self
    {
        $this->existsCaching = true;
        $this->existsCacheTtl = $ttl;

        return $this;
    }

    /**
     * Disable caching for exists() queries.
     *
     * @return $this
     */
    public function disableExistsCache(): self
    {
        $this->existsCaching = false;

        return $this;
    }

    /**
     * Check if exists caching is enabled.
     *
     * @return bool
     */
    public function isExistsCachingEnabled(): bool
    {
        return $this->existsCaching;
    }

    /**
     * Register a new global scope.
     *
     * @param  string  $identifier
     * @param  \Closure|object  $scope
     * @return $this
     */
    public function addGlobalScope($identifier, $scope): self
    {
        $this->instanceGlobalScopes[$identifier] = $scope;

        return $this;
    }

    /**
     * Remove a registered global scope.
     *
     * @param  string|object  $scope
     * @return $this
     */
    public function withoutGlobalScope($scope): self
    {
        if (! is_string($scope)) {
            $scope = get_class($scope);
        }

        unset($this->instanceGlobalScopes[$scope]);
        $this->removedScopes[] = $scope;

        return $this;
    }

    /**
     * Remove all or passed registered global scopes.
     *
     * @param  array|null  $scopes
     * @return $this
     */
    public function withoutGlobalScopes(?array $scopes = null): self
    {
        if (! is_array($scopes)) {
            $scopes = array_keys($this->instanceGlobalScopes);
        }

        foreach ($scopes as $scope) {
            $this->withoutGlobalScope($scope);
        }

        return $this;
    }

    /**
     * Get the global scopes for this builder instance.
     *
     * @return array
     */
    public function getGlobalScopes(): array
    {
        return $this->instanceGlobalScopes;
    }

    /**
     * Apply the global scopes to the builder.
     *
     * @return $this
     */
    public function applyScopes(): self
    {
        if (! $this->instanceGlobalScopes) {
            return $this;
        }

        foreach ($this->instanceGlobalScopes as $identifier => $scope) {
            if (in_array($identifier, $this->removedScopes, true)) {
                continue;
            }

            if ($scope instanceof Closure) {
                $scope($this);
            } elseif (is_object($scope) && method_exists($scope, 'apply')) {
                $scope->apply($this, $this->getModel());
            }
        }

        return $this;
    }

    /**
     * Set the relationships that should be eager loaded.
     */
    public function with($relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $this->eagerLoad = array_merge($this->eagerLoad, is_array($relations) ? $relations : [$relations]);

        return $this;
    }

    /**
     * Get the relationships being eagerly loaded.
     */
    public function getEagerLoads(): array
    {
        return $this->eagerLoad;
    }

    /**
     * Prevent the specified relations from being eager loaded.
     */
    public function without($relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $relations = is_array($relations) ? $relations : [$relations];

        $this->eagerLoad = array_values(array_diff($this->eagerLoad, $relations));

        return $this;
    }

    /**
     * Set the relationships being eagerly loaded, replacing any existing ones.
     */
    public function withOnly($relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $this->eagerLoad = is_array($relations) ? $relations : [$relations];

        return $this;
    }


    /**
     * Clone the query without any bindings.
     */
    public function cloneWithoutBindings(): self
    {
        return $this->cloneWithoutBindingsExcept([]);
    }

    /**
     * Eager load the relationships for the models.
     */
    protected function eagerLoadRelations(array $models): array
    {
        if (empty($models) || empty($this->eagerLoad)) {
            return $models;
        }

        foreach ($this->eagerLoad as $name => $constraints) {
            // If the relationship name is numeric, it means no constraints
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            $this->eagerLoadRelation($models, $name, $constraints);
        }

        return $models;
    }

    /**
     * Eagerly load a single relationship.
     */
    protected function eagerLoadRelation(array &$models, string $name, mixed $constraints = null): void
    {
        // Get the relation instance from the first model
        $firstModel = $this->getFirstModel($models);

        if (!$firstModel || !method_exists($firstModel, $name)) {
            return;
        }

        // Create a relation instance
        $relation = $firstModel->$name();

        // Load the related models
        $related = $this->loadRelation($relation, $models);

        // Match the related models to their parents
        $this->matchRelated($models, $related, $relation, $name);
    }

    /**
     * Get the first model from the array.
     */
    protected function getFirstModel(array $models)
    {
        if (empty($models) || !$this->model) {
            return null;
        }

        return reset($models);
    }

    /**
     * Get the relationship loader instance
     */
    protected function getRelationshipLoader(): RelationshipLoader
    {
        return new RelationshipLoader();
    }

    /**
     * Load the related models for the relation.
     */
    protected function loadRelation($relation, array $models): array
    {
        return $this->getRelationshipLoader()->loadRelated($relation, $models);
    }

    /**
     * Get the keys from the models.
     */
    protected function getKeys(array $models, string $key): array
    {
        $keys = [];

        foreach ($models as $model) {
            $keys[] = $model->getAttribute($key);
        }

        return array_filter($keys, function ($key) {
            return !is_null($key);
        });
    }

    /**
     * Match the related models to their parents.
     */
    protected function matchRelated(array &$models, array $related, $relation, string $name): void
    {
        $this->getRelationshipLoader()->matchRelated($models, $related, $relation, $name);
    }

    /**
     * Build a dictionary of related models keyed by their foreign key.
     * @deprecated Use RelationshipLoader instead
     */
    protected function buildDictionary(array $related, $relation): array
    {
        $dictionary = [];
        $relationClass = get_class($relation);

        // For BelongsTo, we key by the owner key, not the foreign key
        if (strpos($relationClass, 'BelongsTo') !== false && strpos($relationClass, 'BelongsToMany') === false) {
            $keyName = basename(str_replace('\\', '/', $relation->getOwnerKeyName()));
            foreach ($related as $item) {
                $key = $item->getAttribute($keyName);
                $dictionary[$key] = $item;
            }
        } else {
            // For HasOne and HasMany, we key by the foreign key
            // Get just the column name without table prefix
            $foreignKey = basename(str_replace('\\', '/', $relation->getForeignKeyName()));
            if (strpos($foreignKey, '.') !== false) {
                $foreignKey = substr($foreignKey, strrpos($foreignKey, '.') + 1);
            }

            foreach ($related as $item) {
                $key = $item->getAttribute($foreignKey);

                if (strpos($relationClass, 'HasMany') !== false) {
                    $dictionary[$key][] = $item;
                } else {
                    $dictionary[$key] = $item;
                }
            }
        }

        return $dictionary;
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
     * Check if a string contains an aggregate function
     *
     * @param string $column
     * @return bool
     */
    protected function isAggregateFunction(string $column): bool
    {
        // Common SQL aggregate functions
        $aggregateFunctions = [
            'COUNT',
            'SUM',
            'AVG',
            'MIN',
            'MAX',
            'GROUP_CONCAT',
            'STRING_AGG',
            'ARRAY_AGG',
            'JSON_AGG',
            'STDDEV',
            'VARIANCE',
        ];

        // Remove extra spaces and convert to uppercase for comparison
        $normalizedColumn = preg_replace('/\s+/', ' ', strtoupper(trim($column)));

        foreach ($aggregateFunctions as $function) {
            // Check for function with optional space before parenthesis
            // This regex matches: FUNCTION( or FUNCTION (
            $pattern = '/\b' . preg_quote($function, '/') . '\s*\(/';
            if (preg_match($pattern, $normalizedColumn)) {
                return true;
            }
        }

        return false;
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
        // Check for dynamic where methods (whereName, whereOrEmail, whereAndAge)
        if (preg_match('/^where(Or|And)?(.+)$/', $method, $matches)) {
            $boolean = strtolower($matches[1] ?: 'and');
            // Convert StudlyCase to snake_case
            $column = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[2] ?? ''));
            if ($column) {
                return $this->where($column, '=', $parameters[0] ?? null, $boolean);
            }
        }

        // First, check for dynamic finders (findBySlug, whereByStatus, etc.)
        if (method_exists($this, 'handleDynamicFinder')) {
            $result = $this->handleDynamicFinder($method, $parameters);
            if ($result !== null) {
                return $result;
            }
        }

        // Then check for local scopes
        // @codeCoverageIgnoreStart
        if (static::hasScope($method)) {
            return $this->withScope($method, ...$parameters);
        }
        // @codeCoverageIgnoreEnd

        // Then check for macros
        if (static::hasMacro($method)) {
            return $this->invokeMacro($method, $parameters);
        }

        // Check if model has a scope method
        if ($this->model !== null) {
            $scopeMethod = 'scope' . ucfirst($method);
            if (method_exists($this->model, $scopeMethod)) {
                // Call the scope method on the model, passing this builder
                return $this->model->$scopeMethod($this, ...$parameters);
            }
        }

        // Finally, throw an exception
        throw new BadMethodCallException(sprintf(
            'Method %s::%s does not exist.',
            static::class,
            $method
        ));
    }

    /**
     * Invoke a macro
     */
    protected function invokeMacro(string $method, array $parameters)
    {
        $macro = static::$macros[$method];
        if ($macro instanceof Closure) {
            $macro = $macro->bindTo($this, static::class);
        }

        return $macro(...$parameters);
    }

    /**
     * Create a new query instance with global scopes applied.
     */
    public function withGlobalScopes(): self
    {
        $query = $this->cloneForScopes();
        $query->applyGlobalScopes();

        return $query;
    }

    /**
     * Clone the query for applying scopes
     */
    protected function cloneForScopes(): self
    {
        return clone $this;
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

    public mixed $table; // Can be string or Expression

    public function __construct(Builder $parentQuery, string $type, mixed $table)
    {
        parent::__construct($parentQuery->connection);

        $this->type = $type;
        $this->table = $table;
    }

    public function on($first, mixed $operator = null, mixed $second = null, string $boolean = 'and'): self
    {
        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    public function orOn($first, mixed $operator = null, mixed $second = null): self
    {
        return $this->on($first, $operator, $second, 'or');
    }

}
