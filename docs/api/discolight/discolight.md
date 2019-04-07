# Discolight

Event Engine was initially designed as a workshop framework, which is still noticeable in its design. *Discolight* is one of the nice concepts carried over from workshops
into production grade code. 

{.alert .alert-light}
*Credits*: Discolight is inspired by [bitExpert/disco](https://github.com/bitExpert/disco) but removes the need for annotations.

{.alert .alert-info}
Discolight is a very small package. It emphasis "Hand-written service containers" similar to what Matthias Noback suggests in this [blog post](https://matthiasnoback.nl/2019/03/hand-written-service-containers/).

## Installation

```bash
composer require event-engine/discolight
```

## Service Factory

If you walked your way through the tutorial, you already know about Discolight. The [skeleton app](https://github.com/event-engine/php-engine-skeleton)
comes preconfigured with it.

You're asked to provide a `ServiceFactory`, that contains a public factory method for each dependency. 
Such a [class](https://github.com/event-engine/php-engine-skeleton/blob/master/src/ServiceFactory.php) is included in the skeleton. 

{.alert .alert-light}
*Note:* The skeleton organizes factory methods in module specific traits (`src/Domain/DomainServices.php`, `src/Persistence/PersistenceServices.php`, ...) to keep dependencies manageable.
But that's only a suggestion. Each service trait becomes part of the main service factory at runtime. You could also put all methods in one class or organize the traits differently.

 

## Service Ids

The service factory does not need to implement a specific interface. Instead, Discolight scans it and treats **all public methods** of the class as service factory methods.
The return type of a factory method becomes the **service id**.

Let's look at the [method](https://github.com/event-engine/php-engine-skeleton/blob/master/src/Persistence/PersistenceServices.php#L47) which provides the service `EventEngine\Persistence\MultiModelStore`:

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

A lot of stuff going on here, so we'll look at it step by step.

```php
public function multiModelStore(): MultiModelStore
```

It's a `public` method of the `ServiceFactory`, therefor `EventEngine\Persistence\MultiModelStore` becomes the **service id**.
This means that you can do the following to get the multi model store from Discolight:

```php
$store = $discolight->get(MultiModelStore::class);
```

## Singleton Service

In most cases we want to get the same instance of a service from the container no matter how often we request it. This is called a `Singleton`.
Discolight is dead simple. It does not know anything about singletons. Instead we use a pattern called [memoization](https://en.wikipedia.org/wiki/Memoization)
to cache the instance of a service in memory and return it from cache on subsequent calls.

The `ServiceFactory` is userland implementation. No interface implementation required. To add memoization to your service factory use the provided
trait `EventEngine\Discolight\ServiceRegistry` like it is done in the skeleton service factory.

```php
final class ServiceFactory
{
    use ServiceRegistry;
    /* use service traits ... */
```

Now you can store service instances in memory:

```php
public function multiModelStore(): MultiModelStore
{
    return $this->makeSingleton(MultiModelStore::class, function () {
        //...
    });
}
```

You might recognize that we use `MultiModelStore::class` again as service id for the registry. The second argument of `makeSingleton` is a closure which acts
as a **factory function** for the service. When `MultiModelStore::class` is not in the cache, the factory function is called otherwise the service is returned from the registry.

## Injecting Dependencies

Often one service depends on other services. The multi model store requires a `TransactionalConnection` an `EventStore` and a `DocumentStore`
and because all services are provided by the same `ServiceFactory` we can simply get those services by calling the appropriate methods.

{.alert .alert-light}
By default a closure is bound to its parent scope (the service factory instance in this case). Hence, insight the closure we have
access to all methods of the service factory no matter if they are declared public, protected or private.

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


The multi model store interface is service id and return type at the same time. Therefor, PHP's type system ensures at runtime that a valid store is returned.
Internally, we built a `ComposedMultiModelStore`. If we want to switch the store
we can return another implementation.

## Configuration

Another thing that is out of scope for Discolight is application configuration. Remember: *providing a working `ServiceFactory` is your task*. When services
need configuration then pass it to the ServiceFactory. The skeleton uses environmental variables mapped to config params in
[config/autoload/global.php](https://github.com/event-engine/php-engine-skeleton/blob/master/config/autoload/global.php#L14).

The configuration array is then passed to the `ServiceFactory` in the constructor and wrapped with an `ArrayReader`:

```php
final class ServiceFactory
{
    use ServiceRegistry;

    //...

    public function __construct(array $appConfig)
    {
        $this->config = new ArrayReader($appConfig);
    }
```

This way we have access to the configuration when building our services. We can see this in action in the [factory method](https://github.com/event-engine/php-engine-skeleton/blob/master/src/Persistence/PersistenceServices.php#L25) of the `\PDO` connection:

```php
public function pdoConnection(): \PDO
{
    return $this->makeSingleton(\PDO::class, function () {
        $this->assertMandatoryConfigExists('pdo.dsn');
        $this->assertMandatoryConfigExists('pdo.user');
        $this->assertMandatoryConfigExists('pdo.pwd');
        return new \PDO(
            $this->config()->stringValue('pdo.dsn'),
            $this->config()->stringValue('pdo.user'),
            $this->config()->stringValue('pdo.pwd')
        );
    });
}
```

`$this->assertMandatoryConfigExists(/*...*/)` is a helper function of the `ServiceFactory` marked as private. It is ignored by Discolight but we can use
it within factory methods.

```php
private function assertMandatoryConfigExists(string $path): void
{
    if(null === $this->config->mixedValue($path)) {
        throw  new \RuntimeException("Missing application config for $path");
    }
}
```

## Service Alias

In some cases using a full qualified class name (FQCN) of an interface or class as service id is not suitable. In such a case you can configure an **alias**
like shown in the example:


```php
$serviceFactory = new \MyService\ServiceFactory($config);

$container = new \EventEngine\Discolight\Discolight(
    $serviceFactory,
    [PostgresEventStore::class => 'prooph.event_store']
);
```

You pass a map of **service id => alias name** as second argument to Discolight.

## Production Optimization

Discolight uses `\Reflection` to scan the ServiceFactory class and find out about public factory methods and their return types.
It's a myth that reflection is slow. However, rescanning the ServiceFactory on every request in a production environment just does not make sense.
Code does not change, so doing it once and remember the result is the better option.

```php

$serviceMapCache = null;

if(getenv('APP_ENV') === 'prod' && file_exists('data/ee.cache.php')) {
    //Read cache from file
    $serviceMapCache = require 'data/ee.cache.php';
}

$serviceFactory = new \MyService\ServiceFactory($config);

$discolight = new \EventEngine\Discolight\Discolight(
    $serviceFactory,
    [PostgresEventStore::class => 'prooph.event_store'],
    $serviceMapCache // <-- Pass cache as third argument. If it's NULL a rescan is triggered
);

if(!$serviceMapCache && getenv('APP_ENV') === 'prod') {
    // ServiceFactoryMap is an array
    // var_export turns that array in a string parsable by PHP
    // The cache file itself is a PHP script that returns the array
    file_put_contents(
        'data/ee.cache.php',
        "<?php\nreturn " . var_export($discolight->getServiceFactoryMap(), true) . ';'
    );    
}

```