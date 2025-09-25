<?php

use Bob\Query\Builder;
use Bob\Query\DynamicFinder;
use Bob\Database\Connection;
use Bob\Query\Grammar;
use Bob\Query\Processor;
use Mockery as m;

// Create a test class that uses the trait
class TestBuilderWithDynamicFinder extends Builder
{
    use DynamicFinder;

    public function __call($method, $parameters)
    {
        $result = $this->handleDynamicFinder($method, $parameters);
        if ($result !== null) {
            return $result;
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}

beforeEach(function () {
    $this->connection = m::mock(Connection::class);
    $this->grammar = m::mock(Grammar::class)->makePartial();
    $this->processor = m::mock(Processor::class);

    $this->connection->shouldReceive('getQueryGrammar')->andReturn($this->grammar);
    $this->connection->shouldReceive('getPostProcessor')->andReturn($this->processor);

    $this->builder = new TestBuilderWithDynamicFinder($this->connection, $this->grammar, $this->processor);

    // Clear any custom finders before each test
    TestBuilderWithDynamicFinder::clearFinders();
});

afterEach(function () {
    m::close();
    TestBuilderWithDynamicFinder::clearFinders();
});

test('camelToSnake converts camelCase to snake_case', function () {
    expect($this->builder->camelToSnake('camelCase'))->toBe('camel_case');
    expect($this->builder->camelToSnake('UserId'))->toBe('user_id');
    expect($this->builder->camelToSnake('userName'))->toBe('user_name');
    expect($this->builder->camelToSnake('HTMLParser'))->toBe('h_t_m_l_parser');
    expect($this->builder->camelToSnake('id'))->toBe('id');
    expect($this->builder->camelToSnake('ID'))->toBe('i_d');
});

test('findBy pattern calls where and first', function () {
    // Return a result so the method doesn't return null
    $this->processor->shouldReceive('processSelect')->once()->andReturn([(object)['email' => 'test@example.com']]);
    $this->connection->shouldReceive('selectOne')->once()->andReturn((object)['email' => 'test@example.com']);

    $this->builder->from('users');
    $result = $this->builder->findByEmail('test@example.com');

    expect($result)->toBeObject();
    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('email');
    expect($this->builder->wheres[0]['value'])->toBe('test@example.com');
});

test('findAllBy pattern calls where and get', function () {
    $this->processor->shouldReceive('processSelect')->once()->andReturn([]);
    $this->connection->shouldReceive('select')->once()->andReturn([]);

    $this->builder->from('users');
    $result = $this->builder->findAllByStatus('active');

    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('status');
    expect($this->builder->wheres[0]['value'])->toBe('active');
});

test('whereBy pattern adds where clause', function () {
    $this->builder->from('users');
    $result = $this->builder->whereByRole('admin');

    expect($result)->toBe($this->builder); // Returns builder for chaining
    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('role');
    expect($this->builder->wheres[0]['value'])->toBe('admin');
});

test('orWhereBy pattern adds orWhere clause', function () {
    $this->builder->from('users');
    $this->builder->where('active', true);
    $result = $this->builder->orWhereByRole('moderator');

    expect($result)->toBe($this->builder);
    expect($this->builder->wheres)->toHaveCount(2);
    expect($this->builder->wheres[1]['boolean'])->toBe('or');
    expect($this->builder->wheres[1]['column'])->toBe('role');
    expect($this->builder->wheres[1]['value'])->toBe('moderator');
});

test('firstWhere pattern calls where and first', function () {
    // Return a result so the method doesn't return null
    $this->processor->shouldReceive('processSelect')->once()->andReturn([(object)['slug' => 'my-slug']]);
    $this->connection->shouldReceive('selectOne')->once()->andReturn((object)['slug' => 'my-slug']);

    $this->builder->from('users');
    $result = $this->builder->firstWhereSlug('my-slug');

    expect($result)->toBeObject();
    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('slug');
    expect($this->builder->wheres[0]['value'])->toBe('my-slug');
});

test('countBy pattern calls where and count', function () {
    $this->grammar->shouldReceive('compileSelect')->once()->andReturn('select count(*) as aggregate from users where category = ?');
    $this->connection->shouldReceive('select')->once()->andReturn([(object)['aggregate' => 5]]);
    $this->processor->shouldReceive('processSelect')->once()->andReturn([(object)['aggregate' => 5]]);

    $this->builder->from('users');
    $result = $this->builder->countByCategory('electronics');

    expect($result)->toBe(5);
    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('category');
});

test('existsBy pattern calls where and exists', function () {
    $this->grammar->shouldReceive('compileExists')->once()->andReturn('select exists(select * from users where email = ?)');
    $this->processor->shouldReceive('processSelect')->once()->andReturn([['exists' => 1]]);
    $this->connection->shouldReceive('select')->once()->andReturn([['exists' => 1]]);

    $this->builder->from('users');
    $result = $this->builder->existsByEmail('test@example.com');

    expect($result)->toBeTrue();
    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('email');
});

test('deleteBy pattern calls where and delete', function () {
    $this->grammar->shouldReceive('compileDelete')->once()->andReturn('delete from users where id = ?');
    $this->connection->shouldReceive('delete')->once()->andReturn(1);

    $this->builder->from('users');
    $result = $this->builder->deleteById(123);

    expect($result)->toBe(1);
    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('id');
    expect($this->builder->wheres[0]['value'])->toBe(123);
});

test('orderBy pattern with Asc', function () {
    $this->builder->from('users');
    $result = $this->builder->orderByCreatedAtAsc();

    expect($result)->toBe($this->builder);
    expect($this->builder->orders)->toHaveCount(1);
    expect($this->builder->orders[0]['column'])->toBe('created_at');
    expect($this->builder->orders[0]['direction'])->toBe('asc');
});

test('orderBy pattern with Desc', function () {
    $this->builder->from('users');
    $result = $this->builder->orderByUpdatedAtDesc();

    expect($result)->toBe($this->builder);
    expect($this->builder->orders)->toHaveCount(1);
    expect($this->builder->orders[0]['column'])->toBe('updated_at');
    expect($this->builder->orders[0]['direction'])->toBe('desc');
});

test('groupBy pattern adds group by clause', function () {
    $this->builder->from('users');
    $result = $this->builder->groupByStatus();

    expect($result)->toBe($this->builder);
    expect($this->builder->groups)->toHaveCount(1);
    expect($this->builder->groups[0])->toBe('status');
});

test('custom finder pattern can be registered', function () {
    TestBuilderWithDynamicFinder::registerFinder(
        '/^latest(.+)$/',
        function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);
            $limit = $params[0] ?? 10;
            return $this->orderBy($column, 'desc')->limit($limit);
        }
    );

    $this->builder->from('posts');
    $result = $this->builder->latestPublishedAt(5);

    expect($result)->toBe($this->builder);
    expect($this->builder->orders)->toHaveCount(1);
    expect($this->builder->orders[0]['column'])->toBe('published_at');
    expect($this->builder->orders[0]['direction'])->toBe('desc');
    expect($this->builder->limit)->toBe(5);
});

