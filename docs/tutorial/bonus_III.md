# Bonus III - Functional Flavour

Event Engine has a nice feature called **Flavours**. A Flavour lets you customize the way Event Engine interacts with
your code. Throughout the tutorial we worked with the **PrototypingFlavour**, which is the default.

As the name suggests, the PrototypingFlavour is optimized for rapid development. For example instead of defining classes
for each type of message, Event Engine passes its default `Message` implementation to aggregate functions, process managers,
resolvers and projectors. You don't need to care about serialization and mapping.

{.alert .alert-info}
If you want to try out new ideas, PrototypingFlavour is your best friend.
Following Domain-Driven Design best practices **Continuous Discovery** and **Agile Development** are key drivers for successful
projects. This requires experimentation and with the PrototypingFlavour it's easier than ever.

## Harden The Domain Model

Experimentation is great, but at some point you'll be satisfied with the domain model and want to turn it into a clean and
robust implementation. That's very important for long-lived applications. Fortunately, Event Engine offers two additional Flavours.
One is called the **FunctionalFlavour** and the other one **OopFlavour**. Finally, you can implement your own `EventEngine\Runtime\Flavour`
to turn Event Engine into your very own CQRS / ES framework.

First let's look at the **FunctionalFlavour**. It's similar to what we did so far, except that explicit message types are used instead of
generic Event Engine messages.

## Functional Port

The FunctionalFlavour requires an implementation of `EventEngine\Runtime\Functional\Port`. Here you have to define custom mapping and serialization
logic for message types. Create a new class `MyServiceMessagePort` in `src/System/Flavour`:

```php
<?php
declare(strict_types=1);

namespace MyService\System\Flavour;

use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageBag;
use EventEngine\Runtime\Functional\Port;

final class MyServiceMessagePort implements Port
{
    /**
     * @param Message $message
     * @return mixed The custom message
     */
    public function deserialize(Message $message)
    {
        // TODO: Implement deserialize() method.
    }

    /**
     * @param mixed $customMessage
     * @return array
     */
    public function serializePayload($customMessage): array
    {
        // TODO: Implement serializePayload() method.
    }

    /**
     * @param mixed $customCommand
     * @return MessageBag
     */
    public function decorateCommand($customCommand): MessageBag
    {
        // TODO: Implement decorateCommand() method.
    }

    /**
     * @param mixed $customEvent
     * @return MessageBag
     */
    public function decorateEvent($customEvent): MessageBag
    {
        // TODO: Implement decorateEvent() method.
    }

    /**
     * @param string $aggregateIdPayloadKey
     * @param mixed $command
     * @return string
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, $command): string
    {
        // TODO: Implement getAggregateIdFromCommand() method.
    }

    /**
     * @param mixed $customCommand
     * @param mixed $preProcessor Custom preprocessor
     * @return mixed|CommandDispatchResult Custom message or CommandDispatchResult
     */
    public function callCommandPreProcessor($customCommand, $preProcessor)
    {
        // TODO: Implement callCommandPreProcessor() method.
    }

    /**
     * Commands returned by the controller are dispatched automatically
     *
     * @param mixed $customCommand
     * @param mixed $controller
     * @return mixed[]|null|CommandDispatchResult Array of custom commands or null|CommandDispatchResult to indicate that no further action is required
     */
    public function callCommandController($customCommand, $controller)
    {
        // TODO: Implement callCommandController() method.
    }

    /**
     * @param mixed $customCommand
     * @param mixed $contextProvider
     * @return mixed
     */
    public function callContextProvider($customCommand, $contextProvider)
    {
        // TODO: Implement callContextProvider() method.
    }

    public function callResolver($customQuery, $resolver)
    {
        // TODO: Implement callResolver() method.
    }
}

```

We'll implement the interface step by step and define a mapping strategy along the way.

## Deserialize

First method is `deserialize`:

```php
/**
 * @param Message $message
 * @return mixed The custom message
 */
public function deserialize(Message $message)
{
    // TODO: Implement deserialize() method.
}
```

An Event Engine message is passed as argument and the method should return a custom message. The first important decision is required:

{.alert .alert-info}
**Which serialization technique do we want to use?** Some people prefer handcrafted serialization, while others prefer conventions or serializers.
The good news is, every technique can be used! It just needs to be implemented in the Port.

To keep the tutorial simple, we're going to use the tools shipped with Event Engine.
That said, our messages become `ImmutableRecord`s and use the built-in serialization technique provided by `ImmutableRecordLogic`.

