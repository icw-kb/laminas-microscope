# Configuration Guide

This guide covers all configuration options available in Laminas Microscope.

## 📁 Configuration Structure

```
config/autoload/
├── laminas-microscope.yaml           # Main configuration
└── debug-suite/                      # Environment-specific configs
    ├── development.yaml              # Development environment
    ├── staging.yaml                  # Staging environment
    ├── production.yaml               # Production environment
    ├── minimal.yaml                  # Minimal profile
    ├── performance.yaml              # Performance profile
    └── debugging.yaml                # Full debugging profile
```

## ⚙️ Main Configuration

### Basic Settings

```yaml
laminas_microscope:
  # Enable/disable the entire debug suite
  enabled: true
  
  # Current environment (development, staging, production)
  environment: 'development'
  
  # Debug mode (enables verbose logging)
  debug: false
  
  # Log level (emergency, alert, critical, error, warning, notice, info, debug)
  log_level: 'info'
  
  # Custom log file path
  log_file: '/tmp/laminas-microscope.log'
```

### Security Settings

```yaml
laminas_microscope:
  # IP whitelist - restrict access to specific IPs
  ip_whitelist:
    - '127.0.0.1'          # Localhost
    - '192.168.1.0/24'     # Local network  
    - '10.0.0.0/8'         # Private network
    - '::1'                # IPv6 localhost
  
  # Authentication (optional)
  auth:
    enabled: false
    username: 'admin'
    password: 'secure_password'
    
  # HTTPS requirement
  require_https: false
```

### Storage Configuration

```yaml
laminas_microscope:
  storage:
    # Directory for storing analysis reports
    path: '/tmp/laminas-microscope'
    
    # Auto-cleanup older reports (days)
    retention_days: 30
    
    # Maximum storage size (MB)
    max_size: 1000
    
    # Compression for stored reports
    compress: true
```

## 🧩 Component Configuration

### Whoops Error Handler

```yaml
laminas_microscope:
  components:
    whoops:
      # Enable Whoops error pages
      enabled: true
      
      # Show errors in production (not recommended)
      show_in_production: false
      
      # Editor integration for stack traces
      editor: 'vscode'  # Options: vscode, phpstorm, sublime, atom
      
      # Custom editor URL pattern
      editor_url: 'vscode://file/%file:%line'
      
      # Additional handlers
      handlers:
        - 'json'    # JSON error responses for AJAX
        - 'xml'     # XML error responses
        
      # Stack trace filtering
      blacklist:
        - '/vendor/'
        - '/cache/'
```

### Debug Bar

```yaml
laminas_microscope:
  components:
    debug_bar:
      # Enable debug bar
      enabled: true
      
      # Position on page
      position: 'bottom'  # Options: top, bottom
      
      # Maximum height of debug bar
      max_height: '30%'
      
      # Auto-hide after time (seconds, 0 = never)
      auto_hide: 0
      
      # Enabled collectors
      collectors:
        - 'time'         # Request timing
        - 'memory'       # Memory usage
        - 'exceptions'   # Exception tracking
        - 'pdo'          # Database queries
        - 'request'      # HTTP request/response
        - 'config'       # Configuration data
        - 'messages'     # Custom messages
        
      # Collector-specific settings
      collector_settings:
        pdo:
          # Slow query threshold (milliseconds)
          slow_threshold: 100
          
          # Explain slow queries
          explain_queries: true
          
          # Hide query parameters (security)
          hide_params: false
          
        time:
          # Precision for timing (decimal places)
          precision: 2
          
        memory:
          # Show memory graph
          show_graph: true
          
          # Memory usage warnings (MB)
          warning_threshold: 50
          critical_threshold: 100
```

### Microscope Analysis

```yaml
laminas_microscope:
  components:
    microscope:
      # Enable microscope analysis
      enabled: true
      
      # Automatically analyze requests
      auto_analyze: true
      
      # Analysis frequency (every N requests)
      analysis_frequency: 1
      
      # Performance thresholds
      thresholds:
        # Query execution time (milliseconds)
        query_time: 100
        
        # Memory usage (MB)
        memory_usage: 50
        
        # Response time (milliseconds)
        response_time: 1000
        
        # Database connection time (milliseconds)
        connection_time: 50
        
      # Analysis checks to perform
      checks:
        # N+1 query detection
        n_plus_one: true
        
        # Slow query detection
        slow_queries: true
        
        # Duplicate query detection
        duplicate_queries: true
        
        # Memory leak detection
        memory_leaks: true
        
        # Route performance analysis
        route_performance: true
        
        # Configuration optimization
        config_optimization: true
        
      # Report settings
      reports:
        # Maximum reports to keep
        max_reports: 100
        
        # Auto-export reports
        auto_export: false
        
        # Export format
        export_format: 'json'  # Options: json, yaml, csv
```

## 🌍 Environment-Specific Configuration

### Development Environment

```yaml
# config/autoload/debug-suite/development.yaml
laminas_microscope:
  enabled: true
  debug: true
  components:
    whoops:
      enabled: true
      show_in_production: false
      editor: 'vscode'
    debug_bar:
      enabled: true
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
      analysis_frequency: 1
```

### Staging Environment

```yaml
# config/autoload/debug-suite/staging.yaml
laminas_microscope:
  enabled: true
  debug: false
  ip_whitelist:
    - '192.168.1.0/24'
  components:
    whoops:
      enabled: true
      show_in_production: false
    debug_bar:
      enabled: true
      collectors:
        - 'time'
        - 'memory'
        - 'pdo'
    microscope:
      enabled: true
      auto_analyze: false
      analysis_frequency: 10
```

