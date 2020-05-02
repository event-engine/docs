# Bonus IV - OOP Flavour

The previous bonus part introduced Event Engine Flavours, especially the **FunctionalFlavour**.
Biggest change was the replacement of generic Event Engine messages with dedicated message types.

## Original Object-Oriented Programming

Event Engine emphasizes the usage of a functional core. That's true for the **PrototypingFlavour** and unfolds completely
with the **FunctionalFlavour**. A functional core has huge advantages compared to its object-oriented counterpart.
At least compared to the way we tend to work with objects in our projects the last twenty years or so.

Dr. Alan Kay (who has coined the term) had quite a different idea of object-oriented programming back in 1967.

> I thought of objects being like biological cells and/or individual computers on a network, only able to communicate with messages

*[source](http://www.purl.org/stefan_ram/pub/doc_kay_oop_en)*

Let that sink in - *only able to communicate with messages*.

If you look at what we've built so far, you might recognize that we are very close to that statement.
Resolver, Event Listener, Process Manager and Projector all are invoked with messages. They don't interact with each other directly.
Event Engine takes over coordination. It's like the network Alan Kay is talking about. But what about aggregate functions?
The functions are stateless and don't have side effects. They are **pure**. Immutable data types and messages (commands or events) are passed to
them. With coordination performed by Event Engine pure functions work great.

## It Is Not Functional Programming

We are not used to work with pure functions in PHP.
It's not a functional programming language, right? Autoloading functions doesn't work so we are either forced to require all files manually
or use the workaround shown in the tutorial to turn pure functions into static methods of otherwise useless classes.

{.alert .alert-dark}
Personally, I don't have a big problem with the latter approach. I see those classes as the last part of the namespace or even similar to an ES6 module
(if you're familiar with JavaScript). The module (PHP class) can export functions (public static functions) and use internal functions (private static functions).
But I have to admit that it is a workaround.

## OopFlavour on top of FunctionalFlavour

What can we do if the workaround is not acceptable for a project or personal taste? **Exactly, we can pick another Flavour :D**

The **OopFlavour** in a nutshell:

{.alert .alert-info}
Aggregate functions (command handling and apply functions) are combined with state into one object. Each aggregate manages its own state internally.
Commands trigger state changes. A state change is first recorded as an event and then applied by the aggregate.

You know what this means, right?

> I thought of objects being like biological cells and/or individual computers on a network, only able to communicate with messages

As I said, we're very close to that statement. That's the reason why the **OopFlavour** uses the **FunctionalFlavour** internally. It works on top of it
only to combine aggregate functions and state. More on that in a minute. First we need a solid foundation for event sourced objects.

## OOP Port

Similar to the `Functional\Port` we need to implement an `Oop\Port` to use the **OopFlavour**. Let's start again by looking at the required methods.
Create a new class `EventSourcedAggregatePort` in `src/System/Flavour`:

```php
<?php
declare(strict_types=1);

namespace MyService\System\Flavour;

use EventEngine\Runtime\Oop\Port;

final class EventSourcedAggregatePort implements Port
{

    /**
     * @param string $aggregateType
     * @param callable $aggregateFactory
     * @param $customCommand
     * @param array $contextServices
     * @return mixed Created aggregate
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFactory, $customCommand, ...$contextServices)
    {
        // TODO: Implement callAggregateFactory() method.
    }

    /**
     * @param mixed $aggregate
     * @param mixed $customCommand
     * @param array $contextServices
     */
    public function callAggregateWithCommand($aggregate, $customCommand, ...$contextServices): void
    {
        // TODO: Implement callAggregateWithCommand() method.
    }

    /**
     * @param mixed $aggregate
     * @return array of custom events
     */
    public function popRecordedEvents($aggregate): array
    {
        // TODO: Implement popRecordedEvents() method.
    }

    /**
     * @param mixed $aggregate
     * @param mixed $customEvent
     */
    public function applyEvent($aggregate, $customEvent): void
    {
        // TODO: Implement applyEvent() method.
    }

    /**
     * @param mixed $aggregate
     * @return array
     */
    public function serializeAggregate($aggregate): array
    {
        // TODO: Implement serializeAggregate() method.
    }

    /**
     * @param string $aggregateType
     * @param iterable $events history
     * @return mixed Aggregate instance
     */
    public function reconstituteAggregate(string $aggregateType, iterable $events)
    {
        // TODO: Implement reconstituteAggregate() method.
    }

    /**
     * @param string $aggregateType
     * @param array $state
     * @param int $version
     * @return mixed Aggregate instance
     */
    public function reconstituteAggregateFromStateArray(string $aggregateType, array $state, int $version)
    {
        // TODO: Implement reconstituteAggregateFromStateArray() method.
    }
}

```

This time we don't work top to bottom but start in the middle. `popRecordedEvents` and `applyEvent` are the first targets.

{.alert .alert-info}
Same basic rules apply here as we discussed for the `Functional\Port`.
Event Engine does not require a specific strategy to work with event sourced aggregates. You can implement them in any way as
long as the `Oop\Port` is able to fulfill the contract. That said, the approach shown in the tutorial is just a suggestion.
We're going to use a simple and pragmatic implementation with publicly accessible methods that are actually internal methods.
You might want to hide them in your project using a decorator or PHP's Reflection API. Anyway, that would be overkill for the tutorial.

## Event Sourced Aggregate Root

{.alert .alert-light}
A state change is first recorded as an event and then applied by the aggregate.

Let's create an interface for the port to rely on:

`src/Domain/Model/Base/AggregateRoot.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Base;

interface AggregateRoot
{
    /**
     * @return DomainEvent[]
     */
    public function popRecordedEvents(): array;

    public function apply(DomainEvent $event): void;
}

```

We don't have a `DomainEvent` type yet. Add it next to the `AggregateRoot` interface in the same directory.

`src/Model/Base/DomainEvent.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Base;

interface DomainEvent
{
    //Marker interface
}

```

With those two interfaces we can implement the first methods of the `Oop\Port`:

`src/System/Flavour/EventSourcedAggregatePort.php`

```php
<?php
declare(strict_types=1);

namespace MyService\System\Flavour;

use MyService\Domain\Model\Base\AggregateRoot;
use EventEngine\Runtime\Oop\Port;

final class EventSourcedAggregatePort implements Port
{
    /* ... */

    /**
     * @param mixed $aggregate
     * @return array of custom events
     */
    public function popRecordedEvents($aggregate): array
    {
        if(!$aggregate instanceof AggregateRoot) {
            throw new \RuntimeException(
                sprintf("Cannot pop recorded events. Given aggregate is not an instance of %s. Got %s",
                    AggregateRoot::class,
                    (is_object($aggregate)? get_class($aggregate) : gettype($aggregate))
                )
            );
        }

        return $aggregate->popRecordedEvents();
    }

    /**
     * @param mixed $aggregate
     * @param mixed $customEvent
     */
    public function applyEvent($aggregate, $customEvent): void
    {
        if(!$aggregate instanceof AggregateRoot) {
            throw new \RuntimeException(
                sprintf("Cannot apply event. Given aggregate is not an instance of %s. Got %s",
                    AggregateRoot::class,
                    (is_object($aggregate)? get_class($aggregate) : gettype($aggregate))
                )
            );
        }

        $aggregate->apply($customEvent);
    }

    /* ... */
}

```

## Aggregate Root Lifecycle

Next two methods we are looking at are `callAggregateFactory` and `reconstituteAggregate`. The former starts the lifecycle of a new aggregate and the latter brings it
back into shape by passing aggregate event history (all events previously recorded by the aggregate) to the method.

Traits are a great way to reuse code snippets without inheritance. It's like copy and pasting methods from a blueprint into a class. Let's define one for common event sourcing
logic that we can later use in aggregates.

`src/Domain/Model/Base/EventSourced.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Base;

trait EventSourced
{
    /**
     * @var DomainEvent[]
     */
    private $recordedEvents = [];

    /**
     * @param DomainEvent[] $domainEvents
     * @return EventSourced aggregate
     */
    public static function reconstituteFromHistory(DomainEvent ...$domainEvents): AggregateRoot
    {
        $self = new self();
        foreach ($domainEvents as $domainEvent) {
            $self->apply($domainEvent);
        }
        return $self;
    }

    private function __construct()
    {
        //Do not override this!!!!
        //Use named constructors aka public static factory methods to create aggregae instances!
    }

    private function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return DomainEvent[]
     */
    public function popRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    public function apply(DomainEvent $event): void
    {
        $whenMethod = $this->deriveMethodNameFromEvent($event);

        if(!method_exists($this, $whenMethod)) {
            throw new \RuntimeException(\sprintf(
                "Unable to apply event %s. Missing method %s in class %s",
                \get_class($event),
                $whenMethod,
                \get_class($this)
            ));
        }

        $this->{$whenMethod}($event);
    }

    private function deriveMethodNameFromEvent(DomainEvent $event): string
    {
        $nameParts = \explode('\\', \get_class($event));
        return 'when' . \array_pop($nameParts);
    }
}

```

The trait provides implementations for `popRecordedEvents` and `apply` defined by `AggregateRoot`. But it contains some more stuff!

### Derive Method Name From Event

A convention is used that says: **An aggregate should have an apply method for each domain event following the naming pattern "when\<EventName\>",
whereby \<EventName\> is the class name of the event without namespace.**

### Record That

An aggregate should use `recordThat` to record new domain events. The trait takes care of storing recorded events internally until the `Oop\Port` calls `popRecordedEvents()`.

### Private Empty Constructor

While a trait cannot enforce a private empty `__construct` (it could be overridden by a class), it's still included in the trait as a reminder for future developers to not
use `__construct` in aggregate roots but rather use named constructors. This rule is important for `Oop\Port::callAggregateFactory()`. More on that in a minute.

### Reconstitute From History

`reconstituteFromHistory` should be called by the `Oop\Port`. But the port works against our `AggregateRoot` interface, so we should add such a method signature there, too.

`src/Domain/Model/Base/AggregateRoot.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Base;

interface AggregateRoot
{
    public static function reconstituteFromHistory(DomainEvent ...$domainEvents): self;

    /**
     * @return DomainEvent[]
     */
    public function popRecordedEvents(): array;

    public function apply(DomainEvent $event): void;
}

```

Cool, we can implement the next port method now!

`src/System/Flavour/EventSourcedAggregatePort.php`

```php
<?php
declare(strict_types=1);

namespace MyService\System\Flavour;

use EventEngine\Runtime\Oop\Port;
use MyService\Domain\Api\Aggregate;
use MyService\Domain\Model\Base\AggregateRoot;
use MyService\Domain\Model\Building;

final class EventSourcedAggregatePort implements Port
{
    /* ... */

    /**
     * @param string $aggregateType
     * @param iterable $events history
     * @return mixed Aggregate instance
     */
    public function reconstituteAggregate(string $aggregateType, iterable $events)
    {
        $arClass = $this->getAggregateClassOfType($aggregateType);

        /** @var AggregateRoot $arClass */
        return $arClass::reconstituteFromHistory(...$events);
    }

    private function getAggregateClassOfType(string $aggregateType): string
    {
        switch ($aggregateType) {
            case Aggregate::BUILDING:
                return Building::class;
            default:
                throw new \RuntimeException("Unknown aggregate type $aggregateType");
        }
    }
}

```

### Reconstitute From State Array

The `Oop\Port` contract requires another reconstitute method: `reconstituteAggregateFromStateArray`. It's pretty much the same as `reconstituteAggregate` but this time 
the aggregate needs to be reconstituted from a state array. Event Engine needs the functionality when it loads aggregate snapshots either taken by the `MultiModelStore`
or the `AggregateProjector`.

Another method is required in the `AggregateRoot` interface:

`src/Domain/Model/Base/AggregateRoot.php`
 
```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Base;

interface AggregateRoot
{
    public static function reconstituteFromHistory(DomainEvent ...$domainEvents): self;

    public static function reconstituteFromStateArray(array $state): self;

    /**
     * @return DomainEvent[]
     */
    public function popRecordedEvents(): array;

    public function apply(DomainEvent $event): void;
}

```

and the corresponding port implementation:

```php
<?php
declare(strict_types=1);

namespace MyService\System\Flavour;

use EventEngine\Runtime\Oop\Port;
use MyService\Domain\Api\Aggregate;
use MyService\Domain\Model\Base\AggregateRoot;
use MyService\Domain\Model\Building;

final class EventSourcedAggregatePort implements Port
{
    /* ... */

    /**
     * @param string $aggregateType
     * @param iterable $events history
     * @return mixed Aggregate instance
     */
    public function reconstituteAggregateFromStateArray(string $aggregateType, array $state, int $version)
    {
        $arClass = $this->getAggregateClassOfType($aggregateType);

        // Note: $version is ignored, our aggregate implementation
        // relies on the version managed by Event Engine internally
        /** @var AggregateRoot $arClass */
        return $arClass::reconstituteFromStateArray($state);
    }

    private function getAggregateClassOfType(string $aggregateType): string
    {
        switch ($aggregateType) {
            case Aggregate::BUILDING:
                return Building::class;
            default:
                throw new \RuntimeException("Unknown aggregate type $aggregateType");
        }
    }
}

```

Obviously, this won't work. We did not touch `Building` yet. Let's do that next.

## Merge Functions And State

Our `Building` aggregate consists of a set of pure functions grouped in a class and immutable data types. Turning it into an event sourced
object is less work than you might expect:

`src/Domain/Model/Building.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model;

use MyService\Domain\Model\Base\AggregateRoot;
use MyService\Domain\Model\Base\EventSourced;
use MyService\Domain\Model\Building\Command\AddBuilding;
use MyService\Domain\Model\Building\Command\CheckInUser;
use MyService\Domain\Model\Building\Command\CheckOutUser;
use MyService\Domain\Model\Building\Event\BuildingAdded;
use MyService\Domain\Model\Building\Event\DoubleCheckInDetected;
use MyService\Domain\Model\Building\Event\DoubleCheckOutDetected;
use MyService\Domain\Model\Building\Event\UserCheckedIn;
use MyService\Domain\Model\Building\Event\UserCheckedOut;

final class Building implements AggregateRoot
{
    use EventSourced;

    /**
     * @var Building\State
     */
    private $state;
    
    public static function reconstituteFromStateArray(array $state): AggregateRoot
    {
        $self = new self();
        $self->state = Building\State::fromArray($state);
        return $self;
    }

    public static function add(AddBuilding $addBuilding): AggregateRoot
    {
        $self = new self();
        $self->recordThat(BuildingAdded::fromArray($addBuilding->toArray()));
        return $self;
    }

    public function whenBuildingAdded(BuildingAdded $buildingAdded): void
    {
        $this->state = Building\State::fromArray($buildingAdded->toArray());
    }

    public function checkInUser(CheckInUser $checkInUser): void
    {
        if($this->state->isUserCheckedIn($checkInUser->name())) {
            $this->recordThat(DoubleCheckInDetected::fromArray($checkInUser->toArray()));
            return;
        }

        $this->recordThat(UserCheckedIn::fromArray($checkInUser->toArray()));
    }

    private function whenUserCheckedIn(UserCheckedIn $userCheckedIn): void
    {
        $this->state = $this->state->withCheckedInUser($userCheckedIn->name());
    }

    private function whenDoubleCheckInDetected(DoubleCheckInDetected $event): void
    {
        //No state change required
    }

    public function checkOutUser(CheckOutUser $checkOutUser): void
    {
        if(!$this->state->isUserCheckedIn($checkOutUser->name())) {
            $this->recordThat(DoubleCheckOutDetected::fromArray($checkOutUser->toArray()));
            return;
        }

        $this->recordThat(UserCheckedOut::fromArray($checkOutUser->toArray()));
    }

    private function whenUserCheckedOut(UserCheckedOut $userCheckedOut): void
    {
        $this->state = $this->state->withCheckedOutUser($userCheckedOut->name());
    }

    private function whenDoubleCheckOutDetected(DoubleCheckOutDetected $event): void
    {
        //No state change required
    }
}

```

Here are the refactoring steps:

- All events need to implement `MyService\Domain\Model\Base\DomainEvent`
- `Building` implements `AggregateRoot`
- `Building` uses `EventSourced`
- `Building` stores `Building\State` internally in a `state` property
- `Building::add()` creates an instance of itself and records `BuildingAdded` instead of yielding it
- `Building::reconstituteFromStateArray` sets up internal state using `Building\State::fromArray()`
- All other command handling functions:
    - Remove `static`, they become instance methods
    - Change return type to `void`
    - `Building\State` is no longer an argument, but accessed internally
    - Domain events get recorded
- All apply/when functions
    - Remove `static` and make them `private`, they become internal methods
    - Change return type to `void`
    - `Building\State` is no longer an argument, but accessed internally

## Aggregate Factory

`Building::add()` is the aggregate factory for `Building`. The `Oop\Port` can simply call it.

`src/System/Flavour/EventSourcedAggregatePort.php`

```php
<?php
declare(strict_types=1);

namespace MyService\System\Flavour;

use MyService\Domain\Model\Base\AggregateRoot;
use EventEngine\Runtime\Oop\Port;

final class EventSourcedAggregatePort implements Port
{
    /**
     * @param string $aggregateType
     * @param callable $aggregateFactory
     * @param $customCommand
     * @param array $contextServices
     * @return mixed Created aggregate
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFactory, $customCommand, ...$contextServices)
    {
        return $aggregateFactory($customCommand, ...$contextServices);
    }

    /* ... */
}

```

The `callable $aggregateFactory` passed to the port, is still the one we've defined in the Event Engine Description:

`src/Domain/Api/Aggregate.php`

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
            ->identifiedBy(Payload::BUILDING_ID)
            ->handle([Building::class, 'add']) //<-- Aggregate Factory
            ->recordThat(Event::BUILDING_ADDED)
            ->apply([Building::class, 'whenBuildingAdded']);

        /* ... */
    }
}

