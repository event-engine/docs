# Production Optimization

Event Engine boots in three phases: **description phase, initializing phase, bootstrapping phase**.

{.alert .alert-light}
Learn more about the [three phases](https://event-engine.github.io/api/set_up/installation.html#3-1-1-3).

To speed up the process you can skip the first two phases in production. This page explains how it works.

## Warm Cache

The best approach is to warm up the Event Engine cache during deployment. Luckily, Event Engine is already
prepared for that scenario. A simple PHP script is sufficient:

{.alert .alert-light}
Example script is taken from a production system

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

require  'vendor/autoload.php';

/** @var \Interop\Container\ContainerInterface $container */
$container = require 'config/container.php';

$config = $container->get('config');

if($config['cache_enabled'] && $config['cached_config_file']) {
    
    // Thanks to the three phases, we can easily set up Event Engine
    // Without the need to pass all dependencies
    // This is useful, because we don't want to establish a database connection
    // during deployment ...
    $eventEngine = new \EventEngine\EventEngine(
        $container->get(\EventEngine\Schema\Schema::class)
    );
    
    // ... but only want to load EE Descriptions ...
    foreach ($config['descriptions'] as $description) {
        $eventEngine->load($description);
    }
    
    // ... and cache the result
    file_put_contents(
        $config['cached_config_file'],
        "<?php\nreturn " . var_export($eventEngine->compileCacheableConfig(), true) . ';'
    );

    echo sprintf('Event Engine config file "%s" saved.', $config['cached_config_file']) . PHP_EOL;
}

```

{.alert .alert-info}
Call the script while preparing the deployment (f.e. when building the production docker image) and include the generated cache file in the build.

You can then check for the existence of a cache file when setting up Event Engine.

{.alert .alert-light}
Example factory is again taken from a production system.

```php
<?php

declare(strict_types=1);

namespace Acme\Infrastructure\EventEngine;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\Logger\LogEngine;
use EventEngine\Messaging\MessageProducer;
use EventEngine\Persistence\MultiModelStore;
use EventEngine\Runtime\Flavour;
use EventEngine\Schema\Schema;
use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfig;
use Psr\Container\ContainerInterface;

final class EventEngineFactory
{
    public function __invoke(ContainerInterface $container) : EventEngine
    {
        $config = $container->get('config');

        $schema = $container->get(Schema::class);
        $flavour = $container->get(Flavour::class);
        $multiModelStore = $container->get(MultiModelStore::class);
        $logger = $container->get(LogEngine::class);

        $messageProducer = null;

        if ($container->has(MessageProducer::class)) {
            $messageProducer = $container->get(MessageProducer::class);
        }

        if($config['cache_enabled'] && $config['cached_config_file'] && file_exists($config['cached_config_file'])) {
            $cachedConfig = require $config['cached_config_file'];

            $eventEngine = EventEngine::fromCachedConfig(
                $cachedConfig,
                $schema,
                $flavour,
                $multiModelStore,
                $logger,
                $container,
                null,
                $messageProducer
            );
        } else {
            $eventEngine = $this->createEventEngine($schema, $config['descriptions']);
            
            $eventEngine->initialize($flavour, $multiModelStore, $logger, $container, null, $messageProducer);

            // If cache file is missing for whatever reason, recreate it
            if($config['cache_enabled'] && $config['cached_config_file']) {
                file_put_contents(
                    $config['cached_config_file'],
                    "<?php\nreturn " . var_export($eventEngine->compileCacheableConfig(), true) . ';'
                );
            }
        }

        $debug = $container->get('config')['debug'] ?? false;
        $eventEngine->bootstrap($debug ? EventEngine::ENV_DEV : EventEngine::ENV_PROD, $debug);

        return $eventEngine;
    }

    /**
     * Returns a minimal event engine instance to generate cache file
     *
     * @param Schema $schema
     * @param string[] $descriptions FQCN[] of EventEngineDescription implementations
     * @return EventEngine
     */
    private function createEventEngine(Schema $schema, array $descriptions): EventEngine
    {
        $eventEngine = new EventEngine($schema);
        
        foreach ($descriptions as $description) {
            $eventEngine->load($description);
        }

        // We use async projections
        $eventEngine->disableAutoProjecting();

        return $eventEngine;
    }
}

```