### Production Environment

```yaml
# config/autoload/debug-suite/production.yaml
laminas_microscope:
  enabled: false  # Disable by default
  debug: false
  require_https: true
  ip_whitelist:
    - '127.0.0.1'
  components:
    whoops:
      enabled: true
      show_in_production: false
    debug_bar:
      enabled: false
    microscope:
      enabled: false
```

## 📋 Configuration Profiles

### Minimal Profile

```yaml
# config/autoload/debug-suite/minimal.yaml
laminas_microscope:
  components:
    whoops:
      enabled: true
      show_in_production: false
    debug_bar:
      enabled: false
    microscope:
      enabled: false
```

### Performance Profile

```yaml
# config/autoload/debug-suite/performance.yaml
laminas_microscope:
  components:
    whoops:
      enabled: false
    debug_bar:
      enabled: true
      collectors:
        - 'time'
        - 'memory'
        - 'pdo'
    microscope:
      enabled: true
      auto_analyze: true
      checks:
        slow_queries: true
        n_plus_one: true
        route_performance: true
```

### Full Debugging Profile

```yaml
# config/autoload/debug-suite/debugging.yaml
laminas_microscope:
  debug: true
  components:
    whoops:
      enabled: true
    debug_bar:
      enabled: true
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
```

## 🔧 Advanced Configuration

### Custom Collectors

```yaml
laminas_microscope:
  components:
    debug_bar:
      custom_collectors:
        # Custom collector class
        my_collector:
          class: 'MyApp\Debug\MyCustomCollector'
          enabled: true
          config:
            option1: 'value1'
            option2: 'value2'
```

### Event Configuration

```yaml
laminas_microscope:
  events:
    # Listen for application events
    listeners:
      - 'MyApp\Listener\DebugListener'
      
    # Custom event configuration
    analysis_complete:
      enabled: true
      handlers:
        - 'email_notification'
        - 'slack_notification'
```

### Database-Specific Settings

```yaml
laminas_microscope:
  database:
    # Multiple database connections
    adapters:
      default:
        profiler: true
        log_queries: true
      cache:
        profiler: false
        log_queries: false
        
    # Query analysis settings
    query_analysis:
      # EXPLAIN queries automatically
      auto_explain: true
      
      # Analyze query patterns
      pattern_analysis: true
      
      # Index usage analysis
      index_analysis: true
```

## 📊 Performance Tuning

### Memory Optimization

```yaml
laminas_microscope:
  performance:
    # Limit memory usage
    memory_limit: '128M'
    
    # Garbage collection frequency
    gc_frequency: 1000
    
    # Buffer output for better performance
    output_buffering: true
```

### Query Optimization

```yaml
laminas_microscope:
  components:
    microscope:
      query_optimization:
        # Cache query analysis results
        cache_results: true
        
        # Cache lifetime (seconds)
        cache_lifetime: 3600
        
        # Maximum queries to analyze per request
        max_queries_per_request: 50
```

## 🔄 Configuration Loading Order

1. **Main configuration**: `laminas-microscope.yaml`
2. **Environment config**: `debug-suite/{environment}.yaml`  
3. **Profile config**: `debug-suite/{profile}.yaml`
4. **Local overrides**: `laminas-microscope.local.yaml`

## 🌍 Environment Variables

Use environment variables for sensitive configuration:

```yaml
laminas_microscope:
  enabled: '%env(bool:DEBUG_ENABLED)%'
  storage:
    path: '%env(string:DEBUG_STORAGE_PATH)%'
  auth:
    username: '%env(string:DEBUG_USERNAME)%'
    password: '%env(string:DEBUG_PASSWORD)%'
```

Set in your environment:

```bash
export DEBUG_ENABLED=true
export DEBUG_STORAGE_PATH=/var/laminas-microscope
export DEBUG_USERNAME=admin
export DEBUG_PASSWORD=secure_password
```

## ✅ Configuration Validation

Validate your configuration:

```bash
# Via CLI (if available)
php vendor/bin/laminas-microscope config:validate

# Via debug interface
# Visit /_debug/config to see current configuration
```

## 🔧 Configuration Examples

### High-Traffic Production

```yaml
laminas_microscope:
  enabled: true
  ip_whitelist: ['10.0.0.1']  # Admin server only
  components:
    whoops:
      enabled: true
      show_in_production: false
    debug_bar:
      enabled: false
    microscope:
      enabled: false
```

### Development Team

```yaml
laminas_microscope:
  enabled: true
  ip_whitelist: ['192.168.1.0/24']
  auth:
    enabled: true
    username: 'team'
    password: 'dev2023!'
  components:
    whoops:
      enabled: true
    debug_bar:
      enabled: true
    microscope:
      enabled: true
      auto_analyze: true
```

### Performance Testing

```yaml
laminas_microscope:
  enabled: true
  components:
    debug_bar:
      enabled: true
      collectors: ['time', 'memory', 'pdo']
    microscope:
      enabled: true
      auto_analyze: true
      checks:
        slow_queries: true
        n_plus_one: true
        memory_leaks: true
```

---

**Need more help?** Check our [troubleshooting guide](TROUBLESHOOTING.md) or [usage examples](USAGE.md).
