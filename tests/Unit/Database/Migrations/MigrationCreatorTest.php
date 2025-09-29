<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Migrations;

use Bob\Database\Migrations\MigrationCreator;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

// PEST CONVERSION IN PROGRESS - Converting one method at a time

// Pest setup
beforeEach(function () {
    // Create a temporary directory for test migrations
    $this->tempDir = sys_get_temp_dir() . '/bob_migrations_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $this->creator = new MigrationCreator($this->tempDir);
});

afterEach(function () {
    // Clean up temporary files
    if (is_dir($this->tempDir)) {
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);
    }
});

// Helper function
function convertToClassName(string $name): string {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
}

// CONVERTED TEST 1: testCreateBasicMigration
test('basic migration creation', function () {
    $uniqueName = 'create_test_users_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName);

    expect($fileName)->toBeFile();
    expect($fileName)->toContain($uniqueName . '.php');

    $content = file_get_contents($fileName);
    $expectedClassName = convertToClassName($uniqueName);
    expect($content)->toContain("class {$expectedClassName} extends Migration");
    expect($content)->toContain('public function up(): void');
    expect($content)->toContain('public function down(): void');
});

// CONVERTED TEST 2: testCreateMigrationWithTableCreate
test('migration creation with table create mode', function () {
    $uniqueName = 'create_test_posts_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName, 'test_posts', true);

    expect($fileName)->toBeFile();

    $content = file_get_contents($fileName);
    expect($content)->toContain("Schema::create('test_posts'");
    expect($content)->toContain('$table->id();');
    expect($content)->toContain('$table->timestamps();');
    expect($content)->toContain("Schema::dropIfExists('test_posts');");
});

// CONVERTED TEST 3: testCreateMigrationWithTableUpdate
test('migration creation with table update mode', function () {
    $uniqueName = 'add_email_to_test_users_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName, 'test_users', false);

    expect($fileName)->toBeFile();

    $content = file_get_contents($fileName);
    expect($content)->toContain("Schema::table('test_users'");
    expect($content)->toContain('// Add columns or modifications here');
    expect($content)->toContain('// Reverse the changes made in up()');
});

// CONVERTED TEST 4: testCreateBlankMigration
test('blank migration creation', function () {
    $uniqueName = 'do_something_custom_' . uniqid();
    $fileName = $this->creator->create($uniqueName);

    expect($fileName)->toBeFile();

    $content = file_get_contents($fileName);
    $expectedClassName = convertToClassName($uniqueName);
    expect($content)->toContain("class {$expectedClassName} extends Migration");
    expect($content)->toContain('//'); // Should have empty comment placeholders
});

// CONVERTED TEST 5: testCreateUsersTableWithSpecificColumns
test('create users table with specific columns', function () {
    $uniqueName = 'create_app_users_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName, 'app_users', true);

    $content = file_get_contents($fileName);
    expect($content)->toContain('$table->string(\'name\');');
    expect($content)->toContain('$table->string(\'email\')->unique();');
    expect($content)->toContain('$table->timestamp(\'email_verified_at\')->nullable();');
    expect($content)->toContain('$table->string(\'password\');');
    expect($content)->toContain('$table->rememberToken();');
});

// CONVERTED TEST 6: testCreatePostsTableWithSpecificColumns
test('create posts table with specific columns', function () {
    $uniqueName = 'create_blog_posts_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName, 'blog_posts', true);

    $content = file_get_contents($fileName);
    expect($content)->toContain('$table->string(\'title\');');
    expect($content)->toContain('$table->text(\'content\');');
    expect($content)->toContain('$table->string(\'slug\')->unique();');
    expect($content)->toContain('$table->boolean(\'published\')->default(false);');
    expect($content)->toContain('$table->foreignId(\'user_id\')->constrained();');
});

