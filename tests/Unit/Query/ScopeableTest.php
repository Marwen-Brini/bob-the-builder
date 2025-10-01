<?php

use Bob\Query\Scopeable;

// Create a test class that uses the trait
class TestScopeable
{
    use Scopeable;

    public array $conditions = [];

    public array $orders = [];

    public ?int $limitValue = null;

    public function where(string $column, $value): self
    {
        $this->conditions[] = [$column, '=', $value];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = [$column, $direction];

        return $this;
    }

    public function limit(int $value): self
    {
        $this->limitValue = $value;

        return $this;
    }
}

beforeEach(function () {
    TestScopeable::clearScopes();
    $this->instance = new TestScopeable;
});

afterEach(function () {
    TestScopeable::clearScopes();
});

test('scope registers a local scope', function () {
    TestScopeable::scope('active', function () {
        $this->where('active', true);
    });

    expect(TestScopeable::hasLocalScope('active'))->toBeTrue();
    expect(TestScopeable::hasScope('active'))->toBeTrue();
});

test('globalScope registers a global scope', function () {
    TestScopeable::globalScope('notDeleted', function () {
        $this->where('deleted_at', null);
    });

    expect(TestScopeable::hasGlobalScope('notDeleted'))->toBeTrue();
    expect(TestScopeable::hasScope('notDeleted'))->toBeTrue();
});

test('withScope applies a local scope', function () {
    TestScopeable::scope('active', function () {
        $this->where('active', true);
    });

    $this->instance->withScope('active');

    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->conditions[0])->toBe(['active', '=', true]);
    expect($this->instance->hasAppliedScope('active'))->toBeTrue();
});

test('withScope with parameters', function () {
    TestScopeable::scope('ofType', function ($type) {
        $this->where('type', $type);
    });

    $this->instance->withScope('ofType', 'premium');

    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->conditions[0])->toBe(['type', '=', 'premium']);
});

test('withScope throws exception for non-existent scope', function () {
    expect(fn () => $this->instance->withScope('nonExistent'))
        ->toThrow(InvalidArgumentException::class, 'Scope [nonExistent] not found.');
});

test('withScopes applies multiple scopes', function () {
    TestScopeable::scope('active', function () {
        $this->where('active', true);
    });

    TestScopeable::scope('recent', function () {
        $this->orderBy('created_at', 'desc');
    });

    $this->instance->withScopes(['active', 'recent']);

    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->orders)->toHaveCount(1);
    expect($this->instance->hasAppliedScope('active'))->toBeTrue();
    expect($this->instance->hasAppliedScope('recent'))->toBeTrue();
});

test('withScopes with parameters', function () {
    TestScopeable::scope('ofType', function ($type) {
        $this->where('type', $type);
    });

    TestScopeable::scope('limitTo', function ($limit) {
        $this->limit($limit);
    });

    $this->instance->withScopes([
        'ofType' => 'premium',
        'limitTo' => [10],
    ]);

    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->conditions[0])->toBe(['type', '=', 'premium']);
    expect($this->instance->limitValue)->toBe(10);
});

test('applyGlobalScopes applies all global scopes', function () {
    TestScopeable::globalScope('notDeleted', function () {
        $this->where('deleted_at', null);
    });

    TestScopeable::globalScope('published', function () {
        $this->where('published', true);
    });

    $this->instance->applyGlobalScopes();

    expect($this->instance->conditions)->toHaveCount(2);
    expect($this->instance->conditions[0])->toBe(['deleted_at', '=', null]);
    expect($this->instance->conditions[1])->toBe(['published', '=', true]);
});

test('withoutGlobalScope removes a global scope', function () {
    TestScopeable::globalScope('notDeleted', function () {
        $this->where('deleted_at', null);
    });

    TestScopeable::globalScope('published', function () {
        $this->where('published', true);
    });

    $this->instance->withoutGlobalScope('notDeleted')->applyGlobalScopes();

    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->conditions[0])->toBe(['published', '=', true]);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($this->instance);
    $method = $reflection->getMethod('shouldSkipGlobalScope');
    $method->setAccessible(true);
    expect($method->invoke($this->instance, 'notDeleted'))->toBeTrue();
});

