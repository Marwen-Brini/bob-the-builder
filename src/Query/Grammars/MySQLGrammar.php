<?php

namespace Bob\Query\Grammars;

use Bob\Contracts\BuilderInterface;
use Bob\Query\Grammar;

class MySQLGrammar extends Grammar
{
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'not rlike', 'regexp', 'not regexp',
    ];

    protected function wrapValue(string $value): string
    {
        if ($value !== '*') {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        return $value;
    }

    public function compileInsertOrIgnore(BuilderInterface $query, array $values): string
    {
        return str_replace('insert', 'insert ignore', $this->compileInsert($query, $values));
    }

    protected function compileJsonLength(string $column, string $operator, string $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'json_length(' . $field . $path . ') ' . $operator . ' ' . $value;
    }

    protected function wrapJsonFieldAndPath(string $column): array
    {
        $parts = explode('->', $column, 2);

        $field = $this->wrap($parts[0]);
        $path = count($parts) > 1 ? ', ' . $this->wrapJsonPath($parts[1]) : '';

        return [$field, $path];
    }

    protected function wrapJsonPath(string $value): string
    {
        return '\'$.' . str_replace('->', '.', $value) . '\'';
    }

    public function compileUpsert(BuilderInterface $query, array $values, array $uniqueBy, array $update): string
    {
        $sql = $this->compileInsert($query, $values);

        $sql .= ' on duplicate key update ';

        $columns = collect($update)->map(function ($value, $key) {
            return $this->wrap($key) . ' = values(' . $this->wrap($key) . ')';
        })->implode(', ');

        return $sql . $columns;
    }

    public function compileLock(BuilderInterface $query, $value): string
    {
        if (!is_string($value)) {
            return $value ? ' for update' : ' lock in share mode';
        }

        return $value;
    }

    public function compileRandom(string $seed = ''): string
    {
        return 'RAND(' . $seed . ')';
    }
}