# Installation

{.alert .alert-info}
Event Engine is not a full stack framework. Instead you integrate it in any PHP framework that supports [PHP Standards Recommendations](https://www.php-fig.org/psr/){: class="alert-link"}.

## Skeleton

The easiest way to get started is by using the [skeleton](https://github.com/event-engine/php-engine-skeleton).
It ships with a preconfigured Event Engine, a recommended project structure, ready-to-use docker containers and [Zend Strategility](https://github.com/zendframework/zend-stratigility) to handle HTTP requests.

{.alert .alert-light}
*Again*: The skeleton is not the only way to set up Event Engine. You can tweak set up as needed and integrate Event Engine with Symfony, Laravel or any other framework
or middleware dispatcher.

## Required Infrastructure

Event Engine is based on **PHP 7.2 or higher**. Package dependencies are installed using [composer](https://getcomposer.org/).

### Database

By default Event Engine uses [prooph/event-store](http://docs.getprooph.org/event-store/) to store **events** recorded by the **write model**
and a **DocumentStore** (see "Document Store" chapter) to store the **read model**.

{.alert .alert-info}
The skeleton uses prooph's Postgres event store
and a [Postgres Document Store](https://github.com/event-engine/php-postgres-document-store){: class="alert-link"} implementation.
This allows Event Engine to work with a single database, but that's not a requirement. You can mix and match. Event Engine defines a lean
event store interface, that can be found in the [event-engine/php-event-store](https://github.com/event-engine/php-event-store/blob/master/src/EventStore.php) package.
Projections don't have a hard dependency on the document store, either. A document store is only required when using the **MultiModelStore** feature or the default **aggregate projection**.
Other than that, you can use whatever you want to persist the read model.

#### Creating The Event Stream

By default all events are stored in a single stream and **prooph/event-store has to be set up with the SingleStreamStrategy!**
The reason for this is that projections rely on a guaranteed order of events.
A single stream is the only way to fulfill this requirement. 

{.alert .alert-light}
When using a relational database as an event store a single
table is also very efficient. A longer discussion about the topic can be found
in the [prooph/pdo-event-store repo](https://github.com/prooph/pdo-event-store/issues/139){: class="alert-link"}.

An easy way to create the needed stream is to use the event store API directly.

```php
<?php
declare(strict_types=1);

namespace EventEngine;

use ArrayIterator;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;

chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';

$container = require 'config/container.php';

/** @var EventStore $eventStore */
$eventStore = $container->get(EventStore::class);
$eventStore->create(new Stream(new StreamName('event_stream'), new ArrayIterator()));

echo "done.\n";

```

Such a [script](https://github.com/event-engine/php-engine-skeleton/blob/master/scripts/create_event_stream.php) is used in the skeleton.
As you can see we request the event store from a container that we get from a config file. The skeleton uses [Zend Strategility](https://github.com/zendframework/zend-stratigility)
and this is a common approach in Strategility (and Zend Expressive) based applications. 

{.alert .alert-light}
If you want to use another framework, adopt the script accordingly.
The only thing that really matters is that you get a configured prooph/event-store from the [PSR-11 container](https://www.php-fig.org/psr/psr-11/)
used by Event Engine.

#### Read Model Storage

Projection storage is set up on the fly. You don't need to prepare it upfront, but you can if you prefer to work with a database migration tool. It is up to you.
Learn more about read model storage set up in the projections chapter. 

{.alert .alert-warning}
The **Multi-Model-Store** requires existing read model collections. Please find a detailed explanation in [the tutorial](/tutorial/partIII.html#2-4-2). 

## Event Engine Descriptions

Event Engine uses a "zero configuration" approach. While you have to configure integrated packages like *prooph/event-store*, Event Engine itself
does not require centralized configuration. Instead it loads so called *Event Engine Descriptions*:

```php
<?php

declare(strict_types=1);

namespace Prooph\EventEngine;

interface EventEngineDescription
{
    public static function describe(EventEngine $eventEngine): void;
}

```

Any class implementing the interface can be loaded by Event Engine. The task of a *Description* is to tell Event Engine how the application is structured.
This is done in a programmatic way using Event Engine's registration API which we will cover in the next chapter.
Here is a simple example of a *Description* that registers a *command* in Event Engine.

```php
<?php
declare(strict_types=1);


namespace App\Api;

use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;

class Command implements EventEngineDescription
{
    const COMMAND_CONTEXT = 'MyContext.';
    const REGISTER_USER = self::COMMAND_CONTEXT . 'RegisterUser';

    /**
     * @param EventEngine $eventEngine
     */
    public static function describe(EventEngine $eventEngine): void
    {
         $eventEngine->registerCommand(
            self::REGISTER_USER,  //<-- Name of the  command defined as constant above
            JsonSchema::object([
                Payload::USER_ID => Schema::userId(),
                Payload::USERNAME => Schema::username(),
                Payload::EMAIL => Schema::email(),
            ])
         );

    }
}
```

Now we only need to tell Event Engine that it should load the *Description*:

```php
declare(strict_types=1);

require_once 'vendor/autoload.php';

$eventEngine = new EventEngine(
    new OpisJsonSchema() /* Or another Schema implementation */
);

$eventEngine->load(App\Api\Command::class);

```

{.alert .alert-light}
Event Engine only requires a `EventEngine\Schema\Schema` implementation in the constructor. All other dependencies are passed during **initialize** phase (see next section).

## Initialize & Bootstrap

Event Engine is bootstrapped in three phases. **Descriptions** are loaded first, followed by a `$eventEngine->initialize(/* dependencies */)` call.
Finally, `$eventEngine->bootstrap($env, $debugMode)` prepares the system so that it can handle incoming messages.

{.alert .alert-info}
Bootstrapping is split because description and initialization phase can be skipped in production.
Read more about this in [Production Optimization](/api/set-up/production_optimization.html).

### Initialize

Event Engine needs to aggregate information from all **Descriptions**.
This is done in the *Initialize phase*. The phase also requires mandatory and optional dependencies used by Event Engine.
Details are listed on the [Dependencies](/api/set-up/di.html) page.

### Bootstrap

Last, but not least `$eventEngine->bootstrap($environment, $debugMode)` starts the engine and we're ready to take off.
Event Engine supports 3 different environments: `dev`, `prod` and `test`. The environment is mainly used to set up third-party components like a logger.

Same is true for the `debug mode`. It can be used to enable verbose logging or displaying of exceptions even if Event Engine runs in prod environment.
You have to take care of this when setting up services. Event Engine just provides the information:

```
Environment: $eventEngine->env(); // prod | dev | test
Debug Mode: $eventEngine->debugMode(); // bool
```














