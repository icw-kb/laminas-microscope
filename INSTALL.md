# Installation Guide

This guide walks you through installing and configuring Laminas Microscope in your Laminas application.

## 📋 Requirements

- **PHP**: 8.1 or higher
- **Laminas**: 3.0 or higher
- **Composer**: Latest version recommended

## 🚀 Step-by-Step Installation

### 1. Install via Composer

```bash
composer require icw-kb/laminas-microscope --dev
```

> **Note**: We recommend installing as a dev dependency (`--dev`) since this is primarily a debugging tool.

### 2. Component Installer Prompt

After running the composer command, you'll see a prompt from the **Laminas Component Installer**:

```
Please select which config file you wish to inject 'LaminasMicroscope' into:
  [0] Do not inject
  [1] config/modules.config.php
  [2] config/development.config.php.dist
```

#### Option Explanations:

**[0] Do not inject**
- Skips automatic registration
- Choose if you want manual control over module loading
- You'll need to manually add the module to your configuration

**[1] config/modules.config.php** ⭐ **RECOMMENDED**
- Adds `LaminasMicroscope` to your main application modules
- Module will be available in all environments
- You can control activation via configuration settings
- Best for most use cases

**[2] config/development.config.php.dist**
- Adds module only to development configuration template
- Module only loads when development mode is enabled
- Choose if you only want debugging tools in development

#### Recommendation:
**Choose option [1]** for most installations. This gives you the most flexibility while keeping the module safely configurable through environment-specific settings.

### 3. Manual Module Registration (If you chose option [0])

If you selected "Do not inject", manually add the module:

```php
// config/modules.config.php
return [
    'Laminas\Router',
    'Laminas\Validator',
    'Laminas\Db',
    // ... your other modules
    'LaminasMicroscope', // Add this line manually
];
```

### 4. Copy Configuration Files

#### Main Configuration
```bash
cp vendor/icw-kb/laminas-microscope/config/laminas-microscope.yaml config/autoload/
```

#### Environment-Specific Configuration (Optional)
```bash
mkdir -p config/autoload/debug-suite
cp vendor/icw-kb/laminas-microscope/config/environments/* config/autoload/debug-suite/
cp vendor/icw-kb/laminas-microscope/config/profiles/* config/autoload/debug-suite/
```

### 5. Configure for Your Environment

Edit `config/autoload/laminas-microscope.yaml`:

#### Development Environment
```yaml
laminas_microscope:
  enabled: true
  environment: 'development'
  components:
    whoops:
      enabled: true
      editor: 'vscode'
      show_in_production: false
    debug_bar:
      enabled: true
      position: 'bottom'
      collectors:
        - 'time'
        - 'memory'
        - 'exceptions'
        - 'pdo'
        - 'request'
        - 'config'
        - 'messages'
    microscope:
      enabled: true
      auto_analyze: true
      thresholds:
        query_time: 100
        memory_usage: 50
      checks:
        n_plus_one: true
        slow_queries: true
        duplicate_queries: true
```

#### Production Environment
```yaml
laminas_microscope:
  enabled: true
  environment: 'production'
  ip_whitelist:
    - '127.0.0.1'
    - '192.168.1.0/24'
  components:
    whoops:
      enabled: true
      show_in_production: false
    debug_bar:
      enabled: false
    microscope:
      enabled: false
```

## 🎯 Quick Configuration Profiles

### Profile 1: Minimal (Production-Safe)
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

### Profile 2: Performance Monitoring
```yaml
laminas_microscope:
  enabled: true
  components:
    whoops:
      enabled: false
    debug_bar:
      enabled: true
      collectors: ['time', 'memory', 'pdo']
    microscope:
      enabled: true
      auto_analyze: true
```

### Profile 3: Full Development Suite
```yaml
laminas_microscope:
  enabled: true
  components:
    whoops:
      enabled: true
    debug_bar:
      enabled: true
      collectors: ['time', 'memory', 'exceptions', 'pdo', 'request', 'config', 'messages']
    microscope:
      enabled: true
      auto_analyze: true
```

## 🔧 Framework-Specific Setup

### Laminas MVC Application

1. **Standard Installation** - Follow steps 1-5 above
2. **Verify Module Loading** - Check that the module appears in `/_debug`

### Mezzio Application

Additional setup required for Mezzio:

```php
// config/config.php
$aggregator = new ConfigAggregator([
    // ... your config providers
    \LaminasMicroscope\ConfigProvider::class,
]);
```

```php
// config/pipeline.php
$app->pipe(\LaminasMicroscope\Middleware\DebugBarMiddleware::class);
```

### Laminas API Tools

```php
// config/modules.config.php
return [
    'Laminas\ApiTools',
    // ... other modules
    'LaminasMicroscope',
];
```

## 🗃️ Database Configuration

### Laminas DB Adapter