```

{.alert .alert-warning}
`$contextServices` is not an argument of `Building::add()` but PHP does not care. We can use that to our advantage.
The port does not need to know if an aggregate factory or command handling function is interested in a context or requires dependencies.
It just passes it always to the function. If `$contextServices` is empty and the function doesn't care, everything is fine.

## Command Handling

`Oop\Port::callAggregateWithCommand()` is next on the list. Let's see ...

```php
/**
 * @param mixed $aggregate
 * @param mixed $customCommand
 * @param array $contextServices
 */
public function callAggregateWithCommand($aggregate, $customCommand, ...$contextServices): void
{
    // TODO: Implement callAggregateWithCommand() method.
}
```

We get the `$aggregate` instance, a `$customCommand` and optionally a list of `$contextServices`. We could use a `switch (command) -> call $aggregate->method` approach,
but we are lazy. We don't want to touch the port each time we add a new command to the system. Conventions work great to get around the issue.

**An aggregate root should have a method named like the command, whereby command name is derived from its class name without namespace. The first letter of the name is lowercase.**

Looking at `Building` methods, it's exactly what we already have in place ;) We just need to implement the convention in the port.

`src/Infrastructure/Flavour/EventSourcedAggregatePort.php`

```php
<?php
declare(strict_types=1);

