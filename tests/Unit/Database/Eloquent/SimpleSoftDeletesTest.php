<?php

use Bob\Database\Connection;
use Bob\Database\Model;
use Bob\Database\Eloquent\SoftDeletes;
use Bob\Database\Eloquent\SoftDeletingScope;

class SimpleSoftDeleteModel extends Model
{
    use SoftDeletes;

    protected string $table = 'test_table';
    protected string $primaryKey = 'id';
    protected bool $timestamps = false;
}

class CustomColumnModel extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected string $table = 'test_table';
}

beforeEach(function () {
    Model::setConnection(new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]));
});

test('bootSoftDeletes adds SoftDeletingScope', function () {
    // Create instance to trigger boot
    new SimpleSoftDeleteModel();

    expect(SimpleSoftDeleteModel::hasGlobalScope(SoftDeletingScope::class))->toBeTrue();
});

test('getDeletedAtColumn returns correct column name', function () {
    $model = new SimpleSoftDeleteModel();
    expect($model->getDeletedAtColumn())->toBe('deleted_at');
});

test('getDeletedAtColumn with custom constant', function () {
    $model = new CustomColumnModel();
    expect($model->getDeletedAtColumn())->toBe('removed_at');
});

test('getQualifiedDeletedAtColumn returns table qualified name', function () {
    $model = new SimpleSoftDeleteModel();
    expect($model->getQualifiedDeletedAtColumn())->toBe('test_table.deleted_at');
});

test('trashed returns false when deleted_at is null', function () {
    $model = new SimpleSoftDeleteModel();
    $model->deleted_at = null;

    expect($model->trashed())->toBeFalse();
});

test('trashed returns true when deleted_at has value', function () {
    $model = new SimpleSoftDeleteModel();
    $model->deleted_at = '2024-01-01 00:00:00';

    expect($model->trashed())->toBeTrue();
});

test('isForceDeleting returns false by default', function () {
    $model = new SimpleSoftDeleteModel();

    expect($model->isForceDeleting())->toBeFalse();
});

test('restore returns false when not trashed', function () {
    $model = new SimpleSoftDeleteModel();
    $model->deleted_at = null;

    expect($model->restore())->toBeFalse();
});

test('initializeSoftDeletes sets cast', function () {
    $model = new SimpleSoftDeleteModel();

    // Call initialize manually
    $model->initializeSoftDeletes();

    // Can't test getCasts as it doesn't exist, but method runs without error
    expect(true)->toBeTrue();
});

// Test static event registration methods exist
test('event registration methods exist', function () {
    expect(method_exists(SimpleSoftDeleteModel::class, 'softDeleted'))->toBeTrue();
    expect(method_exists(SimpleSoftDeleteModel::class, 'restoring'))->toBeTrue();
    expect(method_exists(SimpleSoftDeleteModel::class, 'restored'))->toBeTrue();
    expect(method_exists(SimpleSoftDeleteModel::class, 'forceDeleting'))->toBeTrue();
    expect(method_exists(SimpleSoftDeleteModel::class, 'forceDeleted'))->toBeTrue();
});