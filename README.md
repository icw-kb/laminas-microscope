# Laminas Microscope 🔬

A comprehensive debugging and profiling suite for Laminas applications that provides deep insights into your application's performance, database queries, and potential issues.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![Laminas Version](https://img.shields.io/badge/laminas-%3E%3D3.0-green)](https://getlaminas.org)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## ✨ Features

### 🎯 **Core Components**

- **🔬 Microscope Analysis** - Advanced code analysis with N+1 query detection and performance profiling
- **📊 Debug Bar** - Real-time request profiling with database query logging and memory tracking  
- **💥 Whoops Integration** - Beautiful error pages with detailed stack traces and debugging info
- **⚙️ Configuration Management** - Easy configuration with environment profiles and presets

### 📈 **Analysis Capabilities**

- **Database Query Analysis** - Slow query detection, duplicate query identification, N+1 problem detection
- **Performance Profiling** - Request timeline, memory usage tracking, bottleneck identification
- **Route Analysis** - Route performance metrics, hit counting, response time tracking
- **Issue Detection** - Automated detection of common performance and coding issues

### 🛠️ **Debug Bar Collectors**

- **⏱️ Time Collector** - Request duration, bootstrap time, component timing
- **💾 Memory Collector** - Memory usage tracking with peak usage alerts
- **🗄️ PDO Collector** - SQL query logging with parameter binding and performance metrics
- **🌐 Request Collector** - HTTP request/response data, headers, parameters
- **⚙️ Config Collector** - Application configuration, module info, service manager data
- **📨 Messages Collector** - Custom logging and timeline events

## 🚀 Quick Start

### Installation

```bash
composer require icw-kb/laminas-microscope --dev
```

### Basic Setup

1. **Copy main configuration:**
```bash
cp vendor/icw-kb/laminas-microscope/config/laminas-microscope.local.php config/autoload/
```

2. **Add module to your application:**
```php
// config/modules.config.php
return [
    'Laminas\Router',
    'Laminas\Validator',
    // ... your modules
    'LaminasMicroscope', // Add this line
];
```

3. **Configure for your environment:**
```php
# config/autoload/laminas-microscope.local.php
return [
    'laminas_microscope' => [
        'enabled' => true,
        'environment' => 'development',
        'components' => [
            'whoops' => ['enabled' => true],
            'debug_bar' => ['enabled' => true],
            'microscope' => ['enabled' => true],
        ],
    ],
];
```

### Access the Debug Suite

Visit `http://your-app.com/_debug` to access the debug dashboard.

## 📖 Detailed Documentation

### 🔧 Configuration

#### Environment-Specific Configuration

Create environment-specific configuration files:

```bash
# Copy environment templates
mkdir -p config/autoload/debug-suite
cp vendor/icw-kb/laminas-microscope/config/environments/* config/autoload/debug-suite/
cp vendor/icw-kb/laminas-microscope/config/profiles/* config/autoload/debug-suite/
```

#### Configuration Profiles

**Minimal Profile** (Production-safe):
```yaml
laminas_microscope:
  enabled: true
  components:
    whoops:
      enabled: true
      show_in_production: false
    debug_bar:
      enabled: false
    microscope:
      enabled: false
```

**Performance Profile** (Staging):
```yaml
laminas_microscope:
  enabled: true
  collectors: ['time', 'memory', 'pdo']
  components:
    whoops:
      enabled: false
    debug_bar:
      enabled: true
      collectors_only: true
    microscope:
      enabled: true
      auto_analyze: true
```

**Full Debugging Profile** (Development):
```yaml
laminas_microscope:
  enabled: true
  collectors: ['time', 'memory', 'exceptions', 'pdo', 'request', 'config', 'messages']
  components:
    whoops:
      enabled: true
    debug_bar:
      enabled: true
      collectors_only: true
    microscope:
      enabled: true
      auto_analyze: true
      checks:
        n_plus_one: true
        slow_queries: true
        duplicate_queries: true
```

### 📊 Debug Bar Usage

The Debug Bar automatically appears at the bottom of HTML pages and shows:

- **Request timing** and performance breakdown
- **Memory usage** with peak tracking
- **Database queries** with execution time and parameters
- **HTTP request/response** details
- **Configuration** data and environment info
- **Custom messages** and timeline events

#### Programmatic Usage

```php
// Add custom timing
$debugBar = $container->get('LaminasMicroscope\DebugBar\DebugBarHandler');
$debugBar->startMeasure('my_operation', 'My Custom Operation');
// ... your code ...
$debugBar->stopMeasure('my_operation');

// Add custom messages
$debugBar->addMessage('Processing started', 'info');
$debugBar->addMessage('Warning: High memory usage', 'warning');

// Log exceptions
try {
    // risky code
} catch (\Exception $e) {
    $debugBar->addException($e);
}
```

### 🔬 Microscope Analysis

#### Automated Analysis

