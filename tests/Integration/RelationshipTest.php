<?php

use Bob\Database\Connection;
use Bob\Database\Model;

// Define test models for relationships
class RelUser extends Model
{
    protected string $table = 'users';
    protected $fillable = ['name', 'email'];
    public bool $timestamps = false;

    public function posts()
    {
        return $this->hasMany(RelPost::class, 'user_id', 'id');
    }

    public function profile()
    {
        return $this->hasOne(RelProfile::class, 'user_id', 'id');
    }
}

class RelPost extends Model
{
    protected string $table = 'posts';
    protected $fillable = ['title', 'content', 'user_id'];
    public bool $timestamps = false;

    public function user()
    {
        return $this->belongsTo(RelUser::class, 'user_id', 'id');
    }

    public function tags()
    {
        return $this->belongsToMany(RelTag::class, 'post_tags', 'post_id', 'tag_id');
    }
}

class RelProfile extends Model
{
    protected string $table = 'profiles';
    protected $fillable = ['user_id', 'bio', 'avatar'];
    public bool $timestamps = false;

    public function user()
    {
        return $this->belongsTo(RelUser::class, 'user_id', 'id');
    }
}

class RelTag extends Model
{
    protected string $table = 'tags';
    protected $fillable = ['name', 'slug'];
    public bool $timestamps = false;

    public function posts()
    {
        return $this->belongsToMany(RelPost::class, 'post_tags', 'tag_id', 'post_id');
    }
}

beforeEach(function () {
    $this->connection = $this->createSQLiteConnection();

    // Create tables for relationship testing
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255),
            email VARCHAR(255) UNIQUE
        )
    ');

    $this->connection->statement('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255),
            content TEXT,
            user_id INTEGER,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )
    ');

    $this->connection->statement('
        CREATE TABLE profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE,
            bio TEXT,
            avatar VARCHAR(255),
            FOREIGN KEY(user_id) REFERENCES users(id)
        )
    ');

    $this->connection->statement('
        CREATE TABLE tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100),
            slug VARCHAR(100) UNIQUE
        )
    ');

    $this->connection->statement('
        CREATE TABLE post_tags (
            post_id INTEGER,
            tag_id INTEGER,
            PRIMARY KEY(post_id, tag_id),
            FOREIGN KEY(post_id) REFERENCES posts(id),
            FOREIGN KEY(tag_id) REFERENCES tags(id)
        )
    ');

    // Set the connection for models
    Model::setConnection($this->connection);
});

