# Usage Guide

This guide shows you how to use Laminas Microscope effectively for debugging and profiling your Laminas applications.

## 🏠 Dashboard Overview

Access the main dashboard at `/_debug` to see:

- **Component Status** - Current state of Whoops, Debug Bar, and Microscope
- **Environment Information** - PHP version, memory limits, storage status
- **Quick Actions** - Direct access to analysis tools and configuration
- **System Status** - Debug mode status and storage permissions

## 📊 Debug Bar Usage

The Debug Bar appears automatically at the bottom (or top) of your HTML pages and provides real-time debugging information.

### 📈 Understanding the Debug Bar

```
🕐 142ms | 💾 8.5MB | 🗄️ 12 queries | 💥 0 errors | 📨 5 messages
```

Click any section to expand detailed information:

- **🕐 Time** - Request duration breakdown
- **💾 Memory** - Current and peak memory usage  
- **🗄️ Queries** - Database queries with timing
- **💥 Errors** - Exceptions and error details
- **📨 Messages** - Custom log messages

### 🔍 Debug Bar Collectors

#### Time Collector
- **Total request time** - Complete request lifecycle
- **Bootstrap time** - Application initialization
- **Route matching** - Time to find the correct route
- **Controller dispatch** - Controller execution time
- **View rendering** - Template processing time

#### Memory Collector
- **Current usage** - Memory at request end
- **Peak usage** - Maximum memory during request
- **Memory graph** - Usage over time visualization
- **Memory warnings** - Alerts for high usage

#### Database (PDO) Collector
- **Query list** - All executed SQL queries
- **Execution time** - Individual query performance
- **Parameters** - Bound values for prepared statements
- **Slow query alerts** - Queries exceeding threshold
- **Duplicate detection** - Identical queries highlighted

#### Request Collector
- **HTTP details** - Method, URI, headers
- **Parameters** - GET, POST, cookies, session data
- **Route information** - Matched route and parameters
- **Response data** - Status codes and headers

### 🎛️ Programmatic Debug Bar Usage

Add custom timing and messages to the debug bar:

```php
// Get the debug bar handler
$debugBar = $container->get('LaminasMicroscope\DebugBar\DebugBarHandler');

// Add custom timing
$debugBar->startMeasure('custom_operation', 'Processing User Data');
// ... your code here ...
$debugBar->stopMeasure('custom_operation');

// Add informational messages
$debugBar->addMessage('Processing started for user ID: ' . $userId, 'info');
$debugBar->addMessage('Cache miss - fetching from database', 'warning');
$debugBar->addMessage('Operation completed successfully', 'success');

// Log exceptions
try {
    // risky operation
    $result = $this->riskyOperation();
} catch (\Exception $e) {
    $debugBar->addException($e);
    $debugBar->addMessage('Failed to complete operation: ' . $e->getMessage(), 'error');
}
```

#### Message Types and Icons

| Type | Color | Use Case |
|------|-------|----------|
| `info` | Blue | General information |
| `warning` | Orange | Potential issues |
| `error` | Red | Errors and failures |
| `success` | Green | Successful operations |
| `debug` | Gray | Debug-specific info |

#### Advanced Timing

```php
// Nested timing for detailed analysis
$debugBar->startMeasure('user_process', 'User Processing');

$debugBar->startMeasure('user_validate', 'User Validation');
$this->validateUser($user);
$debugBar->stopMeasure('user_validate');

$debugBar->startMeasure('user_save', 'Save User');
$this->saveUser($user);
$debugBar->stopMeasure('user_save');

$debugBar->stopMeasure('user_process');
```

## 🔬 Microscope Analysis

Access the Microscope at `/_debug/microscope` for detailed application analysis.

### 🎯 Overview Tab

The overview provides a high-level summary:

- **Query Statistics** - Total queries, slow queries, N+1 issues
- **Performance Metrics** - Average response time and memory usage
- **Recent Reports** - Analysis history with performance scores
- **Issue Summary** - Critical problems requiring attention

### 🗄️ Database Query Analysis

#### Slow Query Detection

Queries are automatically flagged as slow based on your threshold:

```yaml
laminas_microscope:
  components:
    microscope:
      thresholds:
        query_time: 100  # milliseconds
```

**Slow Query Example:**
```sql
SELECT * FROM users WHERE created_at BETWEEN '2023-01-01' AND '2023-12-31'
-- Execution time: 245ms ⚠️ SLOW
-- Suggestion: Add index on created_at column
```

#### N+1 Query Detection

The Microscope automatically detects N+1 query patterns:

