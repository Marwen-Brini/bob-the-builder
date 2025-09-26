<?php

namespace Bob\Database\Eloquent;

use Bob\Query\Builder;
use Bob\Database\Model;

/**
 * Interface for implementing query scopes that can be applied to Eloquent queries.
 * Scopes allow you to define common sets of constraints that may be easily re-used throughout your application.
 */
interface Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder  $builder
     * @param  Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void;
}