{.alert .alert-warning}
The fact that messages are still coupled with the framework is not important here. It's our decision as developers to do it, but nothing required by Event Engine.
We could also write our own serialization mechanism or use a third-party tool like [FPP](https://github.com/prolic/fpp){: class="alert-link"}.

Let's create some types and messages first:

`src/Domain/Model/Building/BuildingId.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class BuildingId
{
    private $buildingId;

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $buildingId): self
    {
        return new self(Uuid::fromString($buildingId));
    }

    private function __construct(UuidInterface $buildingId)
    {
        $this->buildingId = $buildingId;
    }

    public function toString(): string
    {
        return $this->buildingId->toString();
    }

    public function equals($other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->buildingId->equals($other->buildingId);
    }

    public function __toString(): string
    {
        return $this->buildingId->toString();
    }
}

```

`src/Domain/Model/Building/BuildingName.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building;

final class BuildingName
{
    private $name;

    public static function fromString(string $name): self
    {
        return new self($name);
    }

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public function toString(): string
    {
        return $this->name;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->name === $other->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

```

`src/Domain/Model/Building/Username.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building;

final class Username
{
    private $name;

    public static function fromString(string $name): self
    {
        return new self($name);
    }

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public function toString(): string
    {
        return $this->name;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->name === $other->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

```

`src/Domain/Model/Building/Command/AddBuilding.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building\Command;

use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Model\Building\BuildingName;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class AddBuilding implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var BuildingName
     */
    private $name;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return BuildingName
     */
    public function name(): BuildingName
    {
        return $this->name;
    }
}

```

`src/Domain/Model/Building/Command/CheckInUser.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building\Command;

use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Model\Building\Username;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class CheckInUser implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var Username
     */
    private $name;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return Username
     */
    public function name(): Username
    {
        return $this->name;
    }
}

```

`src/Domain/Model/Building/Command/CheckOutUser.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building\Command;

use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Model\Building\Username;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class CheckOutUser implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var Username
     */
    private $name;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return Username
     */
    public function name(): Username
    {
        return $this->name;
    }
}

```

Ok, much more classes now. Each property has its own value object like `BuildingId`, `BuildingName` and `Username`. Again, that's not a requirement but it adds type safety to
the implementation and serves as documentation. Don't worry about the amount of code. Most of it can be generated using PHPStorm templates. Event Engine docs contain useful [tips](https://event-engine.io/api/immutable_state.html#3-4).
Another possibility is the already mentioned library [FPP](https://github.com/prolic/fpp).

With the value objects in place we've added a class for each command and implemented them as immutable records. Now we need a factory to instantiate a command with information
taken from Event Engine messages. `MyService\Domain\Api\Command` already contains command specific information. Let's add the factory there.

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Api;

use MyService\Domain\Model\Building\Command\AddBuilding;
use MyService\Domain\Model\Building\Command\CheckInUser;
use MyService\Domain\Model\Building\Command\CheckOutUser;
use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\JsonSchema\JsonSchema;

class Command implements EventEngineDescription
{
    const ADD_BUILDING = 'AddBuilding';
    const CHECK_IN_USER = 'CheckInUser';
    const CHECK_OUT_USER = 'CheckOutUser';

    const CLASS_MAP = [
        self::ADD_BUILDING => AddBuilding::class,
        self::CHECK_IN_USER => CheckInUser::class,
        self::CHECK_OUT_USER => CheckOutUser::class,
    ];

    public static function createFromNameAndPayload(string $commandName, array $payload)
    {
        $class = self::CLASS_MAP[$commandName] ?? false;

        if($class === false) {
            throw new \InvalidArgumentException("Unknown command name: $commandName");
        }

        //Commands use ImmutableRecordLogic and therefor have a fromArray method
        return $class::fromArray($payload);
    }

    public static function nameOf($command): string
    {
        $name = array_search(\get_class($command), self::CLASS_MAP);

        if($name === false) {
            throw new \InvalidArgumentException("Unknown command. Cannot find a name for class: " . \get_class($command));
        }

        return $name;
    }

    /* ... */
}

```

Finally, the factory can be used in the Port:

`src/System/Flavour/MyServiceMessagePort.php`

```php
/**
 * @param Message $message
 * @return mixed The custom message
 */
public function deserialize(Message $message)
{
    switch ($message->messageType()) {
        case Message::TYPE_COMMAND:
            return Command::createFromNameAndPayload($message->messageName(), $message->payload());
            break;
    }
}
```

A similar implementation is required for events and queries:

`src/Domain/Model/Building/Event/BuildingAdded.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building\Event;

use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Model\Building\BuildingName;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class BuildingAdded implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var BuildingName
     */
    private $name;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return BuildingName
     */
    public function name(): BuildingName
    {
        return $this->name;
    }
}

```

`src/Domain/Model/Building/Event/UserCheckedIn.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building\Event;

use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Model\Building\Username;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class UserCheckedIn implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var Username
     */
    private $name;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return Username
     */
    public function name(): Username
    {
        return $this->name;
    }
}

```

`src/Domain/Model/Building/Event/DoubleCheckInDetected.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building\Event;

use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Model\Building\Username;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class DoubleCheckInDetected implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var Username
     */
    private $name;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return Username
     */
    public function name(): Username
    {
        return $this->name;
    }
}

```

`src/Domain/Model/Building/Event/UserCheckedOut.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building\Event;

use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Model\Building\Username;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class UserCheckedOut implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var Username
     */
    private $name;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return Username
     */
    public function name(): Username
    {
        return $this->name;
    }
}

```

`src/Domain/Model/Building/Event/DoubleCheckOutDetected.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building\Event;

use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Model\Building\Username;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class DoubleCheckOutDetected implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var Username
     */
    private $name;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return Username
     */
    public function name(): Username
    {
        return $this->name;
    }
}

```

`src/Domain/Api/Event.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Api;


use MyService\Domain\Model\Building\Event\BuildingAdded;
use MyService\Domain\Model\Building\Event\DoubleCheckInDetected;
use MyService\Domain\Model\Building\Event\DoubleCheckOutDetected;
use MyService\Domain\Model\Building\Event\UserCheckedIn;
use MyService\Domain\Model\Building\Event\UserCheckedOut;
use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\JsonSchema\JsonSchema;

class Event implements EventEngineDescription
{
    const BUILDING_ADDED = 'BuildingAdded';
    const USER_CHECKED_IN = 'UserCheckedIn';
    const USER_CHECKED_OUT = 'UserCheckedOut';
    const DOUBLE_CHECK_IN_DETECTED = 'DoubleCheckInDetected';
    const DOUBLE_CHECK_OUT_DETECTED = 'DoubleCheckOutDetected';

    const CLASS_MAP = [
        self::BUILDING_ADDED => BuildingAdded::class,
        self::USER_CHECKED_IN => UserCheckedIn::class,
        self::USER_CHECKED_OUT => UserCheckedOut::class,
        self::DOUBLE_CHECK_IN_DETECTED => DoubleCheckInDetected::class,
        self::DOUBLE_CHECK_OUT_DETECTED => DoubleCheckOutDetected::class,
    ];

    public static function createFromNameAndPayload(string $eventName, array $payload)
    {
        $class = self::CLASS_MAP[$eventName] ?? false;

        if($class === false) {
            throw new \InvalidArgumentException("Unknown event name: $eventName");
        }

        //Events use ImmutableRecordLogic and therefor have a fromArray method
        return $class::fromArray($payload);
    }

    public static function nameOf($event): string
    {
        $name = array_search(\get_class($event), self::CLASS_MAP);

        if($name === false) {
            throw new \InvalidArgumentException("Unknown event. Cannot find a name for class: " . \get_class($event));
        }

        return $name;
    }

    /* ... */
}

