<?php

declare(strict_types=1);

use LaminasMicroscope\Manager\ComponentManager;
use LaminasMicroscope\Config\ConfigurationService;
use LaminasMicroscope\Service\ConfigurationManager;
use LaminasMicroscope\Whoops\WhoopsHandler;
use LaminasMicroscope\DebugBar\DebugBarHandler;
use LaminasMicroscope\DebugBar\Collectors\PDOCollector;
use LaminasMicroscope\DebugBar\Collectors\LaminasConfigCollector;
use LaminasMicroscope\DebugBar\Collectors\LaminasRequestCollector;
use LaminasMicroscope\DebugBar\EventListener\QueryLogger;
use LaminasMicroscope\Microscope\MicroscopeHandler;
use LaminasMicroscope\Collector\CollectorRegistry;
use LaminasMicroscope\Controller\MicroscopeController;
use LaminasMicroscope\Controller\DashboardController;
use LaminasMicroscope\Controller\ConfigurationController;
use LaminasMicroscope\Service\AnalysisService;
use LaminasMicroscope\Listener\WhoopsEventListener;
use LaminasMicroscope\Listener\MicroscopeEventListener;
use LaminasMicroscope\Listener\DebugBarEventListener;
use LaminasMicroscope\Cache\CacheManager;
use LaminasMicroscope\DebugBar\Collectors\EnhancedPDOCollector;

// Factory imports
use LaminasMicroscope\Factory\ConfigurationServiceFactory;
use LaminasMicroscope\Factory\ComponentManagerFactory;
use LaminasMicroscope\Factory\DebugBarHandlerFactory;
use LaminasMicroscope\Factory\MicroscopeHandlerFactory;
use LaminasMicroscope\Factory\WhoopsHandlerFactory;
use LaminasMicroscope\Factory\AnalysisServiceFactory;
use LaminasMicroscope\Factory\ConfigurationManagerFactory;
use LaminasMicroscope\Factory\CollectorRegistryFactory;
use LaminasMicroscope\Factory\Controller\DashboardControllerFactory;
use LaminasMicroscope\Factory\Controller\MicroscopeControllerFactory;
use LaminasMicroscope\Factory\Controller\ConfigurationControllerFactory;
use LaminasMicroscope\Factory\Listener\WhoopsEventListenerFactory;
use LaminasMicroscope\Factory\Listener\MicroscopeEventListenerFactory;
use LaminasMicroscope\Factory\Listener\DebugBarEventListenerFactory;
use LaminasMicroscope\Factory\CacheManagerFactory;
use LaminasMicroscope\Factory\EnhancedPDOCollectorFactory;

