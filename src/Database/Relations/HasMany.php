<?php

namespace Bob\Database\Relations;

class HasMany extends HasOneOrMany
{
    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        return ! is_null($this->getParentKey())
                ? $this->query->get()
                : [];
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, []);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, array $results, string $relation): array
    {
        return $this->matchMany($models, $results, $relation);
    }
}