// CONVERTED TEST 7: testCreateArticlesTableWithSpecificColumns
test('create articles table with specific columns', function () {
    $uniqueName = 'create_news_articles_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName, 'news_articles', true);

    $content = file_get_contents($fileName);
    expect($content)->toContain('$table->string(\'title\');');
    expect($content)->toContain('$table->text(\'content\');');
    expect($content)->toContain('$table->string(\'slug\')->unique();');
    expect($content)->toContain('$table->boolean(\'published\')->default(false);');
    expect($content)->toContain('$table->foreignId(\'user_id\')->constrained();');
});

// CONVERTED TEST 8: testCreateGenericTableWithDefaultColumns
test('create generic table with default columns', function () {
    $uniqueName = 'create_categories_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName, 'categories', true);

    $content = file_get_contents($fileName);
    expect($content)->toContain('// Add columns here');
});

// CONVERTED TEST 9: testEnsureMigrationDoesntAlreadyExistClassExists
test('ensure migration doesnt already exist class exists', function () {
    // First test the class_exists scenario by defining a class that would conflict
    if (!class_exists('TestClassConflict')) {
        eval('class TestClassConflict {}');
    }

    expect(fn() => $this->creator->create('test_class_conflict'))
        ->toThrow(InvalidArgumentException::class, 'A TestClassConflict class already exists.');
});

// CONVERTED TEST 10: testEnsureMigrationDoesntAlreadyExistFileExists
test('ensure migration doesnt already exist file exists', function () {
    // Create first migration
    $this->creator->create('test_duplicate');

    // Try to create another with the same name
    expect(fn() => $this->creator->create('test_duplicate'))
        ->toThrow(InvalidArgumentException::class, 'A migration file with name test_duplicate already exists.');
});

// CONVERTED TEST 11: testRegisterCustomStub
test('register custom stub', function () {
    $customStub = <<<'STUB'
<?php

class {{ class }} extends CustomBase
{
    public function execute(): void
    {
        // Custom implementation
    }
}
STUB;

    $this->creator->registerStub('blank', $customStub);
    $uniqueName = 'test_custom_migration_' . uniqid();
    $fileName = $this->creator->create($uniqueName);

    $content = file_get_contents($fileName);
    $expectedClassName = convertToClassName($uniqueName);
    expect($content)->toContain("class {$expectedClassName} extends CustomBase");
    expect($content)->toContain('public function execute(): void');
    expect($content)->toContain('// Custom implementation');
});

// CONVERTED TEST 12: testPathGetterSetter
test('path getter and setter', function () {
    $newPath = '/tmp/new_migrations';
    $this->creator->setPath($newPath);

    expect($this->creator->getPath())->toBe($newPath);
});

// CONVERTED TEST 13: testDatePrefixFormat
test('date prefix format', function () {
    $uniqueName = 'test_date_prefix_' . uniqid();
    $fileName = $this->creator->create($uniqueName);
    $baseName = basename($fileName);

    // Should match YYYY_MM_DD_HHMMSS format
    expect($baseName)->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_' . preg_quote($uniqueName, '/') . '\.php$/');
});

// CONVERTED TEST 14: testClassNameGeneration
test('class name generation from migration name', function () {
    $uniqueName = 'create_user_profile_settings_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName);

    $content = file_get_contents($fileName);
    $expectedClassName = convertToClassName($uniqueName);
    expect($content)->toContain("class {$expectedClassName} extends Migration");
});

// CONVERTED TEST 15: testCreateWordPressGenericMigration
test('create WordPress generic migration', function () {
    $uniqueName = 'create_wp_custom_table_' . uniqid();
    $fileName = $this->creator->createWordPress($uniqueName, 'generic');

    expect($fileName)->toBeFile();

    $content = file_get_contents($fileName);
    $expectedClassName = convertToClassName($uniqueName);
    expect($content)->toContain("class {$expectedClassName} extends Migration");
});

