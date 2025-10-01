<?php

namespace Bob\Database\Relations;

use Bob\Database\Model;
use Bob\Query\Builder;

abstract class HasOneOrMany extends Relation
{
    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;

    /**
     * The local key of the parent model.
     */
    protected string $localKey;

    /**
     * Create a new has one or many relationship instance.
     */
    public function __construct(Builder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->getParentKey());

            $this->query->whereNotNull($this->foreignKey);
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn(
            $this->foreignKey,
            $this->getKeys($models, $this->localKey)
        );
    }

    /**
     * Match the eagerly loaded results to their single parents.
     */
    public function matchOne(array $models, array $results, string $relation): array
    {
        return $this->matchOneOrMany($models, $results, $relation, 'one');
    }

    /**
     * Match the eagerly loaded results to their many parents.
     */
    public function matchMany(array $models, array $results, string $relation): array
    {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    protected function matchOneOrMany(array $models, array $results, string $relation, string $type): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->{$this->localKey};

            if (isset($dictionary[$key])) {
                $model->setRelation(
                    $relation,
                    $this->getRelationValue($dictionary, $key, $type)
                );
            }
        }

        return $models;
    }

    /**
     * Get the value of a relationship by one or many type.
     */
    protected function getRelationValue(array $dictionary, string $key, string $type)
    {
        $value = $dictionary[$key];

        return $type === 'one' ? reset($value) : $value;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $foreign = $result->{$this->getForeignKeyName()};

            $dictionary[$foreign][] = $result;
        }

        return $dictionary;
    }

    /**
     * Find a model by its primary key or return a new instance of the related model.
     */
    public function findOrNew($id, array $columns = ['*'])
    {
        $instance = $this->findExisting($id, $columns);

        if (is_null($instance)) {
            $instance = $this->createNewWithForeignKey();
        }

        return $instance;
    }

    /**
     * Try to find an existing model.
     */
    protected function findExisting($id, array $columns = ['*'])
    {
        return $this->find($id, $columns);
    }

    /**
     * Create a new instance with the foreign key set.
     */
    protected function createNewWithForeignKey(): Model
    {
        $instance = $this->related->newInstance();
        $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());

        return $instance;
    }

    /**
     * Get the first related model record matching the attributes or instantiate it.
     */
    public function firstOrNew(array $attributes = [], array $values = [])
    {
        $instance = $this->findFirstByAttributes($attributes);

        if (is_null($instance)) {
            $instance = $this->createNewWithAttributes($attributes, $values);
        }

        return $instance;
    }

    /**
     * Find first model by attributes.
     */
    protected function findFirstByAttributes(array $attributes)
    {
        return $this->where($attributes)->first();
    }

    /**
     * Create new instance with attributes and foreign key.
     */
    protected function createNewWithAttributes(array $attributes, array $values): Model
    {
        $instance = $this->related->newInstance(array_merge($attributes, $values));
        $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());

        return $instance;
    }

    /**
     * Get the first related record matching the attributes or create it.
     */
    public function firstOrCreate(array $attributes = [], array $values = [])
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->create(array_merge($attributes, $values));
        }

        return $instance;
    }

    /**
     * Create or update a related record matching the attributes, and fill it with values.
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        $instance = $this->firstOrNew($attributes);

        $instance->fill($values);

        $instance->save();

        return $instance;
    }

    /**
     * Attach a model instance to the parent model.
     */
    public function save(Model $model): Model|false
    {
        $model->setAttribute($this->getForeignKeyName(), $this->getParentKey());

        return $model->save() ? $model : false;
    }

    /**
     * Attach a collection of models to the parent instance.
     */
    public function saveMany(array $models): array
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    /**
     * Create a new instance of the related model.
     */
    public function create(array $attributes = []): Model
    {
        $instance = $this->prepareInstanceForCreation($attributes);
        $this->persistInstance($instance);

        return $instance;
    }

    /**
     * Prepare a new instance for creation.
     */
    protected function prepareInstanceForCreation(array $attributes): Model
    {
        $instance = $this->related->newInstance($attributes);
        $foreignKey = $this->extractForeignKeyName();
        $instance->setAttribute($foreignKey, $this->getParentKey());

        return $instance;
    }

    /**
     * Extract the foreign key name without table prefix.
     */
    protected function extractForeignKeyName(): string
    {
        return last(explode('.', $this->getForeignKeyName()));
    }

    /**
     * Persist the instance to the database.
     */
    protected function persistInstance(Model $instance): void
    {
        $instance->save();
    }

    /**
     * Create a collection of new instances of the related model.
     */
    public function createMany(array $records): array
    {
        $instances = [];

        foreach ($records as $record) {
            $instances[] = $this->create($record);
        }

        return $instances;
    }

    /**
     * Perform an update on all the related models.
     */
    public function update(array $attributes): int
    {
        return $this->query->update($attributes);
    }

    /**
     * Get the key value of the parent's local key.
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Get the foreign key for the relationship.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the plain foreign key.
     */
    public function getPlainForeignKey(): string
    {
        $segments = explode('.', $this->getForeignKeyName());

        return end($segments);
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     */
    public function getExistenceCompareKey(): string
    {
        return $this->getQualifiedForeignKeyName();
    }

    /**
     * Get the local key for the relationship.
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }

    /**
     * Get the fully qualified foreign key for the relationship.
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->related->qualifyColumn($this->foreignKey);
    }

    /**
     * Get all of the primary keys for an array of models.
     */
    protected function getKeys(array $models, ?string $key = null): array
    {
        return collect($models)->map(function ($model) use ($key) {
            return $key ? $model->{$key} : $model->getKey();
        })->values()->unique(null, true)->all();
    }
}
