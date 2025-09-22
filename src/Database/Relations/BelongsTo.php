<?php

namespace Bob\Database\Relations;

use Bob\Database\Model;
use Bob\Query\Builder;

class BelongsTo extends Relation
{
    /**
     * The child model instance of the relation.
     */
    protected Model $child;

    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;

    /**
     * The associated key on the parent model.
     */
    protected string $ownerKey;

    /**
     * The name of the relationship.
     */
    protected string $relationName;

    /**
     * Create a new belongs to relationship instance.
     */
    public function __construct(Builder $query, Model $child, string $foreignKey, string $ownerKey, string $relationName)
    {
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;
        $this->foreignKey = $foreignKey;
        $this->child = $child;

        parent::__construct($query, $child, $foreignKey, $ownerKey);
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        if (is_null($this->child->{$this->foreignKey})) {
            // @codeCoverageIgnoreStart
            return $this->getDefaultFor($this->parent);
            // @codeCoverageIgnoreEnd
        }

        // query->first() already returns a Model instance when model is set
        return $this->query->first() ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $table = $this->related->getTable();

            $this->query->where($table.'.'.$this->ownerKey, '=', $this->child->{$this->foreignKey});
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $key = $this->related->getTable().'.'.$this->ownerKey;

        $whereIn = $this->whereInMethod($this->related, $this->ownerKey);

        $this->query->{$whereIn}($key, $this->getEagerModelKeys($models));
    }

    /**
     * Gather the keys from an array of related models.
     */
    protected function getEagerModelKeys(array $models): array
    {
        $keys = [];

        foreach ($models as $model) {
            if (! is_null($value = $model->{$this->foreignKey})) {
                $keys[] = $value;
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->{$this->foreignKey};

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $foreign = $result->{$this->ownerKey};

            $dictionary[$foreign] = $result;
        }

        return $dictionary;
    }

    /**
     * Update the parent model on the relationship.
     */
    public function associate($model): Model
    {
        $ownerKey = $model instanceof Model ? $model->getAttribute($this->ownerKey) : $model;

        $this->child->setAttribute($this->foreignKey, $ownerKey);

        if ($model instanceof Model) {
            $this->child->setRelation($this->relationName, $model);
        } else {
            $this->child->unsetRelation($this->relationName);
        }

        return $this->child;
    }

    /**
     * Dissociate previously associated model from the given parent.
     */
    public function dissociate(): Model
    {
        $this->child->setAttribute($this->foreignKey, null);

        return $this->child->setRelation($this->relationName, null);
    }

    /**
     * Add the constraints for a relationship query.
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, string $columns = '*'): Builder
    {
        if ($parentQuery->from === $query->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        return $query->select($columns)->whereColumn(
            $this->getQualifiedForeignKeyName(), '=', $this->related->qualifyColumn($this->ownerKey)
        );
    }

    /**
     * Add the constraints for a relationship query on the same table.
     */
    public function getRelationExistenceQueryForSelfRelation(Builder $query, Builder $parentQuery, string $columns = '*'): Builder
    {
        $query->select($columns)->from(
            $query->getModel()->getTable().' as '.$hash = $this->getRelationCountHash()
        );

        $query->getModel()->setTable($hash);

        return $query->whereColumn(
            $hash.'.'.$this->ownerKey, '=', $this->getQualifiedForeignKeyName()
        );
    }

    /**
     * Get a relationship join table hash.
     */
    public function getRelationCountHash(): string
    {
        return 'laravel_reserved_'.static::$selfJoinCount++;
    }

    /**
     * Determine if the related model has an auto-incrementing ID.
     */
    protected function relationHasIncrementingId(): bool
    {
        return $this->related->getIncrementing() &&
               in_array($this->related->getKeyType(), ['int', 'integer']);
    }

    /**
     * Make a new related instance for the given model.
     */
    protected function newRelatedInstanceFor(Model $parent): Model
    {
        return $this->related->newInstance();
    }

    /**
     * Get the child of the relationship.
     */
    public function getChild(): Model
    {
        return $this->child;
    }

    /**
     * Get the foreign key of the relationship.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the fully qualified foreign key of the relationship.
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->child->qualifyColumn($this->foreignKey);
    }

    /**
     * Get the associated key of the relationship.
     */
    public function getOwnerKeyName(): string
    {
        return $this->ownerKey;
    }

    /**
     * Get the fully qualified associated key of the relationship.
     */
    public function getQualifiedOwnerKeyName(): string
    {
        return $this->related->qualifyColumn($this->ownerKey);
    }

    /**
     * Get the name of the relationship.
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * Get the name of the relationship.
     */
    public function getRelation(): string
    {
        return $this->relationName;
    }

    /**
     * Get the default value for this relation.
     */
    protected function getDefaultFor(Model $parent)
    {
        return null;
    }

    /**
     * Get the "where in" method for the related model.
     */
    protected function whereInMethod(Model $model, string $key): string
    {
        return 'whereIn';
    }

    /**
     * Static counter for self-join relationships.
     */
    protected static int $selfJoinCount = 0;
}