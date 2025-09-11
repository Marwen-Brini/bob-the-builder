<?php

namespace Bob\Contracts;

interface GrammarInterface
{
    public function compileSelect(BuilderInterface $query): string;

    public function compileInsert(BuilderInterface $query, array $values): string;

    public function compileInsertGetId(BuilderInterface $query, array $values, ?string $sequence = null): string;

    public function compileInsertOrIgnore(BuilderInterface $query, array $values): string;

    public function compileUpdate(BuilderInterface $query, array $values): string;

    public function compileDelete(BuilderInterface $query): string;

    public function compileTruncate(BuilderInterface $query): array;

    public function compileLock(BuilderInterface $query, $value): string;

    public function compileExists(BuilderInterface $query): string;

    public function wrap($value): string;

    public function wrapArray(array $values): array;

    public function wrapTable($table): string;

    public function getValue($expression);

    public function isExpression($value): bool;

    public function getDateFormat(): string;

    public function getTablePrefix(): string;

    public function setTablePrefix(string $prefix): self;

    public function parameter($value): string;

    public function parameterize(array $values): string;

    public function columnize(array $columns): string;

    public function supportsSavepoints(): bool;

    public function compileSavepoint(string $name): string;

    public function compileSavepointRollBack(string $name): string;

    public function supportsReturning(): bool;

    public function supportsJsonOperations(): bool;

    public function getOperators(): array;
}
