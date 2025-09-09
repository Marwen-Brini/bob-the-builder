<?php

declare(strict_types=1);

use Bob\Exceptions\GrammarException;

it('creates unsupported method exception', function () {
    $exception = GrammarException::unsupportedMethod('compileUpsert', 'SQLiteGrammar');
    
    expect($exception->getMessage())->toContain('compileUpsert');
    expect($exception->getMessage())->toContain('SQLiteGrammar');
    expect($exception->getMethod())->toBe('compileUpsert');
    expect($exception->getGrammar())->toBe('SQLiteGrammar');
});

it('creates invalid operator exception', function () {
    $exception = GrammarException::invalidOperator('~=');
    
    expect($exception->getMessage())->toContain('Invalid SQL operator');
    expect($exception->getMessage())->toContain('~=');
});

it('creates compilation error exception', function () {
    $exception = GrammarException::compilationError('WHERE clause', 'invalid column name');
    
    expect($exception->getMessage())->toContain('WHERE clause');
    expect($exception->getMessage())->toContain('invalid column name');
    expect($exception->getMethod())->toBe('WHERE clause');
});

it('creates missing component exception', function () {
    $exception = GrammarException::missingComponent('FROM clause');
    
    expect($exception->getMessage())->toContain('FROM clause');
    expect($exception->getMessage())->toContain('missing');
});

it('creates invalid join type exception', function () {
    $exception = GrammarException::invalidJoinType('full outer');
    
    expect($exception->getMessage())->toContain('full outer');
    expect($exception->getMessage())->toContain('inner, left, right, cross');
});

it('creates invalid aggregate exception', function () {
    $exception = GrammarException::invalidAggregate('median');
    
    expect($exception->getMessage())->toContain('median');
    expect($exception->getMessage())->toContain('count, sum, avg, min, max');
});

it('creates binding error exception', function () {
    $exception = GrammarException::bindingError('too many parameters');
    
    expect($exception->getMessage())->toContain('Parameter binding error');
    expect($exception->getMessage())->toContain('too many parameters');
});

it('preserves exception properties', function () {
    $exception = new GrammarException(
        'Custom error',
        'customMethod',
        'CustomGrammar',
        500
    );
    
    expect($exception->getMessage())->toBe('Custom error');
    expect($exception->getMethod())->toBe('customMethod');
    expect($exception->getGrammar())->toBe('CustomGrammar');
    expect($exception->getCode())->toBe(500);
});

it('handles previous exception', function () {
    $previous = new Exception('Underlying error');
    $exception = new GrammarException(
        'Grammar error',
        'compile',
        'Grammar',
        0,
        $previous
    );
    
    expect($exception->getPrevious())->toBe($previous);
});