return [
    'router' => [
        'routes' => [
            'laminas-microscope' => [
                'type' => 'Literal',
                'options' => [
                    'route' => '/_debug',
                    'defaults' => [
                        'controller' => DashboardController::class,
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'microscope' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/microscope[/:action[/:id]]',
                            'defaults' => [
                                'controller' => MicroscopeController::class,
                                'action' => 'index',
                            ],
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id' => '[0-9a-zA-Z-]+',
                            ],
                        ],
                    ],
                    'config' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/config[/:action]',
                            'defaults' => [
                                'controller' => ConfigurationController::class,
                                'action' => 'index',
                            ],
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                        ],
                    ],
                    'api' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/api[/:action]',
                            'defaults' => [
                                'controller' => DashboardController::class,
                                'action' => 'api',
                            ],
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                        ],
                    ],
                    'microscope-api' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/microscope/api[/:action]',
                            'defaults' => [
                                'controller' => MicroscopeController::class,
                                'action' => 'api',
                            ],
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                        ],
                    ],
                    'analytics' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/analytics[/:action[/:id]]',
                            'defaults' => [
                                'controller' => DashboardController::class,
                                'action' => 'analytics',
                            ],
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id' => '[0-9a-zA-Z-]+',
                            ],
                        ],
                    ],
                    'cache' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/cache[/:action]',
                            'defaults' => [
                                'controller' => DashboardController::class,
                                'action' => 'cache',
                            ],
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                        ],
                    ],
                    'performance' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/performance[/:action]',
                            'defaults' => [
                                'controller' => DashboardController::class,
                                'action' => 'performance',
                            ],
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                        ],
                    ],
                    // Add route for serving DebugBar assets
                    'debugbar-assets' => [
                        'type' => 'Segment',
                        'options' => [
                            // Match the default base_url and capture the file path
                            'route' => '/debugbar/resources[/:file]',
                            'defaults' => [
                                'controller' => DashboardController::class,
                                'action' => 'assets', // New action to serve assets
                                'file' => null, // Allow empty file path for base URL
                            ],
                            'constraints' => [
                                'file' => '.*', // Allow any characters in the file path
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            MicroscopeController::class => MicroscopeControllerFactory::class,
            DashboardController::class => DashboardControllerFactory::class,
            ConfigurationController::class => ConfigurationControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            ConfigurationService::class => ConfigurationServiceFactory::class,
            ComponentManager::class => ComponentManagerFactory::class,
            ConfigurationManager::class => ConfigurationManagerFactory::class,
            AnalysisService::class => AnalysisServiceFactory::class,
            CollectorRegistry::class => CollectorRegistryFactory::class,
            WhoopsHandler::class => WhoopsHandlerFactory::class,
            DebugBarHandler::class => DebugBarHandlerFactory::class,
            MicroscopeHandler::class => MicroscopeHandlerFactory::class,
            CacheManager::class => CacheManagerFactory::class,
            
            // Debug Bar Collectors
            EnhancedPDOCollector::class => EnhancedPDOCollectorFactory::class,
            PDOCollector::class => function (Laminas\ServiceManager\ServiceManager $container) {
                return new PDOCollector($container);
            },
            LaminasConfigCollector::class => function (Laminas\ServiceManager\ServiceManager $container) {
                return new LaminasConfigCollector($container);
            },
            LaminasRequestCollector::class => function (Laminas\ServiceManager\ServiceManager $container) {
                return new LaminasRequestCollector($container);
            },
            QueryLogger::class => function (Laminas\ServiceManager\ServiceManager $container) {
                $pdoCollector = $container->get(PDOCollector::class);
                return new QueryLogger($pdoCollector);
            },
            
            // Event Listeners
            WhoopsEventListener::class => WhoopsEventListenerFactory::class,
            MicroscopeEventListener::class => MicroscopeEventListenerFactory::class,
            DebugBarEventListener::class => DebugBarEventListenerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'template_map' => [
            'laminas-microscope/microscope/index' => __DIR__ . '/../view/laminas-microscope/microscope/index.phtml',
            'laminas-microscope/microscope/view' => __DIR__ . '/../view/laminas-microscope/microscope/view.phtml',
            'laminas-microscope/microscope/queries' => __DIR__ . '/../view/laminas-microscope/microscope/queries.phtml',
            'laminas-microscope/microscope/routes' => __DIR__ . '/../view/laminas-microscope/microscope/routes.phtml',
            'laminas-microscope/microscope/performance' => __DIR__ . '/../view/laminas-microscope/microscope/performance.phtml',
            'laminas-microscope/config/index' => __DIR__ . '/../view/laminas-microscope/config/index.phtml',
            'laminas-microscope/config/profiles' => __DIR__ . '/../view/laminas-microscope/config/profiles.phtml',
            'laminas-microscope/index' => __DIR__ . '/../view/laminas-microscope/index.phtml',
            // Phase 3 Templates
            'laminas-microscope/dashboard/analytics' => __DIR__ . '/../view/laminas-microscope/dashboard/analytics.phtml',
            'laminas-microscope/dashboard/cache' => __DIR__ . '/../view/laminas-microscope/dashboard/cache.phtml',
            'laminas-microscope/dashboard/performance' => __DIR__ . '/../view/laminas-microscope/dashboard/performance.phtml',
        ],
    ],
    // Add default configuration for laminas_microscope key
    'laminas_microscope' => [
        'components' => [
            'debug_bar' => [
                // Add the base_url configuration option
                // This should match the route defined above
                'base_url' => '/_debug/debugbar/resources', // Updated base_url to match new route
            ],
        ],
    ],
];
