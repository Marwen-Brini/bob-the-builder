<?php

use Bob\Query\Macroable;

// Create a test class that uses the trait
class TestMacroable
{
    use Macroable;

    public string $name = 'test';

    public function existingMethod(): string
    {
        return 'existing';
    }

    protected function protectedMethod(): string
    {
        return 'protected';
    }
}

// Create a mixin class for testing mixinClass
class TestMixin
{
    public function mixinMethod(): string
    {
        return 'from mixin';
    }

    public function anotherMethod(string $arg): string
    {
        return 'mixin: '.$arg;
    }

    protected function protectedMixinMethod(): string
    {
        return 'protected mixin';
    }

    public function __construct()
    {
        // Constructor should be ignored
    }

    public function __destruct()
    {
        // Destructor should be ignored
    }
}

beforeEach(function () {
    TestMacroable::clearMacros();
    $this->instance = new TestMacroable;
});

afterEach(function () {
    TestMacroable::clearMacros();
});

test('macro registers a custom method', function () {
    TestMacroable::macro('customMethod', function () {
        return 'custom value';
    });

    expect(TestMacroable::hasMacro('customMethod'))->toBeTrue();
    expect($this->instance->customMethod())->toBe('custom value');
});

test('macro with parameters works correctly', function () {
    TestMacroable::macro('greet', function ($name) {
        return 'Hello, '.$name;
    });

    expect($this->instance->greet('World'))->toBe('Hello, World');
});

test('macro can access instance properties', function () {
    TestMacroable::macro('getName', function () {
        return $this->name;
    });

    expect($this->instance->getName())->toBe('test');
});

test('macro can call instance methods', function () {
    TestMacroable::macro('callExisting', function () {
        return $this->existingMethod();
    });

    expect($this->instance->callExisting())->toBe('existing');
});

test('static macro works correctly', function () {
    TestMacroable::macro('staticMethod', function () {
        return 'static result';
    });

    expect(TestMacroable::staticMethod())->toBe('static result');
});

test('mixin registers multiple macros', function () {
    TestMacroable::mixin([
        'method1' => function () {
            return 'one';
        },
        'method2' => function () {
            return 'two';
        },
        'method3' => function () {
            return 'three';
        },
    ]);

    expect(TestMacroable::hasMacro('method1'))->toBeTrue();
    expect(TestMacroable::hasMacro('method2'))->toBeTrue();
    expect(TestMacroable::hasMacro('method3'))->toBeTrue();
    expect($this->instance->method1())->toBe('one');
    expect($this->instance->method2())->toBe('two');
    expect($this->instance->method3())->toBe('three');
});

test('mixinClass registers methods from a class', function () {
    TestMacroable::mixinClass(new TestMixin);

    expect(TestMacroable::hasMacro('mixinMethod'))->toBeTrue();
    expect(TestMacroable::hasMacro('anotherMethod'))->toBeTrue();
    expect(TestMacroable::hasMacro('protectedMixinMethod'))->toBeTrue();
    expect(TestMacroable::hasMacro('__construct'))->toBeFalse();
    expect(TestMacroable::hasMacro('__destruct'))->toBeFalse();

    expect($this->instance->mixinMethod())->toBe('from mixin');
    expect($this->instance->anotherMethod('test'))->toBe('mixin: test');
});

test('mixinClass with string class name', function () {
    TestMacroable::mixinClass(TestMixin::class);

    expect(TestMacroable::hasMacro('mixinMethod'))->toBeTrue();
    expect($this->instance->mixinMethod())->toBe('from mixin');
});

test('mixinClass with replace false does not override existing macros', function () {
    TestMacroable::macro('mixinMethod', function () {
        return 'original';
    });

    TestMacroable::mixinClass(new TestMixin, false);

    expect($this->instance->mixinMethod())->toBe('original');
});

test('mixinClass with replace true overrides existing macros', function () {
    TestMacroable::macro('mixinMethod', function () {
        return 'original';
    });

    TestMacroable::mixinClass(new TestMixin, true);

    expect($this->instance->mixinMethod())->toBe('from mixin');
});

test('hasMacro returns false for non-existent macro', function () {
    expect(TestMacroable::hasMacro('nonExistent'))->toBeFalse();
});

test('removeMacro removes a macro and returns true', function () {
    TestMacroable::macro('temp', function () {
        return 'temp';
    });

    expect(TestMacroable::hasMacro('temp'))->toBeTrue();
    expect(TestMacroable::removeMacro('temp'))->toBeTrue();
    expect(TestMacroable::hasMacro('temp'))->toBeFalse();
});

test('removeMacro returns false for non-existent macro', function () {
    expect(TestMacroable::removeMacro('nonExistent'))->toBeFalse();
});

test('clearMacros removes all macros', function () {
    TestMacroable::macro('macro1', function () {
        return '1';
    });
    TestMacroable::macro('macro2', function () {
        return '2';
    });

    expect(TestMacroable::hasMacro('macro1'))->toBeTrue();
    expect(TestMacroable::hasMacro('macro2'))->toBeTrue();

    TestMacroable::clearMacros();

    expect(TestMacroable::hasMacro('macro1'))->toBeFalse();
    expect(TestMacroable::hasMacro('macro2'))->toBeFalse();
});

