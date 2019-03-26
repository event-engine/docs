# Part IV - Projections and Queries

In part III of the tutorial we successfully implemented the first write model use case: *Add a new building*.
Connect to the Postgres database and check the event stream table `_4228e4a00331b5d5e751db0481828e22a2c3c8ef`.
The table should contain the first domain event yielded by the `Building` aggregate.

no | event_id | event_name | payload | metadata | created_at
---|-----------|------------|--------|--------|---------
1 | bce42506-...| BuildingAdded | {"buildingId":"9ee8d8a8-...","name":"Acme Headquarters"} | {"_aggregate_id": "9ee8d8a8-...", "_causation_id": "e482f5b8-...", "_aggregate_type": "Building", "_causation_name": "AddBuilding", "_aggregate_version": 1} | 2018-02-14 22:09:32.039848

*If you're wondering why the event stream table has a sha1 hashed name this is because by default prooph/event-store uses that
naming strategy to avoid database vendor specific character constraints. You can however configure a different
naming strategy if you don't like it.*

The write model only needs an event stream to store information but the read side has a hard time querying it.
As long as we only have a few events in the stream queries are simple and fast. But over time this table will
grow and contain many different events. To stay flexible we need to
separate the write side from the read side. An event sourced system normally uses **projections** to
create materialized views of the application state and keep them in sync with the write model.

{.alert .alert-warning}
The problem with projections is [eventual consistency](https://en.wikipedia.org/wiki/Eventual_consistency){: class="alert-link"}.
A highly distributed system has to deal with eventual consistency. In fact, many modern systems already deal with it in one way or the other, 
for example if you use Elastic Search or Redis next to your primary database.

{.alert .alert-success}
However, Event Engine gives you fine grained control of consistency versus performance and availability. All through an easy to use
high level API. The tutorial only covers the tip of the iceberg. But for now it's enough to know that you have many options.
Once you've internalized the basics, you can customize the skeleton to meet your needs.

## Permanent Snapshots

As already discussed in the last tutorial part, Event Engine offers an alternative to async **projections** namely the `MultiModelStore`.
You can think of it like a projection or a snapshot mechanism that runs within the same transaction as the write model does.
The result is a snapshot of the aggregate state that is always in sync with its persisted events. 
Hence, it is safe to rely on the state without worrying about eventual consistency issues.

If you look at the `buildings` table of the Postgres DB, you should see one row with two columns `id` and `doc` 
with id being the buildingId and doc being the JSON representation of the `Building\State`.

{.alert .alert-light}
Event Engine allows you to start with permanent snapshots and switch to async projections later by adjusting the configuration.


## Query, Resolver and Return Type

We already know that Event Engine uses JSON Schema to describe message types and define validation rules.
For queries we can also register **return types** in Event Engine and those return types will appear in the **Model** section of the Swagger UI.

Registering types is done in `src/Domain/Api/Type`.

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\Type\ObjectType;
use MyService\Domain\Model\Building;
use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;

class Type implements EventEngineDescription
{
    const BUILDING = 'Building';

    private static function building(): ObjectType
    {
        return JsonSchema::object([
            Building\State::BUILDING_ID => JsonSchema::uuid(),
            Building\State::NAME => JsonSchema::string(),
        ]);
    }

    /**
     * @param EventEngine $eventEngine
     */
    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerType(self::BUILDING, self::building());
    }
}

```

We describe `Building\State` using JSON Schema and register the type in Event Engine.

{.alert .alert-warning}
*Note: Using aggregate state as return type for queries couples the write model with the read model.
However, you can replace the return type definition at any time. So we can use the short cut
in an early stage and switch to a decoupled version later.*

Next step is to register the query in `src/Domain/Api/Query`:

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\JsonSchema\JsonSchema;

class Query implements EventEngineDescription
{
    const BUILDING = 'Building';

    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerQuery(self::BUILDING, JsonSchema::object([
            'buildingId' => JsonSchema::uuid(),
        ]))
            ->resolveWith(/* ??? */)
            ->setReturnType(JsonSchema::typeRef(Type::BUILDING));
    }
}

```

Queries are named like the "things" they return. This results in a clean and easy to use message box schema.

{.alert .alert-light}
Please note that the return type is a reference: `JsonSchema::typeRef()`.

Last but not least, the query needs to be handled by a so-called resolver.
Create a new class called `BuildingResolver` in a new directory `Resolver` in `src/Domain`.

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver;

use EventEngine\Messaging\Message;
use EventEngine\Querying\Resolver;

final class BuildingResolver implements Resolver
{
    /**
     * @param Message $query
     * @return mixed
     */
    public function resolve(Message $query)
    {
        // TODO: Implement resolve() method.
    }
}

