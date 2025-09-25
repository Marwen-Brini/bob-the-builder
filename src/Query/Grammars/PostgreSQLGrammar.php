<?php

namespace Bob\Query\Grammars;

use Bob\Contracts\BuilderInterface;
use Bob\Query\Grammar;
use function Bob\Query\collect;

class PostgreSQLGrammar extends Grammar
{
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'ilike', 'not ilike',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', '~~*', '!~~*',
        '&', '|', '#', '<<', '>>', '<<=', '>>=',
        '&&', '@>', '<@', '?', '?|', '?&', '||', '-', '@?', '@@', '#-',
        'is distinct from', 'is not distinct from',
    ];

    public function compileInsertGetId(BuilderInterface $query, array $values, ?string $sequence = null): string
    {
        return $this->compileInsert($query, $values).' returning '.($sequence ?: 'id');
    }

    public function compileInsertOrIgnore(BuilderInterface $query, array $values): string
    {
        return $this->compileInsert($query, $values).' on conflict do nothing';
    }

    public function compileUpsert(BuilderInterface $query, array $values, ?array $uniqueBy, ?array $update): string
    {
        $sql = $this->compileInsert($query, $values);

        $sql .= ' on conflict ('.$this->columnize($uniqueBy).') do update set ';

        $columns = collect($update)->map(function ($column) {
            return $this->wrap($column).' = excluded.'.$this->wrap($column);
        })->implode(', ');

        return $sql.$columns;
    }

    public function compileLock(BuilderInterface $query, $value): string
    {
        if (! is_string($value)) {
            return $value ? ' for update' : ' for share';
        }

        return $value;
    }

    protected function compileDateBasedWhere(string $type, BuilderInterface $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        return match ($type) {
            'Day' => 'extract(day from '.$this->wrap($where['column']).') '.$where['operator'].' '.$value,
            'Month' => 'extract(month from '.$this->wrap($where['column']).') '.$where['operator'].' '.$value,
            'Year' => 'extract(year from '.$this->wrap($where['column']).') '.$where['operator'].' '.$value,
            'Date' => $this->wrap($where['column']).'::date '.$where['operator'].' '.$value,
            'Time' => $this->wrap($where['column']).'::time '.$where['operator'].' '.$value,
            default => parent::compileDateBasedWhere($type, $query, $where),
        };
    }

    protected function compileJsonLength(string $column, string $operator, string $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPathUnescaped($column);

        return 'jsonb_array_length('.$field.$path.') '.$operator.' '.$value;
    }

    protected function wrapJsonFieldAndPath(string $column): array
    {
        $parts = explode('->', $column, 2);

        $field = $this->wrap($parts[0]);

        if (count($parts) > 1) {
            $path = '->'.collect(explode('->', $parts[1]))->map(function ($part) {
                return '\\\''.$part.'\\\'';
            })->implode('->');
        } else {
            $path = '';
        }

        return [$field, $path];
    }

    protected function wrapJsonFieldAndPathUnescaped(string $column): array
    {
        $parts = explode('->', $column, 2);

        $field = $this->wrap($parts[0]);

        if (count($parts) > 1) {
            $path = '->'.collect(explode('->', $parts[1]))->map(function ($part) {
                return "'".$part."'";
            })->implode('->');
        } else {
            $path = '';
        }

        return [$field, $path];
    }

    public function compileRandom(string $seed = ''): string
    {
        return 'random()';
    }

    public function compileTruncate(BuilderInterface $query): array
    {
        return ['truncate '.$this->wrapTable($query->getFrom()).' restart identity cascade' => []];
    }

    public function compileJsonContains(string $column, string $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPathUnescaped($column);

        return $field.$path.' @> '.$value;
    }

    public function compileJsonContainsKey(string $column): string
    {
        $segments = explode('->', $column);

        $field = $this->wrap(array_shift($segments));
        $path = count($segments) ? '->'.collect($segments)->map(fn($segment) => "'".$segment."'")->implode('->') : '';

        return $field.$path.' is not null';
    }

    public function supportsReturning(): bool
    {
        return true;
    }

    public function supportsJsonOperations(): bool
    {
        return true;
    }
}
