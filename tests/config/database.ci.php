<?php

/**
 * Database configuration for CI environment
 * This file reads credentials from environment variables
 */

return [
    'mysql' => [
        'driver' => 'mysql',
        'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
        'port' => getenv('MYSQL_PORT') ?: '3306',
        'database' => getenv('MYSQL_DATABASE') ?: 'bob_test',
        'username' => getenv('MYSQL_USERNAME') ?: 'root',
        'password' => getenv('MYSQL_PASSWORD') ?: 'password',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'postgres' => [
        'driver' => 'pgsql',
        'host' => getenv('POSTGRES_HOST') ?: '127.0.0.1',
        'port' => getenv('POSTGRES_PORT') ?: '5432',
        'database' => getenv('POSTGRES_DATABASE') ?: 'bob_test',
        'username' => getenv('POSTGRES_USERNAME') ?: 'postgres',
        'password' => getenv('POSTGRES_PASSWORD') ?: 'password',
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
    ],

    'sqlite' => [
        'driver' => 'sqlite',
        'database' => getenv('DB_DATABASE') ?: ':memory:',
        'prefix' => '',
    ],
];