<?php

declare(strict_types=1);

namespace Bob\Schema;

/**
 * Column Definition - Fluent interface for defining column properties
 *
 * This class provides a fluent interface for setting column modifiers
 * like nullable, default values, indexes, etc.
 */
class ColumnDefinition extends Fluent
{
    /**
     * Set column as nullable
     */
    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    /**
     * Set default value for the column
     */
    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    /**
     * Set column as unsigned
     */
    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;
        return $this;
    }

    /**
     * Set column as auto-incrementing
     */
    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    /**
     * Set column as primary key
     */
    public function primary(bool $value = true): self
    {
        $this->primary = $value;
        return $this;
    }

    /**
     * Set column as unique
     */
    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    /**
     * Add an index to the column
     */
    public function index(bool $value = true): self
    {
        $this->index = $value;
        return $this;
    }

    /**
     * Add a fulltext index to the column
     */
    public function fulltext(bool $value = true): self
    {
        $this->fulltext = $value;
        return $this;
    }

    /**
     * Add a spatial index to the column
     */
    public function spatialIndex(bool $value = true): self
    {
        $this->spatialIndex = $value;
        return $this;
    }

    /**
     * Set column comment
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Set column charset (MySQL)
     */
    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * Set column collation (MySQL)
     */
    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }

    /**
     * Set column to be added after another column
     */
    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    /**
     * Set column to be added as the first column
     */
    public function first(): self
    {
        $this->first = true;
        return $this;
    }

    /**
     * Mark column as to be changed
     */
    public function change(): self
    {
        $this->change = true;
        return $this;
    }

    /**
     * Set column to use current timestamp as default
     */
    public function useCurrent(): self
    {
        $this->useCurrent = true;
        return $this;
    }

    /**
     * Set column to use current timestamp on update
     */
    public function useCurrentOnUpdate(): self
    {
        $this->useCurrentOnUpdate = true;
        return $this;
    }

    /**
     * Set column as virtual/computed (MySQL)
     */
    public function virtualAs(string $expression): self
    {
        $this->virtualAs = $expression;
        return $this;
    }

    /**
     * Set column as stored/computed (MySQL)
     */
    public function storedAs(string $expression): self
    {
        $this->storedAs = $expression;
        return $this;
    }

    /**
     * Set column to always be generated (PostgreSQL)
     */
    public function generatedAs(string $expression): self
    {
        $this->generatedAs = $expression;
        return $this;
    }

    /**
     * Set column as always generated (PostgreSQL identity)
     */
    public function always(): self
    {
        $this->always = true;
        return $this;
    }

    /**
     * Make the column invisible (MySQL 8.0+)
     */
    public function invisible(): self
    {
        $this->invisible = true;
        return $this;
    }

    /**
     * Specify that the column should be placed "first" in the table (MySQL)
     */
    public function from(int $startingValue): self
    {
        $this->from = $startingValue;
        return $this;
    }

    /**
     * Set the starting value of an auto-incrementing field (MySQL/PostgreSQL)
     */
    public function startingValue(int $startingValue): self
    {
        return $this->from($startingValue);
    }

    /**
     * Create a foreign key constraint
     */
    public function constrained(?string $table = null, ?string $column = 'id', ?string $indexName = null): self
    {
        $this->constrained = [
            'table' => $table,
            'column' => $column,
            'indexName' => $indexName,
        ];
        return $this;
    }

    /**
     * Specify cascading on delete for the foreign key
     */
    public function cascadeOnDelete(): self
    {
        $this->onDelete = 'cascade';
        return $this;
    }

    /**
     * Specify restrict on delete for the foreign key
     */
    public function restrictOnDelete(): self
    {
        $this->onDelete = 'restrict';
        return $this;
    }

    /**
     * Specify cascading on update for the foreign key
     */
    public function cascadeOnUpdate(): self
    {
        $this->onUpdate = 'cascade';
        return $this;
    }

    /**
     * Specify restrict on update for the foreign key
     */
    public function restrictOnUpdate(): self
    {
        $this->onUpdate = 'restrict';
        return $this;
    }

    /**
     * Specify the column as a persisted column (SQL Server)
     */
    public function persisted(): self
    {
        $this->persisted = true;
        return $this;
    }
}