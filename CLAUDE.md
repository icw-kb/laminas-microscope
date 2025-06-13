# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Testing
```bash
# Run all tests
composer test
vendor/bin/phpunit

# Run specific test suites
composer test-unit          # Unit tests only
composer test-integration   # Integration tests only
composer test-functional    # Functional tests only

# Test with coverage
composer test-coverage      # Generates HTML and Clover reports in tests/coverage/

# Single test file
vendor/bin/phpunit tests/Unit/Config/ConfigurationServiceTest.php
```

### Code Quality
```bash
# Run all quality checks
composer quality

# Individual quality tools
composer cs-check           # Check coding standards (PSR-12)
composer cs-fix             # Fix coding standards
composer analyze            # PHPStan static analysis (level 7)
composer psalm              # Psalm static analysis (level 3)
composer phpmd              # PHP Mess Detector
composer infection          # Mutation testing

# Security checks
composer security-check     # Check for security vulnerabilities
```

### Development Workflow
```bash
# Setup development environment
make setup-dev

# Quick development cycle
make dev-test               # Fast unit tests only
make dev-fix                # Fix coding standards

# Complete CI pipeline locally
make ci
composer ci

# Pre-commit checks (runs automatically via Captain Hook)
composer pre-commit
```

### Using Make Commands
```bash
make help                   # Show all available commands
make install                # Install dependencies
make test                   # Run PHPUnit tests
make quality                # Run all quality checks
make clean                  # Clean generated files
```

## Architecture Overview

Laminas Microscope is a comprehensive debugging and profiling suite for Laminas applications with three main components:

### Core Components

1. **Whoops Handler** (`src/Whoops/WhoopsHandler.php`)
   - Beautiful error pages with detailed stack traces
   - Editor integration for direct file linking
   - Production-safe error handling

2. **Debug Bar Handler** (`src/DebugBar/DebugBarHandler.php`)
   - Real-time request profiling in the browser
   - Collectors for time, memory, database queries, HTTP requests
   - Automatic injection into HTML responses

3. **Microscope Handler** (`src/Microscope/MicroscopeHandler.php`)
   - Advanced performance analysis and issue detection
   - N+1 query detection, slow query analysis
   - Automated recommendations for optimization

### Component Management

**ComponentManager** (`src/Manager/ComponentManager.php`) orchestrates all components:
- Handles component initialization and lifecycle
- Manages component configuration and enablement
- Provides unified interface for component operations

**ConfigurationService** (`src/Config/ConfigurationService.php`) manages configuration:
- Loads configuration from multiple sources (PHP, YAML)
- Environment-specific configuration support
- Configuration validation and defaults

### Event Lifecycle

The Module class (`src/Module.php`) integrates with Laminas MVC events:
- **Bootstrap**: Initialize components early
- **Route**: Start timing and routing profiling
- **Dispatch**: Profile controller execution
- **Render**: Profile view rendering
- **Finish**: Finalize profiling and inject debug bar

### Debug Bar Collectors

Located in `src/Collector/`:
- **PDOCollector**: Database query logging and analysis
- **LaminasConfigCollector**: Configuration data collection
- **LaminasRequestCollector**: HTTP request/response data
- **EnhancedPDOCollector**: Advanced database query analysis with performance metrics

### Controller Structure

Located in `src/Controller/`:
- **DashboardController**: Main debug dashboard at `/_debug`
- **MicroscopeController**: Microscope analysis interface
- **ConfigurationController**: Configuration management interface

## Configuration System

### Configuration Loading Priority
1. Main config: `config/laminas-microscope.local.php`
2. Environment config: `config/environments/{environment}.yaml`
3. Profile config: `config/profiles/{profile}.yaml`

### Key Configuration Paths
- `config/module.config.php` - Service manager and router configuration
- `config/components/` - Component-specific YAML configurations
- `config/environments/` - Environment-specific settings

### Debug Routes
- `/_debug` - Main dashboard
- `/_debug/microscope` - Analysis interface
- `/_debug/config` - Configuration management
- `/_debug/debugbar/resources` - Debug bar assets

## Testing Architecture

### Test Structure
- **Unit Tests** (`tests/Unit/`) - Component isolation testing
- **Integration Tests** (`tests/Integration/`) - Component interaction testing
- **Functional Tests** (`tests/Functional/`) - End-to-end workflow testing

### Test Utilities
The `tests/bootstrap.php` provides `TestHelper` class with utilities:
- `createMockConfig()` - Generate test configurations
- `createMockServiceManager()` - Mock container for testing
- `createComponentManager()` - Factory for test component managers
- Temporary directory and file management helpers

### Test Environment
- Uses `APPLICATION_ENV=testing`
- Memory limit set to 512M for coverage testing
- Excludes view files and Module.php from coverage

## Code Standards

### PSR-12 Compliance
- Enforced via PHP_CodeSniffer with PSR-12 standard
- Additional Laminas-specific rules in `phpcs.xml.dist`
- Automatic fixing available via `composer cs-fix`

### Static Analysis
- **PHPStan**: Level 7 analysis for type safety
- **Psalm**: Level 3 analysis with PHPUnit plugin
- **PHPMD**: Mess detection with custom rules in `phpmd.xml`

### Git Hooks (Captain Hook)
- **Pre-commit**: PHP linting and PSR-2 checking
- **Commit-msg**: Conventional commit message format (50 char subject, 72 char body)

## Development Patterns

### Dependency Injection
All components use constructor injection with the service manager configured in `config/module.config.php`. The container is passed to handlers for lazy loading of dependencies.

### Event-Driven Architecture
Components listen to Laminas MVC events with specific priorities to ensure proper timing and data collection without interfering with application flow.

### Configuration-Driven Features
All component behavior is configurable via YAML/PHP configuration files, allowing environment-specific customization without code changes.

### Collector Pattern
Debug information is gathered via collectors that implement the DebugBar collector interface, making the system extensible for custom data collection.

## Common Development Tasks

### Adding a New Collector
1. Create collector class in `src/Collector/`
2. Implement `DataCollector` and `Renderable` interfaces
3. Register in service manager in `config/module.config.php`
4. Add to default collector list in component configuration

### Adding Component Configuration
1. Add configuration schema to appropriate file in `config/components/`
2. Update `ConfigurationService` to handle new options
3. Add validation in configuration classes
4. Document in `CONFIG.md`

### Running Debug Suite Tests
When testing the debug suite itself, use the testing environment configuration to avoid recursive debugging scenarios.

## Performance Considerations

### Development Mode Only
The debug suite is designed for development/staging environments. In production:
- Disable debug bar injection
- Limit microscope analysis frequency  
- Use minimal component profile
- Implement IP restrictions

### Memory Management
- Debug bar collectors can consume significant memory
- Microscope analysis stores data in temporary files
- Automatic cleanup configured via retention settings