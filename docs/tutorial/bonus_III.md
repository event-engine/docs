# Bonus III - Functional Flavour

Event Engine has a nice feature called **Flavours**. A Flavour lets you customize the way Event Engine interacts with
your code. Throughout the tutorial we worked with the **PrototypingFlavour**, which is the default.

As the name suggests, the PrototypingFlavour is optimized for rapid development. For example instead of defining classes
for each type of message, Event Engine passes its default `Message` implementation to aggregate functions, process manager,
finder and projectors. You don't need to care about serialization and mapping.

{.alert .alert-info}
If you want to try out new ideas, PrototypingFlavour is your best friend.
Following Domain-Driven Design best practices **Continuous Discovery** and **Agile Development** are key drivers for successful
projects. This requires experimentation and with the PrototypingFlavour it's easier than ever.

## Harden The Domain Model

Experimentation is great, but at some point you'll be satisfied with the domain model and want to turn it into a clean and
robust implementation. That's very important for long-lived applications. Fortunately, Event Engine offers two additional Flavours.
One is called the **FunctionalFlavour** and the other one **OopFlavour**. Finally, you can implement your own `Prooph\EventEngine\Runtime\Flavour`
to turn Event Engine into your very own CQRS / ES framework.

First let's look at the **FunctionalFlavour**. It's similar to what we did so fare, except that explicit message types are used instead of
generic Event Engine messages.

## Functional Port

The FunctionalFlavour requires an implementation of `Prooph\EventEngine\Runtime\Functional\Port`. Here you have to define custom mapping and serialization
logic for message types. Create a new class `AppMessagePort` in `src/Infrastructure/Flavour`:

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Flavour;

use Prooph\EventEngine\Messaging\Message;
use Prooph\EventEngine\Messaging\MessageBag;
use Prooph\EventEngine\Runtime\Functional\Port;