// CONVERTED TEST 16: testCreateWordPressPostMigration
test('create WordPress post migration', function () {
    $uniqueName = 'create_custom_posts_table_' . uniqid();
    $fileName = $this->creator->createWordPress($uniqueName, 'post');

    expect($fileName)->toBeFile();

    $content = file_get_contents($fileName);
    expect($content)->toContain('$table->bigIncrements(\'ID\');');
    expect($content)->toContain('$table->unsignedBigInteger(\'post_author\')');
    expect($content)->toContain('$table->longText(\'post_content\');');
    expect($content)->toContain('$table->text(\'post_title\');');
    expect($content)->toContain('$table->string(\'post_status\', 20)');
    expect($content)->toContain('$table->index([\'post_type\', \'post_status\', \'post_date\', \'ID\']);');
});

// CONVERTED TEST 17: testCreateWordPressTaxonomyMigration
test('create WordPress taxonomy migration', function () {
    $uniqueName = 'create_taxonomy_tables_' . uniqid();
    $fileName = $this->creator->createWordPress($uniqueName, 'taxonomy');

    expect($fileName)->toBeFile();

    $content = file_get_contents($fileName);
    expect($content)->toContain('Schema::create(\'terms\'');
    expect($content)->toContain('Schema::create(\'term_taxonomy\'');
    expect($content)->toContain('Schema::create(\'term_relationships\'');
    expect($content)->toContain('$table->string(\'name\', 200)');
    expect($content)->toContain('$table->string(\'taxonomy\', 32)');
    expect($content)->toContain('$table->foreign(\'term_id\')');
    expect($content)->toContain('Schema::dropIfExists(\'term_relationships\');');
    expect($content)->toContain('Schema::dropIfExists(\'term_taxonomy\');');
    expect($content)->toContain('Schema::dropIfExists(\'terms\');');
});

// CONVERTED TEST 18: testCreateWordPressMetaMigration
test('create WordPress meta migration', function () {
    // Use a specific pattern that we can predict
    $fileName = $this->creator->createWordPress('create_post_meta_table', 'meta');

    expect($fileName)->toBeFile();

    $content = file_get_contents($fileName);
    expect($content)->toContain('$table->bigIncrements(\'meta_id\');');
    expect($content)->toContain('$table->unsignedBigInteger(\'post_id\')');
    expect($content)->toContain('$table->string(\'meta_key\')');
    expect($content)->toContain('$table->longText(\'meta_value\')');
});

// CONVERTED TEST 19: testWordPressStubPopulationWithTableExtraction
test('WordPress stub population with table name extraction', function () {
    // Use a specific pattern that we can predict
    $fileName = $this->creator->createWordPress('create_user_meta_table', 'meta');

    $content = file_get_contents($fileName);
    expect($content)->toContain('create(\'user_meta\'');
    expect($content)->toContain('$table->unsignedBigInteger(\'user_id\')');
    expect($content)->toContain('dropIfExists(\'user_meta\');');
});

// CONVERTED TEST 20: testEnsureWordPressMigrationDoesntAlreadyExist
test('ensure WordPress migration doesnt already exist', function () {
    $uniqueName = 'wp_duplicate_' . uniqid();

    // Create first migration
    $this->creator->createWordPress($uniqueName, 'post');

    // Try to create another with the same name
    expect(fn() => $this->creator->createWordPress($uniqueName, 'taxonomy'))
        ->toThrow(InvalidArgumentException::class, "A migration file with name {$uniqueName} already exists.");
});

// CONVERTED TEST 21: testCustomCreateStub
test('custom create stub', function () {
    $customCreateStub = <<<'STUB'
<?php

class {{ class }} extends Migration
{
    public function up(): void
    {
        // Custom create logic for {{ table }}
        {{ up }}
    }

    public function down(): void
    {
        // Custom drop logic
        {{ down }}
    }
}
STUB;

    $this->creator->registerStub('create', $customCreateStub);
    $uniqueName = 'create_custom_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName, 'custom', true);

    $content = file_get_contents($fileName);
    expect($content)->toContain('// Custom create logic for custom');
    expect($content)->toContain('// Custom drop logic');
});

