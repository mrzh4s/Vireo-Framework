# Contributing to Vireo Framework

Thank you for your interest in contributing to Vireo Framework! This document provides guidelines and instructions for contributing.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for everyone.

## How to Contribute

### Reporting Bugs

Before submitting a bug report:

1. Check the [GitHub Issues](https://github.com/mrzh4s/vireo-framework/issues) to see if the bug has already been reported
2. If not, create a new issue with the following information:
   - A clear, descriptive title
   - Steps to reproduce the issue
   - Expected behavior
   - Actual behavior
   - PHP version and environment details
   - Any relevant code snippets or error messages

### Suggesting Features

Feature requests are welcome! Please create an issue with:

- A clear description of the feature
- The problem it solves or use case it addresses
- Any implementation ideas you have

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Follow the coding standards** (see below)
3. **Write tests** for new functionality
4. **Update documentation** if needed
5. **Ensure all tests pass** before submitting
6. **Submit a pull request** with a clear description of your changes

## Development Setup

### Prerequisites

- PHP 8.4 or higher
- Composer

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/vireo-framework.git
cd vireo-framework

# Install dependencies
composer install

# Run tests
composer test
```

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage
```

## Coding Standards

### PHP Style Guide

- Follow PSR-12 coding standards
- Use type declarations for parameters and return types
- Use meaningful variable and method names
- Keep methods focused and concise

### Example

```php
<?php

namespace Vireo\Framework\Example;

class ExampleClass
{
    private string $property;

    public function __construct(string $property)
    {
        $this->property = $property;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function doSomething(array $items): array
    {
        return array_map(fn($item) => $this->process($item), $items);
    }

    private function process(mixed $item): mixed
    {
        // Process logic here
        return $item;
    }
}
```

### Documentation

- Add PHPDoc blocks to all public methods
- Include `@param` and `@return` annotations
- Provide examples in documentation when helpful

```php
/**
 * Process the given items and return the result.
 *
 * @param array $items The items to process
 * @param bool $strict Whether to use strict mode
 * @return array The processed items
 *
 * @throws InvalidArgumentException If items array is empty
 */
public function process(array $items, bool $strict = false): array
{
    // ...
}
```

### Commit Messages

- Use clear, descriptive commit messages
- Start with a verb in present tense (Add, Fix, Update, Remove, Refactor)
- Keep the first line under 72 characters
- Reference issue numbers when applicable

**Good examples:**
```
Add spatial query builder for PostGIS support
Fix validation rule not handling null values
Update README with migration examples
Refactor permission system for better performance
```

**Bad examples:**
```
fixed stuff
updates
WIP
asdfasdf
```

## Project Structure

```
src/
├── Cache/          # Caching system
├── Cli/            # CLI commands and console
├── Database/       # Database, ORM, migrations
├── Email/          # Email functionality
├── Helpers/        # Global helper functions
├── Http/           # HTTP layer (Request, Response, Router)
├── Logging/        # Logging system
├── Security/       # Security features (CSRF, Permissions)
├── Storage/        # File storage
├── Validation/     # Validation rules and validators
└── View/           # View rendering (Blade, Inertia)
tests/
└── ...             # Test files mirroring src/ structure
```

## Adding New Features

When adding a new feature:

1. **Create the feature class** in the appropriate `src/` directory
2. **Add helper functions** in `src/Helpers/` if needed
3. **Write tests** in the `tests/` directory
4. **Update composer.json** if adding new helper files to autoload
5. **Document the feature** in the README

### Helper Functions

If your feature needs global helper functions:

1. Create a new file in `src/Helpers/` (e.g., `src/Helpers/myfeature.php`)
2. Add it to the `autoload.files` array in `composer.json`
3. Wrap functions in `if (!function_exists())` checks

```php
<?php

if (!function_exists('my_helper')) {
    /**
     * Description of what this helper does.
     *
     * @param string $param Description of parameter
     * @return mixed Description of return value
     */
    function my_helper(string $param): mixed
    {
        // Implementation
    }
}
```

## Testing Guidelines

- Write unit tests for all new functionality
- Test edge cases and error conditions
- Use descriptive test method names
- Follow the Arrange-Act-Assert pattern

```php
<?php

namespace Vireo\Framework\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Vireo\Framework\Validation\Rules\Email;

class EmailRuleTest extends TestCase
{
    public function test_valid_email_passes_validation(): void
    {
        // Arrange
        $rule = new Email();

        // Act
        $result = $rule->validate('test@example.com');

        // Assert
        $this->assertTrue($result);
    }

    public function test_invalid_email_fails_validation(): void
    {
        // Arrange
        $rule = new Email();

        // Act
        $result = $rule->validate('not-an-email');

        // Assert
        $this->assertFalse($result);
    }
}
```

## Questions?

If you have questions about contributing, feel free to:

- Open an issue for discussion
- Reach out to the maintainers

Thank you for contributing to Vireo Framework!