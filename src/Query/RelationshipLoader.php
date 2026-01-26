<?php

namespace Bob\Query;

use Bob\Database\Relations\BelongsTo;
use Bob\Database\Relations\BelongsToMany;
use Bob\Database\Relations\HasOne;
use Bob\Database\Relations\Relation;

/**
 * Handles loading of relationships for query builder
 * Extracted for better testability and separation of concerns
 */
class RelationshipLoader
{
    /**
     * Load related models for eager loading
     */
    public function loadRelated($relation, array $models): array
    {
        if ($relation instanceof BelongsToMany) {
            return $this->loadBelongsToMany($relation, $models);
        }

        if ($relation instanceof BelongsTo) {
            return $this->loadBelongsTo($relation, $models);
        }

        return $this->loadHasRelation($relation, $models);
    }

    /**
     * Check if relation is BelongsToMany
     */
    protected function isBelongsToMany($relation): bool
    {
        return $relation instanceof BelongsToMany;
    }

    /**
     * Check if relation is BelongsTo (but not BelongsToMany)
     */
    protected function isBelongsTo($relation): bool
    {
        return $relation instanceof BelongsTo;
    }

    /**
     * Check if relation is HasOne
     */
    protected function isHasOne($relation): bool
    {
        return $relation instanceof HasOne;
    }

    /**
     * Load BelongsToMany relationship
     */
    protected function loadBelongsToMany($relation, array $models): array
    {
        $related = $relation->getRelated();
        $query = $related->newQuery();

        // Disable constraints temporarily
        $constraintsEnabled = Relation::$constraints;
        Relation::$constraints = false;

        // Create new BelongsToMany for eager loading
        $belongsToMany = new BelongsToMany(
            $query,
            $relation->getParent(),
            $relation->getTable(),
            $relation->getForeignPivotKeyName(),
            $relation->getRelatedPivotKeyName(),
            $relation->getParentKeyName(),
            $relation->getRelatedKeyName()
        );

        // Re-enable constraints
        Relation::$constraints = $constraintsEnabled;

        // Set up the join and constraints
        $belongsToMany->performJoin();
        $belongsToMany->addEagerConstraints($models);

        return $belongsToMany->getEager();
    }

    /**
     * Load BelongsTo relationship
     */
    protected function loadBelongsTo($relation, array $models): array
    {
        $related = $relation->getRelated();
        $query = $related->newQuery();

        // Extract foreign key name
        $foreignKey = $this->extractKeyName($relation->getForeignKeyName());

        // Get foreign key values from parent models
        $keys = $this->extractKeysFromModels($models, $foreignKey);

        if (empty($keys)) {
            return [];
        }

        // Query related models
        $ownerKey = $relation->getOwnerKeyName();

        return $query->whereIn($ownerKey, array_unique($keys))->get();
    }

    /**
     * Load HasOne or HasMany relationship
     */
    protected function loadHasRelation($relation, array $models): array
    {
        $related = $relation->getRelated();
        $query = $related->newQuery();

        // Get parent keys
        $keys = $this->extractKeysFromModels($models, $relation->getLocalKeyName());

        if (empty($keys)) {
            return [];
        }

        // Extract foreign key name
        $foreignKey = $this->extractKeyName($relation->getForeignKeyName());

        return $query->whereIn($foreignKey, array_unique($keys))->get();
    }

    /**
     * Extract key name without table prefix
     */
    protected function extractKeyName(string $key): string
    {
        if (strpos($key, '.') !== false) {
            return substr($key, strrpos($key, '.') + 1);
        }

        return $key;
    }

    /**
     * Extract key values from models
     */
    protected function extractKeysFromModels(array $models, string $keyName): array
    {
        $keys = [];

        foreach ($models as $model) {
            $key = $model->getAttribute($keyName);
            if (! is_null($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Match related models to their parents
     */
    public function matchRelated(array &$models, array $related, $relation, string $name): void
    {
        if ($this->isBelongsToMany($relation)) {
            $relation->match($models, $related, $name);

            return;
        }

        $dictionary = $this->buildDictionary($related, $relation);

        foreach ($models as &$model) {
            $key = $this->getMatchKey($model, $relation);

            if (isset($dictionary[$key])) {
                $model->setRelation($name, $dictionary[$key]);
            }
        }
    }

    /**
     * Get the key for matching relationships
     */
    protected function getMatchKey($model, $relation)
    {
        if ($this->isBelongsTo($relation)) {
            $foreignKey = $this->extractKeyName($relation->getForeignKeyName());

            return $model->getAttribute($foreignKey);
        }

        return $model->getAttribute($relation->getLocalKeyName());
    }

    /**
     * Build dictionary of related models
     */
    protected function buildDictionary(array $related, $relation): array
    {
        $dictionary = [];

        if ($this->isBelongsTo($relation)) {
            $keyName = basename(str_replace('\\', '/', $relation->getOwnerKeyName()));
            foreach ($related as $item) {
                $key = $item->getAttribute($keyName);
                $dictionary[$key] = $item;
            }
        } else {
            $foreignKey = $this->extractKeyName($relation->getForeignKeyName());
            foreach ($related as $item) {
                $key = $item->getAttribute($foreignKey);
                if ($this->isHasOne($relation)) {
                    $dictionary[$key] = $item;
                } else {
                    if (! isset($dictionary[$key])) {
                        $dictionary[$key] = [];
                    }
                    $dictionary[$key][] = $item;
                }
            }
        }

        return $dictionary;
    }
}