test('clearFinders removes all custom patterns', function () {
    TestBuilderWithDynamicFinder::registerFinder('/^test(.+)$/', function() {});

    expect(TestBuilderWithDynamicFinder::getFinderPatterns())->toHaveCount(1);

    TestBuilderWithDynamicFinder::clearFinders();

    expect(TestBuilderWithDynamicFinder::getFinderPatterns())->toHaveCount(0);
});

test('getFinderPatterns returns registered patterns', function () {
    TestBuilderWithDynamicFinder::registerFinder('/^pattern1$/', function() {});
    TestBuilderWithDynamicFinder::registerFinder('/^pattern2$/', function() {});

    $patterns = TestBuilderWithDynamicFinder::getFinderPatterns();

    expect($patterns)->toHaveCount(2);
    expect($patterns)->toHaveKey('/^pattern1$/');
    expect($patterns)->toHaveKey('/^pattern2$/');
});

test('handleDynamicFinder returns null for unmatched patterns', function () {
    $result = $this->builder->handleDynamicFinder('unknownMethod', []);

    expect($result)->toBeNull();
});

test('custom pattern takes precedence over default', function () {
    $customCalled = false;

    TestBuilderWithDynamicFinder::registerFinder(
        '/^findBy(.+)$/',
        function ($matches, $params) use (&$customCalled) {
            $customCalled = true;
            return 'custom result';
        }
    );

    $this->builder->from('users');
    $result = $this->builder->findByEmail('test@example.com');

    expect($customCalled)->toBeTrue();
    expect($result)->toBe('custom result');
});

test('handler with empty parameters uses null as default', function () {
    $this->builder->from('users');
    $result = $this->builder->whereByStatus(); // No parameter provided

    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('status');
    // When no parameter is provided, the value key might not exist or be null
    $whereClause = $this->builder->wheres[0];
    expect($whereClause['value'] ?? null)->toBeNull();
});

test('complex camelCase conversions', function () {
    expect($this->builder->camelToSnake('APIKey'))->toBe('a_p_i_key');
    expect($this->builder->camelToSnake('getUserID'))->toBe('get_user_i_d');
    expect($this->builder->camelToSnake('IOError'))->toBe('i_o_error');
    expect($this->builder->camelToSnake('createdAt'))->toBe('created_at');
    expect($this->builder->camelToSnake('updated_at'))->toBe('updated_at'); // Already snake_case
});

test('non-closure handlers work correctly', function () {
    // Create a callable class
    $handler = new class {
        public function __invoke($matches, $params) {
            return 'handler result';
        }
    };

    TestBuilderWithDynamicFinder::registerFinder('/^custom(.+)$/', $handler);

    $this->builder->from('users');
    $result = $this->builder->customTest();

    expect($result)->toBe('handler result');
});

test('multiple parameters are passed to handler', function () {
    TestBuilderWithDynamicFinder::registerFinder(
        '/^between(.+)$/',
        function ($matches, $params) {
            $column = $this->camelToSnake($matches[1]);
            return $this->whereBetween($column, [$params[0] ?? 0, $params[1] ?? 100]);
        }
    );

    $this->builder->from('users');
    $result = $this->builder->betweenAge(18, 65);

    expect($this->builder->wheres)->toHaveCount(1);
    expect($this->builder->wheres[0]['column'])->toBe('age');
    expect($this->builder->wheres[0]['values'])->toBe([18, 65]);
});

test('undefined dynamic method throws exception', function () {
    $this->builder->from('users');

    expect(fn() => $this->builder->unknownDynamicMethod())
        ->toThrow(\BadMethodCallException::class, 'Method unknownDynamicMethod does not exist.');
});