# Part III - Aggregate State

In part II we took a closer look at pure aggregate functions (implemented as static class methods in PHP because of missing function autoloading capabilities).
Pure functions don't have side effects and are stateless. This makes them easy to test and understand.
But an aggregate without state? How can an aggregate protect invariants (its main purpose) without state?

The aggregate needs a way "to look back". It needs to know what happened in the past
according to its own lifecycle. Without its current state and without information about past changes the aggregate could
only execute business logic and enforce business rules based on the given information of the current command passed to a handling function.
In most cases this is not enough.

The functional programming solution to that problem is to pass the current state (which is computed from the past events recorded by the aggregate)
to each command handling function (except the one handling the first command). This means that aggregate **behaviour** (command handling functions)
and aggregate **state** (a data structure of a certain type) are two different things and separated from each other.
How this is implemented in Event Engine is shown in this part of the tutorial.

## Applying Domain Events

Aggregate state is computed by iterating over all recorded domain events of the aggregate history starting with the oldest event.
Event Engine does not provide a generic way to compute current state, instead the aggregate should have an apply function
for each recorded event. Those apply functions are often prefixed with *when* followed by the event name.

Let's add such a function for our `BuildingAdded` domain event.

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Model;

use MyService\Domain\Api\Event;
use EventEngine\Messaging\Message;

final class Building
{
    public static function add(Message $addBuilding): \Generator
    {
        yield [Event::BUILDING_ADDED, $addBuilding->payload()];
    }

    public static function whenBuildingAdded(Message $buildingAdded): Building\State
    {
        //@TODO: Return new state for the aggregate
    }
}
```
`BuildingAdded` communicates that a new lifecycle of a building was started (new building was added to our system), so the
`Building::whenBuilidngAdded()` function has to return a new state object and does not receive a current state object
as an argument (next when* function will receive one!).

But what does the `State` object look like? Well, you can use whatever you want. Event Engine does not care about a particular
implementation (see docs for details). However, Event Engine ships with a default implementation of an `ImmutableRecord`.
We use that implementation in the tutorial, but it is your choice if you want to use it in your application, too.

Create a `State` class in `src/Domain/Model/Building` (new directory):

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
     * @var string
     */
    private $buildingId;

    /**
     * @var string
     */
    private $name;

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
}

```
{.alert .alert-light}
*Note: You can use PHPStorm to generate the Getter-Methods. You only have to write the private properties and add the doc blocks with @var type hints.
Then use PHPStorm's ability to add the Getter-Methods (ALT+EINF). By default PHPStorm sets a `get*` prefix for each method. However, immutable records don't
have setter methods and don't work with the `get*` prefix. Just change the template in your PHPStorm config: Settings -> Editor -> File and Code Templates -> PHP Getter Method to:*

```
/**
 * @return ${TYPE_HINT}
 */
public ${STATIC} function ${FIELD_NAME}()#if(${RETURN_TYPE}): ${RETURN_TYPE}#else#end
{
#if (${STATIC} == "static")
    return self::$${FIELD_NAME};
#else
    return $this->${FIELD_NAME};
#end
}
```
Now we can return a new `Building\State` from `Building::whenBuilidngAdded()`.

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Model;

use MyService\Domain\Api\Event;
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
}

```

Finally, we have to tell Event Engine about that apply function to complete the `AddBuilding` use case description.
In `src/Domain/Api/Aggregate`:

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
            ->identifiedBy('buildingId')
            ->handle([Building::class, 'add'])
            ->recordThat(Event::BUILDING_ADDED)
            //Map recorded event to apply function
            ->apply([Building::class, 'whenBuildingAdded']);
    }
}

```
We're done with the write model for the first use case. If you send the `AddBuilding` command again using Swagger UI:

```json
{
  "buildingId": "9ee8d8a8-3bd3-4425-acee-f6f08b8633bb",
  "name": "Acme Headquarters"
}
```

... you should receive a new error.

```json
{
  "exception": { 
      "message": "SQLSTATE[42P01]: Undefined table: 7 ERROR:  relation \"building_projection_0_1_0\" does not exist ...",
      "details": "..."
    }
}
```

