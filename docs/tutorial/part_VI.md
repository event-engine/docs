# Part VI - Check in User

The second use case of our Building Management system checks users into buildings. Users are identified by their name.

## Command

Let's add a new command for the use case in `src/Domain/Api/Command`:

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\JsonSchema\JsonSchema;

class Command implements EventEngineDescription
{
    const ADD_BUILDING = 'AddBuilding';
    const CHECK_IN_USER = 'CheckInUser';

    /**
     * @param EventEngine $eventEngine
     */
    public static function describe(EventEngine $eventEngine): void
    {
        /* ... */

        $eventEngine->registerCommand(
            Command::CHECK_IN_USER,
            JsonSchema::object([
                Payload::BUILDING_ID => Schema::buildingId(),
                Payload::NAME => Schema::username(),
            ])
        );
    }
}

```
We can reuse `Payload::NAME` but assign a different schema so that we can change schema for a `building name` without
influencing the schema of `user name`:

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\Type\ArrayType;
use EventEngine\JsonSchema\Type\StringType;
use EventEngine\JsonSchema\Type\TypeRef;
use EventEngine\JsonSchema\Type\UuidType;

class Schema
{
    /* ... */

    public static function username(): StringType
    {
        return JsonSchema::string()->withMinLength(1);
    }
}

```
## Event

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\JsonSchema\JsonSchema;

class Event implements EventEngineDescription
{
    const BUILDING_ADDED = 'BuildingAdded';
    const USER_CHECKED_IN = 'UserCheckedIn';

    /**
     * @param EventEngine $eventEngine
     */
    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerEvent(
            self::BUILDING_ADDED,
            JsonSchema::object(
                [
                    Payload::BUILDING_ID => Schema::buildingId(),
                    Payload::NAME => Schema::buildingName(),
                ]
            )
        );

        $eventEngine->registerEvent(
            self::USER_CHECKED_IN,
            JsonSchema::object([
                Payload::BUILDING_ID => Schema::buildingId(),
                Payload::NAME => Schema::username(),
            ])
        );
    }
}

```

## Aggregate

Did you notice that we are getting faster? Once, you're used to Event Engine's API you can develop at the
speed of light ;).

A user can only check into an existing building. `builidngId` is part of the command payload and should reference a
building in our system. For the command handling aggregate function this means that we also have state of the aggregate
and Event Engine will pass that state as the first argument to the command handling function as well as to the
event apply function:

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Model;

use MyService\Domain\Api\Event;
use MyService\Domain\Api\Payload;
use EventEngine\Messaging\Message;

final class Building
{
    public static function add(Message $addBuilding): \Generator
    {
        yield [Event::BUILDING_ADDED, $addBuilding->payload()];
    }

    public static function whenBuildingAdded(Message $buildingAdded): Building\State
    {
        return Building\State::fromArray($buildingAdded->payload());
    }

    public static function checkInUser(Building\State $state, Message $checkInUser): \Generator
    {
        yield [Event::USER_CHECKED_IN, $checkInUser->payload()];
    }

    public static function whenUserCheckedIn(Building\State $state, Message $userCheckedIn): Building\State
    {
        return $state->withCheckedInUser($userCheckedIn->get(Payload::NAME));
    }
}

```

`Building::checkInUser()` is still a dumb function (we will change that in a minute) but `Building::whenUserCheckedIn()`
contains an interesting detail. `Building\State` is an immutable record. But we can add `with*` methods to it to
modify state. You may know these `with*` methods from the `PSR-7` standard. It is a common practice to prefix
state changing methods of immutable objects with `with`. Those methods should return a new instance with the modified
state rather than changing its own state. Here is the implementation of `Building\State::withCheckedInUser(string $username): Building\State`:

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Model\Building;

use MyService\Domain\Api\Schema;
use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\Type;

final class State implements ImmutableRecord
{
    use ImmutableRecordLogic;
    
    public const BUILDING_ID = 'buildingId';
    public const NAME = 'name';

    /**
     * @var string
     */
    private $buildingId;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $users = [];

    private static function arrayPropItemTypeMap(): array
    {
        return ['users' => JsonSchema::TYPE_STRING];
    }