Ensure your database adapter is properly configured:

```php
// config/autoload/database.local.php
return [
    'db' => [
        'driver'   => 'Pdo_Mysql',
        'hostname' => 'localhost',
        'database' => 'your_database',
        'username' => 'your_username',
        'password' => 'your_password',
        'options'  => [
            'buffer_results' => true,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Laminas\Db\Adapter\Adapter' => 'Laminas\Db\Adapter\AdapterServiceFactory',
        ],
    ],
];
```

### Enable Database Profiling

```php
// In your Module.php or service configuration
use Laminas\Db\Adapter\Profiler\Profiler;

public function onBootstrap($e)
{
    $adapter = $e->getApplication()->getServiceManager()->get('Laminas\Db\Adapter\Adapter');
    $adapter->setProfiler(new Profiler());
}
```

## 📁 Storage Configuration

### Set Storage Permissions

```bash
# Create storage directory
sudo mkdir -p /var/laminas-microscope
sudo chown www-data:www-data /var/laminas-microscope
sudo chmod 755 /var/laminas-microscope
```

### Configure Storage Path

```yaml
laminas_microscope:
  storage:
    path: '/var/laminas-microscope'
    retention_days: 30
```

## 🔒 Security Configuration

### IP Whitelist (Recommended for Production)

```yaml
laminas_microscope:
  ip_whitelist:
    - '127.0.0.1'          # Localhost
    - '192.168.1.0/24'     # Local network
    - '10.0.0.0/8'         # Private network
    - '::1'                # IPv6 localhost
```

### Environment-Based Security

```yaml
laminas_microscope:
  enabled: '%env(bool:DEBUG_MODE)%'
  components:
    whoops:
      show_in_production: '%env(bool:SHOW_ERRORS_IN_PROD)%'
```

## ✅ Verification

### 1. Check Installation

Visit your application and go to `/_debug`. You should see the Laminas Microscope dashboard.

### 2. Test Debug Bar

The debug bar should appear at the bottom of your HTML pages when enabled.

### 3. Test Whoops

Trigger an error to see the Whoops error page:

```php
// Temporary test code
throw new \Exception('Testing Whoops integration');
```

### 4. Test Microscope

Visit `/_debug/microscope` to access the analysis interface.

## 🚨 Troubleshooting

### Common Issues

**Component Installer Issues:**
```bash
# If the installer prompt doesn't appear or fails
composer install --no-plugins
composer require icw-kb/laminas-microscope --dev
# Then manually add to modules.config.php
```

**Debug bar not appearing:**
```bash
# Check configuration
php -r "print_r(include 'config/autoload/laminas-microscope.yaml');"

# Verify HTML output has closing </body> tag
curl -s http://your-app.com | grep -i "</body>"
```

**Module not found errors:**
```php
// Verify module is in modules.config.php
$config = include 'config/modules.config.php';
var_dump(in_array('LaminasMicroscope', $config));
```

**Permission errors:**
```bash
# Fix storage permissions
sudo chown -R www-data:www-data /var/laminas-microscope
sudo chmod -R 755 /var/laminas-microscope
```

**Module not loading:**
```php
// Check module manager
$moduleManager = $application->getServiceManager()->get('ModuleManager');
var_dump($moduleManager->getLoadedModules());
```

**Memory issues:**
```ini
; php.ini adjustments
memory_limit = 256M
max_execution_time = 60
```

### Debug Mode

Enable verbose logging:

```yaml
laminas_microscope:
  debug: true
  log_level: 'debug'
  log_file: '/tmp/laminas-microscope.log'
```

### Reinstallation

If you need to reinstall or reconfigure:

```bash
# Remove and reinstall
composer remove icw-kb/laminas-microscope
composer require icw-kb/laminas-microscope --dev

# Clear any cached configurations
rm -rf data/cache/*
rm -rf data/config-cache.php
```

## 🔄 Updating

```bash
# Update to latest version
composer update icw-kb/laminas-microscope

# Check for configuration changes
diff vendor/icw-kb/laminas-microscope/config/laminas-microscope.yaml config/autoload/laminas-microscope.yaml
```

## 🎯 Next Steps

1. **Configure for your environment** - Adjust settings based on your needs
2. **Set up IP whitelist** - Secure access in production
3. **Customize collectors** - Enable only the debug bar collectors you need
4. **Review storage settings** - Configure retention and cleanup
5. **Explore the interface** - Visit `/_debug` to familiarize yourself with features

## 📚 Additional Resources

- [Configuration Guide](CONFIG.md)
- [Usage Examples](USAGE.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Performance Optimization](PERFORMANCE.md)

---

**Need help?** Open an issue on [GitHub](https://github.com/icw-kb/laminas-microscope/issues) or check our [documentation](https://github.com/icw-kb/laminas-microscope/wiki).
