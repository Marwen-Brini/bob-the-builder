<?php

namespace Bob\Database;

use Bob\Contracts\ConnectionInterface;
use Bob\Query\Builder;

/**
 * Base Model class that provides ActiveRecord-like functionality
 * Combines global extensions (via Macroable) with model-specific methods
 */
abstract class Model
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
     * The model's attributes
     */
    protected array $attributes = [];

    /**
     * The model's original attributes
     */
    protected array $original = [];

    /**
     * Set the global connection for all models
     */
    public static function setConnection(ConnectionInterface $connection): void
    {
        static::$connection = $connection;
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

        return static::getConnection()->table($instance->getTable());
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
        $this->fill($attributes);
        $this->original = $this->attributes;
    }

    /**
     * Fill the model with attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
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
     * Get an attribute from the model
     */
    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Save the model to the database
     */
    public function save(): bool
    {
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
            $this->original = $this->attributes;

            return true;
        }

        return false;
    }

    /**
     * Update the model in the database
     */
    protected function update(): bool
    {
        if ($this->timestamps) {
            $this->setAttribute($this->updatedAt, date('Y-m-d H:i:s'));
        }

        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        $updated = static::query()
            ->where($this->primaryKey, $this->getAttribute($this->primaryKey))
            ->update($dirty);

        if ($updated) {
            $this->original = $this->attributes;

            return true;
        }

        return false;
    }

    /**
     * Delete the model from the database
     */
    public function delete(): bool
    {
        if (! $this->exists()) {
            return false;
        }

        return (bool) static::query()
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
        $result = static::query()
            ->where($instance->primaryKey, $id)
            ->first();

        return $result ? static::hydrate($result) : null;
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

        return $model;
    }

    /**
     * Get all models from the database
     */
    public static function all(): array
    {
        $results = static::query()->get();

        return static::hydrateMany($results);
    }

    /**
     * Get the first model matching the conditions
     */
    public static function first(): ?self
    {
        $result = static::query()->first();

        return $result ? static::hydrate($result) : null;
    }

    /**
     * Create a model instance from database result
     */
    protected static function hydrate($data): self
    {
        $attributes = is_object($data) ? (array) $data : $data;
        $model = new static($attributes);
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
        return $this->attributes;
    }

    /**
     * Convert the model to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
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
        if (method_exists($instance, $method)) {
            // If it's a custom finder method, call it
            return $instance->$method(...$arguments);
        }

        // Check for scope methods (scopeMethodName becomes methodName)
        $scopeMethod = 'scope'.ucfirst($method);
        if (method_exists($instance, $scopeMethod)) {
            $query = static::query();
            $instance->$scopeMethod($query, ...$arguments);

            return $query;
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
}
