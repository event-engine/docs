# Part V - DRY

You may have noticed that we use the static classes in `src/Domain/Api` as a central place to define constants.
At least we did that for messages (Command, Event, Query) and aggregate names. We did not touch `src/Domain/Api/Payload` and
`src/Domain/Api/Schema` yet.

The idea behind those two classes is to group some common constants and static methods so that we don't have to repeat them
over and over again. This makes it much easier to refactor the codebase later.

## Payload

In `src/Domain/Api/Payload` we simply define a constant for each possible message payload key. We've used two keys so far:
`buildingId` and `name` so we should add them ...

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

class Payload
{
    //Predefined keys for query payloads, see MyService\Domain\Api\Schema::queryPagination() for further information
    const SKIP = 'skip';
    const LIMIT = 'limit';

    const BUILDING_ID = 'buildingId';
    const NAME = 'name';
}

```
... and replace plain strings with the constants in our codebase:

`src/Domain/Api/Aggregate`

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


`src/Domain/Api/Command`

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
`src/Domain/Api/Event`

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
`src/Domain/Api/Query`

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\JsonSchema\JsonSchema;
use MyService\Domain\Resolver\BuildingResolver;

class Query implements EventEngineDescription
{
    const BUILDING = 'Building';
    const BUILDINGS = 'Buildings'; //<-- New query, note the plural

    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerQuery(self::BUILDING, JsonSchema::object([
            Payload::BUILDING_ID => JsonSchema::uuid(),
        ]))
            ->resolveWith(BuildingResolver::class)
            ->setReturnType(JsonSchema::typeRef(Type::BUILDING));

        //New query
        $eventEngine->registerQuery(
            self::BUILDINGS,
            JsonSchema::object(
                [], //No required arguments for this query
                //Optional argument name, is a nullable string
                [Payload::NAME => JsonSchema::nullOr(JsonSchema::string()->withMinLength(1))]
            )
        )
            //Resolve query with same finder ...
            ->resolveWith(BuildingResolver::class)
            //... but return an array of Building type
            ->setReturnType(JsonSchema::array(
                JsonSchema::typeRef(Aggregate::BUILDING)
            ));
    }
}

```
`src/Domain/Resolver/BuildingResolver`

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\DocumentStore\Filter\AnyFilter;
use EventEngine\DocumentStore\Filter\LikeFilter;
use EventEngine\Messaging\Message;
use EventEngine\Querying\Resolver;
use MyService\Domain\Api\Payload;
use MyService\Domain\Api\Query;

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
     * @param Message $query
     * @return array
     */
    public function resolve(Message $query): array
    {
        switch ($query->messageName()) {
            case Query::BUILDING:
                return $this->resolveBuilding($query->get(Payload::BUILDING_ID));
            case Query::BUILDINGS:
                return $this->resolveBuildings($query->getOrDefault(Payload::NAME, null));
        }
    }

    private function resolveBuilding(string $buildingId): array
    {
        $buildingDoc = $this->documentStore->getDoc(self::COLLECTION, $buildingId);

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

        $cursor = $this->documentStore->filterDocs(self::COLLECTION, $filter);

        $buildings = [];

        foreach ($cursor as $doc) {
            $buildings[] = $doc[self::STATE];
        }

        return $buildings;
    }
}

```

`scripts/create_collections.php`

```php
<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

$container = require 'config/container.php';

/** @var \EventEngine\DocumentStore\DocumentStore $documentStore */
$documentStore = $container->get(EventEngine\DocumentStore\DocumentStore::class);

if(!$documentStore->hasCollection('buildings')) {
    echo "Creating collection buildings.\n";
    $documentStore->addCollection(
        \MyService\Domain\Resolver\BuildingResolver::COLLECTION
    );
}

echo "done.\n";

```

The `buildings` collection name is now also defined as a constant. Because `BuildingResolver` is responsible for building
related queries, it owns the collection constant. That's not a hard rule but in our case it's a good documentation,
especially in the aggregate description. All information about buildings is in one place now:

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use MyService\Domain\Model\Building;
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
            ->apply([Building::class, 'whenBuildingAdded'])
            ->storeStateIn(BuildingResolver::COLLECTION); //Use buildings collection for aggregate state
    }
}

```


## Schema

Schema definitions are another area where DRY (Don't Repeat Yourself) makes a lot of sense. A good practice is to define
a schema for each payload key and reuse it when registering messages. Type references (JsonSchema::typeRef) should also be wrapped
by a schema method. Open `src/Domain/Api/Schema` and add the static methods:

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\Type\StringType;
use EventEngine\JsonSchema\Type\TypeRef;
use EventEngine\JsonSchema\Type\UuidType;

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
        return JsonSchema::typeRef(Type::BUILDING);
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

{.alert .alert-light}
*Note: Even if we only use "name" in message payload for building names we use a more precise method name in Schema.
A message defines the context so we can use a shorter payload key but the schema should be explicit.*

You can now replace all schema definitions.

`src/Domain/Api/Command`

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

`src/Domain/Api/Event`

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
`src/Domain/Api/Query`

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use App\Infrastructure\Finder\BuildingFinder;
use App\Infrastructure\System\HealthCheckResolver;
use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\JsonSchema\JsonSchema;

class Query implements EventEngineDescription
{
    const BUILDING = 'Building';
    const BUILDINGS = 'Buildings';

    public static function describe(EventEngine $eventEngine): void
    {
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