Enable auto-analysis to automatically detect issues:

```yaml
laminas_microscope:
  components:
    microscope:
      enabled: true
      auto_analyze: true
      thresholds:
        query_time: 100  # ms
        memory_usage: 50 # MB
      checks:
        n_plus_one: true
        slow_queries: true
        duplicate_queries: true
        memory_leaks: true
```

#### Manual Analysis

Access the Microscope interface at `/_debug/microscope` to:

- **Run analysis** on demand
- **View query reports** with performance metrics
- **Analyze routes** and their performance
- **Review detected issues** with suggestions
- **Export reports** for team sharing

#### Issue Detection

The Microscope automatically detects:

- **N+1 Query Problems** - Queries executed in loops
- **Slow Queries** - Queries exceeding threshold
- **Duplicate Queries** - Identical queries run multiple times
- **Memory Leaks** - Excessive memory usage patterns
- **Route Bottlenecks** - Slow-performing routes

### 💥 Whoops Integration

Beautiful error pages with:

- **Detailed stack traces** with syntax highlighting
- **Request/response data** for debugging context
- **Environment information** and configuration
- **Code editor integration** (VS Code, PhpStorm, Sublime)

```yaml
laminas_microscope:
  components:
    whoops:
      enabled: true
      editor: 'vscode'  # 'phpstorm', 'sublime'
      show_in_production: false
```

## 🎛️ Advanced Configuration

### Security Settings

```yaml
laminas_microscope:
  enabled: true
  ip_whitelist:
    - '127.0.0.1'
    - '192.168.1.0/24'
    - '::1'
  storage:
    path: '/tmp/laminas-microscope'
    retention_days: 30
```

### Custom Collectors

Create custom debug bar collectors:

```php
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class MyCustomCollector extends DataCollector implements Renderable
{
    public function collect(): array
    {
        return [
            'my_data' => 'custom information',
            'count' => 42
        ];
    }

    public function getName(): string
    {
        return 'my_custom';
    }

    public function getWidgets(): array
    {
        return [
            'my_custom' => [
                'icon' => 'gear',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'my_custom',
                'default' => '{}'
            ]
        ];
    }
}
```

Register in your service manager:

```php
// In your module's service configuration
'service_manager' => [
    'factories' => [
        MyCustomCollector::class => function($container) {
            return new MyCustomCollector();
        },
    ],
],
```

### Event Integration

Listen for debug events:

```php
use Laminas\EventManager\EventManagerInterface;
use LaminasMicroscope\Event\AnalysisEvent;

class MyListener
{
    public function attach(EventManagerInterface $events): void
    {
        $events->attach('laminas-microscope.analysis.complete', [$this, 'onAnalysisComplete']);
    }

    public function onAnalysisComplete(AnalysisEvent $e): void
    {
        $report = $e->getReport();
        // Handle analysis completion
    }
}
```

## 🔧 Troubleshooting

### Common Issues

**1. Debug Bar not appearing:**
- Check that `laminas_microscope.components.debug_bar.enabled` is `true`
- Ensure your response is HTML content-type
- Verify the closing `</body>` tag exists in your HTML

**2. Storage permission errors:**
- Check write permissions on storage path
- Default: `/tmp/laminas-microscope`
- Configure custom path in configuration

**3. Whoops not showing:**
- Verify `whoops.enabled` is `true`  
- Check environment settings
- Ensure error reporting is enabled

**4. Performance Impact:**
- Use minimal profile in production
- Disable auto-analysis in high-traffic environments
- Configure IP whitelist for security

### Debug Mode

Enable verbose logging:

```yaml
laminas_microscope:
  debug: true
  log_level: 'debug'
  log_file: '/tmp/laminas-microscope-debug.log'
```

## 📊 Performance Impact

| Component | CPU Impact | Memory Impact | Recommended Use |
|-----------|------------|---------------|-----------------|
| Whoops | Minimal | Low | All environments |
| Debug Bar | Low | Medium | Development/Staging |
| Microscope | Medium | Medium-High | Development |
| Full Suite | Medium | High | Development only |

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Setup

```bash
git clone https://github.com/icw-kb/laminas-microscope.git
cd laminas-microscope
composer install
composer test
```

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [GitHub Wiki](https://github.com/icw-kb/laminas-microscope/wiki)
- **Issues**: [GitHub Issues](https://github.com/icw-kb/laminas-microscope/issues)
- **Discussions**: [GitHub Discussions](https://github.com/icw-kb/laminas-microscope/discussions)

## 🙏 Acknowledgments

- Built on top of [maximebf/php-debug-bar](https://github.com/maximebf/php-debug-bar)
- Inspired by [barryvdh/laravel-debugbar](https://github.com/barryvdh/laravel-debugbar)
- Uses [filp/whoops](https://github.com/filp/whoops) for error handling

---

**Made with ❤️ for the Laminas community**
