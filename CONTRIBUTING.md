# Contributing to Laravel Vurb

Thank you for your interest in contributing to Laravel Vurb! This document provides guidelines and information about contributing.

## Code of Conduct

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

## How to Contribute

### Reporting Bugs

Before submitting a bug report:

1. Check the [existing issues](https://github.com/vinkius-labs/laravel-vurb/issues) to avoid duplicates
2. Collect information about the bug:
    - Stack trace
    - PHP version (`php --version`)
    - Laravel version
    - Package version
    - Steps to reproduce

Then [open a new issue](https://github.com/vinkius-labs/laravel-vurb/issues/new?template=bug_report.md) with the bug report template.

### Suggesting Features

Feature requests are welcome! Please:

1. Check existing issues and discussions first
2. Describe the use case clearly
3. Explain why existing features don't solve your problem
4. [Open a feature request](https://github.com/vinkius-labs/laravel-vurb/issues/new?template=feature_request.md)

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Install dependencies**: `composer install`
3. **Make your changes**
4. **Add tests** for any new functionality
5. **Run tests**: `composer test`
6. **Ensure high test coverage** for new code
7. **Submit a pull request**

#### Pull Request Guidelines

- Follow the existing code style (PSR-12)
- Write clear commit messages
- Update documentation if needed
- Add tests for new features
- Keep PRs focused — one feature or fix per PR

### Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/laravel-vurb.git
cd laravel-vurb

# Install dependencies
composer install

# Run tests
php vendor/bin/phpunit

# Run tests with coverage
php vendor/bin/phpunit --coverage-html coverage-report
```

#### Using Docker (recommended)

```bash
# Build the test container
docker build -t laravel-vurb-tests .

# Run the full test suite
docker run --rm -v "$(pwd):/app" -w /app laravel-vurb-tests \
  bash -c "composer install --no-interaction --quiet && php vendor/bin/phpunit"
```

### Code Style

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard
- Use PHP 8.2+ features (typed properties, enums, attributes, match expressions)
- Follow existing patterns in the codebase
- Keep functions small and focused
- Use meaningful variable and method names

### Testing

- Write tests for all new functionality
- Maintain or improve code coverage (currently 97.59%)
- Include edge cases and error scenarios
- Use `VurbTestCase` as your base test class
- Test security boundaries (see `tests/Hostile/` for examples)

### Documentation

- Update `README.md` for user-facing changes
- Update `llms.txt` for any new public API
- Add PHPDoc comments to public methods
- Include code examples where helpful

## Project Structure

```
laravel-vurb/
├── config/
│   └── vurb.php              # Package configuration
├── database/
│   └── migrations/            # Package migrations
├── resources/
│   ├── skills/                # SKILL.md + reference examples
│   └── stubs/                 # Artisan make stubs
├── routes/
│   └── vurb.php               # Bridge routes
├── src/
│   ├── Attributes/            # PHP attributes for tool metadata
│   ├── Console/Commands/      # Artisan commands (install, make:*)
│   ├── Events/                # Domain events
│   ├── Exceptions/            # Custom exceptions
│   ├── Facades/               # Vurb facade
│   ├── Fsm/                   # Finite State Machine gate
│   ├── Governance/            # Compliance and policy enforcement
│   ├── Http/Controllers/      # SSE and HTTP transport controllers
│   ├── Middleware/             # Middleware pipeline
│   ├── Models/                # Eloquent models
│   ├── Observability/         # Debug observer, tracing, integrations
│   ├── Presenters/            # MVA presenter layer
│   ├── Security/              # Firewall, rate limiter, audit trail
│   ├── Services/              # Core MCP service layer
│   ├── Skills/                # Skill definitions
│   ├── StateSync/             # Epistemic cache
│   ├── Testing/               # Test helpers and fakes
│   ├── Tools/                 # Tool builder and registry
│   ├── VurbManager.php        # Main manager class
│   └── VurbServiceProvider.php # Service provider
└── tests/
    ├── Feature/               # Feature tests
    ├── Hostile/               # Security boundary tests
    └── Unit/                  # Unit tests
```

## Questions?

Feel free to [open a discussion](https://github.com/vinkius-labs/laravel-vurb/discussions) for questions or ideas.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
