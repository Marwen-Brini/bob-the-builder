<?php

use Bob\Query\Macroable;

describe('Macroable trait', function () {
    beforeEach(function () {
        // Create a test class that uses Macroable trait
        $this->testClass = new class {
            use Macroable;
            
            public $name = 'test';
            
            public function getName(): string 
            {
                return $this->name;
            }
        };
        
        // Clear any existing macros before each test
        $this->testClass::clearMacros();
    });
    
    afterEach(function () {
        // Clean up macros after each test
        $this->testClass::clearMacros();
    });
    
    it('can register and use a basic macro', function () {
        $this->testClass::macro('customMethod', function () {
            return 'macro result';
        });
        
        expect($this->testClass->customMethod())->toBe('macro result');
    });
    
    it('can register macro with parameters', function () {
        $this->testClass::macro('greet', function (string $name) {
            return "Hello, {$name}!";
        });
        
        expect($this->testClass->greet('World'))->toBe('Hello, World!');
    });
    
    it('can access instance properties in macros', function () {
        $this->testClass::macro('getNameUpper', function () {
            return strtoupper($this->name);
        });
        
        expect($this->testClass->getNameUpper())->toBe('TEST');
    });
    
    it('can access instance methods in macros', function () {
        $this->testClass::macro('getNamePrefixed', function (string $prefix) {
            return $prefix . ': ' . $this->getName();
        });
        
        expect($this->testClass->getNamePrefixed('Name'))->toBe('Name: test');
    });
    
    it('can register multiple macros with mixin', function () {
        $macros = [
            'first' => function () {
                return 'first';
            },
            'second' => function () {
                return 'second';
            }
        ];
        
        $this->testClass::mixin($macros);
        
        expect($this->testClass->first())->toBe('first');
        expect($this->testClass->second())->toBe('second');
    });
    
    it('can check if macro exists with hasMacro', function () {
        expect($this->testClass::hasMacro('nonexistent'))->toBeFalse();
        
        $this->testClass::macro('exists', function () {
            return true;
        });
        
        expect($this->testClass::hasMacro('exists'))->toBeTrue();
    });
    
    it('can remove macros with removeMacro', function () {
        $this->testClass::macro('removable', function () {
            return 'removable';
        });
        
        expect($this->testClass::hasMacro('removable'))->toBeTrue();
        
        $this->testClass::removeMacro('removable');
        
        expect($this->testClass::hasMacro('removable'))->toBeFalse();
    });
    
    it('can clear all macros with clearMacros', function () {
        $this->testClass::macro('first', function () {
            return 'first';
        });
        $this->testClass::macro('second', function () {
            return 'second';
        });
        
        expect(count($this->testClass::getMacros()))->toBe(2);
        
        $this->testClass::clearMacros();
        
        expect(count($this->testClass::getMacros()))->toBe(0);
    });
    
    it('can get all macros with getMacros', function () {
        $macro1 = function () {
            return 'first';
        };
        $macro2 = function () {
            return 'second';
        };
        
        $this->testClass::macro('first', $macro1);
        $this->testClass::macro('second', $macro2);
        
        $macros = $this->testClass::getMacros();
        
        expect($macros)->toHaveKey('first');
        expect($macros)->toHaveKey('second');
        expect($macros['first'])->toBe($macro1);
        expect($macros['second'])->toBe($macro2);
    });
    
    // Lines 83-89: __call method when macro doesn't exist
    it('throws BadMethodCallException for non-existent macro', function () {
        expect(fn() => $this->testClass->nonExistentMethod())
            ->toThrow(BadMethodCallException::class, 'does not exist');
    });
    
    // Lines 91-97: __call method when macro exists and is Closure
    it('handles __call method with existing macro', function () {
        $this->testClass::macro('testCall', function (string $param) {
            return "called with: {$param}";
        });
        
        $result = $this->testClass->testCall('parameter');
        expect($result)->toBe('called with: parameter');
    });
    
    // Test closure binding in __call (line 94)
    it('binds closure context correctly in __call', function () {
        $this->testClass::macro('accessThis', function () {
            return $this->name; // Access instance property
        });
        
        $result = $this->testClass->accessThis();
        expect($result)->toBe('test');
    });
    
    // Lines 109-115: __callStatic method when macro doesn't exist
    it('throws BadMethodCallException for non-existent static macro', function () {
        $testClassName = get_class($this->testClass);
        
        expect(fn() => $testClassName::nonExistentStaticMethod())
            ->toThrow(BadMethodCallException::class, 'does not exist');
    });
    
    // Lines 117-124: __callStatic method when macro exists and is Closure
    it('handles __callStatic method with existing macro', function () {
        $this->testClass::macro('staticTest', function (string $param) {
            return "static called with: {$param}";
        });
        
        $testClassName = get_class($this->testClass);
        $result = $testClassName::staticTest('static parameter');
        expect($result)->toBe('static called with: static parameter');
    });
    
    // Test closure binding in __callStatic (line 120)
    it('binds closure context correctly in __callStatic', function () {
        $this->testClass::macro('staticClosureTest', function (string $suffix) {
            return "static: {$suffix}";
        });
        
        $testClassName = get_class($this->testClass);
        $result = $testClassName::staticClosureTest('test');
        expect($result)->toBe('static: test');
    });
    
    // Test non-Closure callable (edge case)
    it('handles non-Closure macros', function () {
        // Create a test class with an invokable object
        $invokable = new class {
            public function __invoke() {
                return 'invokable result';
            }
        };
        
        // Register as macro (this would be unusual but possible)
        $reflection = new ReflectionClass($this->testClass);
        $macrosProperty = $reflection->getProperty('macros');
        $macrosProperty->setAccessible(true);
        
        $macros = $macrosProperty->getValue();
        $macros['invokableTest'] = $invokable;
        $macrosProperty->setValue(null, $macros);
        
        // This should work even though it's not a Closure
        $result = $this->testClass->invokableTest();
        expect($result)->toBe('invokable result');
    });
    
    it('maintains separate macro namespaces between different classes', function () {
        // Create second test class
        $testClass2 = new class {
            use Macroable;
        };
        
        $testClass2::clearMacros();
        
        // Register different macros on each class
        $this->testClass::macro('classOne', function () {
            return 'class one';
        });
        
        $testClass2::macro('classTwo', function () {
            return 'class two';
        });
        
        // Each class should only have its own macro
        expect($this->testClass::hasMacro('classOne'))->toBeTrue();
        expect($this->testClass::hasMacro('classTwo'))->toBeFalse();
        
        expect($testClass2::hasMacro('classOne'))->toBeFalse();
        expect($testClass2::hasMacro('classTwo'))->toBeTrue();
        
        // Clean up
        $testClass2::clearMacros();
    });
    
    it('preserves macro parameters and return values', function () {
        $this->testClass::macro('calculate', function (int $a, int $b, string $operation = 'add') {
            return match ($operation) {
                'add' => $a + $b,
                'multiply' => $a * $b,
                'subtract' => $a - $b,
                default => 0
            };
        });
        
        expect($this->testClass->calculate(5, 3))->toBe(8);
        expect($this->testClass->calculate(5, 3, 'multiply'))->toBe(15);
        expect($this->testClass->calculate(5, 3, 'subtract'))->toBe(2);
    });
    
    it('handles macros with complex return types', function () {
        $this->testClass::macro('returnArray', function () {
            return ['key' => 'value', 'nested' => ['inner' => 'data']];
        });
        
        $this->testClass::macro('returnObject', function () {
            return (object) ['property' => 'value'];
        });
        
        $array = $this->testClass->returnArray();
        expect($array)->toBeArray();
        expect($array['key'])->toBe('value');
        expect($array['nested']['inner'])->toBe('data');
        
        $object = $this->testClass->returnObject();
        expect($object)->toBeObject();
        expect($object->property)->toBe('value');
    });
});