<?php

declare(strict_types=1);

namespace Bob\Database\Migrations;

use InvalidArgumentException;

/**
 * Migration Creator
 *
 * Creates new migration files from stubs/templates.
 */
class MigrationCreator
{
    /**
     * The path to store migration files
     */
    protected string $path;

    /**
     * The registered custom stubs
     */
    protected array $customStubs = [];

    /**
     * Create a new migration creator instance
     */
    public function __construct(string $path = '')
    {
        $this->path = $path;
    }

    /**
     * Create a new migration file
     */
    public function create(string $name, ?string $table = null, bool $create = false): string
    {
        $this->ensureMigrationDoesntAlreadyExist($name);

        // Build the file name with timestamp prefix
        $fileName = $this->getDatePrefix().'_'.$name.'.php';
        $filePath = $this->path.'/'.$fileName;

        // Get the stub content
        $stub = $this->getStub($table, $create);

        // Populate the stub
        $stub = $this->populateStub($name, $stub, $table, $create);

        // Write the migration file
        file_put_contents($filePath, $stub);

        return $filePath;
    }

    /**
     * Ensure that a migration with the given name doesn't already exist
     */
    protected function ensureMigrationDoesntAlreadyExist(string $name): void
    {
        if (class_exists($className = $this->getClassName($name))) {
            throw new InvalidArgumentException("A {$className} class already exists.");
        }

        $files = glob($this->path.'/*_'.$name.'.php');

        if (count($files) > 0) {
            throw new InvalidArgumentException("A migration file with name {$name} already exists.");
        }
    }

    /**
     * Get the migration stub content
     */
    protected function getStub(?string $table, bool $create): string
    {
        if ($table === null) {
            $stub = $this->getBlankStub();
        } elseif ($create) {
            $stub = $this->getCreateStub();
        } else {
            $stub = $this->getUpdateStub();
        }

        return $stub;
    }

    /**
     * Populate the stub with values
     */
    protected function populateStub(string $name, string $stub, ?string $table, bool $create): string
    {
        $className = $this->getClassName($name);

        $stub = str_replace('{{ class }}', $className, $stub);
        $stub = str_replace('{{ table }}', $table ?? '', $stub);

        if ($table !== null) {
            if ($create) {
                $stub = str_replace('{{ up }}', $this->getCreateUpContent($table), $stub);
                $stub = str_replace('{{ down }}', $this->getCreateDownContent($table), $stub);
            } else {
                $stub = str_replace('{{ up }}', $this->getUpdateUpContent($table), $stub);
                $stub = str_replace('{{ down }}', $this->getUpdateDownContent($table), $stub);
            }
        }

        return $stub;
    }

    /**
     * Get the class name from migration name
     */
    protected function getClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    /**
     * Get the date prefix for the migration
     *
     * Uses GMT/UTC time for consistency across timezones
     */
    protected function getDatePrefix(): string
    {
        return gmdate('Y_m_d_His');
    }