{.alert .alert-light}
Sorry for that many errors. But learning by mistake is the best way to learn!

## MultiModelStore

The skeleton comes preconfigured with a so called `MultiModelStore`. Such a store is capable of storing events and state of an aggregate in one transaction. 

{.alert .alert-info}
Using a **MultiModelStore** reduces the problem of [eventual consistency](https://en.wikipedia.org/wiki/Eventual_consistency){: class="alert-link"}, which many
see as a main drawback of Event Sourcing. This hybrid approach has its own downsides, discussed in more details in the docs (@TODO: link docs).
However, in Event Engine you can switch between "**state only**", "**events and state**" and "**events only**" mode on a per aggregate basis.
That's really powerful. You can choose the right storage strategy for each scenario and continuously fine tune the system.

The error is caused by a missing state table for our `Building` aggregate. At the beginning of the tutorial we've only set up the **write model event stream**.
By default all recorded events of all aggregates are stored in that stream table. A similar table for aggregate state does not exist. We have to create one for each aggregate.

### Buildings Collection

Add a new php file called `create_collections.php` next to the `create_event_stream.php` file in the `scripts` folder:

```php
<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

$container = require 'config/container.php';

/** @var \EventEngine\DocumentStore\DocumentStore $documentStore */
$documentStore = $container->get(EventEngine\DocumentStore\DocumentStore::class);

if(!$documentStore->hasCollection('buildings')) {
    echo "Creating collection buildings.\n";
    $documentStore->addCollection('buildings');
}

echo "done.\n";

```

Run the script with:

```bash
docker-compose run php php scripts/create_collections.php
```

If you look at the Postgres database, you'll see a new `buildings` table. 

{.alert .alert-light}
Ok, what did we do? 

The `MultiModelStore` is composed of an `EventStore` and a `DocumentStore`. 
Both use the same underlying database which is Postgres in our case and they share the same `\PDO` connection.

`src/Persistence/PersistenceServices.php` contains the actual set up logic:

```php
public function multiModelStore(): MultiModelStore
{
    return $this->makeSingleton(MultiModelStore::class, function () {
        return new ComposedMultiModelStore(
            $this->transactionalConnection(),
            $this->eventEngineEventStore(),
            $this->documentStore()
        );
    });
}
```

This allows the `MultiModelStore` to control the transaction for both. The `DocumentStore` interface is inspired by MongoDB's API.
Postgres can be used as a document store due to **JSON** support. You don't need to worry about the low level JSON API but can instead use
the high level abstraction provided by Event Engine. 

### Aggregate Storage Settings

We've just created a new table in Postgres called `buildings` using the high level `DocumentStore` abstraction provided by Event Engine, 
but the error complains about a missing table called `building_projection_0_1_0`.
Event Engine applies a default naming strategy for aggregate state collections (in case of Postgres collection equals table) if not specified otherwise.

We can tell Event Engine to use the `buildings` collection instead by adding a hint to the aggregate description in `src/Domain/Api/Aggregate.php`:

```php
<?php

declare(strict_types=1);

namespace MyService\Domain\Api;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use MyService\Domain\Model\Building;

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
            ->identifiedBy('buildingId')
            ->handle([Building::class, 'add'])
            ->recordThat(Event::BUILDING_ADDED)
            ->apply([Building::class, 'whenBuildingAdded'])
            ->storeStateIn('buildings'); //Use buildings collection for aggregate state
    }
}

```

Send the command again:

```json
{
  "buildingId": "9ee8d8a8-3bd3-4425-acee-f6f08b8633bb",
  "name": "Acme Headquarters"
}
```

This time the command goes through. If everything is fine the message box returns a `202 command accepted` response.
 
{.alert .alert-info} 
Event Engine emphasizes a CQRS and Event Sourcing architecture. For commands this means that no data is returned.
The write model has received and processed the command **AddBuilding** successfully but we don't know what the new
application state looks like. We will use a query, which is the third message type, to get this data.
Head over to tutorial part IV to learn more about queries and application state management using the **MultiModelStore**.

