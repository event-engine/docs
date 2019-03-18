# Part V - DRY

You may have noticed that we use the static classes in `src/Api` as a central place to define constants.
At least we did that for message (Command, Event, Query) and aggregate names. We did not touch `src/Api/Payload` and
`src/Api/Schema` yet.

The idea behind those two classes is to group some common constants and static methods so that we don't have to repeat them
over and over again. This makes it much easier to refactor the codebase later.

## Payload

In `src/Api/Payload` we simply define a constant for each possible message payload key. We've used two keys so far:
`buildingId` and `name` so we should add them ...

```php
<?php

declare(strict_types=1);

namespace App\Api;

class Payload
{
    //Predefined keys for query payloads, see App\Api\Schema::queryPagination() for further information
    const SKIP = 'skip';
    const LIMIT = 'limit';

    const BUILDING_ID = 'buildingId';
    const NAME = 'name';
}

```
... and replace plain strings with the constants in our codebase:

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


`src/Api/Command`

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;
use Prooph\EventEngine\JsonSchema\JsonSchema;

class Command implements EventEngineDescription
{
    const ADD_BUILDING = 'AddBuilding';

    /**
     * @param EventEngine $eventEngine
     */
    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerCommand(
            Command::ADD_BUILDING,
            JsonSchema::object(
                [
                    Payload::BUILDING_ID => JsonSchema::uuid(),
                    Payload::NAME => JsonSchema::string(['minLength' => 2])
                ]
            )
        );


    }
}

```
`src/Api/Event`

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;
use Prooph\EventEngine\JsonSchema\JsonSchema;

class Event implements EventEngineDescription
{
    const BUILDING_ADDED = 'BuildingAdded';

    /**
     * @param EventEngine $eventEngine
     */
    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerEvent(
            self::BUILDING_ADDED,
            JsonSchema::object(
                [
                    Payload::BUILDING_ID => JsonSchema::uuid(),
                    Payload::NAME => JsonSchema::string(['minLength' => 2])
                ]
            )
        );
    }
}

```
`src/Api/Query`

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Infrastructure\Finder\BuildingFinder;
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

    public static function describe(EventEngine $eventEngine): void
    {
        //Default query: can be used to check if service is up and running
        $eventEngine->registerQuery(self::HEALTH_CHECK) //<-- Payload schema is optional for queries
            ->resolveWith(HealthCheckResolver::class) //<-- Service id (usually FQCN) to get resolver from DI container
            ->setReturnType(Schema::healthCheck()); //<-- Type returned by resolver

        $eventEngine->registerQuery(self::BUILDING, JsonSchema::object([
            Payload::BUILDING_ID => JsonSchema::uuid(),
        ]))
            ->resolveWith(BuildingFinder::class)
            ->setReturnType(JsonSchema::typeRef(Aggregate::BUILDING));

        $eventEngine->registerQuery(
            self::BUILDINGS,
            JsonSchema::object(
                [],
                [Payload::NAME => JsonSchema::nullOr(JsonSchema::string()->withMinLength(1))]
            )
        )
            ->resolveWith(BuildingFinder::class)
            ->setReturnType(JsonSchema::array(
                JsonSchema::typeRef(Aggregate::BUILDING)
            ));
    }
}

```
`src/Infrastructure/Finder/BuildingFinder`

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Finder;

use App\Api\Payload;
use App\Api\Query;
use Prooph\EventEngine\Messaging\Message;
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

    public function __invoke(Message $buildingQuery, Deferred $deferred): void
    {
        switch ($buildingQuery->messageName()) {
            case Query::BUILDING:
                $this->resolveBuilding($deferred, $buildingQuery->get(Payload::BUILDING_ID));
                break;
            case Query::BUILDINGS:
                $this->resolveBuildings($deferred, $buildingQuery->getOrDefault(Payload::NAME, null));
                break;
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

## Schema

Schema definitions are another area where DRY (Don't Repeat Yourself) makes a lot of sense. A good practice is to define
a schema for each payload key and reuse it when registering messages. Type references (JsonSchema::typeRef) should also be wrapped
by a schema method. Open `src/Api/Schema` and add the static methods:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventEngine\JsonSchema\JsonSchema;
use Prooph\EventEngine\JsonSchema\Type\StringType;
use Prooph\EventEngine\JsonSchema\Type\TypeRef;
use Prooph\EventEngine\JsonSchema\Type\UuidType;

class Schema
{
    public static function buildingId(): UuidType
    {
        return JsonSchema::uuid();
    }

    public static function buildingName(): StringType
    {
        return JsonSchema::string()->withMinLength(1);
    }

    public static function buildingNameFilter(): StringType
    {
        return JsonSchema::string()->withMinLength(1);
    }

    public static function building(): TypeRef
    {
        return JsonSchema::typeRef(Aggregate::BUILDING);
    }
    
    public static function buildingList(): ArrayType
    {
        return JsonSchema::array(self::building());
    } 
    /* ... */
}

```
Doing this creates one place that gives us an overview of all domain specific schema definitions and we can simply
change them if requirements change.

*Note: Even if we only use "name" in message payload for building names we use a more precise method name in Schema.
A message defines the context so we can use a shorter payload key but the schema should be explicit.*

You can now replace all schema definitions.

`src/Api/Command`

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;
use Prooph\EventEngine\JsonSchema\JsonSchema;

class Command implements EventEngineDescription
{
    const ADD_BUILDING = 'AddBuilding';

    /**
     * @param EventEngine $eventEngine
     */
    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerCommand(
            Command::ADD_BUILDING,
            JsonSchema::object(
                [
                    Payload::BUILDING_ID => Schema::buildingId(),
                    Payload::NAME => Schema::buildingName(),
                ]
            )
        );


    }
}

```

`src/Api/Event`

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Prooph\EventEngine\EventEngine;
use Prooph\EventEngine\EventEngineDescription;
use Prooph\EventEngine\JsonSchema\JsonSchema;

class Event implements EventEngineDescription
{
    const BUILDING_ADDED = 'BuildingAdded';

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
    }
}

```
`src/Api/Query`

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Infrastructure\Finder\BuildingFinder;
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

    public static function describe(EventEngine $eventEngine): void
    {
        //Default query: can be used to check if service is up and running
        $eventEngine->registerQuery(self::HEALTH_CHECK) //<-- Payload schema is optional for queries
            ->resolveWith(HealthCheckResolver::class) //<-- Service id (usually FQCN) to get resolver from DI container
            ->setReturnType(Schema::healthCheck()); //<-- Type returned by resolver

        $eventEngine->registerQuery(self::BUILDING, JsonSchema::object([
            Payload::BUILDING_ID => Schema::buildingId(),
        ]))
            ->resolveWith(BuildingFinder::class)
            ->setReturnType(Schema::building());

        $eventEngine->registerQuery(
            self::BUILDINGS,
            JsonSchema::object(
                [],
                [Payload::NAME => JsonSchema::nullOr(Schema::buildingNameFilter())]
            )
        )
            ->resolveWith(BuildingFinder::class)
            ->setReturnType(Schema::buildingList());
    }
}

```

We're done with the refactoring and ready to add the next use case. Head over to part VI.