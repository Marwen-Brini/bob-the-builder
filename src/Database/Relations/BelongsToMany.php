<?php

namespace Bob\Database\Relations;

use Bob\Database\Model;
use Bob\Query\Builder;

class BelongsToMany extends Relation
{
    /**
     * The intermediate table for the relation.
     */
    protected string $table;

    /**
     * The foreign key of the parent model on the intermediate table.
     */
    protected string $foreignPivotKey;

    /**
     * The associated key of the relation on the intermediate table.
     */
    protected string $relatedPivotKey;

    /**
     * The key name of the parent model.
     */
    protected string $parentKey;

    /**
     * The key name of the related model.
     */
    protected string $relatedKey;

    /**
     * The pivot table columns to retrieve.
     */
    protected array $pivotColumns = [];

    /**
     * Any pivot table values to set when attaching.
     */
    protected array $pivotValues = [];

    /**
     * Indicates if timestamps are available on the pivot table.
     */
    protected bool $withTimestamps = false;

    /**
     * The custom pivot table column for the created_at timestamp.
     */
    protected string $pivotCreatedAt = 'created_at';

    /**
     * The custom pivot table column for the updated_at timestamp.
     */
    protected string $pivotUpdatedAt = 'updated_at';

    /**
     * Create a new belongs to many relationship instance.
     */
    public function __construct(
        Builder $query,
        Model $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        ?string $relationName = null
    ) {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;

        parent::__construct($query, $parent, $foreignPivotKey, $parentKey);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->performJoin();
            $this->addWhereConstraints();
        }
    }

    /**
     * Set the join clause for the relation query.
     */
    public function performJoin(): Builder
    {
        $baseTable = $this->related->getTable();
        $key = $baseTable.'.'.$this->relatedKey;

        $this->query->join($this->table, $key, '=', $this->getQualifiedRelatedPivotKeyName());

        return $this->query;
    }

    /**
     * Set the where clause for the relation query.
     */
    protected function addWhereConstraints(): Builder
    {
        $this->query->where(
            $this->getQualifiedForeignPivotKeyName(),
            '=',
            $this->parent->getAttribute($this->parentKey)
        );

        return $this->query;
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->parentKey);

        $this->query->whereIn($this->getQualifiedForeignPivotKeyName(), $keys);
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
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            } else {
                $model->setRelation($relation, []);
            }
        }

        return $models;
    }

    /**
     * Execute the current query and return raw results.
     *
     * This method is extracted to make testing easier - it can be mocked
     * to return test data without requiring a real database connection.
     */
    protected function executeQuery(): array
    {
        return $this->query->connection->select(
            $this->query->toSql(),
            $this->query->getBindings()
        );
    }

    /**
     * Hydrate raw database results into model instances with pivot data.
     *
     * This method is extracted to make the hydration logic more testable.
     */
    protected function hydrateModelsWithPivot(array $results): array
    {
        $models = [];
        $relatedClass = get_class($this->related);

        // Define pivot column names
        $pivotColumnNames = $this->getPivotColumnNames();

        foreach ($results as $result) {
            // Separate the model attributes from pivot attributes
            $modelAttributes = [];
            $pivotAttributes = [];

            foreach ($result as $key => $value) {
                if (in_array($key, $pivotColumnNames)) {
                    $pivotAttributes[$key] = $value;
                } else {
                    $modelAttributes[$key] = $value;
                }
            }

            $model = $relatedClass::hydrate($modelAttributes);

            // Set pivot data - always set it for eager loading
            $model->setRelation('pivot', (object) $pivotAttributes);

            $models[] = $model;
        }

        return $models;
    }

    /**
     * Get the list of pivot column names including timestamps if enabled.
     */
    protected function getPivotColumnNames(): array
    {
        $columns = [$this->foreignPivotKey, $this->relatedPivotKey];

        if ($this->withTimestamps) {
            $columns[] = $this->pivotCreatedAt;
            $columns[] = $this->pivotUpdatedAt;
        }

        return array_merge($columns, $this->pivotColumns);
    }

    /**
     * Build model dictionary keyed by the parent key.
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            // Get the foreign key value from the pivot columns
            $pivot = $result->getAttribute('pivot');
            if (!$pivot) {
                continue;
            }
            $key = $pivot->{$this->foreignPivotKey} ?? null;

            if ($key !== null) {
                if (!isset($dictionary[$key])) {
                    $dictionary[$key] = [];
                }
                $dictionary[$key][] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * Get the results for eager loading.
     */
    public function getEager(): array
    {
        // Get the select columns
        $columns = $this->getSelectColumns();

        // Save the current columns and set new ones
        $original = $this->query->columns;
        if (is_null($this->query->columns)) {
            $this->query->columns = $columns;
        }

        // Get raw results
        $results = $this->executeQuery();

        // Restore original columns
        $this->query->columns = $original;

        // Hydrate results into model instances with pivot data
        return $this->hydrateModelsWithPivot($results);
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        $columns = $this->shouldSelectAll() ? ['*'] : $this->getSelectColumns();

        return $this->get($columns);
    }

    /**
     * Execute the query and get the results.
     */
    public function get(array $columns = ['*']): array
    {
        // First, we'll add the columns for the pivot table to the select
        $columns = $this->getSelectColumns($columns);

        // Save the current columns and set new ones
        $original = $this->query->columns;
        if (is_null($this->query->columns)) {
            $this->query->columns = $columns;
        }

        // Get raw results, not model instances
        $results = $this->executeQuery();

        // Restore original columns
        $this->query->columns = $original;

        // Hydrate results into model instances with pivot data
        return $this->hydrateModelsWithPivot($results);
    }

    /**
     * Hydrate the pivot table relationship on the models.
     */
    protected function hydratePivotRelation(array $models): void
    {
        foreach ($models as $model) {
            // Create pivot data from the model attributes
            $pivot = [];
            $attributes = $model->getAttributes();

            // Look for pivot columns (they're aliased as pivot_*)
            foreach ($attributes as $key => $value) {
                if (strpos($key, 'pivot_') === 0) {
                    $pivotKey = substr($key, 6); // Remove 'pivot_' prefix
                    $pivot[$pivotKey] = $value;
                    // Remove from model attributes
                    $model->setAttribute($key, null);
                }
            }

            // Set pivot data on model
            $model->pivot = (object) $pivot;
        }
    }

    /**
     * Get the select columns for the relation query.
     */
    protected function getSelectColumns(array $columns = ['*']): array
    {
        if ($columns == ['*']) {
            $columns = [$this->related->getTable().'.*'];
        }

        // Add pivot columns
        $pivotColumns = [];
        $pivotColumns[] = $this->table.'.'.$this->foreignPivotKey;
        $pivotColumns[] = $this->table.'.'.$this->relatedPivotKey;

        if ($this->withTimestamps) {
            $pivotColumns[] = $this->table.'.'.$this->pivotCreatedAt;
            $pivotColumns[] = $this->table.'.'.$this->pivotUpdatedAt;
        }

        foreach ($this->pivotColumns as $column) {
            $pivotColumns[] = $this->table.'.'.$column;
        }

        return array_merge($columns, $pivotColumns);
    }

    /**
     * Get the pivot columns for the relation query.
     */
    protected function aliasedPivotColumns(): array
    {
        $columns = [$this->foreignPivotKey, $this->relatedPivotKey];

        if ($this->withTimestamps) {
            $columns[] = $this->pivotCreatedAt;
            $columns[] = $this->pivotUpdatedAt;
        }

        $columns = array_merge($columns, $this->pivotColumns);

        $aliased = [];
        foreach ($columns as $column) {
            $aliased[] = $this->table.'.'.$column.' as pivot_'.$column;
        }

        return $aliased;
    }

    /**
     * Determine whether we should select all columns.
     */
    protected function shouldSelectAll(): bool
    {
        return empty($this->pivotColumns) && !$this->withTimestamps;
    }

    /**
     * Attach a model to the parent.
     */
    public function attach($id, array $attributes = [], bool $touch = true): void
    {
        if (is_array($id)) {
            $this->attachMultiple($id, $attributes, $touch);
            return;
        }

        $this->attachSingle($id, $attributes, $touch);
    }

    /**
     * Attach multiple records.
     */
    protected function attachMultiple(array $ids, array $attributes = [], bool $touch = true): void
    {
        foreach ($ids as $key => $value) {
            if (is_array($value)) {
                $this->attach($key, $value, $touch);
            } else {
                $this->attach($value, $attributes, $touch);
            }
        }
    }

    /**
     * Attach a single record.
     */
    protected function attachSingle($id, array $attributes = [], bool $touch = true): void
    {
        $query = $this->newPivotQuery();

        $records = $this->formatAttachRecords(
            $this->parseIds($id),
            $attributes
        );

        foreach ($records as $record) {
            $query->insert($record);
        }

        if ($touch) {
            $this->touchIfTouching();
        }
    }

    /**
     * Detach models from the relationship.
     */
    public function detach($ids = null, bool $touch = true): int
    {
        $results = $this->performDetach($ids);

        if ($touch) {
            $this->touchIfTouching();
        }

        return $results;
    }

    /**
     * Perform the detach operation.
     */
    protected function performDetach($ids = null): int
    {
        $query = $this->newPivotQuery();

        if (!is_null($ids)) {
            $ids = $this->parseIds($ids);

            if (empty($ids)) {
                return 0;
            }

            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     */
    public function sync($ids, bool $detaching = true): array
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => []
        ];

        // First, we need to get the current related models
        $currentResults = $this->newPivotQuery()
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->get();

        $current = [];
        foreach ($currentResults as $result) {
            // Handle both array and stdClass results
            if (is_array($result)) {
                $current[] = $result[$this->relatedPivotKey];
            } else {
                $current[] = $result->{$this->relatedPivotKey};
            }
        }

        // Cast the given IDs to an array
        $records = $this->formatAttachRecords(
            $this->parseIds($ids),
            []
        );

        $detach = array_diff($current, array_keys($records));

        // Detach any records that aren't in the sync list
        if ($detaching && count($detach) > 0) {
            $this->detach($detach);
            $changes['detached'] = $detach;
        }

        // Attach any new records
        $attach = array_diff(array_keys($records), $current);

        if (count($attach) > 0) {
            foreach ($attach as $id) {
                $this->attach($id, $records[$id] ?? []);
            }
            $changes['attached'] = array_values($attach);
        }

        return $changes;
    }

    /**
     * Create a new pivot query.
     */
    protected function newPivotQuery(): Builder
    {
        return $this->parent->getConnection()->table($this->table);
    }

    /**
     * Format the attach records.
     */
    protected function formatAttachRecords(array $ids, array $attributes): array
    {
        $records = [];

        foreach ($ids as $id) {
            $records[$id] = $this->formatSingleAttachRecord($id, $attributes);
        }

        return $records;
    }

    /**
     * Format a single attach record.
     */
    protected function formatSingleAttachRecord($id, array $attributes): array
    {
        $record = array_merge(
            $attributes,
            [
                $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
                $this->relatedPivotKey => $id,
            ]
        );

        // Add timestamps if needed
        $record = $this->addTimestampsToAttachRecord($record);

        // Add any pivot values
        $record = $this->addPivotValuesToAttachRecord($record);

        return $record;
    }

    /**
     * Add timestamps to attach record if needed.
     */
    protected function addTimestampsToAttachRecord(array $record): array
    {
        if ($this->withTimestamps) {
            $timestamp = date('Y-m-d H:i:s');
            $record[$this->pivotCreatedAt] = $timestamp;
            $record[$this->pivotUpdatedAt] = $timestamp;
        }

        return $record;
    }

    /**
     * Add pivot values to attach record.
     */
    protected function addPivotValuesToAttachRecord(array $record): array
    {
        foreach ($this->pivotValues as $key => $value) {
            $record[$key] = $value;
        }

        return $record;
    }

    /**
     * Parse the given IDs.
     */
    protected function parseIds($value): array
    {
        if ($value instanceof Model) {
            return [$value->getAttribute($this->relatedKey)];
        }

        if (is_array($value)) {
            return $value;
        }

        return [$value];
    }

    /**
     * Get the key values from the models.
     */
    protected function getKeys(array $models, ?string $key = null): array
    {
        return array_unique(
            array_values(array_filter(array_map(function ($model) use ($key) {
                return $key ? $model->getAttribute($key) : $model->getKey();
            }, $models)))
        );
    }

    /**
     * Touch if the parent model is using timestamps.
     */
    protected function touchIfTouching(): void
    {
        if ($this->parent->timestamps) {
            $this->parent->touch();
        }
    }

    /**
     * Get the fully qualified foreign pivot key name.
     */
    public function getQualifiedForeignPivotKeyName(): string
    {
        return $this->table.'.'.$this->foreignPivotKey;
    }

    /**
     * Get the fully qualified related pivot key name.
     */
    public function getQualifiedRelatedPivotKeyName(): string
    {
        return $this->table.'.'.$this->relatedPivotKey;
    }

    /**
     * Specify that the pivot table has creation and update timestamps.
     */
    public function withTimestamps(?string $createdAt = null, ?string $updatedAt = null): self
    {
        $this->withTimestamps = true;

        if ($createdAt) {
            $this->pivotCreatedAt = $createdAt;
        }

        if ($updatedAt) {
            $this->pivotUpdatedAt = $updatedAt;
        }

        return $this->withPivot($this->pivotCreatedAt, $this->pivotUpdatedAt);
    }

    /**
     * Specify the custom pivot columns to retrieve.
     */
    public function withPivot($columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        return $this;
    }

    /**
     * Set default values for pivot columns.
     */
    public function withPivotValue(string $column, $value): self
    {
        $this->pivotValues[$column] = $value;

        return $this->withPivot($column);
    }

    /**
     * Get the related model.
     */
    public function getRelated(): Model
    {
        return $this->related;
    }

    /**
     * Get the parent model.
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Get the intermediate table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the foreign pivot key name.
     */
    public function getForeignPivotKeyName(): string
    {
        return $this->foreignPivotKey;
    }

    /**
     * Get the related pivot key name.
     */
    public function getRelatedPivotKeyName(): string
    {
        return $this->relatedPivotKey;
    }

    /**
     * Get the parent key name.
     */
    public function getParentKeyName(): string
    {
        return $this->parentKey;
    }

    /**
     * Get the related key name.
     */
    public function getRelatedKeyName(): string
    {
        return $this->relatedKey;
    }

    /**
     * Handle dynamic method calls to the relationship.
     */
    public function __call($method, $parameters)
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}