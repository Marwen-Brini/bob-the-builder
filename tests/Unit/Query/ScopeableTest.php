<?php

use Bob\Query\Scopeable;
use Bob\Database\Connection;
use Bob\Query\Builder;

describe('Scopeable trait', function () {
    beforeEach(function () {
        // Create a test class that uses Scopeable trait
        $this->testClass = new class {
            use Scopeable;
            
            public $conditions = [];
            
            public function where(string $column, string $operator, $value): self
            {
                $this->conditions[] = [$column, $operator, $value];
                return $this;
            }
            
            public function getConditions(): array
            {
                return $this->conditions;
            }
        };
        
        // Clear scopes before each test
        $this->testClass::clearScopes();
    });
    
    afterEach(function () {
        // Clean up scopes after each test
        $this->testClass::clearScopes();
    });
    
    it('can register and check global scopes', function () {
        $this->testClass::globalScope('active', function () {
            $this->where('active', '=', 1);
        });
        
        expect($this->testClass::hasScope('active'))->toBeTrue();
    });
    
    it('can register and check local scopes', function () {
        $this->testClass::scope('recent', function () {
            $this->where('created_at', '>', '2023-01-01');
        });
        
        expect($this->testClass::hasScope('recent'))->toBeTrue();
    });
    
    it('can apply local scopes with withScope', function () {
        $this->testClass::scope('byStatus', function (string $status) {
            $this->where('status', '=', $status);
        });
        
        $instance = new $this->testClass();
        $instance->withScope('byStatus', 'published');
        
        expect($instance->getConditions())->toHaveCount(1);
        expect($instance->getConditions()[0])->toBe(['status', '=', 'published']);
    });
    
    it('can apply local scopes with multiple parameters', function () {
        $this->testClass::scope('byRange', function (string $start, string $end) {
            $this->where('date', '>=', $start);
            $this->where('date', '<=', $end);
        });
        
        $instance = new $this->testClass();
        $instance->withScope('byRange', '2023-01-01', '2023-12-31');
        
        expect($instance->getConditions())->toHaveCount(2);
        expect($instance->getConditions()[0])->toBe(['date', '>=', '2023-01-01']);
        expect($instance->getConditions()[1])->toBe(['date', '<=', '2023-12-31']);
    });
    
    it('ignores non-existent scopes in withScope', function () {
        $instance = new $this->testClass();
        $result = $instance->withScope('nonexistent');
        
        expect($result)->toBe($instance); // Should return self
        expect($instance->getConditions())->toHaveCount(0);
    });
    
    it('can exclude specific global scopes with withoutGlobalScope', function () {
        $this->testClass::globalScope('active', function () {
            $this->where('active', '=', 1);
        });
        
        $instance = new $this->testClass();
        $result = $instance->withoutGlobalScope('active');
        
        expect($result)->toBe($instance);
        
        // Verify the scope is marked as excluded
        $reflection = new ReflectionClass($instance);
        $appliedScopesProperty = $reflection->getProperty('appliedScopes');
        $appliedScopesProperty->setAccessible(true);
        $appliedScopes = $appliedScopesProperty->getValue($instance);
        
        expect($appliedScopes)->toContain('!active');
    });
    
    // Lines 86-90: withoutGlobalScopes method
    it('can exclude all global scopes with withoutGlobalScopes', function () {
        $this->testClass::globalScope('active', function () {
            $this->where('active', '=', 1);
        });
        
        $this->testClass::globalScope('published', function () {
            $this->where('published', '=', 1);
        });
        
        $this->testClass::globalScope('visible', function () {
            $this->where('visible', '=', 1);
        });
        
        $instance = new $this->testClass();
        $result = $instance->withoutGlobalScopes();
        
        expect($result)->toBe($instance);
        
        // Verify all scopes are marked as excluded
        $reflection = new ReflectionClass($instance);
        $appliedScopesProperty = $reflection->getProperty('appliedScopes');
        $appliedScopesProperty->setAccessible(true);
        $appliedScopes = $appliedScopesProperty->getValue($instance);
        
        expect($appliedScopes)->toContain('!active');
        expect($appliedScopes)->toContain('!published');
        expect($appliedScopes)->toContain('!visible');
        expect($appliedScopes)->toHaveCount(3);
    });
    
    it('handles withoutGlobalScopes when no global scopes exist', function () {
        $instance = new $this->testClass();
        $result = $instance->withoutGlobalScopes();
        
        expect($result)->toBe($instance);
        
        // Should have no excluded scopes
        $reflection = new ReflectionClass($instance);
        $appliedScopesProperty = $reflection->getProperty('appliedScopes');
        $appliedScopesProperty->setAccessible(true);
        $appliedScopes = $appliedScopesProperty->getValue($instance);
        
        expect($appliedScopes)->toHaveCount(0);
    });
    
    it('applies global scopes correctly', function () {
        $this->testClass::globalScope('active', function () {
            $this->where('active', '=', 1);
        });
        
        $this->testClass::globalScope('published', function () {
            $this->where('published', '=', 1);
        });
        
        $instance = new $this->testClass();
        
        // Use reflection to call protected method
        $reflection = new ReflectionClass($instance);
        $applyGlobalScopesMethod = $reflection->getMethod('applyGlobalScopes');
        $applyGlobalScopesMethod->setAccessible(true);
        $applyGlobalScopesMethod->invoke($instance);
        
        expect($instance->getConditions())->toHaveCount(2);
        expect($instance->getConditions()[0])->toBe(['active', '=', 1]);
        expect($instance->getConditions()[1])->toBe(['published', '=', 1]);
    });
    
    it('skips excluded global scopes when applying', function () {
        $this->testClass::globalScope('active', function () {
            $this->where('active', '=', 1);
        });
        
        $this->testClass::globalScope('published', function () {
            $this->where('published', '=', 1);
        });
        
        $instance = new $this->testClass();
        $instance->withoutGlobalScope('active');
        
        // Use reflection to call protected method
        $reflection = new ReflectionClass($instance);
        $applyGlobalScopesMethod = $reflection->getMethod('applyGlobalScopes');
        $applyGlobalScopesMethod->setAccessible(true);
        $applyGlobalScopesMethod->invoke($instance);
        
        expect($instance->getConditions())->toHaveCount(1);
        expect($instance->getConditions()[0])->toBe(['published', '=', 1]);
    });
    
    it('checks scope existence in both global and local scopes', function () {
        $this->testClass::globalScope('globalScope', function () {
            // Global scope
        });
        
        $this->testClass::scope('localScope', function () {
            // Local scope
        });
        
        expect($this->testClass::hasScope('globalScope'))->toBeTrue();
        expect($this->testClass::hasScope('localScope'))->toBeTrue();
        expect($this->testClass::hasScope('nonexistent'))->toBeFalse();
    });
    
    // Line 126: removeScope method
    it('can remove scopes with removeScope', function () {
        $this->testClass::globalScope('global1', function () {
            $this->where('global', '=', 1);
        });
        
        $this->testClass::scope('local1', function () {
            $this->where('local', '=', 1);
        });
        
        expect($this->testClass::hasScope('global1'))->toBeTrue();
        expect($this->testClass::hasScope('local1'))->toBeTrue();
        
        $this->testClass::removeScope('global1');
        $this->testClass::removeScope('local1');
        
        expect($this->testClass::hasScope('global1'))->toBeFalse();
        expect($this->testClass::hasScope('local1'))->toBeFalse();
    });
    
    it('can clear all scopes', function () {
        $this->testClass::globalScope('global1', function () {
            // Global scope 1
        });
        
        $this->testClass::globalScope('global2', function () {
            // Global scope 2
        });
        
        $this->testClass::scope('local1', function () {
            // Local scope 1
        });
        
        expect($this->testClass::hasScope('global1'))->toBeTrue();
        expect($this->testClass::hasScope('global2'))->toBeTrue();
        expect($this->testClass::hasScope('local1'))->toBeTrue();
        
        $this->testClass::clearScopes();
        
        expect($this->testClass::hasScope('global1'))->toBeFalse();
        expect($this->testClass::hasScope('global2'))->toBeFalse();
        expect($this->testClass::hasScope('local1'))->toBeFalse();
    });
    
    // Lines 143-146: getScopes method
    it('can get all scopes with getScopes', function () {
        $globalScope1 = function () {
            $this->where('global1', '=', 1);
        };
        
        $globalScope2 = function () {
            $this->where('global2', '=', 1);
        };
        
        $localScope1 = function () {
            $this->where('local1', '=', 1);
        };
        
        $this->testClass::globalScope('global1', $globalScope1);
        $this->testClass::globalScope('global2', $globalScope2);
        $this->testClass::scope('local1', $localScope1);
        
        $scopes = $this->testClass::getScopes();
        
        expect($scopes)->toHaveKey('global');
        expect($scopes)->toHaveKey('local');
        expect($scopes['global'])->toHaveCount(2);
        expect($scopes['local'])->toHaveCount(1);
        expect($scopes['global'])->toHaveKey('global1');
        expect($scopes['global'])->toHaveKey('global2');
        expect($scopes['local'])->toHaveKey('local1');
        expect($scopes['global']['global1'])->toBe($globalScope1);
        expect($scopes['local']['local1'])->toBe($localScope1);
    });
    
    it('returns empty arrays when no scopes are registered', function () {
        $scopes = $this->testClass::getScopes();
        
        expect($scopes)->toHaveKey('global');
        expect($scopes)->toHaveKey('local');
        expect($scopes['global'])->toHaveCount(0);
        expect($scopes['local'])->toHaveCount(0);
    });
    
    it('maintains separate scope namespaces for different classes', function () {
        // Create second test class
        $testClass2 = new class {
            use Scopeable;
        };
        
        $testClass2::clearScopes();
        
        $this->testClass::globalScope('class1Global', function () {
            // Class 1 global scope
        });
        
        $testClass2::globalScope('class2Global', function () {
            // Class 2 global scope
        });
        
        expect($this->testClass::hasScope('class1Global'))->toBeTrue();
        expect($this->testClass::hasScope('class2Global'))->toBeFalse();
        
        expect($testClass2::hasScope('class1Global'))->toBeFalse();
        expect($testClass2::hasScope('class2Global'))->toBeTrue();
        
        // Clean up
        $testClass2::clearScopes();
    });
});