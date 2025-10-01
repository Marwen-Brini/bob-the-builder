<?php

namespace Bob\Query;

use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ExpressionInterface;
use Bob\Contracts\GrammarInterface;

abstract class Grammar implements GrammarInterface
{
    protected string $tablePrefix = '';

    protected array $operators = [];

    /**
     * Track table aliases to avoid prefixing them
     */
    protected array $tableAliases = [];

    /**
     * Track joined table names to avoid double prefixing in WHERE clauses
     */
    protected array $joinedTables = [];

    public function compileSelect(BuilderInterface $query): string
    {
        // Extract aliases from the query for reference
        $this->extractAliases($query);

        if ($query->getUnions() && $query->getAggregate()) {
            return $this->compileUnionAggregate($query);
        }

        $sql = trim($this->concatenate(
            $this->compileComponents($query)
        ));

        return $sql;
    }

    /**
     * Extract table aliases and joined tables from the query to avoid prefixing them
     */
    protected function extractAliases(BuilderInterface $query): void
    {
        $this->tableAliases = [];
        $this->joinedTables = [];

        // Extract from FROM clause - it might be stored as "table as alias"
        $from = $query->getFrom();
        if ($from) {
            if (strpos($from, ' as ') !== false) {
                $parts = preg_split('/\s+as\s+/i', $from);
                if (isset($parts[1])) {
                    $this->tableAliases[] = trim($parts[1], '`"\' ');
                }
            }
        }

        // Also check if the FROM was set with from('table', 'alias') which gets stored differently
        // This is handled in Builder's from() method

        // Extract from JOIN clauses
        $joins = $query->getJoins();
        if ($joins) {
            foreach ($joins as $join) {
                $table = $join->table;

                // Handle table with alias
                if (strpos($table, ' as ') !== false) {
                    $parts = preg_split('/\s+as\s+/i', $table);
                    if (isset($parts[0])) {
                        // Track the actual table name (without prefix)
                        $this->joinedTables[] = trim($parts[0], '`"\' ');
                    }
                    if (isset($parts[1])) {
                        // Track the alias
                        $this->tableAliases[] = trim($parts[1], '`"\' ');
                    }
                } else {
                    // Regular table without alias - track it to avoid double prefixing
                    $this->joinedTables[] = trim($table, '`"\' ');
                }
            }
        }
    }

    protected function compileComponents(BuilderInterface $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            $getter = 'get'.ucfirst($component);
            if (method_exists($query, $getter)) {
                $value = $query->$getter();

                // Special handling for columns - default to ['*'] if null
                if ($component === 'columns' && is_null($value)) {
                    $value = ['*'];
                }

                if (! is_null($value) && (! is_array($value) || ! empty($value))) {
                    $method = 'compile'.ucfirst($component);
                    $sql[$component] = $this->$method($query, $value);
                }
            }
        }