```

`src/Domain/Resolver/Query/GetBuilding.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver\Query;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;
use MyService\Domain\Model\Building\BuildingId;

final class GetBuilding implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }
}

```

`src/Domain/Resolver/Query/GetBuildings.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver\Query;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class GetBuildings implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var string|null
     */
    private $buildingNameFilter;

    /**
     * @return string|null
     */
    public function buildingNameFilter(): ?string
    {
        return $this->buildingNameFilter;
    }
}

```

`src/Domain/Resolver/Query/GetUserBuildingList.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver\Query;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;
use MyService\Domain\Model\Building\Username;

final class GetUserBuildingList implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var Username
     */
    private $name;

    /**
     * @return Username
     */
    public function name(): Username
    {
        return $this->name;
    }
}

```

`src/Domain/Api/Query.php`

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\Messaging\MessageBag;
use MyService\Domain\Resolver\BuildingResolver;
use MyService\Domain\Resolver\Query\GetBuilding;
use MyService\Domain\Resolver\Query\GetBuildings;
use MyService\Domain\Resolver\Query\GetUserBuildingList;
use MyService\Domain\Resolver\UserBuildingResolver;
use MyService\System\Api\SystemQuery;

class Query implements EventEngineDescription
{
    /**
     * Default Query, used to perform health checks using messagebox endpoint
     */
    const HEALTH_CHECK = 'HealthCheck';
    const BUILDING = 'Building';
    const BUILDINGS = 'Buildings';
    const USER_BUILDING = 'UserBuilding';

    const CLASS_MAP = [
        self::BUILDING => GetBuilding::class,
        self::BUILDINGS => GetBuildings::class,
        self::USER_BUILDING => GetUserBuildingList::class,
    ];

    public static function createFromNameAndPayload(string $queryName, array $payload)
    {
        if($queryName === SystemQuery::HEALTH_CHECK) {
            return new MessageBag(
                SystemQuery::HEALTH_CHECK,
                MessageBag::TYPE_QUERY,
                []
            );
        }

        $class = self::CLASS_MAP[$queryName] ?? false;

        if($class === false) {
            throw new \InvalidArgumentException("Unknown query name: $queryName");
        }

        //Queries use ImmutableRecordLogic and therefor have a fromArray method
        return $class::fromArray($payload);
    }

    public static function nameOf($query): string
    {
        if($query instanceof MessageBag) {
            return $query->messageName();
        }

        $name = array_search(\get_class($query), self::CLASS_MAP);

        if($name === false) {
            throw new \InvalidArgumentException("Unknown query. Cannot find a name for class: " . \get_class($query));
        }

        return $name;
    }

    /* ... */
}

```

`src/System/Flavour/MyServiceMessagePort.php`

```php
/**
 * @param Message $message
 * @return mixed The custom message
 */
public function deserialize(Message $message)
{
    switch ($message->messageType()) {
        case Message::TYPE_COMMAND:
            return Command::createFromNameAndPayload($message->messageName(), $message->payload());
        case Message::TYPE_EVENT:
            return Event::createFromNameAndPayload($message->messageName(), $message->payload());
        case Message::TYPE_QUERY:
            return Query::createFromNameAndPayload($message->messageName(), $message->payload());
    }
}

```

## Serialize Payload

To convert our own message types to Event Engine messages we have to implement the `serializePayload` method:

`src/System/Flavour/MyServiceMessagePort.php`

```php
/**
 * @param mixed $customMessage
 * @return array
 */
public function serializePayload($customMessage): array
{
    if(is_array($customMessage)) {
        return $customMessage;
    }

    if(!$customMessage instanceof ImmutableRecord) {
        throw new \RuntimeException(
            "Invalid message passed to " . __METHOD__
            . ". Should be an immutable record, but got "
            . (\is_object($customMessage)? \get_class($customMessage) : \gettype($customMessage)));
    }

    return $customMessage->toArray();
}

```

## Decorate Command / Event

`decorateCommand` and `decorateEvent` are special methods called for each dispatched command and all yielded events.
The expected return type is `EventEngine\Messaging\MessageBag`. You can think of it as an envelop
for custom messages. The MessageBag can be used to add metadata information. Event Engine
adds information like aggregate id, aggregate type, aggregate version, causation id (command id) and causation name (command name)
by default. If you want to add additional metadata, just pass it to the MessageBag constructor (optional argument).

{.alert .alert-light}
Decorating a custom event with a MessageBag has the advantage that a custom message can be carried through the Event Engine layer
without serialization. Event Engine assumes a normal message and adds aggregate specific metadata like described above.
The MessageBag is then passed back to the configured flavour to call a corresponding apply function. The flavour can access
the decorated event and pass it to the function. All without serialization in between. A similar approach is used when commands
are passed to preprocessors or controllers (concepts not included in the tutorial, but you can read about them in the docs).

`src/System/Flavour/MyServiceMessagePort.php`

```php
/**
 * @param mixed $customCommand
 * @return MessageBag
 */
public function decorateCommand($customCommand): MessageBag
{
    return new MessageBag(
        Command::nameOf($customCommand),
        MessageBag::TYPE_COMMAND,
        $customCommand
    //, [] <- you could add additional metadata here
    );
}
    
/**
 * @param mixed $customEvent
 * @return MessageBag
 */
public function decorateEvent($customEvent): MessageBag
{
    return new MessageBag(
        Event::nameOf($customEvent),
        MessageBag::TYPE_EVENT,
        $customEvent
        //, [] <- you could add additional metadata here
    );
}
```

### Get Aggregate ID from Command

Event Engine has a built-in way to locate existing aggregates using a generic command handler and repository. But it needs the correct aggregateId.
Each command should contain the same aggregateId property. Remember that this information is part of an Event Engine description:

`src/Api/Aggregate`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Api;

use MyService\Domain\Model\Building;
use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;

class Aggregate implements EventEngineDescription
{
    const BUILDING = 'Building';

    /**
     * @param EventEngine $eventEngine
     */
    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::ADD_BUILDING)
            ->withNew(self::BUILDING)
            ->identifiedBy(Payload::BUILDING_ID) //<-- AggregateId payload property
            ->handle([Building::class, 'add'])
            ->recordThat(Event::BUILDING_ADDED)
            ->apply([Building::class, 'whenBuildingAdded']);

        /* ... */
    }
}