    /**
     * Get a blank migration stub
     */
    protected function getBlankStub(): string
    {
        return $this->customStubs['blank'] ?? <<<'STUB'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class {{ class }} extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        //
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        //
    }
}
STUB;
    }

    /**
     * Get a create table migration stub
     */
    protected function getCreateStub(): string
    {
        return $this->customStubs['create'] ?? <<<'STUB'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class {{ class }} extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        Schema::create('{{ table }}', function (Blueprint $table) {
            $table->id();
            {{ up }}
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('{{ table }}');
    }
}
STUB;
    }

    /**
     * Get an update table migration stub
     */
    protected function getUpdateStub(): string
    {
        return $this->customStubs['update'] ?? <<<'STUB'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class {{ class }} extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        Schema::table('{{ table }}', function (Blueprint $table) {
            {{ up }}
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('{{ table }}', function (Blueprint $table) {
            {{ down }}
        });
    }
}
STUB;
    }

    /**
     * Get the up method content for create migrations
     */
    protected function getCreateUpContent(string $table): string
    {
        // Provide some common column suggestions based on table name
        $content = [];

        if (strpos($table, 'users') !== false) {
            $content[] = '$table->string(\'name\');';
            $content[] = '$table->string(\'email\')->unique();';
            $content[] = '$table->timestamp(\'email_verified_at\')->nullable();';
            $content[] = '$table->string(\'password\');';
            $content[] = '$table->rememberToken();';
        } elseif (strpos($table, 'posts') !== false || strpos($table, 'articles') !== false) {
            $content[] = '$table->string(\'title\');';
            $content[] = '$table->text(\'content\');';
            $content[] = '$table->string(\'slug\')->unique();';
            $content[] = '$table->boolean(\'published\')->default(false);';
            $content[] = '$table->foreignId(\'user_id\')->constrained();';
        } else {
            $content[] = '// Add columns here';
        }

        return implode("\n            ", $content);
    }

    /**
     * Get the down method content for create migrations
     */
    protected function getCreateDownContent(string $table): string
    {
        return ''; // The stub already handles dropIfExists
    }

    /**
     * Get the up method content for update migrations
     */
    protected function getUpdateUpContent(string $table): string
    {
        return '// Add columns or modifications here';
    }

    /**
     * Get the down method content for update migrations
     */
    protected function getUpdateDownContent(string $table): string
    {
        return '// Reverse the changes made in up()';
    }

    /**
     * Register a custom migration stub
     */
    public function registerStub(string $type, string $stub): void
    {
        $this->customStubs[$type] = $stub;
    }

    /**
     * Set the migration path
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * Get the migration path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Create a WordPress-specific migration
     */
    public function createWordPress(string $name, string $type = 'generic'): string
    {
        $this->ensureMigrationDoesntAlreadyExist($name);

        $fileName = $this->getDatePrefix().'_'.$name.'.php';
        $filePath = $this->path.'/'.$fileName;

        $stub = $this->getWordPressStub($type);
        $stub = $this->populateWordPressStub($name, $stub, $type);

        file_put_contents($filePath, $stub);

        return $filePath;
    }

    /**
     * Get WordPress-specific migration stub
     */
    protected function getWordPressStub(string $type): string
    {
        switch ($type) {
            case 'post':
                return $this->getWordPressPostStub();
            case 'taxonomy':
                return $this->getWordPressTaxonomyStub();
            case 'meta':
                return $this->getWordPressMetaStub();
            default:
                return $this->getBlankStub();
        }
    }

    /**
     * Get WordPress post table stub
     */
    protected function getWordPressPostStub(): string
    {
        return <<<'STUB'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class {{ class }} extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        Schema::create('{{ table }}', function (Blueprint $table) {
            $table->bigIncrements('ID');
            $table->unsignedBigInteger('post_author')->default(0)->index();
            $table->dateTime('post_date')->default('0000-00-00 00:00:00');
            $table->dateTime('post_date_gmt')->default('0000-00-00 00:00:00');
            $table->longText('post_content');
            $table->text('post_title');
            $table->text('post_excerpt');
            $table->string('post_status', 20)->default('publish');
            $table->string('comment_status', 20)->default('open');
            $table->string('ping_status', 20)->default('open');
            $table->string('post_password')->default('');
            $table->string('post_name', 200)->default('')->index();
            $table->text('to_ping');
            $table->text('pinged');
            $table->dateTime('post_modified')->default('0000-00-00 00:00:00');
            $table->dateTime('post_modified_gmt')->default('0000-00-00 00:00:00');
            $table->longText('post_content_filtered');
            $table->unsignedBigInteger('post_parent')->default(0)->index();
            $table->string('guid')->default('');
            $table->integer('menu_order')->default(0);
            $table->string('post_type', 20)->default('post');
            $table->string('post_mime_type', 100)->default('');
            $table->bigInteger('comment_count')->default(0);

            // Indexes
            $table->index(['post_type', 'post_status', 'post_date', 'ID']);
            $table->index('post_author');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('{{ table }}');
    }
}
STUB;
    }

    /**
     * Get WordPress taxonomy table stub
     */
    protected function getWordPressTaxonomyStub(): string
    {
        return <<<'STUB'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class {{ class }} extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        // Create terms table
        Schema::create('terms', function (Blueprint $table) {
            $table->bigIncrements('term_id');
            $table->string('name', 200)->default('')->index();
            $table->string('slug', 200)->default('')->index();
            $table->bigInteger('term_group')->default(0);
        });

        // Create term_taxonomy table
        Schema::create('term_taxonomy', function (Blueprint $table) {
            $table->bigIncrements('term_taxonomy_id');
            $table->unsignedBigInteger('term_id')->default(0);
            $table->string('taxonomy', 32)->default('')->index();
            $table->longText('description');
            $table->unsignedBigInteger('parent')->default(0);
            $table->bigInteger('count')->default(0);

            $table->unique(['term_id', 'taxonomy']);
            $table->foreign('term_id')->references('term_id')->on('terms');
        });

        // Create term_relationships table
        Schema::create('term_relationships', function (Blueprint $table) {
            $table->unsignedBigInteger('object_id')->default(0);
            $table->unsignedBigInteger('term_taxonomy_id')->default(0);
            $table->integer('term_order')->default(0);

            $table->primary(['object_id', 'term_taxonomy_id']);
            $table->index('term_taxonomy_id');

            $table->foreign('term_taxonomy_id')
                  ->references('term_taxonomy_id')
                  ->on('term_taxonomy');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('term_relationships');
        Schema::dropIfExists('term_taxonomy');
        Schema::dropIfExists('terms');
    }
}
STUB;
    }

    /**
     * Get WordPress meta table stub
     */
    protected function getWordPressMetaStub(): string
    {
        return <<<'STUB'
<?php

use Bob\Database\Migrations\Migration;
use Bob\Schema\Schema;
use Bob\Schema\Blueprint;

class {{ class }} extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        Schema::create('{{ table }}', function (Blueprint $table) {
            $table->bigIncrements('meta_id');
            $table->unsignedBigInteger('{{ object }}_id')->default(0)->index();
            $table->string('meta_key')->nullable()->index();
            $table->longText('meta_value')->nullable();
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('{{ table }}');
    }
}
STUB;
    }

    /**
     * Populate WordPress stub with values
     */
    protected function populateWordPressStub(string $name, string $stub, string $type): string
    {
        $className = $this->getClassName($name);
        $stub = str_replace('{{ class }}', $className, $stub);

        // Extract table name from migration name
        $tableName = strtolower(preg_replace('/create_(.+)_table/', '$1', $name));
        $stub = str_replace('{{ table }}', $tableName, $stub);

        // For meta tables, determine the object type
        if ($type === 'meta') {
            $objectType = str_replace('_meta', '', $tableName);
            $stub = str_replace('{{ object }}', $objectType, $stub);
        }

        return $stub;
    }
}
