# Event Engine Descriptions

{.alert .alert-important}
This page is not migrated yet! [Learn more](https://event-engine.io/news/2019-04-05.html#4-1-3){: class="alert-link"}.

In the previous chapter "Set Up" we already learned that Event Engine loads `EventEngineDescription`s and passes itself as the only argument
to a static `describe` method.

```php
<?php

declare(strict_types=1);

namespace Prooph\EventEngine;

interface EventEngineDescription
{
    public static function describe(EventEngine $eventEngine): void;
}

```

Descriptions need to be loaded **before** `EventEngine::initialize()` is called.

{.alert .alert-info}
In the skeleton descriptions are listed in [src/Domain/DomainServices.php](https://github.com/event-engine/php-engine-skeleton/blob/master/src/Domain/DomainServices.php#L16){: class="alert-link"}
and this list is read by the event engine factory method in `src/ServiceFactory`:

```php
public function eventEngine($notInitialized = false): EventEngine
{
    if($notInitialized) {
        $eventEngine = new EventEngine(new OpisJsonSchema());
        foreach ($this->eventEngineDescriptions() as $description) {
            $eventEngine->load($description);
        }
        return $eventEngine;
    }
    $this->assertContainerIsset();
    return $this->makeSingleton(EventEngine::class, function () {
        //@TODO Load from cached config, if it exists
        $eventEngine = new EventEngine(new OpisJsonSchema());
        foreach ($this->eventEngineDescriptions() as $description) {
            $eventEngine->load($description);
        }
        $eventEngine->initialize(
            $this->flavour(),
            $this->multiModelStore(),
            $this->logEngine(),
            $this->container
        );
        return $eventEngine;
    });
}
```

{.alert .alert-info}
**Organising Descriptions:**
If you followed the tutorial, you already know that you can avoid code duplication and typing errors with a few simple tricks.
Clever combinations of class and constant names can provide readable code without much effort. The skeleton ships with default Event Engine Descriptions
to support you with that idea. You can find them in [src/Api](https://github.com/proophsoftware/event-machine-skeleton/tree/master/src/Api){: class="alert-link"}

## Registration API

Event Engine provides various `registration` methods. Those methods can only be called during **description phase** (see "Set Up" chapter for details about bootstrap phases).
Here is an overview of available methods:

```php
<?php

declare(strict_types=1);

namespace Prooph\EventEngine;

//...

final class EventEngine implements MessageDispatcher, AggregateStateStore
{
    //...

    /**
     * Add a command message to the system along with its payload schema
     */
    public function registerCommand(string $commandName, ObjectType $schema): self
    {
        //...
    }

    /**
     * Add an event message to the system along with its payload schema
     */
    public function registerEvent(string $eventName, ObjectType $schema): self
    {
        //...
    }

    /**
     * Add a query message to the system along with its payload schema
     */
    public function registerQuery(string $queryName, ObjectType $payloadSchema = null): QueryDescription
    {
        //...
    }

    /**
     * Add a data type to the system along with its json schema
     */
    public function registerType(string $nameOrImmutableRecordClass, ObjectType $schema = null): void
    {
        //...
    }

    /**
     * Add an enum type to the system along with its json schema
     */
    public function registerEnumType(string $typeName, EnumType $schema): void
    {
        //...
    }

    /**
     * Service id or instance of a CommandPreProcessor invoked before command is dispatched
     *
     * @param string $commandName
     * @param string | CommandPreProcessor $preProcessor
     */
    public function preProcess(string $commandName, $preProcessor): self
    {
        //...
    }

    /**
     * Describe handling of a command using returned CommandProcessorDescription
     */
    public function process(string $commandName): CommandProcessorDescription
    {
        //...
    }

    /**
     * Service id or callable event listener invoked after event is written to event stream
     *
     * @param string $eventName
     * @param string | callable $listener
     */
    public function on(string $eventName, $listener): self
    {
        //...
    }

    /**
     * Describe a projection by using returned ProjectionDescription
     */
    public function watch(Stream $stream): ProjectionDescription
    {
        //...
    }

    //...
}

```

## Message Payload Schema

Messages are like HTTP requests, but they are protocol agnostic. For HTTP requests/responses PHP-FIG has defined a standard known as PSR-7. Event Engine messages on the other hand are [prooph/common messages](https://github.com/prooph/common/blob/master/docs/messaging.md).

Like HTTP requests **messages should be validated before doing anything with them**. It can become a time consuming task to write validation logic for each message
by hand. Hence, Event Engine has a built-in way to validate messages using [Json Schema Draft 6](http://json-schema.org/specification-links.html#draft-6).
You do this using `JsonSchema` wrapper objects provided by Event Engine. Those objects are simple to use and drastically improve
readability of the code.

Again the command registration example from the previous chapter:

```php
$eventEngine->registerCommand(
    self::REGISTER_USER,
    JsonSchema::object([
        Payload::USER_ID => Schema::userId(),
        Payload::USERNAME => Schema::username(),
        Payload::EMAIL => Schema::email(),
    ])
);
```
This code speaks for itself, doesn't it? It is beautiful and clean (IMHO) and once you're used to it you can add new messages to the system in less than 30 seconds.
The chapter about "Json Schema" covers all the details. Make sure to check it out.

{.alert .alert-info}
A nice side effect of this approach is out-of-the-box [Swagger UI](https://swagger.io/tools/swagger-ui/){: class="alert-link"} support. Learn more about it in the "Swagger UI" chapter.

## Command Registration

Event Engine needs to know which commands can be processed by the system. Therefor, you have to register them before defining processing logic.

{.alert .alert-light}
Software developed with Event Engine follows a Command-Query-Responsibility-Segregation (short CQRS) approach.
Commands are used to trigger state changes without returning modified state and queries are used to request current state without modifying it.

You're ask to tell Event Engine a few details about available commands. Each command should have a **unique name** and a **payload schema**.
It is recommended to add a context as prefix in front of each command name. Let's take an example from the tutorial but add a context to the command name:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;
use Prooph\EventEngine\JsonSchema\JsonSchema;

class Command implements EventEngineDescription
{
    const CMD_CXT = 'BuildingMgmt.';
    const ADD_BUILDING = self::CMD_CXT.'AddBuilding';

    /**
     * @param EventEngine $eventEngine
     */
    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerCommand(
            Command::ADD_BUILDING,
            JsonSchema::object(
                [
                    'buildingId' => JsonSchema::uuid(),
                    'name' => JsonSchema::string()->withMinLength(2)
                ]
            )
        );
    }
}

```


Event Engine makes no assumptions about the format of the name. A common approach is to use a *dot notation* to separate context from message name
e.g. `BuildingMgmt.AddBuilding`. Using *dot notation* has the advantage that message broker like RabbitMQ can use it for routing.

## Command Processing

Once Event Engine knows about a command you can register processing logic for it. Commands are processed by **aggregate functions**. Think of an aggregate as a process with
multiple steps. Each step is triggered by a command and there is only one active step for a specific process aka. aggregate at the same time.

![Order Stream](img/order_stream.png)

In Event Engine aggregate functions are **stateless**. You can use plain PHP functions or classes with static public methods.

Before we dive deeper into aggregate functions, let's have a look at how commands are processed.
The following gif shows the power of Event Engine Descriptions especially `CommandProcessingDescription`.
A fluent interface mixed with clever class and constant naming + modern IDE support (PHPStorm in this case)
can assist you while putting together the pieces. You need to remember less which frees your mind to reason more about
the logic you're developing. This results in a higher quality business logic, written in a shorter time.
Try it yourself. It's actually a lot of fun to work with Event Engine.

{.alert .alert-light}
Keep an eye on the array callable syntax: `[ShoppingCart::class, 'addItem']`. PHPStorm provides code completion for it and respects it while renaming methods and classes.
That's an awesome feature and makes the syntax save to use.

![Command Processing Description](img/desc.gif)

Event Engine Descriptions keep glue code outside of the core business logic. This reduces "noise" in the core and creates
a central overview for navigation through the code.

{.alert .alert-info}
Details about the various Description types can be found in their respective chapters like "aggregates", "event listeners" and "projections".













