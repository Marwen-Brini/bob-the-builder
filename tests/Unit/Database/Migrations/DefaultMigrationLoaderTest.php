<?php

declare(strict_types=1);

use Bob\Database\Migrations\DefaultMigrationLoader;
use Bob\Database\Migrations\Migration;

beforeEach(function () {
    $this->loader = new DefaultMigrationLoader;
    $this->tempDir = sys_get_temp_dir().'/migrations_'.uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function () {
    // Clean up temp files
    $files = glob($this->tempDir.'/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }
});

test('loading a file that does not exist throws exception covering line 26', function () {
    expect(fn () => $this->loader->load('/non/existent/file.php'))
        ->toThrow(RuntimeException::class, 'Migration file not found: /non/existent/file.php');
});

test('loading an invalid migration file throws exception covering line 30', function () {
    // Create a non-PHP file
    $file = $this->tempDir.'/invalid.txt';
    file_put_contents($file, 'Not a PHP file');

    expect(fn () => $this->loader->load($file))
        ->toThrow(RuntimeException::class, "Invalid migration file: {$file}");
});

test('when class cannot be determined from file it throws exception covering line 59', function () {
    // Create a PHP file with a filename that will extract to "CreateNonexistentTable"
    // but define a completely different class that won't match any namespace patterns
    $file = $this->tempDir.'/2024_01_01_000000_create_nonexistent_table.php';

    // Create a class with a different name than what extractClassName will generate
    $differentClassName = 'SomeRandomClass'.uniqid();
    file_put_contents($file, "<?php
class {$differentClassName} {
    // This class name doesn't match the expected 'CreateNonexistentTable'
}
");

    // Load it once to define the class, so no new classes will be detected on second load
    require_once $file;

    // Now when we call loader->load(), no new classes will be detected,
    // and extractClassName will look for 'CreateNonexistentTable' (and its namespace variants)
    // but none of these classes exist, so it should throw the exception
    expect(fn () => $this->loader->load($file))
        ->toThrow(RuntimeException::class, "Could not determine class name from migration file: {$file}");
});

test('returns last loaded class when no Migration subclass found covering line 70', function () {
    // Create a unique class name to avoid conflicts
    $className = 'TestNonMigrationClass'.uniqid();

    // Create a PHP file that defines a class but not extending Migration
    $file = $this->tempDir.'/non_migration_class.php';
    $content = <<<PHP
<?php
class {$className} {
    public function test() {}
}
PHP;
    file_put_contents($file, $content);

    $result = $this->loader->load($file);

    expect($result)->toBe($className);
});

test('isValidMigration returns false when file_get_contents fails covering line 116', function () {
    // We need to create a scenario where file_get_contents returns false
    // Using a directory will cause this but with a warning, so we need to suppress it

    // Create a directory with a .php extension
    $dirPath = $this->tempDir.'/directory.php';
    mkdir($dirPath);

    // Use reflection to directly test the method and suppress the error
    $reflection = new ReflectionClass($this->loader);
    $method = $reflection->getMethod('isValidMigration');

    // Capture the current error reporting level
    $oldErrorReporting = error_reporting(E_ERROR);

    try {
        $result = $method->invoke($this->loader, $dirPath);
        expect($result)->toBeFalse();
    } finally {
        // Restore original error reporting
        error_reporting($oldErrorReporting);
    }
});

test('extract class name from migration file', function () {
    // Test with timestamp prefix
    expect($this->loader->extractClassName('/path/2024_01_01_000000_create_users_table.php'))
        ->toBe('CreateUsersTable');

    // Test without timestamp prefix
    expect($this->loader->extractClassName('/path/create_posts_table.php'))
        ->toBe('CreatePostsTable');

    // Test single word
    expect($this->loader->extractClassName('migration.php'))
        ->toBe('Migration');
});

test('isValidMigration with various cases', function () {
    // Test non-PHP file
    $txtFile = $this->tempDir.'/test.txt';
    file_put_contents($txtFile, 'test');
    expect($this->loader->isValidMigration($txtFile))->toBeFalse();

    // Test valid PHP file with class
    $phpFile = $this->tempDir.'/valid.php';
    file_put_contents($phpFile, '<?php class TestMigration {}');
    expect($this->loader->isValidMigration($phpFile))->toBeTrue();

    // Test PHP file without class
    $noClassFile = $this->tempDir.'/no_class.php';
    file_put_contents($noClassFile, '<?php echo "test";');
    expect($this->loader->isValidMigration($noClassFile))->toBeFalse();

    // Test non-existent file
    expect($this->loader->isValidMigration('/non/existent.php'))->toBeFalse();
});

test('successful migration loading', function () {
    // Create a unique class name
    $className = 'TestMigration'.uniqid();

    $file = $this->tempDir.'/test_migration.php';
    $content = <<<PHP
<?php
use Bob\Database\Migrations\Migration;

class {$className} extends Migration {
    public function up(): void {}
    public function down(): void {}
    public function getQueries(string \$direction): array { return []; }
}
PHP;
    file_put_contents($file, $content);

    $result = $this->loader->load($file);

    expect($result)->toBe($className);
    expect(class_exists($result))->toBeTrue();
});

test('loading file when extractClassName finds a class with namespace', function () {
    // Pre-define a class with the expected name in a namespace
    $uniqueId = uniqid();
    $className = "CreateTestTable{$uniqueId}";
    eval("namespace App\\Database\\Migrations; class {$className} extends \\Bob\\Database\\Migrations\\Migration { public function up(): void {} public function down(): void {} public function getQueries(string \$direction): array { return []; }}");

    // Create a file that has a class declaration to pass validation but class already exists
    $file = $this->tempDir."/2024_01_01_000000_create_test_table_{$uniqueId}.php";
    $dummyClassName = "DummyClass{$uniqueId}";
    $content = <<<PHP
<?php
// This file appears to define a class
class {$dummyClassName} {}
PHP;
    file_put_contents($file, $content);

    // Load it once so the dummy class is already defined
    require_once $file;

    $result = $this->loader->load($file);

    // Since no new class is loaded from the file (already required), it will use extractClassName
    // and find the pre-existing class in App\Database\Migrations namespace
    expect($result)->toBe("App\\Database\\Migrations\\{$className}");
});
