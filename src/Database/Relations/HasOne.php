<?php

namespace Bob\Database\Relations;

use Bob\Database\Model;

class HasOne extends HasOneOrMany
{
    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        if ($this->shouldReturnDefaultValue()) {
            return $this->getDefaultFor($this->parent);
        }

        return $this->executeQuery();
    }

    /**
     * Check if we should return the default value.
     */
    protected function shouldReturnDefaultValue(): bool
    {
        return is_null($this->getParentKey());
    }

    /**
     * Execute the relationship query.
     */
    protected function executeQuery()
    {
        $result = $this->getQueryResult();

        return $this->processQueryResult($result);
    }

    /**
     * Get the query result.
     */
    protected function getQueryResult()
    {
        return $this->query->first();
    }

    /**
     * Process the query result.
     */
    protected function processQueryResult($result)
    {
        return $result ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $this->initializeRelationOnModel($model, $relation);
        }

        return $models;
    }

    /**
     * Initialize the relation on a single model.
     */
    protected function initializeRelationOnModel($model, string $relation): void
    {
        $model->setRelation($relation, $this->getDefaultFor($model));
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, array $results, string $relation): array
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Get the default value for this relation.
     */
    protected function getDefaultFor(Model $parent)
    {
        return null;
    }

    /**
     * Make a new related instance for the given model.
     */
    public function newRelatedInstanceFor(Model $parent): Model
    {
        return $this->buildNewRelatedInstance($parent);
    }

    /**
     * Build a new related instance.
     */
    protected function buildNewRelatedInstance(Model $parent): Model
    {
        $instance = $this->createNewRelatedInstance();
        $this->setForeignKeyOnInstance($instance, $parent);

        return $instance;
    }

    /**
     * Create a new instance of the related model.
     */
    protected function createNewRelatedInstance(): Model
    {
        return $this->related->newInstance();
    }

    /**
     * Set the foreign key on the related instance.
     */
    protected function setForeignKeyOnInstance(Model $instance, Model $parent): void
    {
        $instance->setAttribute($this->getForeignKeyName(), $parent->{$this->localKey});
    }
}
