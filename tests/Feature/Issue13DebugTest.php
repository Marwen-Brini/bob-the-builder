<?php

namespace Tests\Feature;

use Bob\Database\Connection;
use Bob\Database\Model;

class Issue13Post extends Model
{
    protected string $table = 'posts';

    protected string $primaryKey = 'ID';

    public bool $timestamps = false;
}

class Issue13Term extends Model
{
    protected string $table = 'terms';

    protected string $primaryKey = 'term_id';

    public bool $timestamps = false;

    public function posts()
    {
        // Let's debug what's happening
        $related = Issue13Post::class;
        $table = 'term_relationships';
        $foreignPivotKey = 'term_taxonomy_id';
        $relatedPivotKey = 'object_id';
        $parentKey = 'term_taxonomy_id'; // Should this be 'term_id'?
        $relatedKey = 'ID';

        return $this->belongsToMany(
            $related,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }
}

beforeEach(function () {
    $this->connection = new Connection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Create tables
    $this->connection->unprepared('
        CREATE TABLE posts (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            post_title TEXT NOT NULL
        )
    ');

    $this->connection->unprepared('
        CREATE TABLE terms (
            term_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )
    ');

    $this->connection->unprepared('
        CREATE TABLE term_taxonomy (
            term_taxonomy_id INTEGER PRIMARY KEY AUTOINCREMENT,
            term_id INTEGER NOT NULL,
            taxonomy TEXT NOT NULL
        )
    ');

    $this->connection->unprepared('
        CREATE TABLE term_relationships (
            object_id INTEGER NOT NULL,
            term_taxonomy_id INTEGER NOT NULL,
            PRIMARY KEY (object_id, term_taxonomy_id)
        )
    ');

    // Insert test data
    $this->connection->table('posts')->insert([
        ['ID' => 1, 'post_title' => 'First Post'],
        ['ID' => 2, 'post_title' => 'Second Post'],
    ]);

    $this->connection->table('terms')->insert([
        ['term_id' => 1, 'name' => 'Technology'],
    ]);

    $this->connection->table('term_taxonomy')->insert([
        ['term_taxonomy_id' => 101, 'term_id' => 1, 'taxonomy' => 'category'],
    ]);

    $this->connection->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 101],
        ['object_id' => 2, 'term_taxonomy_id' => 101],
    ]);

    Model::setConnection($this->connection);
});

test('Debug: Check the parent model attributes', function () {
    $term = Issue13Term::find(1);

    // Debug: What attributes does the term have?
    var_dump($term->getAttributes());

    // The issue is that we're looking for term_taxonomy_id on the terms table,
    // but that field doesn't exist on terms - it's only in term_taxonomy!

    $sql = $term->posts()->toSql();

    $bindings = $term->posts()->getBindings();
    var_dump($bindings);
});

test('Debug: Correct relationship setup', function () {
    // The real issue: term_taxonomy_id doesn't exist on the terms table
    // We need to either:
    // 1. Use term_id as the parent key
    // 2. Or join term_taxonomy table first

    // Let's try with correct parent key
    $term = new class extends Model
    {
        protected string $table = 'terms';

        protected string $primaryKey = 'term_id';

        public bool $timestamps = false;

        public function posts()
        {
            return $this->belongsToMany(
                Issue13Post::class,
                'term_relationships',  // pivot table
                'term_taxonomy_id',    // foreign key in pivot
                'object_id',           // related key in pivot
                'term_id',             // parent key (should be term_id, not term_taxonomy_id!)
                'ID'                   // related model's key
            );
        }
    };

    $term = $term::find(1);

    // But wait, this still won't work because term_relationships
    // references term_taxonomy_id, not term_id directly!
    // This is a WordPress-specific complexity.
});
