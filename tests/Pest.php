<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(
    Tests\TestCase::class
)->in('Integration');

uses(
    \PHPUnit\Framework\TestCase::class
)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function createMockConnection(): \Bob\Database\Connection
{
    return Mockery::mock(\Bob\Database\Connection::class);
}

function createMockBuilder(): \Bob\Query\Builder
{
    return Mockery::mock(\Bob\Query\Builder::class);
}

function createMockModel(): \Bob\Database\Model
{
    return Mockery::mock(\Bob\Database\Model::class);
}
