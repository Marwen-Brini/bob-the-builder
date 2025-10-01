<?php

// =============================================================================
// CONVERTED TO PEST - Original PHPUnit code commented below for reference
// =============================================================================

declare(strict_types=1);

use Bob\Database\Connection;
use Bob\Database\Migrations\Migration;
use Bob\Database\Migrations\MigrationCreator;
use Bob\Database\Migrations\MigrationEvents;
use Bob\Database\Migrations\MigrationRepository;
use Bob\Database\Migrations\MigrationRunner;

/**
 * Tests for the "optional" enhancements that make Bob perfect
 *
 * These tests verify that even the smallest suggestions from the
 * Quantum ORM team have been implemented with excellence.
 */
beforeEach(function () {
    // Create test directory
    $this->testPath = sys_get_temp_dir().'/bob_migration_test_'.uniqid();
    mkdir($this->testPath, 0777, true);

    // Setup SQLite connection for testing
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
});

afterEach(function () {
    // Clean up test directory
    if (is_dir($this->testPath)) {
        array_map('unlink', glob($this->testPath.'/*.php'));
        rmdir($this->testPath);
    }
});

function createTestMigrationPerfection(string $testPath): void
{
    $uniqueId = uniqid();
    $content = '<?php
use Bob\Database\Migrations\Migration;
use Bob\Schema\Blueprint;
use Bob\Schema\Schema;

class TestMigration'.$uniqueId.' extends Migration
{
    public function up(): void
    {
        Schema::create("test_table", function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("test_table");
    }
}';

    file_put_contents($testPath.'/test_migration_'.$uniqueId.'.php', $content);
}

test('migration events interface exists', function () {
    expect(interface_exists(MigrationEvents::class))->toBeTrue();

    // Test all event constants exist
    $expectedEvents = [
        'BEFORE_RUN' => 'migration.before_run',
        'AFTER_RUN' => 'migration.after_run',
        'ERROR' => 'migration.error',
        'BEFORE_MIGRATION' => 'migration.before_migration',
        'AFTER_MIGRATION' => 'migration.after_migration',
        'BEFORE_ROLLBACK' => 'migration.before_rollback',
        'AFTER_ROLLBACK' => 'migration.after_rollback',
        'REPOSITORY_CREATED' => 'migration.repository_created',
        'STATUS_CHECK' => 'migration.status_check',
        'PRETEND' => 'migration.pretend',
    ];

    foreach ($expectedEvents as $constant => $value) {
        expect(defined(MigrationEvents::class.'::'.$constant))
            ->toBeTrue("Constant {$constant} should exist");

        expect(constant(MigrationEvents::class.'::'.$constant))
            ->toBe($value, "Constant {$constant} should have value '{$value}'");
    }
})->group('feature', 'migrations');

test('migration events usage scenario', function () {
    // Simulate an event dispatcher using the constants
    $eventStorage = new class implements \Bob\Events\EventDispatcherInterface
    {
        public array $events = [];

        public function dispatch(string $event, array $payload = []): void
        {
            $this->events[] = ['event' => $event, 'payload' => $payload];
        }
    };

    // Create a runner with event dispatcher
    $runner = new MigrationRunner($this->connection, new MigrationRepository($this->connection));
    $runner->setEventDispatcher($eventStorage);

    // Run migrations (will trigger events)
    $runner->run();

    // Verify events were dispatched with correct constants
    // Should have BEFORE_RUN, REPOSITORY_CREATED, and AFTER_RUN
    expect($eventStorage->events)->toHaveCount(3);
    expect($eventStorage->events[0]['event'])->toBe('migration.before_run');
    expect($eventStorage->events[1]['event'])->toBe('migration.repository_created');
    expect($eventStorage->events[2]['event'])->toBe('migration.after_run');
})->group('feature', 'migrations');

test('migration creator uses gmt', function () {
    $creator = new MigrationCreator($this->testPath);

    // Get the current UTC time
    $beforeCreate = new DateTime('now', new DateTimeZone('UTC'));

    // Create a migration
    $path = $creator->create('perfect_test_migration');

    // Extract timestamp from filename
    $filename = basename($path);
    expect($filename)->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_perfect_test_migration\.php$/');

    // Parse the timestamp
    $parts = explode('_', $filename);
    $year = $parts[0];
    $month = $parts[1];
    $day = $parts[2];
    $time = $parts[3]; // HHMMSS

    // Create DateTime from the parsed timestamp
    $hour = substr($time, 0, 2);
    $minute = substr($time, 2, 2);
    $second = substr($time, 4, 2);

    $migrationTime = new DateTime(
        "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}",
        new DateTimeZone('UTC')
    );

    // Get the current UTC time after creation
    $afterCreate = new DateTime('now', new DateTimeZone('UTC'));

    // The migration timestamp should be between before and after
    expect($migrationTime->getTimestamp())
        ->toBeGreaterThanOrEqual($beforeCreate->getTimestamp())
        ->and($migrationTime->getTimestamp())
        ->toBeLessThanOrEqual($afterCreate->getTimestamp());

    // Clean up
    unlink($path);
})->group('feature', 'migrations');

test('notes array type hint', function () {
    // Use reflection to check the PHPDoc
    $reflection = new \ReflectionClass(MigrationRunner::class);
    $property = $reflection->getProperty('notes');
    $docComment = $property->getDocComment();

    expect($docComment)->not->toBeFalse('Notes property should have a doc comment');
    expect($docComment)->toContain('@var string[]');
})->group('feature', 'migrations');

test('notes array contains strings', function () {
    $repository = new MigrationRepository($this->connection);
    $runner = new MigrationRunner($this->connection, $repository, [$this->testPath]);

    // Create a test migration
    createTestMigrationPerfection($this->testPath);

    // Run migrations
    $runner->run();

    // Get notes using reflection
    $reflection = new \ReflectionClass($runner);
    $notesProperty = $reflection->getProperty('notes');
    $notesProperty->setAccessible(true);
    $notes = $notesProperty->getValue($runner);

    // Verify all notes are strings
    expect($notes)->toBeArray();
    foreach ($notes as $note) {
        expect($note)->toBeString('Each note should be a string');
    }
})->group('feature', 'migrations');

test('migration creator timezone independence', function () {
    // Save current timezone
    $originalTimezone = date_default_timezone_get();

    try {
        // Test with different timezones
        $timezones = ['America/New_York', 'Europe/London', 'Asia/Tokyo', 'Australia/Sydney'];
        $timestamps = [];

        foreach ($timezones as $timezone) {
            // Set PHP default timezone
            date_default_timezone_set($timezone);

            // Create a migration
            $creator = new MigrationCreator($this->testPath);
            $cleanName = str_replace(['/', '\\', ' ', ':'], '_', $timezone);
            $path = $creator->create("test_migration_{$cleanName}");

            // Extract timestamp
            $filename = basename($path);
            if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', $filename, $matches)) {
                $timestamps[] = $matches[1];
            } else {
                throw new Exception("Failed to extract timestamp from: {$filename}");
            }

            // Clean up
            unlink($path);
        }

        // All timestamps should be very close (within a few seconds)
        // since they use GMT regardless of timezone
        $firstTime = DateTime::createFromFormat('Y_m_d_His', $timestamps[0], new DateTimeZone('UTC'));

        foreach ($timestamps as $i => $timestamp) {
            $time = DateTime::createFromFormat('Y_m_d_His', $timestamp, new DateTimeZone('UTC'));
            $diff = abs($time->getTimestamp() - $firstTime->getTimestamp());

            // Should be within 5 seconds (accounting for test execution time)
            expect($diff)->toBeLessThan(5, "Timestamp from timezone {$timezones[$i]} should be close to others (GMT-based)");
        }
    } finally {
        // Restore original timezone
        date_default_timezone_set($originalTimezone);
    }
})->group('feature', 'migrations');