```

Each `Building` command should have a `buildingId` property. Our newly created commands have `buildingId()` methods that we could call.
An explicit implementation looks like this:

```php
/**
 * @param string $aggregateIdPayloadKey
 * @param mixed $command
 * @return string
 */
public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, $command): string
{
    if($command instanceof AddBuilding
        || $command instanceof CheckInUser
        || $command instanceof CheckOutUser) {
        return $command->buildingId()->toString();
    }

    throw new \RuntimeException("Unknown command. Cannot get aggregate id from it. Got " . get_class($command));
}
```

But we would need to remember adding a new command here each time we add a new one to the system. That's annoying and interrupts the flow.
Instead we can define an `AggregateCommand` interface that each command should implement.

`src/Domain/Model/Base/AggregateCommand.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Base;

interface AggregateCommand
{
    public function aggregateId(): string;
}

```

`src/Domain/Model/Building/Command/AddBuilding.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building\Command;

use MyService\Domain\Model\Base\AggregateCommand;
use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Model\Building\BuildingName;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class AddBuilding implements ImmutableRecord, AggregateCommand //<-- Implement new interface
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var BuildingName
     */
    private $name;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return BuildingName
     */
    public function name(): BuildingName
    {
        return $this->name;
    }

    public function aggregateId(): string //<-- new method
    {
        return $this->buildingId->toString();
    }
}

```

Do the same for `CheckInUser` and `CheckOutUser`!

Done? Great! Then we can change the Port to handle any `AggregateCommand`:

`src/System/Flavour/MyServiceMessagePort.php`

```php
/**
 * @param string $aggregateIdPayloadKey
 * @param mixed $command
 * @return string
 */
public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, $command): string
{
    if($command instanceof AggregateCommand) {
        return $command->aggregateId();
    }

    throw new \RuntimeException("Unknown command. Cannot get aggregate id from it. Got " . get_class($command));
}

```

## Call Command Preprocessor

We don't know command preprocessors yet. In short: a command preprocessor can be called before a command
is passed to an aggregate function. This can be useful in cases where you want to enrich a command with additional information or
perform advanced validation that is not covered by Json Schema. Read more about command preprocessors in the docs.

Since we don't use one in the building application, we don't really need to implement the method. Let's assume that
our future command preprocessors will be simple callables:

`src/System/Flavour/MyServiceMessagePort.php`

```php
/**
 * @param mixed $customCommand
 * @param mixed $preProcessor Custom preprocessor
 * @return mixed Custom message
 */
public function callCommandPreProcessor($customCommand, $preProcessor)
{
    if(is_callable($preProcessor)) {
        return $preProcessor($customCommand);
    }

    throw new \RuntimeException("Cannot call preprocessor. Got "
        . (is_object($preProcessor)? get_class($preProcessor) : gettype($preProcessor))
    );
}

```

## Call Command Controller

Instead of an aggregate a command can also be routed to a controller and the controller can decide if it forwards the command to an application service or return a list of other commands
that are dispatched automatically. This is very useful for migrations or in scenarios where you want to use CQRS without event sourcing and without aggregates. In our demo application we don't
use command controllers. Just like preprocessors we define them as callable.

```php
public function callCommandController($customCommand, $controller)
{
    if(is_callable($controller)) {
        return $controller($customCommand);
    }

    throw new \RuntimeException("Cannot call command controller. Got "
        . (is_object($controller)? get_class($controller) : gettype($controller))
    );
}
```


## Call Context Provider

Another concept that we don't know yet. A context provider can be used to inject context into aggregate functions.
Read more about context providers in the docs (@TODO link docs).

We're implementing a functional Flavour, so we expect a callable context provider passed to the port:

`src/System/Flavour/MyServiceMessagePort.php`

```php
/**
 * @param mixed $customCommand
 * @param mixed $contextProvider
 * @return mixed
 */
public function callContextProvider($customCommand, $contextProvider)
{
    if(is_callable($contextProvider)) {
        return $contextProvider($customCommand);
    }

    throw new \RuntimeException("Cannot call context provider. Got "
        . (is_object($contextProvider)? get_class($contextProvider) : gettype($contextProvider))
    );
}

```

## Call Resolver

Last port method asks us to call a resolver. When using the `PrototypingFlavour` query resolvers should implement
`EventEngine\Querying\Resolver`. But this is no longer possible because we want to pass our own queries to
the resolvers and not Event Engine's generic message class. Hence, we need to define a project specific resolver interface instead
along with a query marker interface:

`src/Domain/Resolver/Query.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver;

interface Query
{
    //Query marker interface
}

```

`src/Domain/Resolver/Resolver.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver;

interface Resolver
{
    /**
     * @param Query $query
     * @return mixed array or object with toArray or JsonSerializable support
     */
    public function resolve(Query $query);
}

```

Existing queries should implement the marker interface and resolvers should implement the new resolver interface:

`src/Domain/Resolver/Query/GetBuilding.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver\Query;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;
use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Resolver\Query;

final class GetBuilding implements ImmutableRecord, Query
{
    use ImmutableRecordLogic;

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }
}

```

{.alert .alert-light}
Add the interface to `GetBuildings` and `GetUserBuildingList` as well.

`src/Domain/Resolver/BuildingResolver.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\DocumentStore\Filter\AnyFilter;
use EventEngine\DocumentStore\Filter\LikeFilter;
use MyService\Domain\Api\Payload;
use MyService\Domain\Model\Building\BuildingId;
use MyService\Domain\Resolver\Query\GetBuilding;
use MyService\Domain\Resolver\Query\GetBuildings;

final class BuildingResolver implements Resolver
{
    public const COLLECTION = 'buildings';
    public const STATE = 'state';
    public const STATE_DOT = 'state.';

    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(DocumentStore $documentStore)
    {
        $this->documentStore = $documentStore;
    }

    /**
     * @param Query $query
     * @return array
     */
    public function resolve(Query $query): array
    {
        switch (true) {
            case $query instanceof GetBuilding:
                return $this->resolveBuilding($query->buildingId());
            case $query instanceof GetBuildings:
                return $this->resolveBuildings($query->name());
            default:
                throw new \RuntimeException("Query not supported. Got " . VariableType::determine($query));
        }
    }

