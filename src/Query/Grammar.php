<?php

namespace Bob\Query;

use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ExpressionInterface;
use Bob\Contracts\GrammarInterface;

abstract class Grammar implements GrammarInterface
{
    protected string $tablePrefix = '';
    protected array $operators = [];

    public function compileSelect(BuilderInterface $query): string
    {
        if ($query->unions && $query->aggregate) {
            return $this->compileUnionAggregate($query);
        }

        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $sql = trim($this->concatenate(
            $this->compileComponents($query)
        ));

        $query->columns = $original;

        return $sql;
    }

    protected function compileComponents(BuilderInterface $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (!is_null($query->$component)) {
                $method = 'compile' . ucfirst($component);
                $sql[$component] = $this->$method($query, $query->$component);
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

        if ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }

        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }

    protected function compileColumns(BuilderInterface $query, array $columns): string
    {
        if (!is_null($query->aggregate)) {
            return '';
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        return $select . $this->columnize($columns);
    }

    protected function compileFrom(BuilderInterface $query, string $table): string
    {
        return 'from ' . $this->wrapTable($table);
    }

    protected function compileJoins(BuilderInterface $query, array $joins): string
    {
        $sql = [];

        foreach ($joins as $join) {
            $table = $this->wrapTable($join->table);

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
            $constraint = $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
            if ($where['boolean'] !== 'and' || !empty($where['previous'])) {
                return $where['boolean'] . ' ' . $constraint;
            }
            return $constraint;
        }

        return $this->{"where{$where['type']}"}($query ?? new Builder($this->connection ?? null), $where);
    }

    protected function compileGroups(BuilderInterface $query, array $groups): string
    {
        return 'group by ' . $this->columnize($groups);
    }

    protected function compileHavings(BuilderInterface $query, array $havings): string
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having ' . $this->removeLeadingBoolean($sql);
    }

    protected function compileHaving(array $having): string
    {
        if ($having['type'] === 'Raw') {
            return $having['boolean'] . ' ' . $having['sql'];
        }

        return $this->compileBasicHaving($having);
    }

    protected function compileBasicHaving(array $having): string
    {
        $column = $this->wrap($having['column']);
        $parameter = $this->parameter($having['value']);

        return $having['boolean'] . ' ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
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
                $sql[] = $this->wrap($order['column']) . ' ' . $order['direction'];
            }
        }

        return 'order by ' . implode(', ', $sql);
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

        foreach ($query->unions as $union) {
            $sql .= $this->compileUnion($union);
        }

        if (!empty($query->unionOrders)) {
            $sql .= ' ' . $this->compileOrders($query, $query->unionOrders);
        }

        if (isset($query->unionLimit)) {
            $sql .= ' ' . $this->compileLimit($query, $query->unionLimit);
        }

        if (isset($query->unionOffset)) {
            $sql .= ' ' . $this->compileOffset($query, $query->unionOffset);
        }

        return ltrim($sql);
    }

    protected function compileUnion(array $union): string
    {
        $conjunction = $union['all'] ? ' union all ' : ' union ';

        return $conjunction . '(' . $union['query']->toSql() . ')';
    }

    protected function compileWheres(BuilderInterface $query): string
    {
        if (empty($query->wheres)) {
            return '';
        }

        $sql = [];

        foreach ($query->wheres as $where) {
            $method = "where{$where['type']}";
            $sql[] = $where['boolean'] . ' ' . $this->$method($query, $where);
        }

        return 'where ' . $this->removeLeadingBoolean(implode(' ', $sql));
    }

    protected function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    protected function whereBasic(BuilderInterface $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ' . $value;
    }

    protected function whereIn(BuilderInterface $query, array $where): string
    {
        if (!empty($where['values'])) {
            if (is_array($where['values'])) {
                return $this->wrap($where['column']) . ' in (' . $this->parameterize($where['values']) . ')';
            }
            return $this->wrap($where['column']) . ' in (' . $this->compileSelect($where['values']) . ')';
        }

        return '0 = 1';
    }

    protected function whereInSub(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']) . ' in (' . $this->compileSelect($where['query']) . ')';
    }

    protected function whereNotInSub(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']) . ' not in (' . $this->compileSelect($where['query']) . ')';
    }

    protected function whereNotIn(BuilderInterface $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . $this->parameterize($where['values']) . ')';
        }

        return '1 = 1';
    }

    protected function whereNull(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is null';
    }

    protected function whereNotNull(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is not null';
    }

    protected function whereBetween(BuilderInterface $query, array $where): string
    {
        $between = $where['not'] ?? false ? 'not between' : 'between';

        return $this->wrap($where['column']) . ' ' . $between . ' ? and ?';
    }

    protected function whereNotBetween(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']) . ' not between ? and ?';
    }

    protected function whereRaw(BuilderInterface $query, array $where): string
    {
        return $where['sql'];
    }

    protected function whereExists(BuilderInterface $query, array $where): string
    {
        return 'exists (' . $this->compileSelect($where['query']) . ')';
    }

    protected function whereNotExists(BuilderInterface $query, array $where): string
    {
        return 'not exists (' . $this->compileSelect($where['query']) . ')';
    }

    protected function whereNested(BuilderInterface $query, array $where): string
    {
        $offset = $query === $where['query'] ? 6 : 0;

        return '(' . substr($this->compileWheres($where['query']), $offset) . ')';
    }

    protected function whereSub(BuilderInterface $query, array $where): string
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . " ($select)";
    }

    protected function whereColumn(BuilderInterface $query, array $where): string
    {
        return $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
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

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));
        $parameters = collect($values)->map(function ($record) {
            return '(' . $this->parameterize($record) . ')';
        })->implode(', ');

        return "insert into {$table} ({$columns}) values {$parameters}";
    }

    public function parameterize(array $values): string
    {
        return implode(', ', array_map([$this, 'parameter'], $values));
    }

    public function compileInsertGetId(BuilderInterface $query, array $values, string $sequence = null): string
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
            return $this->wrap($key) . ' = ' . $this->parameter($value);
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
        return ['truncate table ' . $this->wrapTable($query->from) => []];
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

        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedValue($value);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    protected function wrapAliasedValue(string $value): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        return $this->wrap($segments[0]) . ' as ' . $this->wrapValue($segments[1]);
    }

    protected function wrapSegments(array $segments): string
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return $key == 0 && count($segments) > 1
                ? $this->wrapTable($segment)
                : $this->wrapValue($segment);
        })->implode('.');
    }

    protected function wrapValue(string $value): string
    {
        if ($value !== '*') {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    public function wrapArray(array $values): array
    {
        return array_map([$this, 'wrap'], $values);
    }

    public function wrapTable($table): string
    {
        if (!$this->isExpression($table)) {
            return $this->wrap($this->tablePrefix . $table);
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
        return 'SAVEPOINT ' . $name;
    }

    public function compileSavepointRollBack(string $name): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

    protected function compileDateBasedWhere(string $type, BuilderInterface $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ' . $value;
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