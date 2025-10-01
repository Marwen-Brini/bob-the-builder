<?php

declare(strict_types=1);

namespace Bob\Schema;

/**
 * Foreign Key Definition - Fluent interface for defining foreign key constraints
 *
 * This class provides a fluent interface for setting foreign key properties
 * like referenced table, columns, and cascade actions.
 */
class ForeignKeyDefinition extends Fluent
{
    /**
     * Specify the referenced table
     */
    public function references(string|array $columns): self
    {
        $this->references = (array) $columns;

        return $this;
    }

    /**
     * Specify the referenced table
     */
    public function on(string $table): self
    {
        $this->on = $table;

        return $this;
    }

    /**
     * Specify the on delete action
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = $action;

        return $this;
    }

    /**
     * Specify CASCADE for on delete
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    /**
     * Specify RESTRICT for on delete
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('restrict');
    }

    /**
     * Specify SET NULL for on delete
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('set null');
    }

    /**
     * Specify NO ACTION for on delete
     */
    public function noActionOnDelete(): self
    {
        return $this->onDelete('no action');
    }

    /**
     * Specify the on update action
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;

        return $this;
    }

    /**
     * Specify CASCADE for on update
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('cascade');
    }

    /**
     * Specify RESTRICT for on update
     */
    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('restrict');
    }

    /**
     * Specify SET NULL for on update
     */
    public function nullOnUpdate(): self
    {
        return $this->onUpdate('set null');
    }

    /**
     * Specify NO ACTION for on update
     */
    public function noActionOnUpdate(): self
    {
        return $this->onUpdate('no action');
    }

    /**
     * Specify that the foreign key should be deferrable (PostgreSQL)
     */
    public function deferrable(bool $value = true): self
    {
        $this->deferrable = $value;

        return $this;
    }

    /**
     * Specify the initial deferral state (PostgreSQL)
     */
    public function initiallyDeferred(bool $value = true): self
    {
        $this->initiallyDeferred = $value;
        $this->deferrable = true;

        return $this;
    }

    /**
     * Specify that the foreign key should not be validated (PostgreSQL)
     */
    public function notValid(): self
    {
        $this->notValid = true;

        return $this;
    }
}
