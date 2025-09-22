<?php

namespace Bob\Query\Grammars;

use Bob\Contracts\BuilderInterface;
use Bob\Query\Grammar;

class SQLiteGrammar extends Grammar
{
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'ilike',
        '&', '|', '<<', '>>',
    ];

    public function compileInsertOrIgnore(BuilderInterface $query, array $values): string
    {
        return str_replace('insert', 'insert or ignore', $this->compileInsert($query, $values));
    }

    public function compileTruncate(BuilderInterface $query): array
    {
        return [
            'delete from sqlite_sequence where name = ?' => [$query->getFrom()],
            'delete from '.$this->wrapTable($query->getFrom()) => [],
        ];
    }

    public function compileLock(BuilderInterface $query, $value): string
    {
        return '';
    }

    protected function wrapUnion(string $sql): string
    {
        return 'select * from ('.$sql.')';
    }

    public function compileUpsert(BuilderInterface $query, array $values, array $uniqueBy, array $update): string
    {
        $sql = $this->compileInsert($query, $values);

        $sql = str_replace('insert', 'insert or replace', $sql);

        return $sql;
    }

    public function supportsSavepoints(): bool
    {
        return true;
    }

    protected function compileDateBasedWhere(string $type, BuilderInterface $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        return match ($type) {
            'Day' => 'strftime(\'%d\', '.$this->wrap($where['column']).') '.$where['operator'].' cast('.$value.' as text)',
            'Month' => 'strftime(\'%m\', '.$this->wrap($where['column']).') '.$where['operator'].' cast('.$value.' as text)',
            'Year' => 'strftime(\'%Y\', '.$this->wrap($where['column']).') '.$where['operator'].' cast('.$value.' as text)',
            'Date' => 'date('.$this->wrap($where['column']).') '.$where['operator'].' '.$value,
            'Time' => 'time('.$this->wrap($where['column']).') '.$where['operator'].' '.$value,
            default => parent::compileDateBasedWhere($type, $query, $where),
        };
    }

    protected function compileJsonLength(string $column, string $operator, string $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'json_array_length('.$field.$path.') '.$operator.' '.$value;
    }

    protected function wrapJsonFieldAndPath(string $column): array
    {
        $parts = explode('->', $column, 2);

        $field = $this->wrap($parts[0]);
        $path = count($parts) > 1 ? ', '.$this->wrapJsonPath($parts[1]) : '';

        return [$field, $path];
    }

    protected function wrapJsonPath(string $value): string
    {
        return '\'$.'.str_replace('->', '.', $value).'\'';
    }

    public function compileRandom(string $seed = ''): string
    {
        return $seed ? 'abs(random() / '.$seed.')' : 'random()';
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
        return 'cast(strftime(\'%d\', '.$this->wrap($where['column']).') as integer) '.$where['operator'].' ?';
    }

    protected function whereMonth(BuilderInterface $query, array $where): string
    {
        return 'cast(strftime(\'%m\', '.$this->wrap($where['column']).') as integer) '.$where['operator'].' ?';
    }

    protected function whereYear(BuilderInterface $query, array $where): string
    {
        return 'cast(strftime(\'%Y\', '.$this->wrap($where['column']).') as integer) '.$where['operator'].' ?';
    }
}
