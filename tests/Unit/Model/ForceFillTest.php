<?php

namespace Tests\Unit\Model;

use Bob\Database\Model;
use PHPUnit\Framework\TestCase;

class TestModel extends Model
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
        $model = new TestModel();

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
        $model = new TestModel();

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
        $model = new TestModel();

        $result = $model->forceFill(['name' => 'Test']);

        // Should return the model instance for method chaining
        $this->assertInstanceOf(TestModel::class, $result);
        $this->assertSame($model, $result);
    }

    public function testForceFillCanBeUsedWithSyncOriginal()
    {
        $model = new TestModel();

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
        $model = new TestModel();
        $model->forceFill(['name' => 'Test']);

        // Force fill with empty array should not clear existing attributes
        $model->forceFill([]);

        $this->assertEquals('Test', $model->getAttribute('name'));
    }

    public function testForceFillOverwritesExistingAttributes()
    {
        $model = new TestModel();

        $model->forceFill(['name' => 'Original', 'email' => 'original@example.com']);
        $model->forceFill(['name' => 'Updated']);

        $this->assertEquals('Updated', $model->getAttribute('name'));
        $this->assertEquals('original@example.com', $model->getAttribute('email'));
    }
}