test('getMacros returns all registered macros', function () {
    $macro1 = function () {
        return '1';
    };
    $macro2 = function () {
        return '2';
    };

    TestMacroable::macro('macro1', $macro1);
    TestMacroable::macro('macro2', $macro2);

    $macros = TestMacroable::getMacros();

    expect($macros)->toHaveCount(2);
    expect($macros)->toHaveKey('macro1');
    expect($macros)->toHaveKey('macro2');
});

test('getMacro returns specific macro', function () {
    $macro = function () {
        return 'test';
    };
    TestMacroable::macro('testMacro', $macro);

    expect(TestMacroable::getMacro('testMacro'))->toBe($macro);
    expect(TestMacroable::getMacro('nonExistent'))->toBeNull();
});

test('__call throws exception for non-existent method', function () {
    expect(fn () => $this->instance->nonExistentMethod())
        ->toThrow(BadMethodCallException::class, 'Method TestMacroable::nonExistentMethod does not exist.');
});

test('__callStatic throws exception for non-existent static method', function () {
    expect(fn () => TestMacroable::nonExistentStaticMethod())
        ->toThrow(BadMethodCallException::class, 'Method TestMacroable::nonExistentStaticMethod does not exist.');
});

test('macro with non-closure callable works', function () {
    // Test with a regular callable array
    $callable = [new TestMixin, 'mixinMethod'];
    TestMacroable::macro('callableMethod', $callable);

    expect($this->instance->callableMethod())->toBe('from mixin');
});

test('static macro with non-closure callable works', function () {
    $callable = [new TestMixin, 'mixinMethod'];
    TestMacroable::macro('staticCallable', $callable);

    expect(TestMacroable::staticCallable())->toBe('from mixin');
});

test('macro returns various types correctly', function () {
    // Return null
    TestMacroable::macro('returnNull', function () {
        return null;
    });
    expect($this->instance->returnNull())->toBeNull();

    // Return array
    TestMacroable::macro('returnArray', function () {
        return [1, 2, 3];
    });
    expect($this->instance->returnArray())->toBe([1, 2, 3]);

    // Return object
    TestMacroable::macro('returnObject', function () {
        return (object) ['key' => 'value'];
    });
    $result = $this->instance->returnObject();
    expect($result)->toBeObject();
    expect($result->key)->toBe('value');

    // Return boolean
    TestMacroable::macro('returnBool', function () {
        return true;
    });
    expect($this->instance->returnBool())->toBeTrue();
});

test('macro with multiple parameters works', function () {
    TestMacroable::macro('sum', function ($a, $b, $c = 0) {
        return $a + $b + $c;
    });

    expect($this->instance->sum(1, 2))->toBe(3);
    expect($this->instance->sum(1, 2, 3))->toBe(6);
});

test('macro can modify instance state', function () {
    TestMacroable::macro('setName', function ($newName) {
        $this->name = $newName;

        return $this;
    });

    TestMacroable::macro('getName', function () {
        return $this->name;
    });

    $this->instance->setName('changed');
    expect($this->instance->getName())->toBe('changed');
});

test('chaining macros works', function () {
    TestMacroable::macro('chain1', function () {
        $this->name = 'chain1';

        return $this;
    });

    TestMacroable::macro('chain2', function () {
        $this->name .= '-chain2';

        return $this;
    });

    TestMacroable::macro('getResult', function () {
        return $this->name;
    });

    $result = $this->instance->chain1()->chain2()->getResult();
    expect($result)->toBe('chain1-chain2');
});

test('macro with variadic parameters', function () {
    TestMacroable::macro('concat', function (...$strings) {
        return implode(' ', $strings);
    });

    expect($this->instance->concat('Hello', 'World'))->toBe('Hello World');
    expect($this->instance->concat('One', 'Two', 'Three'))->toBe('One Two Three');
});

test('static macro in static context works', function () {
    TestMacroable::macro('staticTest', function () {
        // In PHP, static context still has $this available if called from instance
        return 'result';
    });

    expect(TestMacroable::staticTest())->toBe('result');
    expect($this->instance->staticTest())->toBe('result');
});

test('invokeMacro handles closures properly', function () {
    $reflection = new ReflectionClass($this->instance);
    $method = $reflection->getMethod('invokeMacro');
    $method->setAccessible(true);

    $closure = function () {
        return $this->name ?? 'no instance';
    };

    $result = $method->invoke($this->instance, $closure, []);
    expect($result)->toBe('test');
});

test('invokeStaticMacro handles closures properly', function () {
    $reflection = new ReflectionClass(TestMacroable::class);
    $method = $reflection->getMethod('invokeStaticMacro');
    $method->setAccessible(true);

    $closure = function () {
        return 'static result';
    };

    $result = $method->invoke(null, $closure, []);
    expect($result)->toBe('static result');
});

test('invokeStaticMacro handles non-closure callables', function () {
    $reflection = new ReflectionClass(TestMacroable::class);
    $method = $reflection->getMethod('invokeStaticMacro');
    $method->setAccessible(true);

    $callable = [new TestMixin, 'mixinMethod'];

    $result = $method->invoke(null, $callable, []);
    expect($result)->toBe('from mixin');
});
