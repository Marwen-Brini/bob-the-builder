<?php

use Bob\Database\Connection;
use Bob\Database\Model;

class User extends Model
{
    protected string $table = 'users';
    protected $fillable = ['name', 'email', 'age'];
    public bool $timestamps = false;
}

beforeEach(function () {
    $this->connection = $this->createSQLiteConnection();

    // Create test table
    $this->connection->statement('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255),
            email VARCHAR(255) UNIQUE,
            age INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');

    // Set the connection directly on Model
    Model::setConnection($this->connection);
});

describe('Database Operations', function () {
    
    test('insert and select', function () {
        $result = $this->connection->table('users')->insert([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ]);
        
        expect($result)->toBeTrue();
        
        $users = $this->connection->table('users')->get();
        expect($users)->toHaveCount(1);
        expect($users[0]->name)->toBe('John Doe');
    });
    
    test('insert and get id', function () {
        $id = $this->connection->table('users')->insertGetId([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'age' => 25
        ]);
        
        expect($id)->toBe(1);
        
        $user = $this->connection->table('users')->find($id);
        expect($user->name)->toBe('Jane Doe');
    });
    
    test('bulk insert', function () {
        $result = $this->connection->table('users')->insert([
            ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20],
            ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 25],
            ['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 30],
        ]);
        
        expect($result)->toBeTrue();
        
        $count = $this->connection->table('users')->count();
        expect($count)->toBe(3);
    });
    
    test('update', function () {
        $this->connection->table('users')->insert([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ]);
        
        $affected = $this->connection->table('users')
            ->where('email', 'john@example.com')
            ->update(['age' => 31]);
        
        expect($affected)->toBe(1);
        
        $user = $this->connection->table('users')
            ->where('email', 'john@example.com')
            ->first();
        
        expect($user->age)->toBe(31);
    });
    
    test('delete', function () {
        $this->connection->table('users')->insert([
            ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20],
            ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 25],
            ['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 30],
        ]);
        
        $deleted = $this->connection->table('users')
            ->where('age', '<', 26)
            ->delete();
        
        expect($deleted)->toBe(2);
        
        $remaining = $this->connection->table('users')->count();
        expect($remaining)->toBe(1);
    });
    
    test('where conditions', function () {
        $this->connection->table('users')->insert([
            ['name' => 'John', 'email' => 'john@example.com', 'age' => 30],
            ['name' => 'Jane', 'email' => 'jane@example.com', 'age' => 25],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 35],
        ]);
        
        $users = $this->connection->table('users')
            ->where('age', '>', 25)
            ->get();
        
        expect($users)->toHaveCount(2);
        
        $users = $this->connection->table('users')
            ->whereIn('name', ['John', 'Bob'])
            ->get();
        
        expect($users)->toHaveCount(2);
        
        $users = $this->connection->table('users')
            ->whereBetween('age', [25, 30])
            ->get();
        
        expect($users)->toHaveCount(2);
    });
    
    test('ordering and limiting', function () {
        $this->connection->table('users')->insert([
            ['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 30],
            ['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 35],
        ]);
        
        $users = $this->connection->table('users')
            ->orderBy('name')
            ->get();
        
        expect($users[0]->name)->toBe('Alice');
        expect($users[1]->name)->toBe('Bob');
        expect($users[2]->name)->toBe('Charlie');
        
        $users = $this->connection->table('users')
            ->orderBy('age', 'desc')
            ->limit(2)
            ->get();
        
        expect($users)->toHaveCount(2);
        expect($users[0]->age)->toBe(35);
    });
    
    test('aggregates', function () {
        $this->connection->table('users')->insert([
            ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20],
            ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 30],
            ['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 40],
        ]);
        
        $count = $this->connection->table('users')->count();
        expect($count)->toBe(3);
        
        $max = $this->connection->table('users')->max('age');
        expect($max)->toBe(40);
        
        $min = $this->connection->table('users')->min('age');
        expect($min)->toBe(20);
        
        $avg = $this->connection->table('users')->avg('age');
        expect($avg)->toBe(30.0);
        
        $sum = $this->connection->table('users')->sum('age');
        expect($sum)->toBe(90);
    });
    
    test('transactions', function () {
        $this->connection->transaction(function () {
            $this->connection->table('users')->insert([
                'name' => 'User 1',
                'email' => 'user1@example.com',
                'age' => 25
            ]);
            
            $this->connection->table('users')->insert([
                'name' => 'User 2',
                'email' => 'user2@example.com',
                'age' => 30
            ]);
        });
        
        $count = $this->connection->table('users')->count();
        expect($count)->toBe(2);
        
        // Test rollback
        try {
            $this->connection->transaction(function () {
                $this->connection->table('users')->insert([
                    'name' => 'User 3',
                    'email' => 'user3@example.com',
                    'age' => 35
                ]);
                
                throw new Exception('Rollback');
            });
        } catch (Exception $e) {
            // Transaction should be rolled back
        }
        
        $count = $this->connection->table('users')->count();
        expect($count)->toBe(2); // Still 2, not 3
    });
    
    test('Model operations', function () {
        $user = new User();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->age = 30;
        $user->save();
        
        expect($user->id)->toBe(1);
        expect($user->exists)->toBeTrue();
        
        // Find by ID
        $found = User::find(1);
        expect($found->name)->toBe('John Doe');
        
        // Update
        $found->age = 31;
        $found->save();
        
        $updated = User::find(1);
        expect($updated->age)->toBe(31);
        
        // Delete
        $updated->delete();
        
        $deleted = User::find(1);
        expect($deleted)->toBeNull();
    });
    
    test('Model mass assignment', function () {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'age' => 25
        ]);
        
        expect($user->id)->toBe(1);
        expect($user->name)->toBe('Jane Doe');
        
        // Update with fill
        $user->fill(['age' => 26]);
        $user->save();
        
        $updated = User::find(1);
        expect($updated->age)->toBe(26);
    });
    
    test('Model querying', function () {
        User::create(['name' => 'John', 'email' => 'john@example.com', 'age' => 30]);
        User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'age' => 25]);
        User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 35]);
        
        $users = User::where('age', '>', 25)->get();
        expect($users)->toHaveCount(2);
        
        $user = User::where('email', 'jane@example.com')->first();
        expect($user->name)->toBe('Jane');
        
        $count = User::where('age', '>=', 30)->count();
        expect($count)->toBe(2);
    });
});