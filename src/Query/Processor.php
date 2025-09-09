<?php

namespace Bob\Query;

use Bob\Contracts\BuilderInterface;
use Bob\Contracts\ProcessorInterface;

class Processor implements ProcessorInterface
{
    public function processSelect(BuilderInterface $query, array $results): array
    {
        return $results;
    }

    public function processInsertGetId(BuilderInterface $query, string $sql, array $values, ?string $sequence = null)
    {
        $query->getConnection()->insert($sql, $values);

        $id = $query->getConnection()->getPdo()->lastInsertId($sequence);

        return is_numeric($id) ? (int) $id : $id;
    }

    public function processColumnListing(array $results): array
    {
        return $results;
    }

    public function processColumnTypeListing(array $results): array
    {
        return $results;
    }
}