namespace MyService\System\Flavour;

use MyService\Domain\Model\Base\AggregateRoot;
use EventEngine\Runtime\Oop\Port;

final class EventSourcedAggregatePort implements Port
{
    /* ... */

    /**
     * @param mixed $aggregate
     * @param mixed $customCommand
     * @param array $contextServices
     */
    public function callAggregateWithCommand($aggregate, $customCommand, ...$contextServices): void
    {
        $commandNameParts = \explode('\\', \get_class($customCommand));
        $handlingMethod = \lcfirst(\array_pop($commandNameParts));
        $aggregate->{$handlingMethod}($customCommand, ...$contextServices);
    }

    /* ... */
}

```

Low hanging fruits, right? But the Event Engine Aggregate Description is broken! Handle and apply functions are no longer callable (except aggregate factory),
because they are instance methods now. To get around the issue, we can replace the definition with a `FlavourHint`.

`src/Domain/Api/Aggregate.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\Runtime\Oop\FlavourHint;
use MyService\Domain\Model\Building;
use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use MyService\Domain\Resolver\BuildingResolver;

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
            ->identifiedBy(Payload::BUILDING_ID)
            ->handle([Building::class, 'add'])
            ->recordThat(Event::BUILDING_ADDED)
            ->apply([FlavourHint::class, 'useAggregate'])
            ->storeStateIn(BuildingResolver::COLLECTION);


        $eventEngine->process(Command::CHECK_IN_USER)
            ->withExisting(self::BUILDING)
            ->handle([FlavourHint::class, 'useAggregate'])
            ->recordThat(Event::USER_CHECKED_IN)
            ->apply([FlavourHint::class, 'useAggregate'])
            ->orRecordThat(Event::DOUBLE_CHECK_IN_DETECTED)
            ->apply([FlavourHint::class, 'useAggregate']);

        $eventEngine->process(Command::CHECK_OUT_USER)
            ->withExisting(self::BUILDING)
            ->handle([FlavourHint::class, 'useAggregate'])
            ->recordThat(Event::USER_CHECKED_OUT)
            ->apply([FlavourHint::class, 'useAggregate'])
            ->orRecordThat(Event::DOUBLE_CHECK_OUT_DETECTED)
            ->apply([FlavourHint::class, 'useAggregate']);
    }
}

