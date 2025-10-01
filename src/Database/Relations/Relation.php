<?php

namespace Bob\Database\Relations;

use Bob\Database\Model;
use Bob\Query\Builder;
use Closure;

abstract class Relation
{
    /**
     * The parent model instance.
     */
    protected Model $parent;

    /**
     * The related model instance.
     */
    protected Model $related;

    /**
     * The base query builder instance.
     */
    protected Builder $query;

    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;

    /**
     * The local key of the parent model.
     */
    protected string $localKey;

    /**
     * Indicates if the relation is adding constraints.
     */
    public static bool $constraints = true;

    /**
     * Create a new relation instance.
     */
    public function __construct(Builder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        // Get the related model from the query
        $this->related = $query->getModel();

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     */
    abstract public function addConstraints(): void;

    /**
     * Set the constraints for an eager load of the relation.
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Initialize the relation on a set of models.
     */
    abstract public function initRelation(array $models, string $relation): array;

    /**
     * Match the eagerly loaded results to their parents.
     */
    abstract public function match(array $models, array $results, string $relation): array;

    /**
     * Get the results of the relationship.
     */
    abstract public function getResults();

    /**
     * Execute the query as a "select" statement.
     */
    public function get(array $columns = ['*']): array
    {
        $results = $this->executeSelectQuery($columns);

        return $this->hydrateRelatedModels($results);
    }

    /**
     * Execute the select query.
     */
    protected function executeSelectQuery(array $columns): array
    {
        return $this->query->get($columns);
    }

    /**
     * Hydrate the results into model instances.
     */
    protected function hydrateRelatedModels(array $results): array
    {
        $class = get_class($this->related);

        return array_map(fn ($result) => $class::hydrate($result), $results);
    }

    /**
     * Touch all of the related models for the relationship.
     */
    public function touch(): void
    {
        if ($this->shouldTouch()) {
            $this->performTouch();
        }
    }

    /**
     * Determine if we should touch the related models.
     */
    protected function shouldTouch(): bool
    {
        $model = $this->getRelated();

        return ! $model::isIgnoringTouch();
    }

    /**
     * Perform the actual touch operation.
     */
    protected function performTouch(): void
    {
        $model = $this->getRelated();
        $this->rawUpdate([
            $model->getUpdatedAtColumn() => $model->freshTimestamp(),
        ]);
    }

    /**
     * Run a raw update against the base query.
     */
    public function rawUpdate(array $attributes = []): int
    {
        return $this->query->update($attributes);
    }

    /**
     * Add the constraints for a relationship count query.
     */
    public function getRelationExistenceCountQuery(Builder $query, Builder $parentQuery): Builder
    {
        return $this->getRelationExistenceQuery(
            $query, $parentQuery, 'count(*)'
        );
    }

    /**
     * Add the constraints for an internal relationship existence query.
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, string $columns = '*'): Builder
    {
        return $query->select($columns)->whereColumn(
            $this->getQualifiedParentKeyName(), '=', $this->getExistenceCompareKey()
        );
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     */
    public function getExistenceCompareKey(): string
    {
        return $this->getQualifiedForeignKeyName();
    }

    /**
     * Get the query builder for the relation.
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get the base query builder instance.
     */
    public function getBaseQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get the parent model of the relation.
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Get the fully qualified parent key name.
     */
    public function getQualifiedParentKeyName(): string
    {
        return $this->parent->qualifyColumn($this->localKey);
    }

    /**
     * Get the related model of the relation.
     */
    public function getRelated(): Model
    {
        return $this->related;
    }

    /**
     * Get the name of the "created at" column.
     */
    public function createdAt(): string
    {
        return $this->parent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function updatedAt(): string
    {
        return $this->parent->getUpdatedAtColumn();
    }

    /**
     * Get the name of the related model's "updated at" column.
     */
    public function relatedUpdatedAt(): string
    {
        return $this->related->getUpdatedAtColumn();
    }

    /**
     * Get the foreign key for the relationship.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the fully qualified foreign key for the relationship.
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->related->qualifyColumn($this->foreignKey);
    }

    /**
     * Get the local key for the relationship.
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }

    /**
     * Get the related key for the relationship.
     */
    public function getRelatedKeyName(): string
    {
        return $this->related->getKeyName();
    }

    /**
     * Get the relationship name of the belongs to many.
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * Remove a registered global scope from the relationship query.
     *
     * @param  string|object  $scope
     * @return $this
     */
    public function withoutGlobalScope($scope)
    {
        $this->query->withoutGlobalScope($scope);

        return $this;
    }

    /**
     * Remove all or passed global scopes from the relationship query.
     *
     * @return $this
     */
    public function withoutGlobalScopes(?array $scopes = null)
    {
        $this->query->withoutGlobalScopes($scopes);

        return $this;
    }

    /**
     * Determine if we should execute the query.
     */
    protected static function shouldExecute(): bool
    {
        return static::$constraints;
    }

    /**
     * Run a callback with constraints disabled on the relation.
     */
    public static function noConstraints(Closure $callback)
    {
        $previousConstraints = static::disableConstraints();

        try {
            return $callback();
        } finally {
            static::restoreConstraints($previousConstraints);
        }
    }

    /**
     * Disable constraints and return the previous state.
     */
    protected static function disableConstraints(): bool
    {
        $previous = static::$constraints;
        static::$constraints = false;

        return $previous;
    }

    /**
     * Restore constraints to the given state.
     */
    protected static function restoreConstraints(bool $state): void
    {
        static::$constraints = $state;
    }

    /**
     * Handle dynamic method calls to the relationship.
     */
    public function __call(string $method, array $parameters)
    {
        $result = $this->query->{$method}(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }
}
