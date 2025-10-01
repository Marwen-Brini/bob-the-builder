<?php

use Bob\Database\Eloquent\SoftDeletes;
use Bob\Database\Model;
use Mockery as m;

/**
 * Tests for SoftDeletes event registration methods
 * to cover lines 199-243
 */
class EventTestModel extends Model
{
    use SoftDeletes;

    protected string $table = 'test_table';

    // Track registered events
    protected static array $registeredEvents = [];

    protected static function registerModelEvent(string $event, $callback): void
    {
        static::$registeredEvents[$event] = $callback;
    }

    public static function getRegisteredEvents(): array
    {
        return static::$registeredEvents;
    }

    public static function clearRegisteredEvents(): void
    {
        static::$registeredEvents = [];
    }
}

beforeEach(function () {
    EventTestModel::clearRegisteredEvents();
});

afterEach(function () {
    m::close();
});

test('softDeleted registers trashed event', function () {
    $callback = function () {
        return 'soft deleted';
    };

    // Execute softDeleted - covers lines 199-200
    EventTestModel::softDeleted($callback);

    $events = EventTestModel::getRegisteredEvents();
    expect($events)->toHaveKey('trashed');
    expect($events['trashed'])->toBe($callback);
});

test('restoring registers restoring event', function () {
    $callback = function () {
        return 'restoring';
    };

    // Execute restoring - covers lines 209-211
    EventTestModel::restoring($callback);

    $events = EventTestModel::getRegisteredEvents();
    expect($events)->toHaveKey('restoring');
    expect($events['restoring'])->toBe($callback);
});

test('restored registers restored event', function () {
    $callback = function () {
        return 'restored';
    };

    // Execute restored - covers lines 219-221
    EventTestModel::restored($callback);

    $events = EventTestModel::getRegisteredEvents();
    expect($events)->toHaveKey('restored');
    expect($events['restored'])->toBe($callback);
});

test('forceDeleting registers forceDeleting event', function () {
    $callback = function () {
        return 'force deleting';
    };

    // Execute forceDeleting - covers lines 231-232
    EventTestModel::forceDeleting($callback);

    $events = EventTestModel::getRegisteredEvents();
    expect($events)->toHaveKey('forceDeleting');
    expect($events['forceDeleting'])->toBe($callback);
});

test('forceDeleted registers forceDeleted event', function () {
    $callback = function () {
        return 'force deleted';
    };

    // Execute forceDeleted - covers lines 241-243
    EventTestModel::forceDeleted($callback);

    $events = EventTestModel::getRegisteredEvents();
    expect($events)->toHaveKey('forceDeleted');
    expect($events['forceDeleted'])->toBe($callback);
});

test('multiple event registrations work independently', function () {
    $callbacks = [
        'soft' => fn () => 'soft',
        'restoring' => fn () => 'restoring',
        'restored' => fn () => 'restored',
        'deleting' => fn () => 'deleting',
        'deleted' => fn () => 'deleted',
    ];

    EventTestModel::softDeleted($callbacks['soft']);
    EventTestModel::restoring($callbacks['restoring']);
    EventTestModel::restored($callbacks['restored']);
    EventTestModel::forceDeleting($callbacks['deleting']);
    EventTestModel::forceDeleted($callbacks['deleted']);

    $events = EventTestModel::getRegisteredEvents();

    expect($events)->toHaveKey('trashed');
    expect($events)->toHaveKey('restoring');
    expect($events)->toHaveKey('restored');
    expect($events)->toHaveKey('forceDeleting');
    expect($events)->toHaveKey('forceDeleted');
    expect(count($events))->toBe(5);
});
