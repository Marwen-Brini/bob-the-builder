<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

use Bob\Database\Model;

// Test model class for force fill testing
class ForceFillTestModel extends Model
{
    protected string $table = 'test_models';

    // Define fillable attributes
    protected array $fillable = ['name', 'email'];

    // Define guarded attributes
    protected array $guarded = ['admin', 'role'];
}

test('fill respects fillable attributes', function () {
    $model = new ForceFillTestModel();

    $attributes = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'admin' => true,  // This is guarded
        'role' => 'superadmin'  // This is guarded
    ];

    $model->fill($attributes);

    // Only fillable attributes should be set
    expect($model->getAttribute('name'))->toBe('John Doe');
    expect($model->getAttribute('email'))->toBe('john@example.com');
    expect($model->getAttribute('admin'))->toBeNull();
    expect($model->getAttribute('role'))->toBeNull();
});

test('force fill bypasses mass assignment protection', function () {
    $model = new ForceFillTestModel();

    $attributes = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'admin' => true,  // This is guarded
        'role' => 'superadmin',  // This is guarded
        'random_field' => 'value'  // Not in fillable
    ];

    $model->forceFill($attributes);

    // All attributes should be set
    expect($model->getAttribute('name'))->toBe('John Doe');
    expect($model->getAttribute('email'))->toBe('john@example.com');
    expect($model->getAttribute('admin'))->toBeTrue();
    expect($model->getAttribute('role'))->toBe('superadmin');
    expect($model->getAttribute('random_field'))->toBe('value');
});

test('force fill returns model instance for chaining', function () {
    $model = new ForceFillTestModel();

    $result = $model->forceFill(['name' => 'Test']);

    // Should return the model instance for method chaining
    expect($result)->toBeInstanceOf(ForceFillTestModel::class);
    expect($result)->toBe($model);
});

test('force fill can be used with sync original', function () {
    $model = new ForceFillTestModel();

    $attributes = [
        'id' => 123,
        'name' => 'John Doe',
        'admin' => true,
        'created_at' => '2024-01-01 00:00:00'
    ];

    // This is the pattern from the workaround in the bug report
    $model->forceFill($attributes)->syncOriginal();

    // All attributes should be set
    expect($model->getAttribute('id'))->toBe(123);
    expect($model->getAttribute('name'))->toBe('John Doe');
    expect($model->getAttribute('admin'))->toBeTrue();
    expect($model->getAttribute('created_at'))->toBe('2024-01-01 00:00:00');

    // Should not be dirty after syncOriginal
    expect($model->getDirty())->toBeEmpty();
});

test('force fill with empty array', function () {
    $model = new ForceFillTestModel();
    $model->forceFill(['name' => 'Test']);

    // Force fill with empty array should not clear existing attributes
    $model->forceFill([]);

    expect($model->getAttribute('name'))->toBe('Test');
});

test('force fill overwrites existing attributes', function () {
    $model = new ForceFillTestModel();

    $model->forceFill(['name' => 'Original', 'email' => 'original@example.com']);
    $model->forceFill(['name' => 'Updated']);

    expect($model->getAttribute('name'))->toBe('Updated');
    expect($model->getAttribute('email'))->toBe('original@example.com');
});

// =============================================================================
// ORIGINAL PHPUNIT CODE (COMMENTED FOR REFERENCE)
// =============================================================================

/*
namespace Tests\Unit\Model;

use Bob\Database\Model;
use PHPUnit\Framework\TestCase;

class ForceFillTestModel extends Model
{
    protected string $table = 'test_models';

    // Define fillable attributes
    protected array $fillable = ['name', 'email'];

    // Define guarded attributes
    protected array $guarded = ['admin', 'role'];
}

class ForceFillTest extends TestCase
{
    public function testFillRespectssFillableAttributes()
    {
        $model = new ForceFillTestModel();

        $attributes = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'admin' => true,  // This is guarded
            'role' => 'superadmin'  // This is guarded
        ];

        $model->fill($attributes);

        // Only fillable attributes should be set
        $this->assertEquals('John Doe', $model->getAttribute('name'));
        $this->assertEquals('john@example.com', $model->getAttribute('email'));
        $this->assertNull($model->getAttribute('admin'));
        $this->assertNull($model->getAttribute('role'));
    }

    public function testForceFillBypassesMassAssignmentProtection()
    {
        $model = new ForceFillTestModel();

        $attributes = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'admin' => true,  // This is guarded
            'role' => 'superadmin',  // This is guarded
            'random_field' => 'value'  // Not in fillable
        ];

        $model->forceFill($attributes);

        // All attributes should be set
        $this->assertEquals('John Doe', $model->getAttribute('name'));
        $this->assertEquals('john@example.com', $model->getAttribute('email'));
        $this->assertTrue($model->getAttribute('admin'));
        $this->assertEquals('superadmin', $model->getAttribute('role'));
        $this->assertEquals('value', $model->getAttribute('random_field'));
    }

    public function testForceFillReturnsModelInstanceForChaining()
    {
        $model = new ForceFillTestModel();

        $result = $model->forceFill(['name' => 'Test']);

        // Should return the model instance for method chaining
        $this->assertInstanceOf(ForceFillTestModel::class, $result);
        $this->assertSame($model, $result);
    }

    public function testForceFillCanBeUsedWithSyncOriginal()
    {
        $model = new ForceFillTestModel();

        $attributes = [
            'id' => 123,
            'name' => 'John Doe',
            'admin' => true,
            'created_at' => '2024-01-01 00:00:00'
        ];

        // This is the pattern from the workaround in the bug report
        $model->forceFill($attributes)->syncOriginal();

        // All attributes should be set
        $this->assertEquals(123, $model->getAttribute('id'));
        $this->assertEquals('John Doe', $model->getAttribute('name'));
        $this->assertTrue($model->getAttribute('admin'));
        $this->assertEquals('2024-01-01 00:00:00', $model->getAttribute('created_at'));

        // Should not be dirty after syncOriginal
        $this->assertEmpty($model->getDirty());
    }

    public function testForceFillWithEmptyArray()
    {
        $model = new ForceFillTestModel();
        $model->forceFill(['name' => 'Test']);

        // Force fill with empty array should not clear existing attributes
        $model->forceFill([]);

        $this->assertEquals('Test', $model->getAttribute('name'));
    }

    public function testForceFillOverwritesExistingAttributes()
    {
        $model = new ForceFillTestModel();

        $model->forceFill(['name' => 'Original', 'email' => 'original@example.com']);
        $model->forceFill(['name' => 'Updated']);

        $this->assertEquals('Updated', $model->getAttribute('name'));
        $this->assertEquals('original@example.com', $model->getAttribute('email'));
    }
}
*/