test('withoutGlobalScopes removes all global scopes', function () {
    TestScopeable::globalScope('notDeleted', function () {
        $this->where('deleted_at', null);
    });

    TestScopeable::globalScope('published', function () {
        $this->where('published', true);
    });

    $this->instance->withoutGlobalScopes()->applyGlobalScopes();

    expect($this->instance->conditions)->toHaveCount(0);
});

test('withoutGlobalScopes with specific scopes', function () {
    TestScopeable::globalScope('scope1', function () {
        $this->where('field1', 'value1');
    });

    TestScopeable::globalScope('scope2', function () {
        $this->where('field2', 'value2');
    });

    TestScopeable::globalScope('scope3', function () {
        $this->where('field3', 'value3');
    });

    $this->instance->withoutGlobalScopes(['scope1', 'scope3'])->applyGlobalScopes();

    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->conditions[0])->toBe(['field2', '=', 'value2']);
});

test('hasLocalScope returns correct values', function () {
    TestScopeable::scope('local', function () {});

    expect(TestScopeable::hasLocalScope('local'))->toBeTrue();
    expect(TestScopeable::hasLocalScope('nonExistent'))->toBeFalse();
});

test('hasGlobalScope returns correct values', function () {
    TestScopeable::globalScope('global', function () {});

    expect(TestScopeable::hasGlobalScope('global'))->toBeTrue();
    expect(TestScopeable::hasGlobalScope('nonExistent'))->toBeFalse();
});

test('hasScope checks both local and global scopes', function () {
    TestScopeable::scope('local', function () {});
    TestScopeable::globalScope('global', function () {});

    expect(TestScopeable::hasScope('local'))->toBeTrue();
    expect(TestScopeable::hasScope('global'))->toBeTrue();
    expect(TestScopeable::hasScope('nonExistent'))->toBeFalse();
});

test('getLocalScope returns scope callback', function () {
    $callback = function () {
        return 'local';
    };
    TestScopeable::scope('local', $callback);

    expect(TestScopeable::getLocalScope('local'))->toBe($callback);
    expect(TestScopeable::getLocalScope('nonExistent'))->toBeNull();
});

test('getGlobalScope returns scope callback', function () {
    $callback = function () {
        return 'global';
    };
    TestScopeable::globalScope('global', $callback);

    expect(TestScopeable::getGlobalScope('global'))->toBe($callback);
    expect(TestScopeable::getGlobalScope('nonExistent'))->toBeNull();
});

test('removeScope removes both local and global scopes', function () {
    TestScopeable::scope('test', function () {});
    TestScopeable::globalScope('test', function () {});

    expect(TestScopeable::hasScope('test'))->toBeTrue();

    $removed = TestScopeable::removeScope('test');

    expect($removed)->toBeTrue();
    expect(TestScopeable::hasLocalScope('test'))->toBeFalse();
    expect(TestScopeable::hasGlobalScope('test'))->toBeFalse();
});

test('removeScope returns false for non-existent scope', function () {
    expect(TestScopeable::removeScope('nonExistent'))->toBeFalse();
});

test('clearScopes removes all scopes', function () {
    TestScopeable::scope('local1', function () {});
    TestScopeable::scope('local2', function () {});
    TestScopeable::globalScope('global1', function () {});
    TestScopeable::globalScope('global2', function () {});

    TestScopeable::clearScopes();

    $scopes = TestScopeable::getScopes();
    expect($scopes['local'])->toBeEmpty();
    expect($scopes['global'])->toBeEmpty();
});

test('clearLocalScopes removes only local scopes', function () {
    TestScopeable::scope('local', function () {});
    TestScopeable::globalScope('global', function () {});

    TestScopeable::clearLocalScopes();

    expect(TestScopeable::hasLocalScope('local'))->toBeFalse();
    expect(TestScopeable::hasGlobalScope('global'))->toBeTrue();
});

test('clearGlobalScopes removes only global scopes', function () {
    TestScopeable::scope('local', function () {});
    TestScopeable::globalScope('global', function () {});

    TestScopeable::clearGlobalScopes();

    expect(TestScopeable::hasLocalScope('local'))->toBeTrue();
    expect(TestScopeable::hasGlobalScope('global'))->toBeFalse();
});

test('getScopes returns all registered scopes', function () {
    $localCallback = function () {
        return 'local';
    };
    $globalCallback = function () {
        return 'global';
    };

    TestScopeable::scope('localScope', $localCallback);
    TestScopeable::globalScope('globalScope', $globalCallback);

    $scopes = TestScopeable::getScopes();

    expect($scopes)->toHaveKey('local');
    expect($scopes)->toHaveKey('global');
    expect($scopes['local'])->toHaveKey('localScope');
    expect($scopes['global'])->toHaveKey('globalScope');
});