describe('Relationships', function () {

    test('hasOne relationship', function () {
        // Create a user
        $userId = $this->connection->table('users')->insertGetId([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // Create a profile for the user
        $this->connection->table('profiles')->insert([
            'user_id' => $userId,
            'bio' => 'Software Developer',
            'avatar' => 'avatar.jpg'
        ]);

        // Test the relationship
        $user = RelUser::find($userId);
        $profile = $user->profile;

        expect($profile)->toBeInstanceOf(RelProfile::class);
        expect($profile->bio)->toBe('Software Developer');
        expect($profile->user_id)->toBe($userId);
    });

    test('hasMany relationship', function () {
        // Create a user
        $userId = $this->connection->table('users')->insertGetId([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);

        // Create posts for the user
        $this->connection->table('posts')->insert([
            ['title' => 'First Post', 'content' => 'Content 1', 'user_id' => $userId],
            ['title' => 'Second Post', 'content' => 'Content 2', 'user_id' => $userId],
            ['title' => 'Third Post', 'content' => 'Content 3', 'user_id' => $userId],
        ]);

        // Test the relationship
        $user = RelUser::find($userId);
        $posts = $user->posts;

        expect($posts)->toBeArray();
        expect($posts)->toHaveCount(3);
        expect($posts[0])->toBeInstanceOf(RelPost::class);
        expect($posts[0]->title)->toBe('First Post');
    });

    test('belongsTo relationship', function () {
        // Create a user
        $userId = $this->connection->table('users')->insertGetId([
            'name' => 'Bob Smith',
            'email' => 'bob@example.com'
        ]);

        // Create a post
        $postId = $this->connection->table('posts')->insertGetId([
            'title' => 'My Blog Post',
            'content' => 'Blog content',
            'user_id' => $userId
        ]);

        // Test the relationship
        $post = RelPost::find($postId);
        $user = $post->user;

        expect($user)->toBeInstanceOf(RelUser::class);
        expect($user->name)->toBe('Bob Smith');
        expect($user->id)->toBe($userId);
    });

    test('belongsToMany relationship', function () {
        // Create tags
        $tagIds = [];
        $tagIds[] = $this->connection->table('tags')->insertGetId([
            'name' => 'PHP',
            'slug' => 'php'
        ]);
        $tagIds[] = $this->connection->table('tags')->insertGetId([
            'name' => 'Laravel',
            'slug' => 'laravel'
        ]);
        $tagIds[] = $this->connection->table('tags')->insertGetId([
            'name' => 'Testing',
            'slug' => 'testing'
        ]);

        // Create a user and post
        $userId = $this->connection->table('users')->insertGetId([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com'
        ]);

        $postId = $this->connection->table('posts')->insertGetId([
            'title' => 'Testing in Laravel',
            'content' => 'How to test Laravel applications',
            'user_id' => $userId
        ]);

        // Create pivot table entries
        foreach ($tagIds as $tagId) {
            $this->connection->table('post_tags')->insert([
                'post_id' => $postId,
                'tag_id' => $tagId
            ]);
        }

        // Test the relationship
        $post = RelPost::find($postId);
        $tags = $post->tags;

        expect($tags)->toBeArray();
        expect($tags)->toHaveCount(3);
        expect($tags[0])->toBeInstanceOf(RelTag::class);

        $tagNames = array_map(fn($tag) => $tag->name, $tags);
        expect($tagNames)->toContain('PHP');
        expect($tagNames)->toContain('Laravel');
        expect($tagNames)->toContain('Testing');
    });

    test('eager loading with with()', function () {
        // Create users with posts
        $user1Id = $this->connection->table('users')->insertGetId([
            'name' => 'User 1',
            'email' => 'user1@example.com'
        ]);

        $user2Id = $this->connection->table('users')->insertGetId([
            'name' => 'User 2',
            'email' => 'user2@example.com'
        ]);

        // Create posts
        $this->connection->table('posts')->insert([
            ['title' => 'User 1 Post 1', 'content' => 'Content', 'user_id' => $user1Id],
            ['title' => 'User 1 Post 2', 'content' => 'Content', 'user_id' => $user1Id],
            ['title' => 'User 2 Post 1', 'content' => 'Content', 'user_id' => $user2Id],
        ]);

        // Test eager loading
        $users = RelUser::query()->with('posts')->get();

        expect($users)->toHaveCount(2);

        // Check that posts are loaded
        foreach ($users as $user) {
            expect($user->relationLoaded('posts'))->toBeTrue();
            $posts = $user->posts;
            if ($user->name === 'User 1') {
                expect($posts)->toHaveCount(2);
            } else {
                expect($posts)->toHaveCount(1);
            }
        }
    });

    test('relationship chaining', function () {
        // Create a user
        $userId = $this->connection->table('users')->insertGetId([
            'name' => 'Charlie Brown',
            'email' => 'charlie@example.com'
        ]);

        // Create posts
        $post1Id = $this->connection->table('posts')->insertGetId([
            'title' => 'Published Post',
            'content' => 'Content',
            'user_id' => $userId
        ]);

        $post2Id = $this->connection->table('posts')->insertGetId([
            'title' => 'Draft Post',
            'content' => 'Draft content',
            'user_id' => $userId
        ]);

        // Test relationship with query constraints
        $user = RelUser::find($userId);

        // Get only posts with 'Published' in title
        $publishedPosts = $user->posts()->where('title', 'LIKE', '%Published%')->get();

        expect($publishedPosts)->toHaveCount(1);
        expect($publishedPosts[0]->title)->toBe('Published Post');
    });

    test('attach and detach for belongsToMany', function () {
        // Create a post and tags
        $userId = $this->connection->table('users')->insertGetId([
            'name' => 'David Lee',
            'email' => 'david@example.com'
        ]);

        $postId = $this->connection->table('posts')->insertGetId([
            'title' => 'My Post',
            'content' => 'Content',
            'user_id' => $userId
        ]);

        $tag1Id = $this->connection->table('tags')->insertGetId([
            'name' => 'JavaScript',
            'slug' => 'javascript'
        ]);

        $tag2Id = $this->connection->table('tags')->insertGetId([
            'name' => 'React',
            'slug' => 'react'
        ]);

        $post = RelPost::find($postId);

        // Attach tags
        $post->tags()->attach($tag1Id);
        $post->tags()->attach($tag2Id);

        $tags = $post->tags()->get();
        expect($tags)->toHaveCount(2);

        // Detach one tag
        $post->tags()->detach($tag1Id);

        $tags = $post->tags()->get();
        expect($tags)->toHaveCount(1);
        expect($tags[0]->name)->toBe('React');

        // Sync tags (replace all)
        $post->tags()->sync([$tag1Id]);

        $tags = $post->tags()->get();
        expect($tags)->toHaveCount(1);
        expect($tags[0]->name)->toBe('JavaScript');
    });
});