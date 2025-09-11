<?php

namespace Bob\Contracts;

interface ProcessorInterface
{
    public function processSelect(BuilderInterface $query, array $results): array;

    public function processInsertGetId(BuilderInterface $query, string $sql, array $values, ?string $sequence = null);

    public function processColumnListing(array $results): array;

    public function processColumnTypeListing(array $results): array;
}
