<?php

declare(strict_types=1);

use Bob\Contracts\ProcessorInterface;
use Bob\Contracts\BuilderInterface;

it('implements ProcessorInterface', function () {
    $processor = Mockery::mock(ProcessorInterface::class);
    
    expect($processor)->toBeInstanceOf(ProcessorInterface::class);
});

it('has process methods', function () {
    $processor = Mockery::mock(ProcessorInterface::class);
    
    expect(method_exists($processor, 'processSelect'))->toBeTrue();
    expect(method_exists($processor, 'processInsertGetId'))->toBeTrue();
    expect(method_exists($processor, 'processColumnListing'))->toBeTrue();
    expect(method_exists($processor, 'processColumnTypeListing'))->toBeTrue();
});

it('processSelect accepts builder and results', function () {
    $processor = Mockery::mock(ProcessorInterface::class);
    $builder = Mockery::mock(BuilderInterface::class);
    
    $processor->shouldReceive('processSelect')
        ->with(Mockery::type(BuilderInterface::class), Mockery::type('array'))
        ->andReturn([['id' => 1, 'name' => 'John']]);
    
    $result = $processor->processSelect($builder, [['id' => 1, 'name' => 'John']]);
    
    expect($result)->toBeArray();
    expect($result[0])->toHaveKey('id');
    expect($result[0])->toHaveKey('name');
});

it('processInsertGetId returns integer or string', function () {
    $processor = Mockery::mock(ProcessorInterface::class);
    $builder = Mockery::mock(BuilderInterface::class);
    
    $processor->shouldReceive('processInsertGetId')
        ->with(
            Mockery::type(BuilderInterface::class),
            Mockery::type('string'),
            Mockery::type('array'),
            Mockery::type('string')
        )
        ->andReturn(1);
    
    $result = $processor->processInsertGetId(
        $builder,
        'INSERT INTO users VALUES (?)',
        ['John'],
        'id'
    );
    
    expect($result)->toBeInt();
});

it('processColumnListing returns array', function () {
    $processor = Mockery::mock(ProcessorInterface::class);
    
    $processor->shouldReceive('processColumnListing')
        ->with(Mockery::type('array'))
        ->andReturn(['id', 'name', 'email']);
    
    $result = $processor->processColumnListing([
        ['column_name' => 'id'],
        ['column_name' => 'name'],
        ['column_name' => 'email']
    ]);
    
    expect($result)->toBeArray();
    expect($result)->toContain('id');
    expect($result)->toContain('name');
    expect($result)->toContain('email');
});

it('processColumnTypeListing returns array', function () {
    $processor = Mockery::mock(ProcessorInterface::class);
    
    $processor->shouldReceive('processColumnTypeListing')
        ->with(Mockery::type('array'))
        ->andReturn([
            'id' => 'integer',
            'name' => 'string',
            'email' => 'string'
        ]);
    
    $result = $processor->processColumnTypeListing([
        ['column_name' => 'id', 'data_type' => 'int'],
        ['column_name' => 'name', 'data_type' => 'varchar'],
        ['column_name' => 'email', 'data_type' => 'varchar']
    ]);
    
    expect($result)->toBeArray();
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('name');
    expect($result)->toHaveKey('email');
});

it('processInsertGetId handles null sequence', function () {
    $processor = Mockery::mock(ProcessorInterface::class);
    $builder = Mockery::mock(BuilderInterface::class);
    
    $processor->shouldReceive('processInsertGetId')
        ->with(
            Mockery::type(BuilderInterface::class),
            Mockery::type('string'),
            Mockery::type('array'),
            null
        )
        ->andReturn(1);
    
    $result = $processor->processInsertGetId(
        $builder,
        'INSERT INTO users VALUES (?)',
        ['John'],
        null
    );
    
    expect($result)->toBe(1);
});