```php
// This code would trigger N+1 detection:
$users = $this->userTable->fetchAll();  // 1 query
foreach ($users as $user) {
    $posts = $this->postTable->fetchByUserId($user->getId());  // N queries
}

// Suggested fix:
$users = $this->userTable->fetchAllWithPosts();  // 1 optimized query
```

#### Duplicate Query Detection

Identical queries executed multiple times are highlighted:

```sql
-- Query executed 5 times:
SELECT * FROM categories WHERE active = 1
-- Suggestion: Cache the result or optimize the calling code
```

### 🛣️ Route Performance Analysis

Monitor route-specific performance:

- **Hit Count** - How often each route is accessed
- **Average Duration** - Mean response time per route
- **Min/Max Times** - Performance variance
- **Error Rate** - Failed requests percentage

**Example Route Analysis:**
```
Route: user-profile (/user/{id})
├── Hits: 1,247
├── Avg Duration: 156ms
├── Min/Max: 23ms / 1,204ms
└── Performance Score: 82%
```

### ⚡ Performance Profiling

#### Memory Usage Analysis

Track memory consumption patterns:

- **Peak Memory** - Maximum usage during request
- **Memory Leaks** - Steadily increasing usage
- **Garbage Collection** - GC frequency and impact

#### Request Timeline

Visualize where time is spent:

```
Bootstrap    ████████░░ 45ms (18%)
Routing      ██░░░░░░░░ 12ms (5%)
Controller   ████████████ 89ms (35%)
View         ██████░░░░ 67ms (27%)
Database     █████░░░░░ 38ms (15%)
```

### 🚨 Issue Detection and Recommendations

The Microscope provides actionable recommendations:

#### High Priority Issues
- **N+1 Queries** - Specific loops and suggested fixes
- **Missing Indexes** - Database optimization opportunities  
- **Memory Leaks** - Objects not being garbage collected

#### Medium Priority Issues
- **Slow Queries** - Optimization suggestions
- **Large Response Times** - Performance bottlenecks
- **Inefficient Routes** - Routing optimization

#### Low Priority Issues
- **Unused Config** - Cleanup opportunities
- **Deprecation Warnings** - Future compatibility

### 📊 Running Manual Analysis

```php
// Trigger analysis programmatically
$microscope = $container->get('LaminasMicroscope\Microscope\MicroscopeHandler');

// Run full analysis
$report = $microscope->runAnalysis();

// Run specific checks
$queryIssues = $microscope->analyzeQueries();
$routeIssues = $microscope->analyzeRoutes();
$memoryIssues = $microscope->analyzeMemory();

// Generate recommendations
$recommendations = $microscope->generateRecommendations($report);
```

## 💥 Whoops Error Pages

Whoops provides beautiful, detailed error pages with:

### 🔍 Stack Trace Features

- **Syntax highlighted code** around the error
- **Variable inspection** in each frame
- **File path links** (with editor integration)
- **Search functionality** through the stack

### 🛠️ Editor Integration

Configure your preferred editor:

```yaml
laminas_microscope:
  components:
    whoops:
      editor: 'vscode'  # Options: vscode, phpstorm, sublime, atom
```

**Custom Editor URL:**
```yaml
laminas_microscope:
  components:
    whoops:
      editor_url: 'vscode://file/%file:%line'
```

### 📋 Request Information

Whoops shows complete request context:

- **Request headers** and parameters
- **Session data** (sanitized)
- **Environment variables**
- **Server configuration**

### 🎯 AJAX Error Handling

For AJAX requests, Whoops returns JSON error responses:

```json
{
  "error": {
    "type": "RuntimeException",
    "message": "Database connection failed",
    "file": "/app/src/Service/UserService.php",
    "line": 45,
    "trace": [...]
  }
}
```

## ⚙️ Configuration Management

Access configuration at `/_debug/config`.

### 🎛️ Component Configuration

#### Toggle Components
- **Enable/disable** individual components
- **Real-time preview** of configuration changes
- **Environment-specific** settings

#### Debug Bar Collectors
```yaml
# Enable specific collectors
laminas_microscope:
  collectors:
    - 'time'       # ✅ Performance timing
    - 'memory'     # ✅ Memory usage
    - 'pdo'        # ✅ Database queries
    - 'request'    # ❌ HTTP request data
    - 'config'     # ❌ Configuration dump
```

### 📋 Configuration Profiles

#### Quick Profile Switching
- **Minimal** - Whoops only (production-safe)
- **Performance** - Debug Bar + Microscope
- **Full Debug** - All components enabled

#### Custom Profiles
Create custom configurations for specific scenarios:

```php
// Custom profile for API debugging
$apiProfile = [
    'components' => [
        'whoops' => ['enabled' => true],
        'debug_bar' => [
            'enabled' => true,
            'collectors' => ['time', 'memory', 'pdo', 'request']
        ],
        'microscope' => ['enabled' => false]
    ]
];
```