```

{.alert .alert-warning}
That's a bit of a drawback of the **OopFlavour**. It relies less on Event Engine, but Event Engine still wants to make
sure that you don't forget to handle a command or apply an event (handle and apply definition is mandatory). With the
`FlavourHint` we basically tell Event Engine: "Don't worry, we know what we're doing!". It's a small extra step, but trust
me, it still saves you time. Forgetting to add a route for a message to some config or have a typo somewhere is one of the
most silly bugs that can cost you hours for nothing!

## Aggregate State

One method left in the port: `serializeAggregate()`. 
A simple `toArray()` on the aggregate is sufficient. We add it to the `AggregateRoot` interface to enforce its implementation.

`src/Model/Base/AggregateRoot.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Base;

interface AggregateRoot
{
    /**
     * @return DomainEvent[]
     */
    public function popRecordedEvents(): array;

    public function apply(DomainEvent $event): void;

    public function toArray(): array;
}

```

`Building` can call the `toArray` method of `Building\State` ...

`src/Domain/Model/Building.php`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model;

use MyService\Domain\Model\Base\AggregateRoot;
use MyService\Domain\Model\Base\EventSourced;
use MyService\Domain\Model\Building\Command\AddBuilding;
use MyService\Domain\Model\Building\Command\CheckInUser;
use MyService\Domain\Model\Building\Command\CheckOutUser;
use MyService\Domain\Model\Building\Event\BuildingAdded;
use MyService\Domain\Model\Building\Event\DoubleCheckInDetected;
use MyService\Domain\Model\Building\Event\DoubleCheckOutDetected;
use MyService\Domain\Model\Building\Event\UserCheckedIn;
use MyService\Domain\Model\Building\Event\UserCheckedOut;

final class Building implements AggregateRoot
{
    use EventSourced;

    /**
     * @var Building\State
     */
    private $state;

    /* ... */

    public function toArray(): array
    {
        return $this->state->toArray();
    }
}


```

