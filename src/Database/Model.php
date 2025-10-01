<?php

namespace Bob\Database;

use Bob\Contracts\ConnectionInterface;
use Bob\Database\Eloquent\Scope;
use Bob\Query\Builder;
use Bob\Support\Collection;
use Closure;
use JsonSerializable;

/**
 * Base Model class that provides ActiveRecord-like functionality
 * Combines global extensions (via Macroable) with model-specific methods
 */
class Model implements JsonSerializable
{
    /**
     * The connection instance
     */
    protected static ?ConnectionInterface $connection = null;

    /**
     * The table associated with the model
     */
    protected string $table = '';

    /**
     * The primary key for the model
     */
    protected string $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped
     */
    protected bool $timestamps = true;

    /**
     * The name of the "created at" column
     */
    protected string $createdAt = 'created_at';

    /**
     * The name of the "updated at" column
     */
    protected string $updatedAt = 'updated_at';

    /**
     * The attributes that should be cast to native types
     */
    protected array $casts = [];

    /**
     * The model's attributes
     */
    protected array $attributes = [];

    /**
     * The model's original attributes
     */
    protected array $original = [];

    /**
     * The changed attributes for the model.
     */
    protected array $changes = [];

    /**
     * The loaded relationships for the model.
     */
    protected array $relations = [];

    /**
     * Indicates if the model was recently created.
     */
    protected bool $wasRecentlyCreated = false;

    /**
     * Indicates if we should ignore touch events.
     */
    protected static bool $ignoreTouch = false;

    /**
     * Indicates if relationships should apply global scopes from the related model.
     * Set to false to prevent global scope inheritance in relationships.
     */
    protected bool $applyGlobalScopesToRelationships = true;

    /**
     * The array of global scopes for each model class.
     *
     * @var array<string, array<string, \Closure|Scope>>
     */
    protected static array $globalScopes = [];

    /**
     * The array of booted models.
     *
     * @var array<class-string, bool>
     */
    protected static array $booted = [];

    /**
     * The array of trait boot methods that have been called.
     *
     * @var array<class-string, array>
     */
    protected static array $bootedMethods = [];

    /**
     * Set the global connection for all models
     */
    public static function setConnection(?ConnectionInterface $connection): void
    {
        static::$connection = $connection;
    }

    /**
     * Clear the global connection
     */
    public static function clearConnection(): void
    {
        static::$connection = null;
    }

    /**
     * Get the connection instance
     */
    public static function getConnection(): ConnectionInterface
    {
        if (! static::$connection) {
            throw new \RuntimeException('No database connection configured for models');
        }

        return static::$connection;
    }

    /**
     * Get a new query builder instance for the model
     */
    public static function query(): Builder
    {
        $instance = new static;

        return $instance->newQuery();
    }

