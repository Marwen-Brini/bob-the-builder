<?php

namespace Bob\Database\Eloquent;

/**
 * Trait to add soft delete functionality to models.
 *
 * Usage:
 * class User extends Model
 * {
 *     use SoftDeletes;
 * }
 */
trait SoftDeletes
{
    /**
     * Indicates if the model is currently force deleting.
     *
     * @var bool
     */
    protected bool $forceDeleting = false;

    /**
     * Boot the soft deleting trait for a model.
     *
     * @return void
     */
    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeletingScope);
    }

    /**
     * Initialize the soft deleting trait for an instance.
     *
     * @return void
     */
    public function initializeSoftDeletes(): void
    {
        if (! isset($this->casts[$this->getDeletedAtColumn()])) {
            $this->casts[$this->getDeletedAtColumn()] = 'datetime';
        }
    }

    /**
     * Force a hard delete on a soft deleted model.
     *
     * @return bool|null
     */
    public function forceDelete()
    {
        $this->forceDeleting = true;

        return tap($this->delete(), function ($deleted) {
            $this->forceDeleting = false;

            if ($deleted) {
                $this->fireModelEvent('forceDeleted', false);
            }
        });
    }

    /**
     * Perform a model delete operation.
     *
     * @return bool
     */
    protected function performDeleteOnModel(): bool
    {
        if ($this->forceDeleting) {
            return $this->setKeysForSaveQuery($this->newQuery())
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->delete();
        }

        return $this->runSoftDelete();
    }

    /**
     * Perform the actual delete query on the model.
     *
     * @return bool
     */
    protected function runSoftDelete(): bool
    {
        $query = $this->setKeysForSaveQuery($this->newQuery())
            ->withoutGlobalScope(SoftDeletingScope::class);

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);

        $this->syncOriginal();

        return true;
    }

    /**
     * Restore a soft-deleted model instance.
     *
     * @return bool
     */
    public function restore(): bool
    {
        // If the model doesn't use soft deletes, do nothing
        if (! $this->trashed()) {
            return false;
        }

        $this->fireModelEvent('restoring', false);

        $this->{$this->getDeletedAtColumn()} = null;

        // Also restore the updated timestamp if applicable
        if ($this->usesTimestamps()) {
            $this->{$this->getUpdatedAtColumn()} = $this->freshTimestamp();
        }

        $result = $this->save();

        if ($result) {
            $this->fireModelEvent('restored', false);
        }

        return $result;
    }

    /**
     * Restore multiple soft-deleted models by their primary keys.
     *
     * @param  array  $ids
     * @return int
     */
    public static function restoreMany(array $ids): int
    {
        return static::withTrashed()
            ->whereIn((new static)->getKeyName(), $ids)
            ->restore();
    }

    /**
     * Determine if the model is currently force deleting.
     *
     * @return bool
     */
    public function isForceDeleting(): bool
    {
        return $this->forceDeleting;
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedAtColumn(): string
    {
        return defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }

    /**
     * Get the fully qualified "deleted at" column.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }

    /**
     * Determine if the model instance has been soft-deleted.
     *
     * @return bool
     */
    public function trashed(): bool
    {
        return ! is_null($this->{$this->getDeletedAtColumn()});
    }

    /**
     * Register a "softDeleted" model event callback.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function softDeleted($callback): void
    {
        static::registerModelEvent('trashed', $callback);
    }

    /**
     * Register a "restoring" model event callback.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function restoring($callback): void
    {
        static::registerModelEvent('restoring', $callback);
    }

    /**
     * Register a "restored" model event callback.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function restored($callback): void
    {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Register a "forceDeleting" model event callback.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function forceDeleting($callback): void
    {
        static::registerModelEvent('forceDeleting', $callback);
    }

    /**
     * Register a "forceDeleted" model event callback.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function forceDeleted($callback): void
    {
        static::registerModelEvent('forceDeleted', $callback);
    }
}