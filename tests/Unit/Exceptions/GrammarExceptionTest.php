<?php

use Bob\Exceptions\GrammarException;

describe('GrammarException Tests', function () {

    test('GrammarException constructor sets message and properties', function () {
        $exception = new GrammarException('Test message', 'testMethod', 'TestGrammar', 100);

        expect($exception->getMessage())->toBe('Test message');
        expect($exception->getMethod())->toBe('testMethod');
        expect($exception->getGrammar())->toBe('TestGrammar');
        expect($exception->getCode())->toBe(100);
    });

    test('GrammarException constructor with previous exception', function () {
        $previous = new Exception('Previous exception');
        $exception = new GrammarException('Test message', 'testMethod', 'TestGrammar', 100, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    test('GrammarException constructor with default values', function () {
        $exception = new GrammarException();

        expect($exception->getMessage())->toBe('');
        expect($exception->getMethod())->toBe('');
        expect($exception->getGrammar())->toBe('');
        expect($exception->getCode())->toBe(0);
        expect($exception->getPrevious())->toBeNull();
    });

    test('unsupportedMethod static method creates exception with correct message', function () {
        $exception = GrammarException::unsupportedMethod('compileCustom', 'MySQL');

        expect($exception)->toBeInstanceOf(GrammarException::class);
        expect($exception->getMessage())->toBe("Method 'compileCustom' is not supported by MySQL grammar");
        expect($exception->getMethod())->toBe('compileCustom');
        expect($exception->getGrammar())->toBe('MySQL');
    });

    test('unsupportedMethod with different method and grammar', function () {
        $exception = GrammarException::unsupportedMethod('compileSpecial', 'PostgreSQL');

        expect($exception->getMessage())->toBe("Method 'compileSpecial' is not supported by PostgreSQL grammar");
        expect($exception->getMethod())->toBe('compileSpecial');
        expect($exception->getGrammar())->toBe('PostgreSQL');
    });

    test('invalidOperator static method creates exception with correct message', function () {
        $exception = GrammarException::invalidOperator('><');

        expect($exception)->toBeInstanceOf(GrammarException::class);
        expect($exception->getMessage())->toBe('Invalid SQL operator: ><');
        expect($exception->getMethod())->toBe('');
        expect($exception->getGrammar())->toBe('');
    });

    test('invalidOperator with special characters', function () {
        $exception = GrammarException::invalidOperator('!@#');

        expect($exception->getMessage())->toBe('Invalid SQL operator: !@#');
    });

    test('compilationError static method creates exception with correct message', function () {
        $exception = GrammarException::compilationError('WHERE clause', 'missing column name');

        expect($exception)->toBeInstanceOf(GrammarException::class);
        expect($exception->getMessage())->toBe('Failed to compile WHERE clause: missing column name');
        expect($exception->getMethod())->toBe('WHERE clause');
        expect($exception->getGrammar())->toBe('');
    });

    test('compilationError with different component and reason', function () {
        $exception = GrammarException::compilationError('JOIN', 'invalid table reference');

        expect($exception->getMessage())->toBe('Failed to compile JOIN: invalid table reference');
        expect($exception->getMethod())->toBe('JOIN');
    });

    test('missingComponent static method creates exception with correct message', function () {
        $exception = GrammarException::missingComponent('FROM clause');

        expect($exception)->toBeInstanceOf(GrammarException::class);
        expect($exception->getMessage())->toBe('Cannot compile query: missing FROM clause component');
        expect($exception->getMethod())->toBe('');
        expect($exception->getGrammar())->toBe('');
    });

    test('missingComponent with different component', function () {
        $exception = GrammarException::missingComponent('SELECT columns');

        expect($exception->getMessage())->toBe('Cannot compile query: missing SELECT columns component');
    });

    test('invalidJoinType static method creates exception with correct message', function () {
        $exception = GrammarException::invalidJoinType('outer');

        expect($exception)->toBeInstanceOf(GrammarException::class);
        expect($exception->getMessage())->toBe('Invalid join type: outer. Supported types are: inner, left, right, cross');
        expect($exception->getMethod())->toBe('');
        expect($exception->getGrammar())->toBe('');
    });

    test('invalidJoinType with special characters', function () {
        $exception = GrammarException::invalidJoinType('full-outer');

        expect($exception->getMessage())->toBe('Invalid join type: full-outer. Supported types are: inner, left, right, cross');
    });

    test('invalidAggregate static method creates exception with correct message', function () {
        $exception = GrammarException::invalidAggregate('median');

        expect($exception)->toBeInstanceOf(GrammarException::class);
        expect($exception->getMessage())->toBe('Invalid aggregate function: median. Supported functions are: count, sum, avg, min, max');
        expect($exception->getMethod())->toBe('');
        expect($exception->getGrammar())->toBe('');
    });

    test('invalidAggregate with uppercase function name', function () {
        $exception = GrammarException::invalidAggregate('STDDEV');

        expect($exception->getMessage())->toBe('Invalid aggregate function: STDDEV. Supported functions are: count, sum, avg, min, max');
    });

    test('bindingError static method creates exception with correct message', function () {
        $exception = GrammarException::bindingError('parameter count mismatch');

        expect($exception)->toBeInstanceOf(GrammarException::class);
        expect($exception->getMessage())->toBe('Parameter binding error: parameter count mismatch');
        expect($exception->getMethod())->toBe('');
        expect($exception->getGrammar())->toBe('');
    });

    test('bindingError with detailed reason', function () {
        $exception = GrammarException::bindingError('expected 3 parameters but got 2');

        expect($exception->getMessage())->toBe('Parameter binding error: expected 3 parameters but got 2');
    });

    test('getMethod returns the method property', function () {
        $exception = new GrammarException('', 'customMethod', '');

        expect($exception->getMethod())->toBe('customMethod');
    });

    test('getGrammar returns the grammar property', function () {
        $exception = new GrammarException('', '', 'SQLiteGrammar');

        expect($exception->getGrammar())->toBe('SQLiteGrammar');
    });

    test('GrammarException can be thrown and caught', function () {
        $caught = false;

        try {
            throw GrammarException::unsupportedMethod('test', 'TestGrammar');
        } catch (GrammarException $e) {
            $caught = true;
            expect($e->getMessage())->toContain("Method 'test' is not supported");
        }

        expect($caught)->toBeTrue();
    });

    test('GrammarException extends Exception class', function () {
        $exception = new GrammarException();

        expect($exception)->toBeInstanceOf(Exception::class);
        expect($exception)->toBeInstanceOf(Throwable::class);
    });

    test('All static methods return GrammarException instance', function () {
        $methods = [
            GrammarException::unsupportedMethod('m', 'g'),
            GrammarException::invalidOperator('op'),
            GrammarException::compilationError('c', 'r'),
            GrammarException::missingComponent('comp'),
            GrammarException::invalidJoinType('type'),
            GrammarException::invalidAggregate('func'),
            GrammarException::bindingError('reason'),
        ];

        foreach ($methods as $exception) {
            expect($exception)->toBeInstanceOf(GrammarException::class);
        }
    });

    test('Exception properties are accessible', function () {
        $exception = new GrammarException('msg', 'method', 'grammar', 42);

        // Test that all properties are set correctly
        expect($exception->getMessage())->toBe('msg');
        expect($exception->getMethod())->toBe('method');
        expect($exception->getGrammar())->toBe('grammar');
        expect($exception->getCode())->toBe(42);
        expect($exception->getFile())->toBeString();
        expect($exception->getLine())->toBeInt();
        expect($exception->getTrace())->toBeArray();
        expect($exception->getTraceAsString())->toBeString();
    });

});