```

The `BuildingResolver` implements `EventEngine\Querying\Resolver`. It receives the query message as the only argument.

Task of the resolver is to query the read model. While looking at snapshots and projections we briefly discussed
Event Engine's `DocumentStore` API. The resolver can use it to access documents organized in collections. Let's see
how that works.

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\Messaging\Message;
use EventEngine\Querying\Resolver;

final class BuildingResolver implements Resolver
{
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
        $buildingId = $query->get('buildingId');

        $buildingDoc = $this->documentStore->getDoc('buildings', $buildingId);

        if(!$buildingDoc) {
            throw new \RuntimeException("Building not found", 404);
        }

        return $buildingDoc['state'];
    }
}

```

The implementation is self explanatory, but a few notes should be made.

Each Event Engine message has a `get` and a `getOrDefault`
method which are both short cuts to access keys of the message payload. The difference between the two is obvious.
If the payload key is NOT set and you use `get` the message will throw an exception. If the payload key is NOT set and you use
`getOrDefault` you get back the default passed as the second argument.

The second note is about `$buildingDoc['state']`. The document store returns raw data stored as a document.
And because we use the `MultiModeStore` to persist aggregate state, it is stored as a snapshot of the form:

```json
{
  "id": "<aggregate_id>",
  "doc": {
    "state": {"state":  {"of":  "the aggregate"}},
    "version": 1
  }
}
```
We don't want to expose internal storage format to public consumers. Therefor, the resolver only returns 
what is stored in the `state` property of the document. 

{.alert .alert-light}
We could also use a DTO instead of returning an array to add more type safety to the resolver. The DTO should either has a `toArray` method
or implement `\JsonSerializable` so that the message box can turn it into a JSON response. By the way, the message box is part of
the skeleton. Feel free to adjust it, if you want to use a serializer library.

Finally, we need to configure Event Engine's DI container to inject the dependencies into our new resolver.

## Discolight PSR-11 Container

Event Engine can use any PSR-11 compatible container. By default it uses a very simple implementation called `Discolight` included
in the Event Engine package family. The DI container is inspired by `bitExpert/disco` but removes the need for annotations.
Dependencies are aggregated in a single `ServiceFactory` class which is located directly in `src`.

The `ServiceFactory` pulls dependencies from modules. The skeleton organizes modules by system layers:

- **Domain**: contains everything related to the business logic
- **Http**: contains the message box and other PSR-15 middleware
- **Persistence**: contains classes and definitions related to storage
- **System**: contains things like a logger and the default HealthCheckResolver

Each module has a `<Module>Services` trait, which is loaded into the `ServiceFactory`. It's an easy way to group dependencies using
plain PHP. No configuration files, no magic and IDE support without extra plugins.

{.alert .alert-light}
When working with Event Engine you'll recognize that you don't need a heavy DI container. You have a single message box instead of a growing 
number of controllers. You don't have heavy application services, but small single purpose ones like the resolver we've just added.
You don't need repositories for entities, because Event Engine manages persistence based on aggregate logic and descriptions.
In fact, the entire CQRS / ES application architecture ensures that you use small building blocks and coordinate them by using messages.

To set up the `BuildingResolver` adjust `src/Domain/DomainServices.php`:

```php
<?php
declare(strict_types=1);

namespace MyService\Domain;

use MyService\Domain\Api\Aggregate;
use MyService\Domain\Api\Command;
use MyService\Domain\Api\Event;
use MyService\Domain\Api\Listener;
use MyService\Domain\Api\Projection;
use MyService\Domain\Api\Query;
use MyService\Domain\Api\Type;
use MyService\Domain\Resolver\BuildingResolver;

trait DomainServices
{
    public function buildingResolver(): BuildingResolver
    {
        return $this->makeSingleton(BuildingResolver::class, function () {
            return new BuildingResolver($this->documentStore());
        });
    }
    
    public function domainDescriptions(): array
    {
        return [
            Type::class,
            Command::class,
            Event::class,
            Query::class,
            Aggregate::class,
            Projection::class,
            Listener::class,
        ];
    }
}

```

{.alert .alert-light}
A few notes about the trait. It uses methods defined in other `<Module>Services` traits or in the main `ServiceFactory`.
PHPStorm can suggest and resolve methods, because all traits are combined in the ServiceFactory. This allows you to quickly navigate
the dependency tree. Something that is really painful when using most other DI container solutions. 

{.alert .alert-light}
`$this->makeSingleton()` is a helper method that turns the requested service in a singleton. All subsequent calls to `buildingResolver()` will
return the same instance instead of a new one. Return services directly if the container should provide a new instance on each `$container->get(Service::class)` call.