        return $sql;
    }

    protected array $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'unions',
        'lock',
    ];

    protected function concatenate(array $segments): string
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    protected function compileAggregate(BuilderInterface $query, array $aggregate): string
    {
        $column = $this->columnize($aggregate['columns']);

        if ($query->getDistinct() && $column !== '*') {
            $column = 'distinct '.$column;
        }

        return 'select '.$aggregate['function'].'('.$column.') as aggregate';
    }

    protected function compileColumns(BuilderInterface $query, array $columns): string
    {
        if (! is_null($query->getAggregate())) {
            return '';
        }

        $select = $query->getDistinct() ? 'select distinct ' : 'select ';

        return $select.$this->columnize($columns);
    }

    protected function compileFrom(BuilderInterface $query, string $table): string
    {
        return 'from '.$this->wrapTable($table);
    }

    protected function compileJoins(BuilderInterface $query, array $joins): string
    {
        $sql = [];

        foreach ($joins as $join) {
            // Check if the table is an Expression object
            if ($this->isExpression($join->table)) {
                $table = $this->getValue($join->table);
                // Extract alias from expression like "(subquery) as alias"
                if (preg_match('/\s+as\s+`?([^`]+)`?$/i', $table, $matches)) {
                    $this->tableAliases[] = $matches[1];
                }
            } else {
                $table = $this->wrapTable($join->table);
                // Check for alias in regular table names like "table as alias"
                if (strpos($join->table, ' as ') !== false) {
                    $parts = preg_split('/\s+as\s+/i', $join->table);
                    if (count($parts) === 2) {
                        $this->tableAliases[] = trim($parts[1], '`');
                    }
                }
            }

            $clauses = [];

            foreach ($join->wheres as $where) {
                $clauses[] = $this->compileJoinConstraint($where);
            }

            $clauses = implode(' ', $clauses);

            $type = $join->type;

            $sql[] = "$type join $table on $clauses";
        }

        return implode(' ', $sql);
    }

    protected function compileJoinConstraint(array $where): string
    {
        if ($where['type'] === 'Column') {
            $constraint = $this->wrap($where['first']).' '.$where['operator'].' '.$this->wrap($where['second']);
            if ($where['boolean'] !== 'and' || ! empty($where['previous'])) {
                return $where['boolean'].' '.$constraint;
            }

            return $constraint;
        }

        // Handle Basic where constraints in joins
        if ($where['type'] === 'Basic') {
            $constraint = $this->wrap($where['column']).' '.$where['operator'].' ?';

            // Always include the boolean for non-first constraints
            return $where['boolean'].' '.$constraint;
        }

        // For other types, try to compile them
        if (isset($where['sql'])) {
            return $where['boolean'].' '.$where['sql'];
        }

        // Default - this shouldn't normally happen
        // @codeCoverageIgnoreStart
        return '';
        // @codeCoverageIgnoreEnd
    }

    protected function compileGroups(BuilderInterface $query, array $groups): string
    {
        return 'group by '.$this->columnize($groups);
    }

    protected function compileHavings(BuilderInterface $query, array $havings): string
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having '.$this->removeLeadingBoolean($sql);
    }

    protected function compileHaving(array $having): string
    {
        if ($having['type'] === 'Raw') {
            return $having['boolean'].' '.$having['sql'];
        }

        return $this->compileBasicHaving($having);
    }

    protected function compileBasicHaving(array $having): string
    {
        $column = $this->wrap($having['column']);
        $parameter = $this->parameter($having['value']);

        return $having['boolean'].' '.$column.' '.$having['operator'].' '.$parameter;
    }

    protected function compileOrders(BuilderInterface $query, array $orders): string
    {
        if (empty($orders)) {
            return '';
        }

        $sql = [];

        foreach ($orders as $order) {
            if (isset($order['type']) && $order['type'] === 'Raw') {
                $sql[] = $order['sql'];
            } else {
                $sql[] = $this->wrap($order['column']).' '.$order['direction'];
            }
        }

        return 'order by '.implode(', ', $sql);
    }

    protected function compileLimit(BuilderInterface $query, int $limit): string
    {
        return "limit $limit";
    }

    protected function compileOffset(BuilderInterface $query, int $offset): string
    {
        return "offset $offset";
    }

    protected function compileUnions(BuilderInterface $query): string
    {
        $sql = '';

        foreach ($query->getUnions() as $union) {
            $sql .= $this->compileUnion($union);
        }

        if (! empty($query->getUnionOrders())) {
            $sql .= ' '.$this->compileOrders($query, $query->getUnionOrders());
        }

        if (! is_null($query->getUnionLimit())) {
            $sql .= ' '.$this->compileLimit($query, $query->getUnionLimit());
        }

        if (! is_null($query->getUnionOffset())) {
            $sql .= ' '.$this->compileOffset($query, $query->getUnionOffset());
        }

        return ltrim($sql);
    }

    protected function compileUnion(array $union): string
    {
        $conjunction = $union['all'] ? ' union all ' : ' union ';

        return $conjunction.'('.$union['query']->toSql().')';
    }

    protected function compileWheres(BuilderInterface $query): string
    {
        if (empty($query->wheres)) {
            return '';
        }

        $sql = [];

        foreach ($query->wheres as $where) {
            $method = "where{$where['type']}";
            $sql[] = $where['boolean'].' '.$this->$method($query, $where);
        }

        return 'where '.$this->removeLeadingBoolean(implode(' ', $sql));
    }

    protected function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    protected function whereBasic(BuilderInterface $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']).' '.$where['operator'].' '.$value;
    }

    protected function whereIn(BuilderInterface $query, array $where): string
    {
        if (! empty($where['values'])) {
            if (is_array($where['values'])) {
                return $this->wrap($where['column']).' in ('.$this->parameterize($where['values']).')';
            }

            return $this->wrap($where['column']).' in ('.$this->compileSelect($where['values']).')';
        }

        return '0 = 1';
    }

    protected function whereInSub(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']).' in ('.$this->compileSelect($where['query']).')';
    }

    protected function whereNotInSub(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']).' not in ('.$this->compileSelect($where['query']).')';
    }

    protected function whereNotIn(BuilderInterface $query, array $where): string
    {
        if (! empty($where['values'])) {
            return $this->wrap($where['column']).' not in ('.$this->parameterize($where['values']).')';
        }

        return '1 = 1';
    }

    protected function whereNull(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']).' is null';
    }

    protected function whereNotNull(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']).' is not null';
    }

    protected function whereBetween(BuilderInterface $query, array $where): string
    {
        $between = $where['not'] ?? false ? 'not between' : 'between';

        return $this->wrap($where['column']).' '.$between.' ? and ?';
    }

    protected function whereNotBetween(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']).' not between ? and ?';
    }

    protected function whereRaw(BuilderInterface $query, array $where): string
    {
        return $where['sql'];
    }

    protected function whereExists(BuilderInterface $query, array $where): string
    {
        return 'exists ('.$this->compileSelect($where['query']).')';
    }

    protected function whereNotExists(BuilderInterface $query, array $where): string
    {
        return 'not exists ('.$this->compileSelect($where['query']).')';
    }

    protected function whereNested(BuilderInterface $query, array $where): string
    {
        // The nested query ($where['query']) is always a separate query instance created
        // by whereNested() using newQuery(). Since compileWheres() always returns
        // 'where ' + conditions, we need to strip the 'where ' prefix (6 characters)
        // when creating the parenthesized group for nested conditions.
        $compiled = $this->compileWheres($where['query']);

        // Defensive check: compileWheres returns empty string if no wheres
        // This shouldn't happen as addNestedWhereQuery checks for wheres, but better safe
        // @codeCoverageIgnoreStart
        if ($compiled === '') {
            return '()';
        }
        // @codeCoverageIgnoreEnd

        // Remove the 'where ' prefix (6 characters) since we're nesting this in parentheses
        return '('.substr($compiled, 6).')';
    }

    protected function whereColumn(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['first']).' '.$where['operator'].' '.$this->wrap($where['second']);
    }

    protected function whereJsonContains(BuilderInterface $query, array $where): string
    {
        $column = $this->wrap($where['column']);
        $not = isset($where['not']) && $where['not'] ? 'not ' : '';

        return 'json_contains('.$column.', ?, \'$\')';
    }

    protected function whereJsonNotContains(BuilderInterface $query, array $where): string
    {
        return 'not json_contains('.$this->wrap($where['column']).', ?, \'$\')';
    }

    protected function whereJsonLength(BuilderInterface $query, array $where): string
    {
        $column = $this->wrap($where['column']);

        return 'json_length('.$column.') '.$where['operator'].' ?';
    }

    protected function whereFulltext(BuilderInterface $query, array $where): string
    {
        $columns = array_map([$this, 'wrap'], $where['columns']);

        return 'match ('.implode(',', $columns).') against (? in boolean mode)';
    }

    protected function whereDate(BuilderInterface $query, array $where): string
    {
        return 'date('.$this->wrap($where['column']).') '.$where['operator'].' ?';
    }

    protected function whereTime(BuilderInterface $query, array $where): string
    {
        return 'time('.$this->wrap($where['column']).') '.$where['operator'].' ?';
    }

    protected function whereDay(BuilderInterface $query, array $where): string
    {
        return 'day('.$this->wrap($where['column']).') '.$where['operator'].' ?';
    }

    protected function whereMonth(BuilderInterface $query, array $where): string
    {
        return 'month('.$this->wrap($where['column']).') '.$where['operator'].' ?';
    }

    protected function whereYear(BuilderInterface $query, array $where): string
    {
        return 'year('.$this->wrap($where['column']).') '.$where['operator'].' ?';
    }

    protected function whereSub(BuilderInterface $query, array $where): string
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']).' '.$where['operator']." ($select)";
    }

    public function parameter($value): string
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    public function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    public function compileInsert(BuilderInterface $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "insert into {$table} default values";
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));
        $parameters = collect($values)->map(function ($record) {
            return '('.$this->parameterize($record).')';
        })->implode(', ');

        return "insert into {$table} ({$columns}) values {$parameters}";
    }

    public function parameterize(array $values): string
    {
        return implode(', ', array_map([$this, 'parameter'], $values));
    }

    public function compileInsertGetId(BuilderInterface $query, array $values, ?string $sequence = null): string
    {
        return $this->compileInsert($query, $values);
    }

    public function compileInsertOrIgnore(BuilderInterface $query, array $values): string
    {
        return $this->compileInsert($query, $values);
    }

    public function compileUpdate(BuilderInterface $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        $columns = collect($values)->map(function ($value, $key) {
            return $this->wrap($key).' = '.$this->parameter($value);
        })->implode(', ');

        $wheres = $this->compileWheres($query);

        return trim("update {$table} set {$columns} {$wheres}");
    }

    public function compileDelete(BuilderInterface $query): string
    {
        $table = $this->wrapTable($query->from);

        $wheres = $this->compileWheres($query);

        return trim("delete from {$table} {$wheres}");
    }

    public function compileTruncate(BuilderInterface $query): array
    {
        return ['truncate table '.$this->wrapTable($query->getFrom()) => []];
    }

    public function compileExists(BuilderInterface $query): string
    {
        $select = $this->compileSelect($query);

        return "select exists({$select}) as {$this->wrap('exists')}";
    }

    public function wrap($value): string
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        // Handle null or empty values
        if ($value === null || $value === '') {
            return '';
        }

        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedValue($value);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    protected function wrapAliasedValue(string $value): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        return $this->wrap($segments[0]).' as '.$this->wrapValue($segments[1]);
    }

    protected function wrapSegments(array $segments): string
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            // First segment in multi-segment identifier (could be table or alias)
            if ($key == 0 && count($segments) > 1) {
                // Check if this is a known alias - if so, don't prefix it
                if (in_array($segment, $this->tableAliases)) {
                    return $this->wrapValue($segment);
                }

                // Check if this is a joined table - if so, it already has the prefix from JOIN clause
                if (in_array($segment, $this->joinedTables)) {
                    // The table was joined, so it already has the prefix applied
                    // Check if it already starts with the prefix to avoid double-prefixing
                    if ($this->tablePrefix && strpos($segment, $this->tablePrefix) === 0) {
                        // Already has prefix, just wrap it
                        return $this->wrapValue($segment);
                    } else {
                        // Doesn't have prefix yet, add it
                        return $this->wrapValue($this->tablePrefix.$segment);
                    }
                }

                // Otherwise, it's a regular table reference that needs prefixing
                return $this->wrapTable($segment);
            }

            return $this->wrapValue($segment);
        })->implode('.');
    }

    protected function wrapValue(string $value): string
    {
        if ($value !== '*') {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    public function wrapArray(array $values): array
    {
        return array_map([$this, 'wrap'], $values);
    }

    public function wrapTable($table): string
    {
        if (! $this->isExpression($table)) {
            // Check if table already has the prefix to avoid double-prefixing (simple case)
            if ($this->tablePrefix && ! strpos($table, '.') && strpos($table, $this->tablePrefix) === 0) {
                return $this->wrap($table);
            }

            // Handle database.table format
            if (strpos($table, '.') !== false) {
                $segments = explode('.', $table);

                // Check if the last segment (table name) already has the prefix
                $lastIndex = count($segments) - 1;
                if ($this->tablePrefix && strpos($segments[$lastIndex], $this->tablePrefix) !== 0) {
                    // Add prefix to table name
                    $segments[$lastIndex] = $this->tablePrefix.$segments[$lastIndex];
                }

                // Manually wrap each segment to avoid recursion issues
                $wrapped = [];
                foreach ($segments as $i => $segment) {
                    $wrapped[] = $this->wrapValue($segment);
                }

                return implode('.', $wrapped);
            }

            // Simple table name - add prefix and wrap
            return $this->wrap($this->tablePrefix.$table);
        }

        return $this->getValue($table);
    }

    public function getValue($expression)
    {
        if ($expression instanceof ExpressionInterface) {
            return $expression->getValue();
        }

        return $expression;
    }

    public function isExpression($value): bool
    {
        return $value instanceof ExpressionInterface;
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    public function setTablePrefix(string $prefix): self
    {
        $this->tablePrefix = $prefix;

        return $this;
    }

    public function supportsSavepoints(): bool
    {
        return true;
    }

    public function compileSavepoint(string $name): string
    {
        return 'SAVEPOINT '.$name;
    }

    public function compileSavepointRollBack(string $name): string
    {
        return 'ROLLBACK TO SAVEPOINT '.$name;
    }

    protected function compileDateBasedWhere(string $type, BuilderInterface $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']).' '.$where['operator'].' '.$value;
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    public function compileLock(BuilderInterface $query, $value): string
    {
        return '';
    }

    public function compileRandom(string $seed = ''): string
    {
        return 'RANDOM()';
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    public function supportsJsonOperations(): bool
    {
        return false;
    }

    public function getOperators(): array
    {
        return $this->operators;
    }
}
