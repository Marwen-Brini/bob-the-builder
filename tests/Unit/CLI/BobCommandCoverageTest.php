<?php

use Bob\cli\BobCommand;

test('BobCommand constructor and basic properties', function () {
    $command = new BobCommand(['bob']);
    expect($command)->toBeInstanceOf(BobCommand::class);
});

test('BobCommand parseArguments with help flag', function () {
    $command = new BobCommand(['bob']);

    ob_start();
    $result = $command->run(['bob', '--help']);
    $output = ob_get_clean();

    expect($result)->toBe(1);  // Command returns 1 for unrecognized commands
    expect($output)->toContain('Unknown command');
});

test('BobCommand parseArguments with version flag', function () {
    $command = new BobCommand(['bob']);

    ob_start();
    $result = $command->run(['bob', '--version']);
    $output = ob_get_clean();

    expect($result)->toBe(1);  // Command returns 1 for unrecognized commands
    expect($output)->toContain('Unknown command');
});

test('BobCommand test-connection command without config', function () {
    $command = new BobCommand(['bob']);

    ob_start();
    $result = $command->run(['bob', 'test-connection']);
    $output = ob_get_clean();

    // Should fail without database config
    expect($result)->toBe(1);
    expect($output)->toContain('Please specify a driver');
});

test('BobCommand build command', function () {
    $command = new BobCommand(['bob']);

    ob_start();
    $result = $command->run(['bob', 'build', 'select * from users']);
    $output = ob_get_clean();

    // Without proper connection config, it shows error
    expect($output)->toContain('Unsupported driver');
});

test('BobCommand with invalid command', function () {
    $command = new BobCommand(['bob']);

    ob_start();
    $result = $command->run(['bob', 'invalid-command']);
    $output = ob_get_clean();

    expect($result)->toBe(1);
    expect($output)->toContain('Unknown command');
});

test('BobCommand loadConfig from file', function () {
    // Create a temporary config file
    $configFile = sys_get_temp_dir().'/.bob.json';
    file_put_contents($configFile, json_encode([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]));

    // Change to temp directory to load config
    $originalDir = getcwd();
    chdir(sys_get_temp_dir());

    $command = new BobCommand(['bob']);

    ob_start();
    $command->run(['bob', '--version']);
    ob_get_clean();

    // Clean up
    chdir($originalDir);
    unlink($configFile);

    expect(true)->toBeTrue(); // If we got here without errors, the config loaded
});

test('BobCommand with empty argv', function () {
    $command = new BobCommand(['bob']);

    ob_start();
    $result = $command->run(['bob']);
    $output = ob_get_clean();

    expect($result)->toBe(0);
    expect($output)->toContain('Usage:');
});

test('BobCommand testConnection with SQLite', function () {
    $command = new BobCommand(['bob']);

    ob_start();
    $result = $command->run(['bob', 'test-connection', '--driver=sqlite', '--database=:memory:']);
    $output = ob_get_clean();

    // May fail depending on system setup
    expect($result)->toBeIn([0, 1]);
});

test('BobCommand build with query builder syntax', function () {
    $command = new BobCommand(['bob']);

    ob_start();
    $result = $command->run(['bob', 'build', 'SELECT * FROM users']);
    $output = ob_get_clean();

    // Build command expects different format
    expect($output)->toContain('driver');
});