... and the `Oop\Port` does the same:

`src/System/Flavour/EventSourcedAggregatePort.php`

```php
<?php
declare(strict_types=1);

namespace MyService\System\Flavour;

use MyService\Domain\Model\Base\AggregateRoot;
use EventEngine\Runtime\Oop\Port;

final class EventSourcedAggregatePort implements Port
{
    /* ... */

    /**
     * @param mixed $aggregate
     * @return array
     */
    public function serializeAggregate($aggregate): array
    {
        if(!$aggregate instanceof AggregateRoot) {
            throw new \RuntimeException(
                sprintf("Cannot serialize aggregate. Given aggregate is not an instance of %s. Got %s",
                    AggregateRoot::class,
                    (is_object($aggregate)? get_class($aggregate) : gettype($aggregate))
                )
            );
        }

        return $aggregate->toArray();
    }

    /* ... */
}

```

{.alert .alert-info}
Of course, you can use a totally different serialization strategy. Organising aggregate state in a single immutable state object is also
only a suggestion. Do whatever you like. It's your choice!

## Activate OopFlavour

As a last step (before looking at the tests 🙈) we should activate the **OopFlavour** in `src/System/SystemServices.php`:

`src/Service/ServiceFactory.php`

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
use EventEngine\Runtime\OopFlavour;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use MyService\Domain\Api\Event;
use MyService\System\Api\EventEngineConfig;
use MyService\System\Api\SystemQuery;
use MyService\System\Api\SystemType;
use MyService\System\Flavour\EventSourcedAggregatePort;
use MyService\System\Flavour\MyServiceMessagePort;
use Prooph\Common\Messaging\NoOpMessageConverter;
use Prooph\ServiceBus\Message\HumusAmqp\AmqpMessageProducer;
use Psr\Log\LoggerInterface;

