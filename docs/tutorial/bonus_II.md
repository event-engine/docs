# Bonus II - Unit and Integration Tests

Unit testing the different parts of the application is easy. In most cases we have single purpose classes and
functions that can be tested without mocking.

## Testing Aggregate functions

Aggregate functions are pure which makes them easy to test. php-engine-skeleton provides some test helpers in
`tests/BaseTestCase.php`, so, if you extend from that base class, you're ready to go. Add a the folders `Domain/Model` in `tests`
and a class `BuildingTest` with the following content:

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
        $command = $this->makeCommand(Command::CHECK_IN_USER, [
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
You can run tests with:

```bash
docker-compose run php php vendor/bin/phpunit -vvv
```

## Testing Projectors

Testing projectors is also easy when they use the `DocumentStore` API to manage projections. Event Engine ships with
an `InMemoryDocumentStore` implementation that works great in test cases. Here is an example:

*tests/Domain/Projector/UserBuildingListTest.php*
```php
<?php
declare(strict_types=1);

namespace MyServiceTest\Domain\Projector;

use EventEngine\DocumentStore\Filter\AnyFilter;
use MyService\Domain\Api\Event;
use MyService\Domain\Api\Payload;
use MyService\Domain\Api\Projection;
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

        $johnCheckedIn = $this->makeEvent(Event::USER_CHECKED_IN, [
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

        $janeCheckedIn = $this->makeEvent(Event::USER_CHECKED_IN, [
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

        $johnCheckedOut = $this->makeEvent(Event::USER_CHECKED_OUT, [
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

## Testing Resolvers

Resolvers can be tested in the same manner as projectors, using the `InMemoryDocumentStore` with test data.
I will leave implementing these tests as an exercise for you ;)

## Integration Tests

If you want to test the "whole thing" then you can extend your test class from `IntegrationTestCase`. It sets up Event Engine 
with an `InMemoryEventStore` and an `InMemoryDocumentStore`. A special PSR-11 MockContainer ensures that all other services are mocked.
Let's see it in action. The annotated integration test should be self explanatory.

*tests/Integration/NotifySecurityTest.php*
```php
<?php
declare(strict_types=1);

namespace MyServiceTest\Integration;

use EventEngine\Messaging\Message;
use MyService\Domain\Api\Command;
use MyService\Domain\Api\Event;
use MyService\Domain\Api\Payload;
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

            public function __invoke(Message $event): void
            {
                $this->lastReceivedMessage = $event;
            }

            public function lastReceivedMessage(): Message
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

        $this->assertEquals(Event::DOUBLE_CHECK_IN_DETECTED, $this->uiExchange->lastReceivedMessage()->messageName());

        $this->assertEquals([
            Payload::BUILDING_ID => self::BUILDING_ID,
            Payload::NAME => self::USERNAME
        ], $this->uiExchange->lastReceivedMessage()->payload());
    }
}

```
{.alert .alert-success}
With a solid test suite in place, we can safely start refactoring our code towards a rich domain model. The next bonus part
introduces stricter types for state and messages.