    /**
     * Get the table name for the model
     */
    public function getTable(): string
    {
        if (! empty($this->table)) {
            return $this->table;
        }

        // Auto-generate table name from class name
        $className = (new \ReflectionClass($this))->getShortName();

        return $this->pluralize(strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)));
    }

    /**
     * Get the primary key name for the model
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Simple pluralization (can be overridden for complex cases)
     */
    protected function pluralize(string $singular): string
    {
        $last = substr($singular, -1);
        $beforeLast = substr($singular, -2, 1);

        if ($last === 'y' && ! in_array($beforeLast, ['a', 'e', 'i', 'o', 'u'])) {
            return substr($singular, 0, -1).'ies';
        }

        if (substr($singular, -1) === 's') {
            return $singular.'es';
        }

        return $singular.'s';
    }

    /**
     * Create a new model instance
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->fill($attributes);
        $this->wasRecentlyCreated = false;
        // Don't set original here as hydrate handles it
    }

    /**
     * Check if the model needs to be booted and boot it if necessary.
     */
    protected function bootIfNotBooted(): void
    {
        if (! isset(static::$booted[static::class])) {
            static::booting();
            static::boot();
            static::booted();
            static::$booted[static::class] = true;
        }
    }

    /**
     * Perform any actions required before the model boots.
     */
    protected static function booting(): void
    {
        //
    }

    /**
     * The "boot" method of the model.
     * Override this in child classes to add global scopes.
     */
    protected static function boot(): void
    {
        // Boot all traits that have boot methods
        static::bootTraits();
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        //
    }

    /**
     * Boot all of the bootable traits on the model.
     */
    protected static function bootTraits(): void
    {
        $class = static::class;

        $traits = class_uses($class);
        // @codeCoverageIgnoreStart
        if ($traits === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

        foreach ($traits as $trait) {
            // Get just the trait name without namespace
            $traitName = substr($trait, strrpos($trait, '\\') + 1);
            $method = 'boot'.$traitName;

            if (method_exists($class, $method) && ! in_array($method, static::$bootedMethods[$class] ?? [])) {
                forward_static_call([$class, $method]);
                static::$bootedMethods[$class][] = $method;
            }
        }
    }

    /**
     * Fill the model with attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            // Check if the attribute is fillable
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Force fill the model with attributes, bypassing mass assignment protection
     */
    public function forceFill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            // Directly set attributes without checking fillable/guarded
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Determine if the given attribute may be mass assigned
     */
    protected function isFillable(string $key): bool
    {
        // If fillable is empty and guarded is empty, allow all
        if (empty($this->fillable) && empty($this->guarded)) {
            return true;
        }

        // If fillable is set, only allow those attributes
        if (! empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        // If guarded is set, allow all except guarded
        return ! in_array($key, $this->guarded);
    }

    /**
     * Set an attribute on the model
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get an attribute from the model
     */
    public function getAttribute(string $key, $default = null)
    {
        // Handle special properties
        if ($key === 'exists') {
            return $this->exists();
        }

        if ($key === 'wasRecentlyCreated') {
            return $this->wasRecentlyCreated;
        }

        // Check attributes first
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }

        // Check loaded relations
        if (isset($this->relations[$key])) {
            return $this->relations[$key];
        }

        // Check if it's a relationship method
        if (method_exists($this, $key)) {
            $relation = $this->{$key}();

            // If it's a relation object, execute the query
            if ($relation instanceof Relations\Relation) {
                $result = $relation->getResults();
                $this->relations[$key] = $result;

                return $result;
            }
        }

        return $default;
    }

    /**
     * Save the model to the database
     */
    public function save(): bool
    {
        // If model has an ID but empty original, check if it actually exists in DB
        if (isset($this->attributes[$this->primaryKey]) &&
            ! empty($this->attributes[$this->primaryKey]) &&
            empty($this->original)) {

            // Check database to see if record with this ID actually exists
            $existing = static::query()
                ->where($this->primaryKey, $this->getAttribute($this->primaryKey))
                ->first();

            if ($existing) {
                // Record exists in DB, populate original and update
                $this->original = $existing->getAttributes();

                return $this->update();
            }
            // Record doesn't exist, treat as new (but keep the provided ID)
        }

        if ($this->exists()) {
            return $this->update();
        }

        return $this->insert();
    }

    /**
     * Insert the model into the database
     */
    protected function insert(): bool
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $this->setAttribute($this->createdAt, $now);
            $this->setAttribute($this->updatedAt, $now);
        }

        $id = static::query()->insertGetId($this->attributes);

        if ($id) {
            $this->setAttribute($this->primaryKey, $id);
            $this->syncChanges();
            $this->original = $this->attributes;
            $this->wasRecentlyCreated = true;

            return true;
        }

        return false;
    }

    /**
     * Update the model in the database
     */
    protected function update(): bool
    {
        // First check if anything is dirty before timestamps
        if (! $this->isDirty()) {
            // Nothing dirty, no need to update
            return true;
        }

        $dirty = $this->prepareAttributesForUpdate();

        if (empty($dirty)) {
            return true;
        }

        return $this->performUpdate($dirty);
    }

    /**
     * Prepare attributes for update
     */
    protected function prepareAttributesForUpdate(): array
    {
        $dirty = $this->getDirty();

        if ($this->timestamps) {
            $this->setAttribute($this->updatedAt, date('Y-m-d H:i:s'));
            $dirty = $this->getDirty();
        }

        return $dirty;
    }

    /**
     * Perform the actual update query
     */
    protected function performUpdate(array $dirty): bool
    {
        $updated = static::query()
            ->where($this->primaryKey, $this->getAttribute($this->primaryKey))
            ->update($dirty);

        if ($updated) {
            $this->syncChanges();
            $this->original = $this->attributes;
            $this->wasRecentlyCreated = false;

            return true;
        }

        return false;
    }

    /**
     * Delete the model from the database
     */
    public function delete(): bool
    {
        if (! $this->canDelete()) {
            return false;
        }

        $result = $this->performDelete();

        // Clear model state after successful deletion
        if ($result) {
            $this->original = [];
            unset($this->attributes[$this->primaryKey]);
        }

        return $result;
    }

    /**
     * Check if model can be deleted
     */
    protected function canDelete(): bool
    {
        return $this->exists();
    }

    /**
     * Perform the actual delete query
     */
    protected function performDelete(): bool
    {
        // For delete operations, we don't want global scopes since we're deleting by primary key
        return (bool) static::query()
            ->withoutGlobalScopes()
            ->where($this->primaryKey, $this->getAttribute($this->primaryKey))
            ->delete();
    }

    /**
     * Check if the model exists in the database
     */
    public function exists(): bool
    {
        return ! empty($this->original) &&
               isset($this->attributes[$this->primaryKey]) &&
               ! empty($this->attributes[$this->primaryKey]);
    }

    /**
     * Get the attributes that have been changed
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (! array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Register a global scope for this model.
     *
     * @param  string|\Closure|Scope  $scope
     * @param  \Closure|Scope|null  $implementation
     */
    public static function addGlobalScope($scope, $implementation = null): void
    {
        if (is_string($scope) && $implementation !== null) {
            static::$globalScopes[static::class][$scope] = $implementation;
        } elseif ($scope instanceof Closure) {
            static::$globalScopes[static::class][spl_object_hash((object) $scope)] = $scope;
        } elseif ($scope instanceof Scope) {
            static::$globalScopes[static::class][get_class($scope)] = $scope;
        }
    }

    /**
     * Determine if a model has a global scope.
     *
     * @param  string|Scope  $scope
     */
    public static function hasGlobalScope($scope): bool
    {
        return ! is_null(static::getGlobalScope($scope));
    }

    /**
     * Get a global scope registered with the model.
     *
     * @param  string|Scope  $scope
     * @return \Closure|Scope|null
     */
    public static function getGlobalScope($scope)
    {
        if (is_string($scope)) {
            return static::$globalScopes[static::class][$scope] ?? null;
        }

        return static::$globalScopes[static::class][get_class($scope)] ?? null;
    }

    /**
     * Get all of the global scopes for the model.
     *
     * @return array<string, \Closure|Scope>
     */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    /**
     * Create a new model instance and save it
     */
    public static function create(array $attributes): ?self
    {
        $model = new static($attributes);

        return $model->save() ? $model : null;
    }

    /**
     * Find a model by its primary key
     */
    public static function find($id): ?self
    {
        $instance = new static;
        // Use qualified column name to avoid ambiguity with JOINs
        $qualifiedKey = $instance->getTable().'.'.$instance->primaryKey;

        return static::query()
            ->where($qualifiedKey, $id)
            ->first();
    }

    /**
     * Find a model by primary key or throw an exception
     */
    public static function findOrFail($id): self
    {
        $model = static::find($id);

        if (! $model) {
            throw new \RuntimeException("Model not found with ID: {$id}");
        }

        return $model; // @codeCoverageIgnore
    }

    /**
     * Eager load relationships on the model
     *
     * @param  string|array  $relations
     * @return $this
     */
    public function load($relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        foreach ($relations as $relation) {
            // Load the relationship if it hasn't been loaded yet
            if (! isset($this->relations[$relation])) {
                $this->relations[$relation] = $this->$relation()->get();
            }
        }

        return $this;
    }

    /**
     * Get all models from the database
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * Get the first model matching the conditions
     */
    public static function first(): ?self
    {
        return static::query()->first();
    }

    /**
     * Create a model instance from database result
     */
    public static function hydrate($data): self
    {
        // If data is already a Model instance, just return it
        if ($data instanceof self) {
            return $data;
        }

        $attributes = is_object($data) ? (array) $data : $data;
        $model = new static;

        // Directly set attributes without fillable check for hydration
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }

        $model->original = $attributes;

        return $model;
    }

    /**
     * Create multiple model instances from database results
     */
    protected static function hydrateMany(array $results): array
    {
        return array_map(fn ($result) => static::hydrate($result), $results);
    }

    /**
     * Convert the model to an array
     */
    public function toArray(): array
    {
        $array = $this->getVisibleAttributes();
        $array = $this->appendAccessors($array);
        $array = $this->appendRelations($array);

        return $array;
    }

    /**
     * Get visible attributes
     */
    protected function getVisibleAttributes(): array
    {
        $array = [];
        foreach ($this->attributes as $key => $value) {
            if ($this->isVisible($key)) {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Append accessor values to array
     */
    protected function appendAccessors(array $array): array
    {
        foreach ($this->appends ?? [] as $key) {
            $accessor = 'get'.str_replace('_', '', ucwords($key, '_')).'Attribute';
            if (method_exists($this, $accessor)) {
                $array[$key] = $this->$accessor();
            }
        }

        return $array;
    }

    /**
     * Append relation values to array
     */
    protected function appendRelations(array $array): array
    {
        foreach ($this->relations as $key => $value) {
            if ($this->isVisible($key)) {
                $array[$key] = $this->serializeRelation($value);
            }
        }

        return $array;
    }

    /**
     * Serialize a relation value
     */
    protected function serializeRelation($value)
    {
        if ($value instanceof Model) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(function ($item) {
                return $item instanceof Model ? $item->toArray() : $item;
            }, $value);
        }

        if ($value instanceof Collection) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Check if an attribute is visible
     */
    protected function isVisible(string $key): bool
    {
        return ! in_array($key, $this->hidden ?? []);
    }

    /**
     * Convert the model to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the model's attributes to an array.
     */
    public function attributesToArray(): array
    {
        // Reuse the same logic as toArray
        return $this->toArray();
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Convert the model to its string representation
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Get a casted attribute value
     */
    public function getCastedAttribute(string $key)
    {
        $value = $this->getAttribute($key);

        if (! isset($this->casts[$key])) {
            return $value;
        }

        return $this->castAttribute($key, $value);
    }

    /**
     * Cast an attribute to a native PHP type
     */
    protected function castAttribute(string $key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        $castType = $this->casts[$key] ?? null;

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
                return is_string($value) ? json_decode($value, true) : (array) $value;
            case 'json':
                return is_string($value) ? json_decode($value, true) : (array) $value;
            case 'object':
                return is_string($value) ? json_decode($value) : (object) $value;
            case 'datetime':
                return new \DateTime($value);
            default:
                return $value;
        }
    }

    /**
     * Get an attribute value
     */
    public function __get(string $name)
    {
        return $this->getAttribute($name);
    }

    /**
     * Set an attribute value
     */
    public function __set(string $name, $value): void
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Check if an attribute is set
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Unset an attribute
     */
    public function __unset(string $name): void
    {
        unset($this->attributes[$name]);
    }

    /**
     * Handle dynamic method calls
     *
     * This allows for model-specific methods and forwards to query builder
     */
    public static function __callStatic(string $method, array $arguments)
    {
        // First check if the method exists on the model instance
        // This allows for custom static methods defined in child models
        $instance = new static;

        // Check for custom instance methods that should work statically
        // @codeCoverageIgnoreStart
        // This is unreachable in normal PHP as __callStatic won't be called if method exists
        if (method_exists($instance, $method)) {
            // If it's a custom finder method, call it
            return $instance->$method(...$arguments);
        }
        // @codeCoverageIgnoreEnd

        // Check for scope methods (scopeMethodName becomes methodName)
        $scopeMethod = 'scope'.ucfirst($method);
        if (method_exists($instance, $scopeMethod)) {
            $query = static::query();

            // The scope method should return the builder for chaining
            return $instance->$scopeMethod($query, ...$arguments);
        }

        // Otherwise, forward to the query builder
        return static::query()->$method(...$arguments);
    }

    /**
     * Handle dynamic instance method calls
     */
    public function __call(string $method, array $arguments)
    {
        // Forward to query builder with current model constraints
        $query = static::query();

        if ($this->exists()) {
            $query->where($this->primaryKey, $this->getAttribute($this->primaryKey));
        }

        return $query->$method(...$arguments);
    }

    /**
     * Example of how to define custom methods in child models:
     *
     * In your Post model:
     *
     * public static function findBySlug(string $slug): ?self
     * {
     *     $result = static::query()->where('slug', $slug)->first();
     *     return $result ? static::hydrate($result) : null;
     * }
     *
     * public function scopePublished(Builder $query): void
     * {
     *     $query->where('status', 'published');
     * }
     *
     * Then you can use:
     * $post = Post::findBySlug('my-post');
     * $posts = Post::published()->get();
     */

    // ============================================================
    // Relationship Methods
    // ============================================================

    /**
     * Define a one-to-one relationship.
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): Relations\HasOne
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new Relations\HasOne($instance->newQueryForRelationship(), $this, $instance->getTable().'.'.$foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): Relations\HasMany
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new Relations\HasMany($instance->newQueryForRelationship(), $this, $instance->getTable().'.'.$foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): Relations\BelongsTo
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        if (is_null($foreignKey)) {
            $foreignKey = $this->getForeignKeyForBelongsTo($relation);
        }

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new Relations\BelongsTo(
            $instance->newQueryForRelationship(), $this, $foreignKey, $ownerKey, $relation
        );
    }

    /**
     * Define a many-to-many relationship.
     */
    public function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?string $relation = null
    ): Relations\BelongsToMany {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        if (is_null($table)) {
            $table = $this->joiningTable($this->getTable(), $instance->getTable());
        }

        $parentKey = $parentKey ?: $this->getKeyName();
        $relatedKey = $relatedKey ?: $instance->getKeyName();

        return new Relations\BelongsToMany(
            $instance->newQueryForRelationship(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relation
        );
    }

    /**
     * Create a new model instance for a related model.
     */
    protected function newRelatedInstance(string $class): self
    {
        return new $class;
    }

    /**
     * Get the foreign key for the model.
     */
    public function getForeignKey(): string
    {
        return $this->getSnakeCase(class_basename($this)).'_'.$this->getKeyName();
    }

    /**
     * Get the foreign key for a belongs to relationship.
     */
    protected function getForeignKeyForBelongsTo(string $relation): string
    {
        return $this->getSnakeCase($relation).'_id';
    }

    /**
     * Guess the "belongs to" relationship name.
     */
    protected function guessBelongsToRelation(): string
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller[2]['function'] ?? '';
    }

    /**
     * Guess the "belongs to many" relationship name.
     */
    protected function guessBelongsToManyRelation(): string
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller[2]['function'] ?? '';
    }

    /**
     * Get the joining table name for a many-to-many relation.
     */
    protected function joiningTable(string $first, string $second): string
    {
        // Sort tables alphabetically and join with underscore
        $tables = [
            str_replace('_', '', $this->getSnakeCase($first)),
            str_replace('_', '', $this->getSnakeCase($second)),
        ];

        sort($tables);

        return implode('_', $tables);
    }

    /**
     * Convert a string to snake case.
     */
    protected function getSnakeCase(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    /**
     * Get the key name for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Qualify the given column name by the model's table.
     */
    public function qualifyColumn(string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable().'.'.$column;
    }

    /**
     * Get a relationship value from a method.
     */
    protected function getRelationValue(string $key)
    {
        // Check if already loaded
        if ($this->relationLoaded($key)) {
            return $this->getLoadedRelation($key);
        }

        // Try to load from method
        if ($this->hasRelationMethod($key)) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    /**
     * Get a loaded relation
     */
    protected function getLoadedRelation(string $key)
    {
        return $this->relations[$key] ?? null;
    }

    /**
     * Check if a relation method exists
     */
    protected function hasRelationMethod(string $key): bool
    {
        return method_exists($this, $key);
    }

    /**
     * Get a relationship value from a method.
     */
    protected function getRelationshipFromMethod(string $method)
    {
        $relation = $this->$method();

        if (! $relation instanceof Relations\Relation) {
            throw new \LogicException(sprintf(
                '%s::%s must return a relationship instance.', static::class, $method
            ));
        }

        return tap($relation->getResults(), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }

    /**
     * Set the given relationship on the model.
     */
    public function setRelation(string $relation, $value): self
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Unset a loaded relationship.
     */
    public function unsetRelation(string $relation): self
    {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * Determine if the given relation is loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Get all the loaded relations for the instance.
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Get a specific relationship value.
     *
     * @return mixed
     */
    public function getRelation(string $relation)
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Sync the original attributes with the current.
     */
    public function syncOriginal(): self
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Get the original attribute values.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getOriginal(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->original;
        }

        return $this->original[$key] ?? $default;
    }

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        if (is_null($attributes)) {
            return count($this->getDirty()) > 0;
        }

        $attributes = is_array($attributes) ? $attributes : [$attributes];

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $this->getDirty())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the name of the "created at" column.
     */
    public function getCreatedAtColumn(): string
    {
        return $this->createdAt;
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function getUpdatedAtColumn(): string
    {
        return $this->updatedAt;
    }

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Determine if the model is ignoring touch events.
     */
    public static function isIgnoringTouch(): bool
    {
        return static::$ignoreTouch;
    }

    /**
     * Get a new query builder for the model's table.
     */
    public function newQuery(): Builder
    {
        $builder = static::getConnection()->table($this->getTable());
        $builder->setModel($this);

        // Apply global scopes
        $this->registerGlobalScopes($builder);

        return $builder;
    }

    /**
     * Get a new query builder without any global scopes.
     */
    public function newQueryWithoutScopes(): Builder
    {
        $builder = static::getConnection()->table($this->getTable());
        $builder->setModel($this);

        return $builder;
    }

    /**
     * Register global scopes with the builder.
     * They will be applied when the query is executed.
     */
    protected function registerGlobalScopes(Builder $builder): void
    {
        foreach (static::getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }
    }

    /**
     * Get a new query builder for relationships.
     * This method respects the applyGlobalScopesToRelationships setting.
     */
    public function newQueryForRelationship(): Builder
    {
        if ($this->applyGlobalScopesToRelationships) {
            return $this->newQuery();
        }

        // Create a query without global scopes
        $builder = static::getConnection()->table($this->getTable());
        $builder->setModel($this);

        return $builder;
    }

    /**
     * Create a new instance of the given model.
     */
    public function newInstance(array $attributes = []): self
    {
        $model = new static;

        $model->fill($attributes);

        return $model;
    }

    /**
     * Get the model for a bound value.
     */
    public function getModel(): self
    {
        return $this;
    }

    /**
     * Determine if the model uses auto-incrementing IDs.
     */
    public function getIncrementing(): bool
    {
        return true;
    }

    /**
     * Get the type of the primary key.
     */
    public function getKeyType(): string
    {
        return 'int';
    }

    /**
     * Determine if the model or any attributes have been modified.
     */
    public function isClean(array|string|null $attributes = null): bool
    {
        return ! $this->isDirty($attributes);
    }

    /**
     * Determine if the model or given attribute(s) were changed.
     */
    public function wasChanged(array|string|null $attributes = null): bool
    {
        if (is_null($attributes)) {
            return count($this->changes) > 0;
        }

        $attributes = is_array($attributes) ? $attributes : [$attributes];

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $this->changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that were changed.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Sync the changes.
     */
    public function syncChanges(): self
    {
        $this->changes = $this->getDirty();

        return $this;
    }

    /**
     * Get only the specified attributes from the model.
     */
    public function only($attributes): array
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $results = [];

        foreach ($attributes as $attribute) {
            $results[$attribute] = $this->getAttribute($attribute);
        }

        return $results;
    }

    /**
     * Get all attributes except the specified ones.
     */
    public function except($attributes): array
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        return array_diff_key($this->attributes, array_flip($attributes));
    }

    /**
     * Clone the model into a new, non-existing instance.
     */
    public function replicate(array $except = []): self
    {
        $attributes = $this->except(array_merge(
            [$this->getKeyName()],
            $except
        ));

        $model = new static;
        $model->fill($attributes);

        return $model;
    }

    /**
     * Reload a fresh model instance from the database.
     */
    public function fresh($with = []): ?self
    {
        if (! $this->exists()) {
            return null;
        }

        return static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->first();
    }

    /**
     * Reload the current model instance with fresh attributes from the database.
     */
    public function refresh(): self
    {
        if (! $this->exists()) {
            return $this;
        }

        $fresh = $this->fresh();

        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->original = $fresh->original;
        }

        return $this;
    }

    /**
     * Update the model's update timestamp.
     */
    public function touch(): bool
    {
        if (! $this->timestamps) {
            return false;
        }

        $this->setAttribute($this->getUpdatedAtColumn(), $this->freshTimestamp());

        return $this->save();
    }

    /**
     * Determine if two models have the same ID and belong to the same table.
     */
    public function is($model): bool
    {
        if (is_null($model)) {
            return false;
        }

        return ! is_null($this->getKey()) &&
               $this->getKey() === $model->getKey() &&
               $this->getTable() === $model->getTable() &&
               get_class($this) === get_class($model);
    }

    /**
     * Determine if two models are not the same.
     */
    public function isNot($model): bool
    {
        return ! $this->is($model);
    }

    /**
     * Save the model and all of its relationships.
     */
    public function push(): bool
    {
        if (! $this->save()) {
            return false;
        }

        // In a full implementation, this would also save loaded relationships
        // For now, just save the model itself
        foreach ($this->relations as $relation) {
            // Future: Save each loaded relationship
        }

        return true;
    }

    /**
     * Make the given, typically hidden, attributes visible.
     */
    public function makeVisible($attributes): self
    {
        $this->hidden = array_diff($this->hidden, (array) $attributes);

        if (! empty($this->visible)) {
            $this->visible = array_merge($this->visible, (array) $attributes);
        }

        return $this;
    }

    /**
     * Make the given, typically visible, attributes hidden.
     */
    public function makeHidden($attributes): self
    {
        $this->hidden = array_merge($this->hidden, (array) $attributes);

        return $this;
    }

    /**
     * Append attributes to query when building a query.
     */
    public function append($attributes): self
    {
        $this->appends = array_merge(
            $this->appends,
            is_string($attributes) ? [$attributes] : $attributes
        );

        return $this;
    }

    /**
     * Set the accessors to be appended to model arrays.
     */
    public function setAppends(array $appends): self
    {
        $this->appends = $appends;

        return $this;
    }

    /**
     * Get the appendable attributes for the model.
     */
    public function getAppends(): array
    {
        return $this->appends;
    }
}
