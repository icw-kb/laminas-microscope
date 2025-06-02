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
use LaminasMicroscope\Controller\MicroscopeController;
use LaminasMicroscope\Controller\DashboardController;
use LaminasMicroscope\Controller\ConfigurationController;
use LaminasMicroscope\Service\AnalysisService; // Ensure AnalysisService is imported
use Laminas\ServiceManager\ServiceManager; // Added this use statement

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
            // Factory for MicroscopeController
            // Changed type hint from ContainerInterface to ServiceManager
            MicroscopeController::class => function (Laminas\ServiceManager\ServiceManager $container) {
                // Get ConfigurationService and AnalysisService from the container
                $config = $container->get(ConfigurationService::class);
                $analysisService = $container->get(AnalysisService::class); // Requesting AnalysisService
                // Instantiate MicroscopeController with the correct dependencies
                return new MicroscopeController($config, $analysisService); // This line passes what was retrieved
            },
            // Factory for DashboardController
            // Changed type hint from ContainerInterface to ServiceManager
            DashboardController::class => function (Laminas\ServiceManager\ServiceManager $container) {
                $componentManager = $container->get(ComponentManager::class);
                $config = $container->get(ConfigurationService::class);
                // Assuming DashboardController constructor takes ComponentManager and ConfigurationService
                return new DashboardController($componentManager, $config);
            },
            // Factory for ConfigurationController
            // Changed type hint from ContainerInterface to ServiceManager
            ConfigurationController::class => function (Laminas\ServiceManager\ServiceManager $container) {
                $componentManager = $container->get(ComponentManager::class);
                $config = $container->get(ConfigurationService::class);
                $configManager = $container->get(ConfigurationManager::class);
                // Assuming ConfigurationController constructor takes ComponentManager, ConfigurationService, and ConfigurationManager
                return new ConfigurationController($componentManager, $config, $configManager);
            },
        ],
    ],
    'service_manager' => [
        'factories' => [
            // Factory for ConfigurationService
            // Changed type hint from ContainerInterface to ServiceManager
            ConfigurationService::class => function (Laminas\ServiceManager\ServiceManager $container) {
                // Get the main application configuration array
                $config = $container->get('Config');

                // REMOVED: Debug dump
                // echo "--- Config received by ConfigurationServiceFactory ---\n";
                // var_dump($config['laminas_microscope'] ?? 'laminas_microscope key not found');
                // echo "-----------------------------------------------------\n";

                // Pass the *entire* configuration array to the ConfigurationService constructor
                return new ConfigurationService($config);
            },
            // Factory for ComponentManager
            // Changed type hint from ContainerInterface to ServiceManager
            ComponentManager::class => function (Laminas\ServiceManager\ServiceManager $container) {
                $config = $container->get(ConfigurationService::class);
                // Pass the container to ComponentManager if its constructor requires it
                // Based on ComponentManager.php, it takes ConfigurationService and optional ContainerInterface
                return new ComponentManager($config, $container);
            },
            // Factory for ConfigurationManager
            // Changed type hint from ContainerInterface to ServiceManager
            ConfigurationManager::class => function (Laminas\ServiceManager\ServiceManager $container) {
                $config = $container->get(ConfigurationService::class);
                // ConfigurationManager constructor takes ConfigurationService and optional LoggerInterface
                // Assuming no logger is needed for now, adjust if necessary
                return new ConfigurationManager($config);
            },
            // Factory for AnalysisService
            // Changed type hint from ContainerInterface to ServiceManager
            AnalysisService::class => function (Laminas\ServiceManager\ServiceManager $container) {
                 $config = $container->get(ConfigurationService::class);
                 // Instantiate AnalysisService with ConfigurationService
                 return new AnalysisService($config);
            },
            // Factory for WhoopsHandler
            // Changed type hint from ContainerInterface to ServiceManager
            WhoopsHandler::class => function (Laminas\ServiceManager\ServiceManager $container) {
                // WhoopsHandler constructor takes ConfigurationService
                $config = $container->get(ConfigurationService::class);
                return new WhoopsHandler($config);
            },
            // Factory for DebugBarHandler
            // Changed type hint from ContainerInterface to ServiceManager
            DebugBarHandler::class => function (Laminas\ServiceManager\ServiceManager $container) {
                $config = $container->get(ConfigurationService::class);
                // DebugBarHandler constructor now takes ConfigurationService and Container
                return new DebugBarHandler($config, $container); // Pass the container
            },
            // Factory for MicroscopeHandler
            // Changed type hint from ContainerInterface to ServiceManager
            MicroscopeHandler::class => function (Laminas\ServiceManager\ServiceManager $container) {
                // MicroscopeHandler constructor takes ComponentManager, ConfigurationService, and ContainerInterface
                $componentManager = $container->get(ComponentManager::class);
                $config = $container->get(ConfigurationService::class);
                return new MicroscopeHandler($componentManager, $config, $container);
            },
            // Debug Bar Collectors
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
