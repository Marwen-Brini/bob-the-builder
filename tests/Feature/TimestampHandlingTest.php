<?php

use Bob\Database\Connection;
use Bob\Database\Model;

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    Model::setConnection($this->connection);
});

afterEach(function () {
    Model::clearConnection();
});

test('model respects timestamps = false on insert', function () {
    // Create WordPress-style table WITHOUT timestamp columns
    $this->connection->statement('
        CREATE TABLE wp_posts (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            post_title TEXT,
            post_content TEXT,
            post_status TEXT,
            post_date DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');

    class WPPost extends Model
    {
        protected string $table = 'wp_posts';

        protected string $primaryKey = 'ID';

        protected bool $timestamps = false; // WordPress doesn't use created_at/updated_at
    }

    // This should NOT try to set created_at/updated_at
    $post = new WPPost;
    $post->post_title = 'Test Post';
    $post->post_content = 'Test content';
    $post->post_status = 'publish';

    // Save should work without trying to set timestamps
    $result = $post->save();

    expect($result)->toBeTrue();
    expect($post->ID)->toBeGreaterThan(0);

    // Verify the post was saved
    $saved = WPPost::find($post->ID);
    expect($saved)->toBeInstanceOf(WPPost::class);
    expect($saved->post_title)->toBe('Test Post');
});

test('model respects timestamps = false on update', function () {
    $this->connection->statement('
        CREATE TABLE wp_posts (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            post_title TEXT,
            post_content TEXT,
            post_status TEXT,
            post_modified DATETIME
        )
    ');

    // Insert a test record
    $this->connection->table('wp_posts')->insert([
        'post_title' => 'Original Title',
        'post_content' => 'Original content',
        'post_status' => 'publish',
    ]);

    class WPPostUpdate extends Model
    {
        protected string $table = 'wp_posts';

        protected string $primaryKey = 'ID';

        protected bool $timestamps = false;
    }

    $post = WPPostUpdate::find(1);
    expect($post)->toBeInstanceOf(WPPostUpdate::class);

    // Update the post - should NOT try to set updated_at
    $post->post_title = 'Updated Title';
    $result = $post->save();

    expect($result)->toBeTrue();

    // Verify the update worked
    $updated = WPPostUpdate::find(1);
    expect($updated->post_title)->toBe('Updated Title');
});

test('model with timestamps = true sets timestamps correctly', function () {
    // Create a table WITH timestamp columns
    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )
    ');

    class TimestampedPost extends Model
    {
        protected string $table = 'posts';

        protected bool $timestamps = true; // Default behavior
    }

    // Insert with timestamps
    $post = new TimestampedPost;
    $post->title = 'Test Post';
    $result = $post->save();

    expect($result)->toBeTrue();
    expect($post->created_at)->not->toBeNull();
    expect($post->updated_at)->not->toBeNull();
    expect($post->created_at)->toBe($post->updated_at); // Should be same on insert

    // Update should change updated_at
    $originalUpdated = $post->updated_at;
    sleep(1); // Ensure time difference

    $post->title = 'Updated Post';
    $post->save();

    expect($post->updated_at)->not->toBe($originalUpdated);
});

test('static create method respects timestamps setting', function () {
    $this->connection->statement('
        CREATE TABLE wp_users (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            user_login TEXT,
            user_email TEXT,
            user_registered DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');

    class WPUser extends Model
    {
        protected string $table = 'wp_users';

        protected string $primaryKey = 'ID';

        protected bool $timestamps = false;

        protected array $fillable = ['user_login', 'user_email'];
    }

    // This should work without timestamp columns
    $user = WPUser::create([
        'user_login' => 'testuser',
        'user_email' => 'test@example.com',
    ]);

    expect($user)->toBeInstanceOf(WPUser::class);
    expect($user->ID)->toBeGreaterThan(0);
    expect($user->user_login)->toBe('testuser');
});

test('query builder insert does not add timestamps', function () {
    $this->connection->statement('
        CREATE TABLE wp_options (
            option_id INTEGER PRIMARY KEY AUTOINCREMENT,
            option_name TEXT,
            option_value TEXT
        )
    ');

    // Direct query builder insert should never add timestamps
    $result = $this->connection->table('wp_options')->insert([
        'option_name' => 'test_option',
        'option_value' => 'test_value',
    ]);

    expect($result)->toBeTrue();

    // Verify it worked
    $option = $this->connection->table('wp_options')
        ->where('option_name', 'test_option')
        ->first();

    expect($option)->not->toBeNull();
    expect($option->option_value)->toBe('test_value');
});

test('model with custom timestamp column names', function () {
    $this->connection->statement('
        CREATE TABLE custom_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            created DATETIME,
            modified DATETIME
        )
    ');

    class CustomTimestampPost extends Model
    {
        protected string $table = 'custom_posts';

        protected bool $timestamps = true;

        protected string $createdAt = 'created';  // Custom column name

        protected string $updatedAt = 'modified'; // Custom column name
    }

    $post = new CustomTimestampPost;
    $post->title = 'Test';
    $result = $post->save();

    expect($result)->toBeTrue();
    expect($post->created)->not->toBeNull();
    expect($post->modified)->not->toBeNull();

    // Update should only change 'modified'
    $originalCreated = $post->created;
    sleep(1);

    $post->title = 'Updated';
    $post->save();

    expect($post->created)->toBe($originalCreated);
    expect($post->modified)->not->toBe($originalCreated);
});

test('touch method respects timestamps setting', function () {
    $this->connection->statement('
        CREATE TABLE wp_comments (
            comment_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            comment_content TEXT,
            comment_date DATETIME
        )
    ');

    class WPComment extends Model
    {
        protected string $table = 'wp_comments';

        protected string $primaryKey = 'comment_ID';

        protected bool $timestamps = false;
    }

    $this->connection->table('wp_comments')->insert([
        'comment_content' => 'Test comment',
    ]);

    $comment = WPComment::find(1);

    // Touch should return false when timestamps = false
    $result = $comment->touch();

    expect($result)->toBeFalse();
});