test('getAppliedScopes returns applied scopes', function () {
    TestScopeable::scope('scope1', function () {});
    TestScopeable::scope('scope2', function () {});

    $this->instance->withScope('scope1')->withScope('scope2');

    $applied = $this->instance->getAppliedScopes();
    expect($applied)->toContain('scope1');
    expect($applied)->toContain('scope2');
});

test('hasAppliedScope checks if scope is applied', function () {
    TestScopeable::scope('test', function () {});

    $this->instance->withScope('test');

    expect($this->instance->hasAppliedScope('test'))->toBeTrue();
    expect($this->instance->hasAppliedScope('other'))->toBeFalse();
});

test('resetAppliedScopes clears applied scopes', function () {
    TestScopeable::scope('test', function () {});

    $this->instance->withScope('test');
    expect($this->instance->getAppliedScopes())->not->toBeEmpty();

    $this->instance->resetAppliedScopes();
    expect($this->instance->getAppliedScopes())->toBeEmpty();
});

test('scope with non-closure callable works', function () {
    $callable = [new class
    {
        public function handle($query)
        {
            $query->where('callable', true);
        }
    }, 'handle'];

    TestScopeable::scope('callableScope', $callable);

    $this->instance->withScope('callableScope');

    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->conditions[0])->toBe(['callable', '=', true]);
});

test('global scope with non-closure callable works', function () {
    $callable = [new class
    {
        public function apply($query)
        {
            $query->where('global_callable', true);
        }
    }, 'apply'];

    TestScopeable::globalScope('callableGlobal', $callable);

    $this->instance->applyGlobalScopes();

    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->conditions[0])->toBe(['global_callable', '=', true]);
});

test('scope can access instance properties and methods', function () {
    TestScopeable::scope('complex', function () {
        $this->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(10);
    });

    $this->instance->withScope('complex');

    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->orders)->toHaveCount(1);
    expect($this->instance->limitValue)->toBe(10);
});

test('multiple parameters in scope', function () {
    TestScopeable::scope('between', function ($min, $max) {
        $this->where('value', $min)->where('value', $max);
    });

    $this->instance->withScope('between', 10, 100);

    expect($this->instance->conditions)->toHaveCount(2);
    expect($this->instance->conditions[0])->toBe(['value', '=', 10]);
    expect($this->instance->conditions[1])->toBe(['value', '=', 100]);
});

test('chaining scope applications', function () {
    TestScopeable::scope('active', function () {
        $this->where('active', true);
    });

    TestScopeable::scope('recent', function () {
        $this->orderBy('created_at', 'desc');
    });

    $result = $this->instance->withScope('active')->withScope('recent');

    expect($result)->toBe($this->instance);
    expect($this->instance->conditions)->toHaveCount(1);
    expect($this->instance->orders)->toHaveCount(1);
});

test('applying same scope twice is prevented', function () {
    TestScopeable::scope('test', function () {
        $this->where('test', true);
    });

    $this->instance->withScope('test');

    // Record should prevent duplicate
    $applied = $this->instance->getAppliedScopes();
    expect($applied)->toHaveCount(1);

    // Manually calling recordAppliedScope again shouldn't duplicate
    $reflection = new ReflectionClass($this->instance);
    $method = $reflection->getMethod('recordAppliedScope');
    $method->setAccessible(true);
    $method->invoke($this->instance, 'test');

    $applied = $this->instance->getAppliedScopes();
    expect($applied)->toHaveCount(1);
});

test('removing same global scope twice is prevented', function () {
    $this->instance->withoutGlobalScope('test');

    $applied = $this->instance->getAppliedScopes();
    expect($applied)->toContain('!test');
    expect(count(array_filter($applied, fn ($s) => $s === '!test')))->toBe(1);

    // Manually calling recordRemovedScope again shouldn't duplicate
    $reflection = new ReflectionClass($this->instance);
    $method = $reflection->getMethod('recordRemovedScope');
    $method->setAccessible(true);
    $method->invoke($this->instance, 'test');

    $applied = $this->instance->getAppliedScopes();
    expect(count(array_filter($applied, fn ($s) => $s === '!test')))->toBe(1);
});