    private function resolveBuilding(BuildingId $buildingId): array
    {
        $buildingDoc = $this->documentStore->getDoc(self::COLLECTION, $buildingId->toString());

        if(!$buildingDoc) {
            throw new \RuntimeException("Building not found", 404);
        }

        return $buildingDoc[self::STATE];
    }

    private function resolveBuildings(string $nameFilter = null): array
    {
        $filter = $nameFilter?
            new LikeFilter(self::STATE_DOT . Payload::NAME, "%$nameFilter%")
            : new AnyFilter();

        $cursor = $this->documentStore->findDocs(self::COLLECTION, $filter);

        $buildings = [];

        foreach ($cursor as $doc) {
            $buildings[] = $doc[self::STATE];
        }

        return $buildings;
    }
}

```

`src/Domain/Resolver/UserBuildingResolver.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\Util\VariableType;
use MyService\Domain\Resolver\Query\GetUserBuildingList;

final class UserBuildingResolver implements Resolver
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var string
     */
    private $userBuildingCollection;

    /**
     * @var string
     */
    private $buildingCollection;

    public function __construct(DocumentStore $documentStore, string $userBuildingCol, string $buildingCol)
    {
        $this->documentStore = $documentStore;
        $this->userBuildingCollection = $userBuildingCol;
        $this->buildingCollection = $buildingCol;
    }

    public function resolve(Query $query): array
    {
        if(!$query instanceof GetUserBuildingList) {
            throw new \RuntimeException("Invalid query. Can only handle " . GetUserBuildingList::class 
                . '. But got ' . VariableType::determine($query));
        }
        
        $userBuilding = $this->documentStore->getDoc(
            $this->userBuildingCollection,
            $query->name()->toString()
        );

        if(!$userBuilding) {
            return [
                'user' => $query->name()->toString(),
                'building' => null
            ];
        }

        $building = $this->documentStore->getDoc(
            $this->buildingCollection,
            $userBuilding['buildingId']
        );

        if(!$building) {
            return [
                'user' => $query->name()->toString(),
                'building' => null
            ];
        }

        return [
            'user' => $query->name()->toString(),
            'building' => $building['state'],
        ];
    }
}

```

`src/System/Flavour/MyServiceMessagePort.php`

```php
public function callResolver($customQuery, $resolver)
{
    if(! $resolver instanceof Resolver) {
        throw new \RuntimeException("Unsupported resolver. Got " . VariableType::determine($resolver));
    }

    return $resolver->resolve($customQuery);
}
```

{.alert .alert-success}
All methods of the `Functional\Port` are implemented. Good job! But we're not done yet.

## Switching The Flavour

The flavour is configured in `src/System/SystemServices.php` and can be changed there:

```php
<?php
declare(strict_types=1);

namespace MyService\System;

use EventEngine\Data\ImmutableRecordDataConverter;
use EventEngine\Logger\LogEngine;
use EventEngine\Logger\SimpleMessageEngine;
use EventEngine\Messaging\Message;
use EventEngine\Prooph\V7\EventStore\GenericProophEvent;
use EventEngine\Runtime\Flavour;
use EventEngine\Runtime\FunctionalFlavour;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use MyService\System\Api\EventEngineConfig;
use MyService\System\Api\SystemQuery;
use MyService\System\Api\SystemType;
use MyService\System\Flavour\MyServiceMessagePort;
use Prooph\Common\Messaging\NoOpMessageConverter;
use Prooph\ServiceBus\Message\HumusAmqp\AmqpMessageProducer;
use Psr\Log\LoggerInterface;

trait SystemServices
{
    /* ... */

    public function flavour(): Flavour
    {
        return $this->makeSingleton(Flavour::class, function () {
            return new FunctionalFlavour(new MyServiceMessagePort(), new ImmutableRecordDataConverter());
        });
    }

    /* ... */
}

```

{.alert .alert-success}
Everything set up 🎉. Refactoring can start!

## Refactoring

Switching the Flavour means all generic messages have to be replaced with their concrete implementations.

{.alert .alert-info}
In a larger project we might want to switch to another Flavour step by step. In that case a "Proxy Flavour" is required that
uses **PrototypingFlavour** and **FunctionalFlavour** (or OopFlavour) internally together with a mapping of already migrated parts of
the application.

`src/Domain/Model/Building.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model;

use MyService\Domain\Model\Building\Command\AddBuilding;
use MyService\Domain\Model\Building\Command\CheckInUser;
use MyService\Domain\Model\Building\Command\CheckOutUser;
use MyService\Domain\Model\Building\Event\BuildingAdded;
use MyService\Domain\Model\Building\Event\DoubleCheckInDetected;
use MyService\Domain\Model\Building\Event\DoubleCheckOutDetected;
use MyService\Domain\Model\Building\Event\UserCheckedIn;
use MyService\Domain\Model\Building\Event\UserCheckedOut;

final class Building
{
    public static function add(AddBuilding $addBuilding): \Generator
    {
        yield BuildingAdded::fromArray($addBuilding->toArray());
    }

    public static function whenBuildingAdded(BuildingAdded $buildingAdded): Building\State
    {
        return Building\State::fromArray($buildingAdded->toArray());
    }

    public static function checkInUser(Building\State $state, CheckInUser $checkInUser): \Generator
    {
        if($state->isUserCheckedIn($checkInUser->name())) {
            yield DoubleCheckInDetected::fromArray($checkInUser->toArray());
            return;
        }

        yield UserCheckedIn::fromArray($checkInUser->toArray());
    }

    public static function whenUserCheckedIn(Building\State $state, UserCheckedIn $userCheckedIn): Building\State
    {
        return $state->withCheckedInUser($userCheckedIn->name());
    }

    public static function whenDoubleCheckInDetected(Building\State $state, DoubleCheckInDetected $event): Building\State
    {
        //No state change required, simply return current state
        return $state;
    }