// CONVERTED TEST 22: testCustomUpdateStub
test('custom update stub', function () {
    $customUpdateStub = <<<'STUB'
<?php

class {{ class }} extends Migration
{
    public function up(): void
    {
        // Custom update logic for {{ table }}
        {{ up }}
    }

    public function down(): void
    {
        // Custom rollback logic
        {{ down }}
    }
}
STUB;

    $this->creator->registerStub('update', $customUpdateStub);
    $uniqueName = 'modify_existing_table_' . uniqid();
    $fileName = $this->creator->create($uniqueName, 'existing', false);

    $content = file_get_contents($fileName);
    expect($content)->toContain('// Custom update logic for existing');
    expect($content)->toContain('// Custom rollback logic');
});

// class MigrationCreatorTest extends TestCase
// {
//     protected MigrationCreator $creator;
//     protected string $tempDir;
// 
//     protected function setUp(): void
//     {
//         parent::setUp();
// 
//         // Create a temporary directory for test migrations
//         $this->tempDir = sys_get_temp_dir() . '/bob_migrations_test_' . uniqid();
//         mkdir($this->tempDir, 0777, true);
// 
//         $this->creator = new MigrationCreator($this->tempDir);
//     }
// 
//     protected function tearDown(): void
//     {
//         // Clean up temporary files
//         if (is_dir($this->tempDir)) {
//             $files = glob($this->tempDir . '/*');
//             foreach ($files as $file) {
//                 if (is_file($file)) {
//                     unlink($file);
//                 }
//             }
//             rmdir($this->tempDir);
//         }
// 
//         parent::tearDown();
//     }
// 
//     // /**
//     //  * Test basic migration creation
//     //  */
//     // public function testCreateBasicMigration()
//     // {
//     //     $uniqueName = 'create_test_users_table_' . uniqid();
//     //     $fileName = $this->creator->create($uniqueName);
// 
//     //     $this->assertFileExists($fileName);
//     //     $this->assertStringContainsString($uniqueName . '.php', $fileName);
// 
//     //     $content = file_get_contents($fileName);
//     //     $expectedClassName = $this->convertToClassName($uniqueName);
//     //     $this->assertStringContainsString("class {$expectedClassName} extends Migration", $content);
//     //     $this->assertStringContainsString('public function up(): void', $content);
//     //     $this->assertStringContainsString('public function down(): void', $content);
//     // }
// 
//     /**
//      * Helper method to convert migration name to class name (matches MigrationCreator logic)
//      */
//     private function convertToClassName(string $name): string
//     {
//         return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
//     }
// 
//     // /**
//     //  * Test migration creation with table (create mode)
//     //  */
//     // public function testCreateMigrationWithTableCreate()
//     // {
//     //     $uniqueName = 'create_test_posts_table_' . uniqid();
//     //     $fileName = $this->creator->create($uniqueName, 'test_posts', true);
// 
//     //     $this->assertFileExists($fileName);
// 
//     //     $content = file_get_contents($fileName);
//     //     $this->assertStringContainsString("Schema::create('test_posts'", $content);
//     //     $this->assertStringContainsString('$table->id();', $content);
//     //     $this->assertStringContainsString('$table->timestamps();', $content);
//     //     $this->assertStringContainsString("Schema::dropIfExists('test_posts');", $content);
//     // }
// 
//     // /**
//     //  * Test migration creation with table (update mode)
//     //  */
//     // public function testCreateMigrationWithTableUpdate()
//     // {
//     //     $uniqueName = 'add_email_to_test_users_table_' . uniqid();
//     //     $fileName = $this->creator->create($uniqueName, 'test_users', false);
// 
//     //     $this->assertFileExists($fileName);
// 
//     //     $content = file_get_contents($fileName);
//     //     $this->assertStringContainsString("Schema::table('test_users'", $content);
//     //     $this->assertStringContainsString('// Add columns or modifications here', $content);
//     //     $this->assertStringContainsString('// Reverse the changes made in up()', $content);
//     // }
// 
//     /**
//      * Test blank migration creation
//      */
//     public function testCreateBlankMigration()
//     {
//         $uniqueName = 'do_something_custom_' . uniqid();
//         $fileName = $this->creator->create($uniqueName);
// 
//         $this->assertFileExists($fileName);
// 
//         $content = file_get_contents($fileName);
//         $expectedClassName = $this->convertToClassName($uniqueName);
//         $this->assertStringContainsString("class {$expectedClassName} extends Migration", $content);
//         $this->assertStringContainsString('//', $content); // Should have empty comment placeholders
//     }
// 
//     /**
//      * Test migration creation for users table (covers lines 245-250)
//      */
//     public function testCreateUsersTableWithSpecificColumns()
//     {
//         $uniqueName = 'create_app_users_table_' . uniqid();
//         $fileName = $this->creator->create($uniqueName, 'app_users', true);
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('$table->string(\'name\');', $content);
//         $this->assertStringContainsString('$table->string(\'email\')->unique();', $content);
//         $this->assertStringContainsString('$table->timestamp(\'email_verified_at\')->nullable();', $content);
//         $this->assertStringContainsString('$table->string(\'password\');', $content);
//         $this->assertStringContainsString('$table->rememberToken();', $content);
//     }
// 
//     /**
//      * Test migration creation for posts table (covers lines 251-256)
//      */
//     public function testCreatePostsTableWithSpecificColumns()
//     {
//         $uniqueName = 'create_blog_posts_table_' . uniqid();
//         $fileName = $this->creator->create($uniqueName, 'blog_posts', true);
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('$table->string(\'title\');', $content);
//         $this->assertStringContainsString('$table->text(\'content\');', $content);
//         $this->assertStringContainsString('$table->string(\'slug\')->unique();', $content);
//         $this->assertStringContainsString('$table->boolean(\'published\')->default(false);', $content);
//         $this->assertStringContainsString('$table->foreignId(\'user_id\')->constrained();', $content);
//     }
// 
//     /**
//      * Test migration creation for articles table (covers lines 251-256)
//      */
//     public function testCreateArticlesTableWithSpecificColumns()
//     {
//         $uniqueName = 'create_news_articles_table_' . uniqid();
//         $fileName = $this->creator->create($uniqueName, 'news_articles', true);
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('$table->string(\'title\');', $content);
//         $this->assertStringContainsString('$table->text(\'content\');', $content);
//         $this->assertStringContainsString('$table->string(\'slug\')->unique();', $content);
//         $this->assertStringContainsString('$table->boolean(\'published\')->default(false);', $content);
//         $this->assertStringContainsString('$table->foreignId(\'user_id\')->constrained();', $content);
//     }
// 
//     /**
//      * Test migration creation for generic table (covers lines 257-259)
//      */
//     public function testCreateGenericTableWithDefaultColumns()
//     {
//         $uniqueName = 'create_categories_table_' . uniqid();
//         $fileName = $this->creator->create($uniqueName, 'categories', true);
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('// Add columns here', $content);
//     }
// 
//     /**
//      * Test duplicate migration name detection (class exists - covers line 63)
//      */
//     public function testEnsureMigrationDoesntAlreadyExistClassExists()
//     {
//         // First test the class_exists scenario by defining a class that would conflict
//         if (!class_exists('TestClassConflict')) {
//             eval('class TestClassConflict {}');
//         }
// 
//         $this->expectException(InvalidArgumentException::class);
//         $this->expectExceptionMessage('A TestClassConflict class already exists.');
// 
//         // Try to create migration with name that would create TestClassConflict class
//         $this->creator->create('test_class_conflict');
//     }
// 
//     /**
//      * Test duplicate migration file detection
//      */
//     public function testEnsureMigrationDoesntAlreadyExistFileExists()
//     {
//         $this->expectException(InvalidArgumentException::class);
//         $this->expectExceptionMessage('A migration file with name test_duplicate already exists.');
// 
//         // Create first migration
//         $this->creator->create('test_duplicate');
// 
//         // Try to create another with the same name
//         $this->creator->create('test_duplicate');
//     }
// 
//     /**
//      * Test custom stub registration and usage (covers lines 168-532 for custom stubs)
//      */
//     public function testRegisterCustomStub()
//     {
//         $customStub = <<<'STUB'
// <?php
// 
// class {{ class }} extends CustomBase
// {
//     public function execute(): void
//     {
//         // Custom implementation
//     }
// }
// STUB;
// 
//         $this->creator->registerStub('blank', $customStub);
//         $uniqueName = 'test_custom_migration_' . uniqid();
//         $fileName = $this->creator->create($uniqueName);
// 
//         $content = file_get_contents($fileName);
//         $expectedClassName = $this->convertToClassName($uniqueName);
//         $this->assertStringContainsString("class {$expectedClassName} extends CustomBase", $content);
//         $this->assertStringContainsString('public function execute(): void', $content);
//         $this->assertStringContainsString('// Custom implementation', $content);
//     }
// 
//     /**
//      * Test path getter and setter
//      */
//     public function testPathGetterSetter()
//     {
//         $newPath = '/tmp/new_migrations';
//         $this->creator->setPath($newPath);
// 
//         $this->assertEquals($newPath, $this->creator->getPath());
//     }
// 
//     /**
//      * Test date prefix format
//      */
//     public function testDatePrefixFormat()
//     {
//         $uniqueName = 'test_date_prefix_' . uniqid();
//         $fileName = $this->creator->create($uniqueName);
//         $baseName = basename($fileName);
// 
//         // Should match YYYY_MM_DD_HHMMSS format
//         $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_' . preg_quote($uniqueName, '/') . '\.php$/', $baseName);
//     }
// 
//     /**
//      * Test class name generation from migration name
//      */
//     public function testClassNameGeneration()
//     {
//         $uniqueName = 'create_user_profile_settings_table_' . uniqid();
//         $fileName = $this->creator->create($uniqueName);
// 
//         $content = file_get_contents($fileName);
//         $expectedClassName = $this->convertToClassName($uniqueName);
//         $this->assertStringContainsString("class {$expectedClassName} extends Migration", $content);
//     }
// 
//     /**
//      * Test WordPress generic migration creation (covers lines 315-328)
//      */
//     public function testCreateWordPressGenericMigration()
//     {
//         $uniqueName = 'create_wp_custom_table_' . uniqid();
//         $fileName = $this->creator->createWordPress($uniqueName, 'generic');
// 
//         $this->assertFileExists($fileName);
// 
//         $content = file_get_contents($fileName);
//         $expectedClassName = $this->convertToClassName($uniqueName);
//         $this->assertStringContainsString("class {$expectedClassName} extends Migration", $content);
//     }
// 
//     /**
//      * Test WordPress post migration creation (covers lines 336-406)
//      */
//     public function testCreateWordPressPostMigration()
//     {
//         $uniqueName = 'create_custom_posts_table_' . uniqid();
//         $fileName = $this->creator->createWordPress($uniqueName, 'post');
// 
//         $this->assertFileExists($fileName);
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('$table->bigIncrements(\'ID\');', $content);
//         $this->assertStringContainsString('$table->unsignedBigInteger(\'post_author\')', $content);
//         $this->assertStringContainsString('$table->longText(\'post_content\');', $content);
//         $this->assertStringContainsString('$table->text(\'post_title\');', $content);
//         $this->assertStringContainsString('$table->string(\'post_status\', 20)', $content);
//         $this->assertStringContainsString('$table->index([\'post_type\', \'post_status\', \'post_date\', \'ID\']);', $content);
//     }
// 
//     /**
//      * Test WordPress taxonomy migration creation (covers lines 411-474)
//      */
//     public function testCreateWordPressTaxonomyMigration()
//     {
//         $uniqueName = 'create_taxonomy_tables_' . uniqid();
//         $fileName = $this->creator->createWordPress($uniqueName, 'taxonomy');
// 
//         $this->assertFileExists($fileName);
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('Schema::create(\'terms\'', $content);
//         $this->assertStringContainsString('Schema::create(\'term_taxonomy\'', $content);
//         $this->assertStringContainsString('Schema::create(\'term_relationships\'', $content);
//         $this->assertStringContainsString('$table->string(\'name\', 200)', $content);
//         $this->assertStringContainsString('$table->string(\'taxonomy\', 32)', $content);
//         $this->assertStringContainsString('$table->foreign(\'term_id\')', $content);
//         $this->assertStringContainsString('Schema::dropIfExists(\'term_relationships\');', $content);
//         $this->assertStringContainsString('Schema::dropIfExists(\'term_taxonomy\');', $content);
//         $this->assertStringContainsString('Schema::dropIfExists(\'terms\');', $content);
//     }
// 
//     /**
//      * Test WordPress meta migration creation (covers lines 479-512)
//      */
//     public function testCreateWordPressMetaMigration()
//     {
//         // Use a specific pattern that we can predict
//         $fileName = $this->creator->createWordPress('create_post_meta_table', 'meta');
// 
//         $this->assertFileExists($fileName);
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('$table->bigIncrements(\'meta_id\');', $content);
//         $this->assertStringContainsString('$table->unsignedBigInteger(\'post_id\')', $content);
//         $this->assertStringContainsString('$table->string(\'meta_key\')', $content);
//         $this->assertStringContainsString('$table->longText(\'meta_value\')', $content);
//     }
// 
//     /**
//      * Test WordPress stub population with table name extraction (covers lines 517-533)
//      */
//     public function testWordPressStubPopulationWithTableExtraction()
//     {
//         // Use a specific pattern that we can predict
//         $fileName = $this->creator->createWordPress('create_user_meta_table', 'meta');
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('create(\'user_meta\'', $content);
//         $this->assertStringContainsString('$table->unsignedBigInteger(\'user_id\')', $content);
//         $this->assertStringContainsString('dropIfExists(\'user_meta\');', $content);
//     }
// 
//     /**
//      * Test duplicate WordPress migration detection
//      */
//     public function testEnsureWordPressMigrationDoesntAlreadyExist()
//     {
//         $uniqueName = 'wp_duplicate_' . uniqid();
//         $this->expectException(InvalidArgumentException::class);
//         $this->expectExceptionMessage("A migration file with name {$uniqueName} already exists.");
// 
//         // Create first migration
//         $this->creator->createWordPress($uniqueName, 'post');
// 
//         // Try to create another with the same name
//         $this->creator->createWordPress($uniqueName, 'taxonomy');
//     }
// 
//     /**
//      * Test custom stub for create migrations
//      */
//     public function testCustomCreateStub()
//     {
//         $customCreateStub = <<<'STUB'
// <?php
// 
// class {{ class }} extends Migration
// {
//     public function up(): void
//     {
//         // Custom create logic for {{ table }}
//         {{ up }}
//     }
// 
//     public function down(): void
//     {
//         // Custom drop logic
//         {{ down }}
//     }
// }
// STUB;
// 
//         $this->creator->registerStub('create', $customCreateStub);
//         $uniqueName = 'create_custom_table_' . uniqid();
//         $fileName = $this->creator->create($uniqueName, 'custom', true);
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('// Custom create logic for custom', $content);
//         $this->assertStringContainsString('// Custom drop logic', $content);
//     }
// 
//     /**
//      * Test custom stub for update migrations
//      */
//     public function testCustomUpdateStub()
//     {
//         $customUpdateStub = <<<'STUB'
// <?php
// 
// class {{ class }} extends Migration
// {
//     public function up(): void
//     {
//         // Custom update logic for {{ table }}
//         {{ up }}
//     }
// 
//     public function down(): void
//     {
//         // Custom rollback logic
//         {{ down }}
//     }
// }
// STUB;
// 
//         $this->creator->registerStub('update', $customUpdateStub);
//         $uniqueName = 'modify_existing_table_' . uniqid();
//         $fileName = $this->creator->create($uniqueName, 'existing', false);
// 
//         $content = file_get_contents($fileName);
//         $this->assertStringContainsString('// Custom update logic for existing', $content);
//         $this->assertStringContainsString('// Custom rollback logic', $content);
//     }
// }// PHPUNIT CLASS COMMENTED OUT - ALL TESTS CONVERTED TO PEST