test('perfect integration', function () {
    // Setup event tracking
    $eventTracker = new class
    {
        public array $events = [];
    };

    // Create a perfect migration system using all enhancements
    $repository = new MigrationRepository($this->connection);
    $runner = new class($this->connection, $repository, $eventTracker) extends MigrationRunner
    {
        private object $tracker;

        public function __construct(Connection $connection, MigrationRepository $repository, object $tracker)
        {
            parent::__construct($connection, $repository);
            $this->tracker = $tracker;
        }

        protected function beforeRun(): void
        {
            $this->tracker->events[] = MigrationEvents::BEFORE_RUN;
            parent::beforeRun();
        }

        protected function afterRun(array $migrations): void
        {
            $this->tracker->events[] = MigrationEvents::AFTER_RUN;
            parent::afterRun($migrations);
        }

        public function getNotes(): array
        {
            return $this->notes; // Type-hinted as string[]
        }
    };

    // Create a migration using GMT timestamp
    $creator = new MigrationCreator($this->testPath);
    $migrationFile = $creator->create('perfect_migration');

    // Add path to runner
    $runner->addPath($this->testPath);

    // Run the migration
    $runner->run();

    // Verify all three enhancements work together
    // 1. Events were triggered
    expect($eventTracker->events)->toContain(MigrationEvents::BEFORE_RUN);
    expect($eventTracker->events)->toContain(MigrationEvents::AFTER_RUN);

    // 2. Notes are strings
    $notes = $runner->getNotes();
    foreach ($notes as $note) {
        expect($note)->toBeString();
    }

    // 3. Migration file has GMT timestamp
    $filename = basename($migrationFile);
    expect($filename)->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_/');

    // Clean up
    unlink($migrationFile);
})->group('feature', 'migrations');