final class AppMessagePort implements Port
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
     * @return mixed Custom message
     */
    public function callCommandPreProcessor($customCommand, $preProcessor)
    {
        // TODO: Implement callCommandPreProcessor() method.
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
That said, our messages become `ImmutableRecord`s and use the build-in serialization technique provided by `ImmutableRecordLogic`.

{.alert .alert-warning}
The fact that messages are still coupled with the framework is not important here. It's our decision as developers to do it, but nothing required by Event Engine.
We could also write our own serialization mechanism or use a third-party tool like [FPP](https://github.com/prolic/fpp){: class="alert-link"}.

Let's create some types and messages first:

`src/Model/Building/BuildingId.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building;

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

`src/Model/Building/BuildingName.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building;

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

`src/Model/Building/Username.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building;

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

`src/Model/Building/Command/AddBuilding.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building\Command;

use App\Model\Building\BuildingId;
use App\Model\Building\BuildingName;
use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

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

`src/Model/Building/Command/CheckInUser.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building\Command;

use App\Model\Building\BuildingId;
use App\Model\Building\Username;
use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

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

`src/Model/Building/Command/CheckOutUser.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building\Command;

use App\Model\Building\BuildingId;
use App\Model\Building\Username;
use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

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
the implementation and serves as documentation. Don't worry about the amount of code. Most of it can be generated using PHPStorm templates. Event Engine docs contain useful tips.
Another possibility is the already mentioned library [FPP](https://github.com/prolic/fpp).

With the value objects in place we've added a class for each command and implemented them as immutable records. Now we need a factory to instantiate a command with information
taken from Event Engine messages. `App\Api\Command` already contains command specific information. Let's add the factory there.

```php
<?php
declare(strict_types=1);

namespace App\Api;

use App\Model\Building\Command\AddBuilding;
use App\Model\Building\Command\CheckInUser;
use App\Model\Building\Command\CheckOutUser;
use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;
use Prooph\EventEngine\JsonSchema\JsonSchema;

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

`src/Infrastructure/Flavour/AppMssagePort.php`

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

`src/Model/Building/Event/BuildingAdded.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building\Event;

use App\Model\Building\BuildingId;
use App\Model\Building\BuildingName;
use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

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

`src/Model/Building/Event/UserCheckedIn.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building\Event;

use App\Model\Building\BuildingId;
use App\Model\Building\Username;
use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

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

`src/Model/Building/Event/DoubleCheckInDetected.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building\Event;

use App\Model\Building\BuildingId;
use App\Model\Building\Username;
use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

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

`src/Model/Building/Event/UserCheckedOut.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building\Event;

use App\Model\Building\BuildingId;
use App\Model\Building\Username;
use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

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

`src/Model/Building/Event/DoubleCheckOutDetected.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building\Event;

use App\Model\Building\BuildingId;
use App\Model\Building\Username;
use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

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

`src/Api/Event.php`

```php
<?php
declare(strict_types=1);

namespace App\Api;


use App\Model\Building\Event\BuildingAdded;
use App\Model\Building\Event\DoubleCheckInDetected;
use App\Model\Building\Event\DoubleCheckOutDetected;
use App\Model\Building\Event\UserCheckedIn;
use App\Model\Building\Event\UserCheckedOut;
use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;
use Prooph\EventEngine\JsonSchema\JsonSchema;

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

        //Commands use ImmutableRecordLogic and therefor have a fromArray method
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

`src/Infrastructure/Finder/Query/GetBuilding.php`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder\Query;

use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

final class GetBuilding implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var string
     */
    private $buildingId;

    /**
     * @return string
     */
    public function buildingId(): string
    {
        return $this->buildingId;
    }
}

```

`src/Infrastructure/Finder/Query/GetBuildings.php`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder\Query;

use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

final class GetBuildings implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var string|null
     */
    private $name;

    /**
     * @return null|string
     */
    public function name(): ?string
    {
        return $this->name;
    }
}

```

`src/Infrastructure/Finder/Query/GetUserBuildingList.php`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder\Query;

use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

final class GetUserBuildingList implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var string
     */
    private $name;

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }
}

```

`src/Api/Query.php`

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Infrastructure\Finder\BuildingFinder;
use App\Infrastructure\Finder\Query\GetBuilding;
use App\Infrastructure\Finder\Query\GetBuildings;
use App\Infrastructure\Finder\Query\GetUserBuildingList;
use App\Infrastructure\Finder\UserBuildingFinder;
use App\Infrastructure\System\HealthCheckResolver;
use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;
use Prooph\EventEngine\JsonSchema\JsonSchema;

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
        if($queryName === self::HEALTH_CHECK) {
            return new MessageBag(
                self::HEALTH_CHECK,
                MessageBag::TYPE_QUERY,
                []
            );
        }

        $class = self::CLASS_MAP[$queryName] ?? false;

        if($class === false) {
            throw new \InvalidArgumentException("Unknown query name: $queryName");
        }

        //Commands use ImmutableRecordLogic and therefor have a fromArray method
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

`src/Infrastructure/Flavour/AppMssagePort.php`

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

`src/Infrastructure/Flavour/AppMssagePort.php`

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

## Decorate Event

`decorateEvent` is a special method called for each event yielded by an aggregate function.
The expected return type is `Prooph\EventEngine\Messaging\MessageBag`. You can think of it as an envelop
for custom messages. The MessageBag can be used to add metadata information to events. Event Engine
adds information like aggregate id, aggregate type, aggregate version, causation id (command id) and causation name (command name)
by default. If you want to add additional metadata, just pass it to the MessageBag constructor (optional argument).

{.alert .alert-light}
Decorating a custom event with a MessageBas has the advantage that a custom message can be carried through the Event Engine layer
without serialization. Event Engine assumes a normal message and adds aggregate specific metadata like described above.
The MessageBag is then passed back to the configured flavour to call a corresponding apply function. The flavour can access
the decorated event and pass it to the function. All without serialization in between.

`src/Infrastructure/Flavour/AppMssagePort.php`

```php
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

namespace App\Api;

use App\Model\Building;
use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;

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

Each `Building` command should have a `builidngId` property. Our newly created commands have `buildingId()` methods that we could call.
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

`src/Model/Base/AggregateCommand.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Base;

interface AggregateCommand
{
    public function aggregateId(): string;
}

```

`src/Model/Building/Command/AddBuilding.php`

```php
<?php
declare(strict_types=1);

namespace App\Model\Building\Command;

use App\Model\Base\AggregateCommand;
use App\Model\Building\BuildingId;
use App\Model\Building\BuildingName;
use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

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

`src/Infrastructure/Flavour/AppMssagePort.php`

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

`src/Infrastructure/Flavour/AppMssagePort.php`

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

## Call Context Provider

Another concept that we don't know yet. A context provider can be used to inject context into aggregate functions.
Again, read more about context providers in the docs.

We're implementing a functional Flavour, so we expect a callable context provider passed to the port:

`src/Infrastructure/Flavour/AppMssagePort.php`

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

All methods of the `Functional\Port` are implemented. Good job! But we're not done yet.

## Switching The Flavour

Event Engine is looking for a Flavour in the app container passed to `EventEngine::initialize()`.
With a new `flavour` method in the `ServiceFactory` we can provide one.

`src/Service/ServiceFactory.php`

```php
<?php
namespace App\Service;

use App\Infrastructure\Flavour\AppMessagePort;
use Prooph\EventEngine\Runtime\Flavour;
use Prooph\EventEngine\Runtime\FunctionalFlavour;
/* ... */

final class ServiceFactory
{
    use ServiceRegistry;

    /**
     * @var ArrayReader
     */
    private $config;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(array $appConfig)
    {
        $this->config = new ArrayReader($appConfig);
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    //Flavour
    public function flavour(): Flavour
    {
        return $this->makeSingleton(Flavour::class, function () {
            return new FunctionalFlavour(
                new AppMessagePort()
                /*
                 * Additionally, inject a custom Prooph\EventEngine\Data\DataConverter
                 * if aggregate state does not implement ImmutableRecord!
                 */
            );
        });
    }

    /* ... */
}

```

Additionally, a service alias is required because Event Engine uses `EventEngine::SERVICE_ID_FLAVOUR` for look ups.
Such an alias can be defined in `config/container.php` (using the Event Engine Skeleton app):

```php
<?php
declare(strict_types = 1);

$config = include 'config.php';

$serviceFactory = new \App\Service\ServiceFactory($config);

//@TODO use cached serviceFactoryMap for production
$container = new \Prooph\EventEngine\Container\ReflectionBasedContainer(
    $serviceFactory,
    [
        \Prooph\EventEngine\EventEngine::SERVICE_ID_EVENT_STORE => \Prooph\EventStore\EventStore::class,
        \Prooph\EventEngine\EventEngine::SERVICE_ID_PROJECTION_MANAGER => \Prooph\EventStore\Projection\ProjectionManager::class,
        \Prooph\EventEngine\EventEngine::SERVICE_ID_COMMAND_BUS => \App\Infrastructure\ServiceBus\CommandBus::class,
        \Prooph\EventEngine\EventEngine::SERVICE_ID_EVENT_BUS => \App\Infrastructure\ServiceBus\EventBus::class,
        \Prooph\EventEngine\EventEngine::SERVICE_ID_QUERY_BUS => \App\Infrastructure\ServiceBus\QueryBus::class,
        \Prooph\EventEngine\EventEngine::SERVICE_ID_DOCUMENT_STORE => \Prooph\EventEngine\Persistence\DocumentStore::class,
        //Flavour alias
        \Prooph\EventEngine\EventEngine::SERVICE_ID_FLAVOUR => \Prooph\EventEngine\Runtime\Flavour::class,
    ]
);

$serviceFactory->setContainer($container);

return $container;

```

Everything set up 🎉. Refactoring can start!

## Refactoring

Switching the Flavour means all generic messages have to be replaced with their concrete implementations.

{.alert .alert-info}
In a larger project we might want to switch to another Flavour step by step. In that case a "Proxy Flavour" is required that
uses **PrototypingFlavour** and **FunctionalFlavour** (or OopFlavour) internally together with a mapping of already migrated parts of
the application.

`src/Model/Building.php`

```php
<?php
declare(strict_types=1);

namespace App\Model;

use App\Model\Building\Command\AddBuilding;
use App\Model\Building\Command\CheckInUser;
use App\Model\Building\Command\CheckOutUser;
use App\Model\Building\Event\BuildingAdded;
use App\Model\Building\Event\DoubleCheckInDetected;
use App\Model\Building\Event\DoubleCheckOutDetected;
use App\Model\Building\Event\UserCheckedIn;
use App\Model\Building\Event\UserCheckedOut;

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
        //No state change required, simply return current state
        return $state;
    }
}


```

`Building\State` should make use of the new data types as well:

```php
<?php
declare(strict_types=1);

namespace App\Model\Building;

use Prooph\EventEngine\Data\ImmutableRecord;
use Prooph\EventEngine\Data\ImmutableRecordLogic;

final class State implements ImmutableRecord
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

`UserBuildingList` projector now needs to implement the interface `Prooph\EventEngine\Projecting\CustomEventProjector`
instead of `Prooph\EventEngine\Projecting\Projector`:

`src/Infrastructure/Projector/UserBuildingList.php`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Projector;

use App\Api\Event;
use App\Api\Payload;
use App\Model\Building\Event\UserCheckedIn;
use App\Model\Building\Event\UserCheckedOut;
use Prooph\EventEngine\Persistence\DocumentStore;
use Prooph\EventEngine\Projecting\AggregateProjector;
use Prooph\EventEngine\Projecting\CustomEventProjector;

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

    public function prepareForRun(string $appVersion, string $projectionName): void
    {
        if(!$this->documentStore->hasCollection($this->generateCollectionName($appVersion, $projectionName))) {
            $this->documentStore->addCollection(
                $this->generateCollectionName($appVersion, $projectionName)
            /* Note: we could pass index configuration as a second argument, see docs for details */
            );
        }
    }

    public function handle(string $appVersion, string $projectionName, $event): void
    {
        $collection = $this->generateCollectionName($appVersion, $projectionName);

        switch (\get_class($event)) {
            case UserCheckedIn::class:
                /** @var $event UserCheckedIn */
                $this->documentStore->addDoc(
                    $collection,
                    $event->name()->toString(), //Use username as doc id
                    [Payload::BUILDING_ID => $event->buildingId()->toString()]
                );
                break;
            case UserCheckedOut::class:
                /** @var $event UserCheckedOut */
                $this->documentStore->deleteDoc($collection, $event->name()->toString());
                break;
            default:
                //Ignore unknown events
        }
    }

    public function deleteReadModel(string $appVersion, string $projectionName): void
    {
        $this->documentStore->dropCollection($this->generateCollectionName($appVersion, $projectionName));
    }

    private function generateCollectionName(string $appVersion, string $projectionName): string
    {
        //We can use the naming strategy of the aggregate projector for our custom projection, too
        return AggregateProjector::generateCollectionName($appVersion, $projectionName);
    }
}

```

The `UiExchange` event listener included in the skeleton application needs to b aligned, too.
First, the corresponding interface should handle any type of event:

`src/Infrastructure/ServiceBus/UiExchange.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\ServiceBus;

use Prooph\EventEngine\Messaging\Message;

/**
 * Marker Interface UiExchange
 *
 * @package App\Infrastructure\ServiceBus
 */
interface UiExchange
{
    public function __invoke($event): void;
}
```

Second, an implementation of the interface should handle our event objects. The skeleton simply uses an anonymous class
to implement the interface. It can be found and changed in the `ServiceFactory`.

{.alert .alert-warning}
It's an anonymous class because the UiExchange is only included in the skeleton to demonstrate how events can be pushed
to a message queue and consumed by a UI. The implementation is not meant to be used in production. You can get some inspiration
from it, but please work out a production grade solution yourself.

`src/Service/ServiceFactory.php`

```php
<?php
namespace App\Service;

/* ... */

final class ServiceFactory
{
    /* ... */

    public function uiExchange(): UiExchange
    {
        return $this->makeSingleton(UiExchange::class, function () {
           $this->assertMandatoryConfigExists('rabbit.connection');

            $connection = new \Humus\Amqp\Driver\AmqpExtension\Connection(
                $this->config->arrayValue('rabbit.connection')
            );

            $connection->connect();

            $channel = $connection->newChannel();

            $exchange = $channel->newExchange();

            $exchange->setName($this->config->stringValue('rabbit.ui_exchange', 'ui-exchange'));

            $exchange->setType('fanout');

            $humusProducer = new \Humus\Amqp\JsonProducer($exchange);

            $messageProducer = new \Prooph\ServiceBus\Message\HumusAmqp\AmqpMessageProducer(
                $humusProducer,
                new class implements MessageConverter {
                    public function convertToArray(\Prooph\Common\Messaging\Message $domainMessage): array
                    {
                        return [
                            'uuid' => $domainMessage->uuid()->toString(),
                            'message_name' => $domainMessage->messageName(),
                            'payload' => $domainMessage->payload(),
                            'metadata' => $domainMessage->metadata(),
                            'created_at' => $domainMessage->createdAt()
                        ];
                    }
                }
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

                    $this->producer->__invoke($this->flavour->prepareNetworkTransmission($messageBag));
                }
            };
        });
    }

    /* ... */
}

```

Finally, the two query resolvers should use typed queries:

`src/Infrastructure/Finder/BuildingFinder.php`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder;

use App\Api\Payload;
use App\Infrastructure\Finder\Query\GetBuilding;
use App\Infrastructure\Finder\Query\GetBuildings;
use Prooph\EventEngine\Persistence\DocumentStore;
use React\Promise\Deferred;

final class BuildingFinder
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var string
     */
    private $collectionName;

    public function __construct(string $collectionName, DocumentStore $documentStore)
    {
        $this->collectionName = $collectionName;
        $this->documentStore = $documentStore;
    }

    public function __invoke($buildingQuery, Deferred $deferred): void
    {
        switch (\get_class($buildingQuery)) {
            case GetBuilding::class:
                /** @var $buildingQuery GetBuilding */
                $this->resolveBuilding($deferred, $buildingQuery->buildingId());
                break;
            case GetBuildings::class:
                /** @var $buildingQuery GetBuildings */
                $this->resolveBuildings($deferred, $buildingQuery->name());
                break;
            default:
                throw new \InvalidArgumentException("Unknown query. Got "
                    . (is_object($buildingQuery)? get_class($buildingQuery) : gettype($buildingQuery))
                );
        }
    }

    private function resolveBuilding(Deferred $deferred, string $buildingId): void
    {
        $buildingDoc = $this->documentStore->getDoc($this->collectionName, $buildingId);

        if(!$buildingDoc) {
            $deferred->reject(new \RuntimeException('Building not found', 404));
            return;
        }

        $deferred->resolve($buildingDoc);
    }

    private function resolveBuildings(Deferred $deferred, string $nameFilter = null): array
    {
        $filter = $nameFilter?
            new DocumentStore\Filter\LikeFilter(Payload::NAME, "%$nameFilter%")
            : new DocumentStore\Filter\AnyFilter();

        $cursor = $this->documentStore->filterDocs($this->collectionName, $filter);

        $deferred->resolve(iterator_to_array($cursor));
    }
}

```

{.alert .alert-info}
You might have noticed that the queries don't use value objects like commands or events. That's because we don't want to couple
the read model with the write model that much. Simple scalar types are usually enough for queries. Validation is done by Json Schema anyway.

`src/Infrastructure/Finder/UserBuildingFinder.phhp`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder;

use App\Api\Payload;
use App\Infrastructure\Finder\Query\GetUserBuildingList;
use Prooph\EventEngine\Persistence\DocumentStore;
use React\Promise\Deferred;

final class UserBuildingFinder
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

    public function __invoke(GetUserBuildingList $query, Deferred $deferred): void
    {
        $userBuilding = $this->documentStore->getDoc(
            $this->userBuildingCollection,
            $query->name()
        );

        if(!$userBuilding) {
            $deferred->resolve([
                'user' => $query->name(),
                'building' => null
            ]);
            return;
        }

        $building = $this->documentStore->getDoc(
            $this->buildingCollection,
            $userBuilding['buildingId']
        );

        if(!$building) {
            $deferred->resolve([
                'user' => $query->name(),
                'building' => null
            ]);
            return;
        }

        $deferred->resolve([
            'user' => $query->name(),
            'building' => $building
        ]);
        return;
    }
}

```

That's it! You can use the [Swagger UI](http://localhost:8080/swagger/index.html#/) to test changes.

Or wait! We did not run the tests!

```bash
docker-compose run php php vendor/bin/phpunit
```

Doesn't look good, right? Let's fix them!

The skeleton provides a `BaseTestCase` and in its `setUp` method we can change the Flavour used during testing.

```php
<?php
declare(strict_types=1);

namespace AppTest;

use App\Infrastructure\Flavour\AppMessagePort;
use PHPUnit\Framework\TestCase;
use Prooph\EventEngine\Container\ContainerChain;
use Prooph\EventEngine\Container\EventEngineContainer;
use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\Messaging\Message;
use Prooph\EventEngine\Runtime\FunctionalFlavour;

class BaseTestCase extends TestCase
{
    /**
     * @var EventEngine
     */
    protected $eventEngine;

    /**
     * @var Flavour
     */
    protected $flavour;

    protected function setUp()
    {
        $this->eventEngine = new EventEngine();
        $this->flavour = new FunctionalFlavour(new AppMessagePort());

        $config = include __DIR__ . '/../config/autoload/global.php';

        foreach ($config['event_machine']['descriptions'] as $description) {
            $this->eventEngine->load($description);
        }

        $this->eventEngine->initialize(
            new ContainerChain(
                new FlavourContainer($this->flavour),
                new EventEngineContainer($this->eventEngine)
            )
        );
    }

    /* ... */
}

```

The `assertRecordedEvent` method needs an adjustment, too:

```php
<?php
declare(strict_types=1);

namespace AppTest;

use App\Infrastructure\Flavour\AppMessagePort;
use PHPUnit\Framework\TestCase;
use Prooph\EventEngine\Container\ContainerChain;
use Prooph\EventEngine\Container\EventEngineContainer;
use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\Messaging\Message;
use Prooph\EventEngine\Runtime\FunctionalFlavour;

class BaseTestCase extends TestCase
{
    /**
     * @var EventEngine
     */
    protected $eventEngine;

    /**
     * @var Flavour
     */
    protected $flavour;

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

namespace AppTest\Integration;

use App\Api\Command;
use App\Api\Event;
use App\Api\Payload;
use App\Infrastructure\ServiceBus\UiExchange;
use AppTest\BaseTestCase;

final class NotifySecurityTest extends BaseTestCase
{
    const BUILDING_ID = '7c5f0c8a-54f2-4969-9596-b5bddc1e9421';
    const BUILDING_NAME = 'Acme Headquarters';
    const USERNAME = 'John';

    private $uiExchange;

    protected function setUp()
    {
        //The BaseTestCase loads all Event Engine descriptions configured in config/autoload/global.php
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
    }

    /**
     * @test
     */
    public function it_detects_double_check_in_and_notifies_security()
    {
        $this->eventEngine->bootstrapInTestMode(
        //Add history events that should have been recorded before current test scenario
            [
                $this->message(Event::BUILDING_ADDED, [
                    Payload::BUILDING_ID => self::BUILDING_ID,
                    Payload::NAME => self::BUILDING_NAME
                ]),
                $this->message(Event::USER_CHECKED_IN, [
                    Payload::BUILDING_ID => self::BUILDING_ID,
                    Payload::NAME => self::USERNAME
                ]),
            ],
            //Provide mocked services used in current test scenario, if you forget one the test will throw an exception
            //You don't have to mock the event store and document store, that is done internally
            [
                //Remember, UiExchange is our process manager that pushes events to rabbit
                //Event Engine is configured to push DoubleCheckInDetected events on to UiExchange (src/Api/Listener.php)
                UiExchange::class => $this->uiExchange
            ]
        );

        //Try to check in John twice
        $checkInJohn = $this->message(Command::CHECK_IN_USER, [
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME
        ]);

        $this->eventEngine->dispatch($checkInJohn);

        //After dispatch $this->lastPublishedEvent points to the event received by UiExchange mock
        $this->assertNotNull($this->uiExchange->lastReceivedMessage());

        $this->assertEquals(Event::DOUBLE_CHECK_IN_DETECTED, Event::nameOf($this->uiExchange->lastReceivedMessage()));

        $this->assertEquals([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME
        ], $this->uiExchange->lastReceivedMessage()->toArray());
    }
}
```

Next on the list is `BuildingTest`. It breaks because we introduced types for building state properties.
That's going to be an easy fix.

`tests/Model/BuildingTest.php`

```php
<?php
declare(strict_types=1);

namespace AppTest\Model;

use App\Api\Event;
use App\Api\Payload;
use AppTest\BaseTestCase;
use Ramsey\Uuid\Uuid;
use App\Model\Building;

class BuildingTest extends BaseTestCase
{
    /* ... */

    /**
     * @test
     */
    public function it_detects_double_check_in()
    {
        /* ... */

        $state = $state->withCheckedInUser(Building\Username::fromString($this->username));

        /* ... */
    }
}

```

And last adjustments in `UserBuildingListTest::it_manages_list_of_users_with_building_reference()`.
The projector expects dedicated event objects now.

`tests/Infrastructure/Projector/UserBuildingListTest.php`

```php
<?php
declare(strict_types=1);

namespace AppTest\Infrastructure\Projector;

use App\Api\Payload;
use App\Infrastructure\Projector\UserBuildingList;
use App\Model\Building\Event\UserCheckedIn;
use App\Model\Building\Event\UserCheckedOut;
use AppTest\BaseTestCase;
use Prooph\EventEngine\Persistence\DocumentStore;
use Prooph\EventEngine\Persistence\InMemoryConnection;
use Prooph\EventEngine\Projecting\AggregateProjector;

final class UserBuildingListTest extends BaseTestCase
{
    const APP_VERSION = '0.1.0';
    const PROJECTION_NAME = 'user_building_list';
    const BUILDING_ID = '7c5f0c8a-54f2-4969-9596-b5bddc1e9421';
    const USERNAME1 = 'John';
    const USERNAME2 = 'Jane';

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var UserBuildingList
     */
    private $projector;

    protected function setUp()
    {
        parent::setUp();

        $this->documentStore = new DocumentStore\InMemoryDocumentStore(new InMemoryConnection());
        $this->projector = new UserBuildingList($this->documentStore);
        $this->projector->prepareForRun(self::APP_VERSION, self::PROJECTION_NAME);
    }

    /**
     * @test
     */
    public function it_manages_list_of_users_with_building_reference()
    {
        $collection = AggregateProjector::generateCollectionName(self::APP_VERSION, self::PROJECTION_NAME);

        $johnCheckedIn = UserCheckedIn::fromArray([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME1
        ]);

        $this->projector->handle(self::APP_VERSION, self::PROJECTION_NAME, $johnCheckedIn);

        $users = iterator_to_array($this->documentStore->filterDocs($collection, new DocumentStore\Filter\AnyFilter()));

        $this->assertEquals($users, [
            'John' => ['buildingId' => self::BUILDING_ID]
        ]);

        $janeCheckedIn = UserCheckedIn::fromArray([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME2
        ]);

        $this->projector->handle(self::APP_VERSION, self::PROJECTION_NAME, $janeCheckedIn);

        $users = iterator_to_array($this->documentStore->filterDocs($collection, new DocumentStore\Filter\AnyFilter()));

        $this->assertEquals($users, [
            'John' => ['buildingId' => self::BUILDING_ID],
            'Jane' => ['buildingId' => self::BUILDING_ID],
        ]);

        $johnCheckedOut = UserCheckedOut::fromArray([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME1
        ]);

        $this->projector->handle(self::APP_VERSION, self::PROJECTION_NAME, $johnCheckedOut);

        $users = iterator_to_array($this->documentStore->filterDocs($collection, new DocumentStore\Filter\AnyFilter()));

        $this->assertEquals($users, [
            'Jane' => ['buildingId' => self::BUILDING_ID],
        ]);
    }
}

```

{.alert .alert-success}
**Tests are green again. Refactoring finished successfully. Was it worth the effort?**
Switching the Flavour is quite some workt to do, isn't it? Depending on the amount of already written code and tests this task can take some days and you need to make sure
that you don't break existing functionality. On the other hand you get a fully decoupled domain model.
Of course, it's also possible to use another Flavour right from the beginning. But keep in mind, that the PrototypingFlavour saves a lot of time in the early days
of a project. You don't know if the first app version really meets business and user needs. You can only try and experiment. The faster you have a working
app, the faster you can get feedback from users and stakeholders. A lean implementation and simple infrastructure gives you a lot of flexibility at the beginning.
Starting with a MVP is not a new concept. Event Engine just gives you a nice tool to build one and
reuse parts of your experiments in later project phases. Also using CQRS / ES from day one gives you full advantage of a reactive system.

Still curious to see what the **OopFlavour** can do? The last bonus part sheds light on it.