    public static function checkOutUser(Building\State $state, CheckOutUser $checkOutUser): \Generator
    {
        if(!$state->isUserCheckedIn($checkOutUser->name())) {
            yield DoubleCheckOutDetected::fromArray($checkOutUser->toArray());
            return;
        }

        yield UserCheckedOut::fromArray($checkOutUser->toArray());
    }

    public static function whenUserCheckedOut(Building\State $state, UserCheckedOut $userCheckedOut): Building\State
    {
        return $state->withCheckedOutUser($userCheckedOut->name());
    }

    public static function whenDoubleCheckOutDetected(Building\State $state, DoubleCheckOutDetected $event): Building\State
    {
        return $state;
    }
}

```

`Building\State` should make use of the new data types as well:

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class State implements ImmutableRecord
{
    use ImmutableRecordLogic;
    
    public const BUILDING_ID = 'buildingId';
    public const NAME = 'name';

    /**
     * @var BuildingId
     */
    private $buildingId;

    /**
     * @var BuildingName
     */
    private $name;

    /**
     * @var Username[]
     */
    private $users = [];

    private static function arrayPropItemTypeMap(): array
    {
        return ['users' => Username::class];
    }

    /**
     * Called in constructor after setting props but before not null assertion
     *
     * Override to set default props after construction
     */
    private function init(): void
    {
        //Build internal users map
        $users = [];
        foreach ($this->users as $username) {
            $users[$username->toString()] = null;
        }
        $this->users = $users;
    }

    /**
     * @return BuildingId
     */
    public function buildingId(): BuildingId
    {
        return $this->buildingId;
    }

    /**
     * @return BuildingName
     */
    public function name(): BuildingName
    {
        return $this->name;
    }

    /**
     * @return Username[]
     */
    public function users(): array
    {
        return array_map(function (string $username) {
            return Username::fromString($username);
        }, array_keys($this->users));
    }

    public function withCheckedInUser(Username $username): State
    {
        $copy = clone $this;
        $copy->users[$username->toString()] = null;
        return $copy;
    }

    public function withCheckedOutUser(Username $username): State
    {
        if(!$this->isUserCheckedIn($username)) {
            return $this;
        }

        $copy = clone $this;
        unset($copy->users[$username->toString()]);
        return $copy;
    }

    public function isUserCheckedIn(Username $username): bool
    {
        return array_key_exists($username->toString(), $this->users);
    }
}

```

`UserBuildingList` projector now needs to implement the interface `EventEngine\Projecting\CustomEventProjector`
instead of `EventEngine\Projecting\Projector`:

`src/Domain/Projector/UserBuildingList.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Projector;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\Projecting\CustomEventProjector;
use MyService\Domain\Api\Payload;
use EventEngine\Projecting\AggregateProjector;
use MyService\Domain\Model\Building\Event\UserCheckedIn;
use MyService\Domain\Model\Building\Event\UserCheckedOut;

final class UserBuildingList implements CustomEventProjector
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(DocumentStore $documentStore)
    {
        $this->documentStore = $documentStore;
    }

    public function prepareForRun(string $projectionVersion, string $projectionName): void
    {
        if(!$this->documentStore->hasCollection(self::generateCollectionName($projectionVersion, $projectionName))) {
            $this->documentStore->addCollection(
                self::generateCollectionName($projectionVersion, $projectionName)
            /* Note: we could pass index configuration as a second argument, see docs for details */
            );
        }
    }

    public function handle(string $appVersion, string $projectionName, $event): void
    {
        $collection = $this->generateCollectionName($appVersion, $projectionName);

        switch (true) {
            case $event instanceof UserCheckedIn:
                $this->documentStore->addDoc(
                    $collection,
                    $event->name()->toString(), //Use username as doc id
                    [Payload::BUILDING_ID => $event->buildingId()->toString()]
                );
                break;
            case $event instanceof UserCheckedOut:
                $this->documentStore->deleteDoc($collection, $event->name()->toString());
                break;
            default:
                //Ignore unknown events
        }
    }
    
    public function deleteReadModel(string $appVersion, string $projectionName): void
    {
        $this->documentStore->dropCollection(self::generateCollectionName($appVersion, $projectionName));
    }

    public static function generateCollectionName(string $projectionVersion, string $projectionName): string
    {
        //We can use the naming strategy of the aggregate projector for our custom projection
        return AggregateProjector::generateCollectionName($projectionVersion, $projectionName);
    }
}

```

The `UiExchange` event listener included in the skeleton application needs to be aligned, too.
First, the corresponding interface should handle any type of event:

`src/System/UiExchange.php`

```php
<?php
declare(strict_types=1);

namespace MyService\System;

interface UiExchange
{
    public function __invoke($event): void;
}

```

Second, an implementation of the interface should handle our event objects. The skeleton simply uses an anonymous class
to implement the interface. It can be found and changed in `src/System/SystemServices.php`.

{.alert .alert-warning}
It's an anonymous class because the UiExchange is only included in the skeleton to demonstrate how events can be pushed
to a message queue and consumed by a UI. The implementation is not meant to be used in production. You can get some inspiration
from it, but please work out a production grade solution yourself.