trait SystemServices
{
    /* ... */
    
    //Flavour
    public function flavour(): Flavour
    {
        return $this->makeSingleton(Flavour::class, function () {
            return new OopFlavour(
                new EventSourcedAggregatePort(),
                new FunctionalFlavour(new MyServiceMessagePort(), new ImmutableRecordDataConverter())
            );
        });
    }

    /* ... */
}

```

As stated at the beginning, the **OopFlavour** uses the **FunctionalFlavour** mainly to make use of custom message handling.

## Fixing tests

At least the `BuildingTest` should fail after latest changes. Let's see if we need to work some extra hours or can go out for a beer with a friend:

```bash
docker-compose run php php vendor/bin/phpunit
```

As expected, `BuildingTest` is broken, but should be easy to fix:

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

        /** @var Building $building */
        $building = Building::reconstituteFromStateArray($state->toArray());

        //Use test helper UnitTestCase::makeCommand() to construct command
        $command = Building\Command\CheckInUser::fromArray([
            Building\State::BUILDING_ID => $this->buildingId,
            Building\State::NAME => $this->username,
        ]);

        $building->checkInUser($command);

        $events = $building->popRecordedEvents();

        //Another test helper to assert that list of recorded events contains given event
        $this->assertRecordedEvent(Event::USER_CHECKED_IN, [
            Payload::BUILDING_ID => $this->buildingId,
            Payload::NAME => $this->username
        ], $events);
    }
}

```

We can use methods from `AggregateRoot` to set up our `Building` with state, invoke the command handling method and use
`popRecordedEvents()` to access newly recorded events. That's it!

## Wrap Up

{.alert .alert-success}
At this point the tutorial ends. Thank you for taking the tour through the world of CQRS and Event Sourcing with Event Engine.
We started our tour with a rapid development approach. Event Engine really shines here. The skeleton application is preconfigured including
some best practices like splitting Event Engine Descriptions by functionality. We learned how to react on domain events and how
to project them into a read model, that we can access using queries and resolvers. All that with a minimum of boilerplate. Finally, Event Engine
Flavours gave us a way to write more explicit code and harden the domain model. Every team can find its own style by mixing Flavours, conventions
and serialization techniques.

**What's next?**

You can start to work on your own project. Event Engine docs cover advanced topics and a lot more details, but get some practice first and revisit them every now and then.









