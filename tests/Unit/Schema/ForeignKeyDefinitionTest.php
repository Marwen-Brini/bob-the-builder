<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Schema\ForeignKeyDefinition;

beforeEach(function () {
    $this->foreignKey = new ForeignKeyDefinition();
});

test('references with string column', function ()
    {
        $result = $this->foreignKey->references('id');

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->references)->toBe(['id']);
    });

test('references with array columns', function ()
    {
        $result = $this->foreignKey->references(['id', 'company_id']);

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->references)->toBe(['id', 'company_id']);
    });

test('on', function ()
    {
        $result = $this->foreignKey->on('users');

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->on)->toBe('users');
    });

test('onDelete', function ()
    {
        $result = $this->foreignKey->onDelete('cascade');

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onDelete)->toBe('cascade');
    });

test('cascade on delete', function ()
    {
        $result = $this->foreignKey->cascadeOnDelete();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onDelete)->toBe('cascade');
    });

test('restrict on delete', function ()
    {
        $result = $this->foreignKey->restrictOnDelete();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onDelete)->toBe('restrict');
    });

test('null on delete', function ()
    {
        $result = $this->foreignKey->nullOnDelete();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onDelete)->toBe('set null');
    });

test('no action on delete', function ()
    {
        $result = $this->foreignKey->noActionOnDelete();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onDelete)->toBe('no action');
    });

test('onUpdate', function ()
    {
        $result = $this->foreignKey->onUpdate('cascade');

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onUpdate)->toBe('cascade');
    });

test('cascade on update', function ()
    {
        $result = $this->foreignKey->cascadeOnUpdate();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onUpdate)->toBe('cascade');
    });

test('restrict on update', function ()
    {
        $result = $this->foreignKey->restrictOnUpdate();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onUpdate)->toBe('restrict');
    });

test('null on update', function ()
    {
        $result = $this->foreignKey->nullOnUpdate();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onUpdate)->toBe('set null');
    });

test('no action on update', function ()
    {
        $result = $this->foreignKey->noActionOnUpdate();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->onUpdate)->toBe('no action');
    });

test('deferrable', function ()
    {
        $result = $this->foreignKey->deferrable();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->deferrable)->toBeTrue();
    });

test('deferable with false', function ()
    {
        $result = $this->foreignKey->deferrable(false);

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->deferrable)->toBeFalse();
    });

test('initially deferred', function ()
    {
        $result = $this->foreignKey->initiallyDeferred();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->initiallyDeferred)->toBeTrue();
        expect($this->foreignKey->deferrable)->toBeTrue(); // Should also set deferrable to true
    });

test('initially deferred with false', function ()
    {
        $result = $this->foreignKey->initiallyDeferred(false);

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->initiallyDeferred)->toBeFalse();
        expect($this->foreignKey->deferrable)->toBeTrue(); // Should still set deferrable to true
    });

test('not valid', function ()
    {
        $result = $this->foreignKey->notValid();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->notValid)->toBeTrue();
    });

test('method chaining', function ()
    {
        $result = $this->foreignKey
            ->references('id')
            ->on('users')
            ->cascadeOnDelete()
            ->restrictOnUpdate()
            ->deferrable()
            ->initiallyDeferred()
            ->notValid();

        expect($result)->toBe($this->foreignKey);
        expect($this->foreignKey->references)->toBe(['id']);
        expect($this->foreignKey->on)->toBe('users');
        expect($this->foreignKey->onDelete)->toBe('cascade');
        expect($this->foreignKey->onUpdate)->toBe('restrict');
        expect($this->foreignKey->deferrable)->toBeTrue();
        expect($this->foreignKey->initiallyDeferred)->toBeTrue();
        expect($this->foreignKey->notValid)->toBeTrue();
    });

test('complex foreign key definition', function ()
    {
        $this->foreignKey
            ->references(['user_id', 'company_id'])
            ->on('user_companies')
            ->nullOnDelete()
            ->cascadeOnUpdate()
            ->deferrable(true)
            ->notValid();

        expect($this->foreignKey->references)->toBe(['user_id', 'company_id']);
        expect($this->foreignKey->on)->toBe('user_companies');
        expect($this->foreignKey->onDelete)->toBe('set null');
        expect($this->foreignKey->onUpdate)->toBe('cascade');
        expect($this->foreignKey->deferrable)->toBeTrue();
        expect($this->foreignKey->notValid)->toBeTrue();
    });

test('postgreSQL specific features', function ()
    {
        $this->foreignKey
            ->references('id')
            ->on('users')
            ->deferrable()
            ->initiallyDeferred()
            ->notValid();

        // Test that PostgreSQL-specific features are set correctly
        expect($this->foreignKey->deferrable)->toBeTrue();
        expect($this->foreignKey->initiallyDeferred)->toBeTrue();
        expect($this->foreignKey->notValid)->toBeTrue();
    });

test('overriding actions', function ()
    {
        // Test that later actions override earlier ones
        $this->foreignKey
            ->cascadeOnDelete()
            ->restrictOnDelete()
            ->nullOnUpdate()
            ->cascadeOnUpdate();

        expect($this->foreignKey->onDelete)->toBe('restrict');
        expect($this->foreignKey->onUpdate)->toBe('cascade');
    });

test('accessing properties directly', function ()
    {
        // Test that we can access properties directly (inherited from Fluent)
        $this->foreignKey->references('id');
        $this->foreignKey->on('users');

        expect($this->foreignKey->references)->toBe(['id']);
        expect($this->foreignKey->on)->toBe('users');
    });

test('inherited fluent behavior', function ()
    {
        // Test that ForeignKeyDefinition inherits Fluent behavior
        expect($this->foreignKey)->toBeInstanceOf(\Bob\Schema\Fluent::class);

        // Test magic property access
        $this->foreignKey->customProperty = 'test';
        expect($this->foreignKey->customProperty)->toBe('test');
    });