```php
<?php
declare(strict_types=1);

namespace MyService\System;

use EventEngine\Data\ImmutableRecordDataConverter;
use EventEngine\Logger\LogEngine;
use EventEngine\Logger\SimpleMessageEngine;
use EventEngine\Messaging\MessageBag;
use EventEngine\Prooph\V7\EventStore\GenericProophEvent;
use EventEngine\Runtime\Flavour;
use EventEngine\Runtime\FunctionalFlavour;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use MyService\Domain\Api\Event;
use MyService\System\Api\EventEngineConfig;
use MyService\System\Api\SystemQuery;
use MyService\System\Api\SystemType;
use MyService\System\Flavour\MyServiceMessagePort;
use Prooph\Common\Messaging\NoOpMessageConverter;
use Prooph\ServiceBus\Message\HumusAmqp\AmqpMessageProducer;
use Psr\Log\LoggerInterface;

trait SystemServices
{
    /* ... */

    public function uiExchange(): UiExchange
    {
        return $this->makeSingleton(UiExchange::class, function () {
            $this->assertMandatoryConfigExists('rabbit.connection');

            $connection = new \Humus\Amqp\Driver\AmqpExtension\Connection(
                $this->config()->arrayValue('rabbit.connection')
            );

            $connection->connect();

            $channel = $connection->newChannel();

            $exchange = $channel->newExchange();

            $exchange->setName($this->config()->stringValue('rabbit.ui_exchange', 'ui-exchange'));

            $exchange->setType('fanout');

            $humusProducer = new \Humus\Amqp\JsonProducer($exchange);

            $messageProducer = new \Prooph\ServiceBus\Message\HumusAmqp\AmqpMessageProducer(
                $humusProducer,
                new NoOpMessageConverter()
            );

            $flavour = $this->flavour();

            return new class($messageProducer, $flavour) implements UiExchange {
                private $producer;
                private $flavour;
                public function __construct(AmqpMessageProducer $messageProducer, Flavour $flavour)
                {
                    $this->producer = $messageProducer;
                    $this->flavour = $flavour;
                }

                public function __invoke($event): void
                {
                    $messageBag = new MessageBag(
                        Event::nameOf($event),
                        MessageBag::TYPE_EVENT,
                        $event
                    );

                    $event = $this->flavour->prepareNetworkTransmission($messageBag);

                    $this->producer->__invoke(GenericProophEvent::fromArray([
                        'uuid' => $event->uuid()->toString(),
                        'message_name' => $event->messageName(),
                        'payload' => $event->payload(),
                        'metadata' => $event->metadata(),
                        'created_at' => $event->createdAt()
                    ]));
                }
            };
        });
    }
}

```

That's it! You can use [Cockpit](https://localhost:4444) to test the changes.

Or wait! We did not run the tests!

```bash
docker-compose run php php vendor/bin/phpunit
```

Doesn't look good, right? Let's fix it!

`TestCaseAbstract::assertRecordedEvent()` method need to be aligned:

```php
<?php
declare(strict_types=1);

namespace MyServiceTest;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventEngine;
use EventEngine\EventStore\EventStore;
use EventEngine\Logger\DevNull;
use EventEngine\Logger\SimpleMessageEngine;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageProducer;
use EventEngine\Persistence\InMemoryConnection;
use EventEngine\Prooph\V7\EventStore\InMemoryMultiModelStore;
use EventEngine\Util\MessageTuple;
use MyService\Domain\Api\Event;
use MyService\ServiceFactory;
use MyServiceTest\Mock\EventQueueMock;
use MyServiceTest\Mock\MockContainer;
use PHPUnit\Framework\TestCase;

class TestCaseAbstract extends TestCase
{
    /* ... */

    protected function assertRecordedEvent(string $eventName, array $payload, array $events, $assertNotRecorded = false): void
    {
        $isRecorded = false;

        foreach ($events as $evt) {
            if($evt === null) {
                continue;
            }

            //Convert domain events to raw data
            $evtName = Event::nameOf($evt);
            $evtPayload = $evt->toArray();

            if($eventName === $evtName) {
                $isRecorded = true;

                if(!$assertNotRecorded) {
                    $this->assertEquals($payload, $evtPayload, "Payload of recorded event $evtName does not match with expected payload.");
                }
            }
        }

        if($assertNotRecorded) {
            $this->assertFalse($isRecorded, "Event $eventName is recorded");
        } else {
            $this->assertTrue($isRecorded, "Event $eventName is not recorded");
        }
    }
}

```

`NotifySecurityTest` contains a mocked `UiExchange`. We changed the interface earlier, but did not change the mock.
The test itself needs minor adjustments, too.

`tests/Integration/NotifySecurityTest.php`

```php
<?php
declare(strict_types=1);

namespace MyServiceTest\Integration;

use MyService\Domain\Api\Command;
use MyService\Domain\Api\Event;
use MyService\Domain\Api\Payload;
use MyService\Domain\Model\Building\Event\DoubleCheckInDetected;
use MyService\System\UiExchange;
use MyServiceTest\IntegrationTestCase;

final class NotifySecurityTest extends IntegrationTestCase
{
    const BUILDING_ID = '7c5f0c8a-54f2-4969-9596-b5bddc1e9421';
    const BUILDING_NAME = 'Acme Headquarters';
    const USERNAME = 'John';

    private $uiExchange;

    protected function setUp(): void
    {
        parent::setUp();

        //Mock UiExchange with an anonymous class that keeps track of the last received message
        $this->uiExchange = new class implements UiExchange {

            private $lastReceivedMessage;

            public function __invoke($event): void
            {
                $this->lastReceivedMessage = $event;
            }

            public function lastReceivedMessage()
            {
                return $this->lastReceivedMessage;
            }
        };

        // Mocks are passed to EE set up method
        // The IntegrationTestCase loads all EE descriptions
        // and uses the configured Flavour (PrototypingFlavour in our case)
        // to set up Event Engine
        $this->setUpEventEngine([
            UiExchange::class => $this->uiExchange,
        ]);

        /**
         * We can pass fixtures to the database set up:
         *
         * Stream to events map:
         *
         * [streamName => Event[]]
         *
         * Collection to documents map:
         *
         * [collectionName => [docId => doc]]
         */
        $this->setUpDatabase([
            // We use the default write model stream in the buildings app
            // and add a history for the test building
            // aggregate state is derived from history automatically during set up
            $this->eventEngine->writeModelStreamName() => [
                $this->makeEvent(Event::BUILDING_ADDED, [
                    Payload::BUILDING_ID => self::BUILDING_ID,
                    Payload::NAME => self::BUILDING_NAME
                ]),
                $this->makeEvent(Event::USER_CHECKED_IN, [
                    Payload::BUILDING_ID => self::BUILDING_ID,
                    Payload::NAME => self::USERNAME
                ]),
            ]
        ]);
    }

    /**
     * @test
     */
    public function it_detects_double_check_in_and_notifies_security()
    {
        //Try to check in John twice
        $checkInJohn = $this->makeCommand(Command::CHECK_IN_USER, [
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME
        ]);

        $this->eventEngine->dispatch($checkInJohn);

        //The IntegrationTestCase sets up an in-memory queue (accessible by $this->eventQueue)
        //You can inspect published events or simply process the queue
        //so that event listeners get invoked like our mocked UiExchange listener
        $this->processEventQueueWhileNotEmpty();

        //Now $this->lastPublishedEvent should point to the event received by UiExchange mock
        $this->assertNotNull($this->uiExchange->lastReceivedMessage());

        $this->assertInstanceOf(DoubleCheckInDetected::class, $this->uiExchange->lastReceivedMessage());

        $this->assertEquals([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME
        ], $this->uiExchange->lastReceivedMessage()->toArray());
    }
}

```