{.alert .alert-light}
`domainDescriptions()` is a method required by the main `ServiceFactory`. It returns all Event Engine descriptions of the module so that
they are registered in Event Engine.

Finally, tell Event Engine that the `BuildingResolver` is responsible for the `Building` query:

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

    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerQuery(self::BUILDING, JsonSchema::object([
            'buildingId' => JsonSchema::uuid(),
        ]))
            ->resolveWith(BuildingResolver::class)
            ->setReturnType(JsonSchema::typeRef(Type::BUILDING));
    }
}

```
Ok! We should be able to query buildings by buildingId now. Switch to Swagger and reload the schema (press the "explore" button).
The Documentation Explorer should show a new Query:  `Building`.
If we send that query with the `buildingId` used in `AddBuilding`:

```json
{
  "buildingId": "9ee8d8a8-3bd3-4425-acee-f6f08b8633bb"
}
```
We get back:

```json
{
  "name": "Acme Headquarters",
  "buildingId": "9ee8d8a8-3bd3-4425-acee-f6f08b8633bb"
}
```

Awesome, isn't it?

## Optional Query Arguments

Resolvers can also handle multiple queries. This is useful when different queries can be resolved by accessing the same
read model collection. A second query for the `BuildingResolver` would be one that lists all buildings or a subset filtered
by name.

Add the query to `src/Domain/Api/Query`:

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
            'buildingId' => JsonSchema::uuid(),
        ]))
            ->resolveWith(BuildingResolver::class)
            ->setReturnType(JsonSchema::typeRef(Type::BUILDING));

        //New query
        $eventEngine->registerQuery(
            self::BUILDINGS,
            JsonSchema::object(
                [], //No required arguments for this query
                //Optional argument name, is a nullable string
                ['name' => JsonSchema::nullOr(JsonSchema::string()->withMinLength(1))]
            )
        )
            //Resolve query with same resolver ...
            ->resolveWith(BuildingResolver::class)
            //... but return an array of Building type
            ->setReturnType(JsonSchema::array(
                JsonSchema::typeRef(Type::BUILDING)
            ));
    }
}

```

The refactored `BuildingResolver` looks like this:

```php
<?php
declare(strict_types=1);

namespace MyService\Domain\Resolver;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\DocumentStore\Filter\AnyFilter;
use EventEngine\DocumentStore\Filter\LikeFilter;
use EventEngine\Messaging\Message;
use EventEngine\Querying\Resolver;
use MyService\Domain\Api\Query;

final class BuildingResolver implements Resolver
{
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
                return $this->resolveBuilding($query->get('buildingId'));
            case Query::BUILDINGS:
                return $this->resolveBuildings($query->getOrDefault('name', null));
        }
    }

    private function resolveBuilding(string $buildingId): array
    {
        $buildingDoc = $this->documentStore->getDoc('buildings', $buildingId);

        if(!$buildingDoc) {
            throw new \RuntimeException("Building not found", 404);
        }

        return $buildingDoc['state'];
    }

    private function resolveBuildings(string $nameFilter = null): array
    {
        $filter = $nameFilter?
            new LikeFilter('state.name', "%$nameFilter%")
            : new AnyFilter();

        $cursor = $this->documentStore->filterDocs('buildings', $filter);

        $buildings = [];

        foreach ($cursor as $doc) {
            $buildings[] = $doc['state'];
        }

        return $buildings;
    }
}

```

`BuildingResolver` can resolve both queries by mapping the query name to an internal `resolve*` method.
For the new `Buildings` query the resolver makes use of `DocumentStore\Filter`s. The `LikeFilter` works the same way as
a SQL like expression using `%` as a placeholder. `AnyFilter` matches any documents in the collection.
There are many more filters available. Read more about filters in the docs (@TODO: link docs).

You can test the new query using Swagger. 
This is an example query with a name filter:

```json
{
  "name": "Acme"
}
```
Add some more buildings and play with the queries. Try to exchange the `LikeFilter` with an `EqFilter` for example.
Or see what happens if you pass an empty string as name filter.

{.alert .alert-info}
Since aggregate state is stored as a snapshot, we need to keep in mind that **state** is the root property. Nested keys can be
referenced using **dot notation** (f.e. "state.name"). If you don't use the MultiModelStore and create your own read models, 
then you don't need that snapshot format. But remember: without the MultiModelStore you definitely need to deal 
with eventual consistency whenever reading data from a read model. 

In part VI we get back to the write model and learn how to work with process managers. But before we continue,
we should clean up our code a bit. Part V describes what we can improve.







