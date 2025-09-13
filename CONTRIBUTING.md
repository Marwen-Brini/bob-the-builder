# Contributing to Bob Query Builder

First off, thank you for considering contributing to Bob Query Builder! It's people like you that make this project such a great tool.

## Code of Conduct

By participating in this project, you are expected to uphold our values of respect and professionalism.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues as you might find out that you don't need to create one. When you are creating a bug report, please include as many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples to demonstrate the steps**
- **Describe the behavior you observed and what you expected**
- **Include code samples and error messages**
- **Note your PHP version and database type**

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, please include:

- **Use a clear and descriptive title**
- **Provide a detailed description of the suggested enhancement**
- **Provide specific examples to demonstrate the enhancement**
- **Describe the current behavior and explain the expected behavior**
- **Explain why this enhancement would be useful**

### Pull Requests

Please follow these steps for sending us a pull request:

1. Fork the repo and create your branch from `main`
2. If you've added code that should be tested, add tests
3. If you've changed APIs, update the documentation
4. Ensure the test suite passes
5. Make sure your code follows PSR-12
6. Issue that pull request!

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git
- PDO with MySQL, PostgreSQL, and SQLite drivers

### Setting Up Your Development Environment

1. Fork and clone the repository:
```bash
git clone https://github.com/your-username/bob-query-builder.git
cd bob-query-builder
```

2. Install dependencies:
```bash
composer install
```

3. Set up test databases:
```bash
# SQLite (automatically created in memory during tests)
# No setup needed

# MySQL (optional for integration tests)
mysql -u root -p -e "CREATE DATABASE bob_test;"

# PostgreSQL (optional for integration tests)
createdb bob_test
```

4. Run tests to verify setup:
```bash
composer test
```

## Development Workflow

### Test-Driven Development (TDD)

**This project strictly follows TDD principles. Tests MUST be written before implementation.**

1. **Write failing tests first**
```bash
# Create your test file
touch tests/Feature/YourFeatureTest.php

# Write tests that fail
vendor/bin/pest tests/Feature/YourFeatureTest.php
```

2. **Implement the feature**
```php
// Write minimal code to make tests pass
```

3. **Refactor**
```php
// Improve code while keeping tests green
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/pest tests/Feature/YourFeatureTest.php

# Run with coverage
composer test:coverage

# Run with verbose output
vendor/bin/pest -vvv

# Run only unit tests
vendor/bin/pest tests/Unit

# Run only integration tests
vendor/bin/pest tests/Integration
```

### Code Style

We follow PSR-12 coding standards. Before committing:

```bash
# Check code style
vendor/bin/phpcs src tests

# Fix code style issues
vendor/bin/phpcbf src tests

# Run static analysis
vendor/bin/phpstan analyse
```

### Writing Tests

#### Test Structure

```php
<?php

declare(strict_types=1);

use Bob\Query\Builder;
use Bob\Database\Connection;

beforeEach(function () {
    // Setup code
    $this->connection = new Connection([...]);
});

afterEach(function () {
    // Cleanup code
});

it('describes what it does', function () {
    // Arrange
    $builder = $this->connection->table('users');
    
    // Act
    $result = $builder->where('active', true)->get();
    
    // Assert
    expect($result)->toBeArray();
    expect($result)->toHaveCount(3);
});
```

#### Test Categories

1. **Contract Tests** (`tests/Contract/`)
   - Test interface implementations
   - Verify method signatures
   - Check return types

2. **Unit Tests** (`tests/Unit/`)
   - Test individual methods
   - Mock dependencies
   - Focus on single responsibility

3. **Integration Tests** (`tests/Integration/`)
   - Test database interactions
   - Use real database connections
   - Test complete workflows

4. **Feature Tests** (`tests/Feature/`)
   - Test user-facing features
   - End-to-end scenarios
   - API usage examples

### Commit Messages

Follow conventional commit format:

```
type(scope): subject

body

footer
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance tasks

Examples:
```
feat(builder): add support for union queries
fix(grammar): correct MySQL limit compilation
docs(readme): add transaction examples
test(integration): add PostgreSQL join tests
```

## Testing Guidelines

### Coverage Requirements

- **100% code coverage is mandatory**
- No PR will be merged with less than 100% coverage
- Use `@codeCoverageIgnore` sparingly and with justification

### Test Naming

- Use descriptive test names
- Start with "it" or "test"
- Describe the behavior, not implementation

Good:
```php
it('returns null when no records match the criteria')
it('throws exception when table name is empty')
```

Bad:
```php
it('works')
test('query')
```

### Database Testing

When testing database operations:

1. Use transactions for cleanup:
```php
beforeEach(function () {
    $this->connection->beginTransaction();
});

afterEach(function () {
    $this->connection->rollBack();
});
```

2. Use factories or fixtures for test data:
```php
function createUser(array $attributes = []): array
{
    return array_merge([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'active' => true,
    ], $attributes);
}
```

3. Test with multiple databases:
```php
dataset('databases', [
    'sqlite' => [['driver' => 'sqlite', 'database' => ':memory:']],
    'mysql' => [['driver' => 'mysql', ...]],
    'pgsql' => [['driver' => 'pgsql', ...]],
]);

it('works with all databases', function ($config) {
    $connection = new Connection($config);
    // Test code
})->with('databases');
```

## Documentation

### Code Documentation

All public methods must have PHPDoc blocks:

```php
/**
 * Execute the query and get all results.
 *
 * @param array|string $columns The columns to select
 * @return array The query results
 * @throws QueryException If the query fails
 */
public function get(array|string $columns = ['*']): array
{
    // Implementation
}
```

### README Updates

When adding features, update the README:
- Add usage examples
- Update feature list if needed
- Add to roadmap if applicable

### API Documentation

For new public APIs, update `API.md` with:
- Method signature
- Parameters description
- Return type
- Usage example
- Exceptions thrown

## Performance Considerations

### Query Optimization

- Use prepared statements
- Implement query caching where appropriate
- Minimize database round trips
- Use batch operations for bulk data

### Memory Management

- Use generators for large result sets
- Implement chunking for batch operations
- Clean up resources properly
- Avoid memory leaks in long-running processes

## Security

### SQL Injection Prevention

- **Always use parameter binding**
- Never concatenate user input into SQL
- Validate and sanitize input
- Use whitelists for dynamic column/table names

### Error Handling

- Don't expose sensitive information in errors
- Log security-related errors
- Validate all input parameters
- Use type declarations

## Release Process

1. Update version in `composer.json`
2. Update `CHANGELOG.md`
3. Run full test suite
4. Create release PR
5. After merge, tag release
6. Publish to Packagist

## Questions?

Feel free to open an issue for:
- Questions about contributing
- Help with development setup
- Clarification on guidelines

## Recognition

Contributors will be recognized in:
- The project README
- Release notes
- Annual contributor report

Thank you for contributing to Bob Query Builder!