    /**
     * @return string
     */
    public function buildingId(): string
    {
        return $this->buildingId;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function users(): array
    {
        return array_keys($this->users);
    }

    public function withCheckedInUser(string $username): State
    {
        $copy = clone $this;
        $copy->users[$username] = null;
        return $copy;
    }

    public function isUserCheckedIn(string $username): bool
    {
        return array_key_exists($username, $this->users);
    }
}

```

We can make a copy of the record and modify that. The original record is not modified,
and we return the copy to satisfy the immutable record contract.

Besides `withCheckedInUser` we've added a new property, `users`, and a getter for it. We also overrode the private static method `arrayPropItemTypeMap`
of `ImmutableRecordLogic` to define a type hint for the items in the `users` array property.
Unfortunately, we can only type hint for `array` in PHP, and it is not possible to use return type hints like `string[]`.
Hopefully this will change in a future version of PHP, but, for now, we have to live with the workaround and give
`ImmutableRecordLogic` a hint that array items of the `users` property are of type `string`.

{.alert .alert-light}
*Note: ImmutableRecordLogic derives type information by inspecting return types of getter methods named like their
corresponding private properties.*

Internally, user names are used as the array index so the same user cannot appear twice in the list. With `Building\State::isUserCheckedIn(string $username): bool`
we can look up if the given user is currently in the building. `Building\State::users()` on the other hand returns a list
of user names. Internal state is used for fast look ups and external schema is used for the
read model. More on that in a minute.

## Command Processing

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
        /* ... */

        $eventEngine->process(Command::CHECK_IN_USER)
            ->withExisting(self::BUILDING)
            ->handle([Building::class, 'checkInUser'])
            ->recordThat(Event::USER_CHECKED_IN)
            ->apply([Building::class, 'whenUserCheckedIn']);
    }
}

```

Pretty much the same command processing description but with command, event and function names based on
the new use case. An important difference is that we use `->withExisting` instead of `->withNew`.
As already stated this tells Event Engine to look up an existing Building using the `buildingId` from the `CheckInUser` command.

The following command should check in *John* into the *Acme Headquarters*.

```json
{
  "buildingId": "9ee8d8a8-3bd3-4425-acee-f6f08b8633bb",
  "name": "John"
}
```

Looks good! And what does the response of the `Buildings` query look now? If you inspect the schema of the query
and click on the `Building` return type you'll notice the new property `users`. 

```json
{
  "name": "Acme"
}
```
Response

```json
[
  {
    "name": "Acme Headquarters",
    "users": [
      "John"
    ],
    "buildingId": "9ee8d8a8-3bd3-4425-acee-f6f08b8633bb"
  }
]
```
Great! We get back the list of users checked into the building.

## Protect Invariants

One of the main tasks of an aggregate is to protect invariants. A user cannot check in twice. The `Building` aggregate
should enforce the business rule:

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Model;

use MyService\Domain\Api\Event;
use MyService\Domain\Api\Payload;
use EventEngine\Messaging\Message;

final class Building
{
    public static function add(Message $addBuilding): \Generator
    {
        yield [Event::BUILDING_ADDED, $addBuilding->payload()];
    }

    public static function whenBuildingAdded(Message $buildingAdded): Building\State
    {
        return Building\State::fromArray($buildingAdded->payload());
    }

    public static function checkInUser(Building\State $state, Message $checkInUser): \Generator
    {
        if($state->isUserCheckedIn($checkInUser->get(Payload::NAME))) {
            throw new \DomainException(sprintf(
                "User %s is already in the building",
                $checkInUser->get(Payload::NAME)
            ));
        }

        yield [Event::USER_CHECKED_IN, $checkInUser->payload()];
    }

    public static function whenUserCheckedIn(Building\State $state, Message $userCheckedIn): Building\State
    {
        return $state->withCheckedInUser($userCheckedIn->get(Payload::NAME));
    }
}

```

The command handling function can make use of `$state` passed to it as this will always be the current state of the aggregate.
If the given user is already checked in we throw an exception to stop command processing.

Let's try it:

```json
{
  "buildingId": "9ee8d8a8-3bd3-4425-acee-f6f08b8633bb",
  "name": "John"
}
```

Response:

```json
{
  "exception": {
    "message": "User John is already in the building",
    "details": "..."
  }
}
```

{.alert .alert-success}
Throwing an exception is the simplest way to protect invariants. However, with event sourcing we have a different
(and in most cases) better option. This will be covered in the next part.