## 🔧 Best Practices

### 🏗️ Development Workflow

1. **Start with Full Debug** profile for initial development
2. **Use Performance** profile when optimizing
3. **Switch to Minimal** profile for production testing
4. **Monitor continuously** with appropriate profiles

### 🎯 Performance Monitoring

```php
// Add performance checkpoints
class UserController
{
    public function createAction()
    {
        $debugBar = $this->getDebugBar();
        
        $debugBar->startMeasure('user_creation', 'User Creation Process');
        
        $debugBar->startMeasure('validation', 'Input Validation');
        $this->validateInput($data);
        $debugBar->stopMeasure('validation');
        
        $debugBar->startMeasure('business_logic', 'Business Logic');
        $user = $this->userService->createUser($data);
        $debugBar->stopMeasure('business_logic');
        
        $debugBar->startMeasure('persistence', 'Database Save');
        $this->userRepository->save($user);
        $debugBar->stopMeasure('persistence');
        
        $debugBar->stopMeasure('user_creation');
        
        return $this->redirect()->toRoute('user-profile', ['id' => $user->getId()]);
    }
}
```

### 🗄️ Database Optimization

```php
// Monitor query patterns
class ProductService
{
    public function getProductsWithCategories()
    {
        $debugBar = $this->getDebugBar();
        $debugBar->addMessage('Fetching products with categories', 'info');
        
        // ❌ Bad: N+1 queries
        // $products = $this->productRepository->findAll();
        // foreach ($products as $product) {
        //     $product->setCategory($this->categoryRepository->find($product->getCategoryId()));
        // }
        
        // ✅ Good: Single optimized query
        $products = $this->productRepository->findAllWithCategories();
        
        $debugBar->addMessage('Loaded ' . count($products) . ' products', 'success');
        
        return $products;
    }
}
```

### 🔒 Production Safety

#### Environment Detection
```php
// Automatically adjust based on environment
if ($this->isProduction()) {
    $config['laminas_microscope']['components']['debug_bar']['enabled'] = false;
    $config['laminas_microscope']['components']['microscope']['enabled'] = false;
}
```

#### IP Restrictions
```yaml
# Restrict access in production
laminas_microscope:
  ip_whitelist:
    - '127.0.0.1'      # Local debugging
    - '10.0.0.100'     # Admin workstation
    - '192.168.1.50'   # Development team
```

## 📊 Export and Reporting

### 📈 Analysis Reports

Export analysis data for team review:

```bash
# Via web interface
# Visit /_debug/microscope and click "Export Data"

# Programmatically
$microscope = $container->get('LaminasMicroscope\Microscope\MicroscopeHandler');
$report = $microscope->exportReport('json');  # or 'yaml', 'csv'
```

### 📋 Performance Reports

Generate team performance reports:

```php
$performanceReport = [
    'timeframe' => '2023-12-01 to 2023-12-31',
    'metrics' => [
        'avg_response_time' => '156ms',
        'slow_queries' => 23,
        'n_plus_one_issues' => 5,
        'memory_usage' => '45MB avg'
    ],
    'recommendations' => [
        'Add index to user.created_at',
        'Optimize product listing query',
        'Cache category data'
    ]
];
```

## 🚨 Troubleshooting Common Issues

### Debug Bar Not Appearing

```php
// Check if HTML response
$response = $event->getResponse();
$contentType = $response->getHeaders()->get('Content-Type');
if (!$contentType || strpos($contentType->getFieldValue(), 'text/html') === false) {
    // Debug bar only works with HTML responses
}

// Verify closing body tag
$content = $response->getContent();
if (strpos($content, '</body>') === false) {
    // Debug bar needs closing </body> tag for injection
}
```

### Memory Issues

```php
// Monitor memory usage
$debugBar->addMessage('Memory before operation: ' . memory_get_usage(true), 'info');
$this->heavyOperation();
$debugBar->addMessage('Memory after operation: ' . memory_get_usage(true), 'info');

// Set memory warnings
ini_set('memory_limit', '256M');
```

### Slow Performance

```yaml
# Reduce debug overhead
laminas_microscope:
  collectors:
    - 'time'    # Keep essential only
    - 'memory'
    - 'pdo'
  components:
    debug_bar:
    microscope:
      auto_analyze: false  # Disable auto-analysis
      analysis_frequency: 10  # Analyze every 10th request
```

---

**Want more?** Check our [configuration guide](CONFIG.md) for advanced setups or [troubleshooting guide](TROUBLESHOOTING.md) for common issues.