Next on the list is `BuildingTest`.
`tests/Domain/Model/BuildingTest.php`

```php
<?php
declare(strict_types=1);

namespace MyServiceTest\Domain\Model;

use MyService\Domain\Api\Command;
use MyService\Domain\Api\Event;
use MyService\Domain\Api\Payload;
use MyServiceTest\UnitTestCase;
use Ramsey\Uuid\Uuid;
use MyService\Domain\Model\Building;

final class BuildingTest extends UnitTestCase
{
    private $buildingId;
    private $buildingName;
    private $username;

    protected function setUp(): void
    {
        $this->buildingId = Uuid::uuid4()->toString();
        $this->buildingName = 'Acme Headquarters';
        $this->username = 'John';

        parent::setUp();
    }

    /**
     * @test
     */
    public function it_checks_in_a_user()
    {
        //Prepare expected aggregate state
        $state = Building\State::fromArray([
            Building\State::BUILDING_ID => $this->buildingId,
            Building\State::NAME => $this->buildingName
        ]);

        //Use test helper UnitTestCase::makeCommand() to construct command
        $command = Building\Command\CheckInUser::fromArray([
            Building\State::BUILDING_ID => $this->buildingId,
            Building\State::NAME => $this->username,
        ]);

        //Aggregate functions yield events, we have to collect them with a test helper
        $events = $this->collectNewEvents(
            Building::checkInUser($state, $command)
        );

        //Another test helper to assert that list of recorded events contains given event
        $this->assertRecordedEvent(Event::USER_CHECKED_IN, [
            Payload::BUILDING_ID => $this->buildingId,
            Payload::NAME => $this->username
        ], $events);
    }
}

```

And last adjustments in `UserBuildingListTest::it_manages_list_of_users_with_building_reference()`.
The projector expects dedicated event objects now.

`tests/Domain/Projector/UserBuildingListTest.php`

```php
<?php
declare(strict_types=1);

namespace MyServiceTest\Domain\Projector;

use EventEngine\DocumentStore\Filter\AnyFilter;
use MyService\Domain\Api\Event;
use MyService\Domain\Api\Payload;
use MyService\Domain\Api\Projection;
use MyService\Domain\Model\Building\Event\UserCheckedIn;
use MyService\Domain\Model\Building\Event\UserCheckedOut;
use MyService\Domain\Projector\UserBuildingList;
use MyServiceTest\UnitTestCase;

final class UserBuildingListTest extends UnitTestCase
{
    const PRJ_VERSION = '0.1.0';
    const BUILDING_ID = '7c5f0c8a-54f2-4969-9596-b5bddc1e9421';
    const USERNAME1 = 'John';
    const USERNAME2 = 'Jane';

    /**
     * @var UserBuildingList
     */
    private $projector;

    protected function setUp(): void
    {
        parent::setUp();

        //DocumentStore is set up in parent::setUp()
        $this->projector = new UserBuildingList($this->documentStore);
        $this->projector->prepareForRun(
            self::PRJ_VERSION,
            Projection::USER_BUILDING_LIST
        );
    }

    /**
     * @test
     */
    public function it_manages_list_of_users_with_building_reference()
    {
        $collection = UserBuildingList::generateCollectionName(
            self::PRJ_VERSION,
            Projection::USER_BUILDING_LIST
        );

        $johnCheckedIn = UserCheckedIn::fromArray([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME1
        ]);

        $this->projector->handle(
            self::PRJ_VERSION,
            Projection::USER_BUILDING_LIST,
            $johnCheckedIn
        );

        $users = iterator_to_array($this->documentStore->findDocs(
            $collection,
            new AnyFilter()
        ));

        $this->assertEquals($users, [
            'John' => ['buildingId' => self::BUILDING_ID]
        ]);

        $janeCheckedIn = UserCheckedIn::fromArray([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME2
        ]);

        $this->projector->handle(
            self::PRJ_VERSION,
            Projection::USER_BUILDING_LIST,
            $janeCheckedIn
        );

        $users = iterator_to_array($this->documentStore->findDocs(
            $collection,
            new AnyFilter()
        ));

        $this->assertEquals($users, [
            'John' => ['buildingId' => self::BUILDING_ID],
            'Jane' => ['buildingId' => self::BUILDING_ID],
        ]);

        $johnCheckedOut = UserCheckedOut::fromArray([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME1
        ]);

        $this->projector->handle(
            self::PRJ_VERSION,
            Projection::USER_BUILDING_LIST,
            $johnCheckedOut
        );

        $users = iterator_to_array($this->documentStore->findDocs(
            $collection,
            new AnyFilter()
        ));

        $this->assertEquals($users, [
            'Jane' => ['buildingId' => self::BUILDING_ID],
        ]);
    }
}

```

{.alert .alert-success}
**Tests are green again. Refactoring finished successfully. Was it worth the effort?**
Switching the Flavour is quite some work to do, isn't it? Depending on the amount of already written code and tests this task can take some days and you need to make sure
that you don't break existing functionality. On the other hand you get a fully decoupled domain model.
Of course, it's also possible to use another Flavour right from the beginning. But keep in mind, that the PrototypingFlavour saves a lot of time in the early days
of a project. You don't know if the first app version really meets business and user needs. You can only try and experiment. The faster you have a working
app, the faster you can get feedback from users and stakeholders. A lean implementation and simple infrastructure gives you a lot of flexibility at the beginning.
Starting with a MVP is not a new concept. Event Engine just gives you a nice tool to build one and
reuse parts of your experiments in later project phases. Also using CQRS / ES from day one gives you full advantage of a reactive system.

Still curious to see what the **OopFlavour** can do? The last bonus part sheds light on it.


