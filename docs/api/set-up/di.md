# Dependencies

{.alert .alert-info}
When initializing Event Engine - `EventEngine::initialize()` or `EventEngine::fromCachedConfig()` - you have to pass a couple
of mandatory and optional dependencies. This page serves as an overview and provides links to the docs of each dependency.

## Schema

{.alert .alert-warning}
**Mandatory Dependency**


An `EventEngine\Schema\Schema` is either required in the Event Engine constructor or as the first argument of `EventEngine::fromCachedConfig()`.
The latter method is used when initializing Event Engine from a cached config. [Production Optimization](/api/set-up/production_optimization.html) contains
further information.

@TODO Link to schema docs

{.alert .alert-info}
[event-engine/php-json-schema](#){: class="alert-link"} (@TODO add link) provides a JSON Schema implementation of `EventEngine\Schema\Schema`.

{.alert .alert-light}
A Schema implementation is the only dependency required in the constructor. Event Engine needs the schema to validate message schema defined in the **description phase**.
All other dependencies are first required when initializing Event Engine. `EventEngine::fromCachedConfig()` skips **description phase** and **initialize phase**, therefor
it requires all dependencies along with the cached config.

## Flavour

{.alert .alert-warning}
**Mandatory Dependency**

A Flavour is the gateway between Event Engine and your code. Three different Flavours are available and you can implement your own `EventEngine\Runtime\Flavour` if needed.
Learn more about [Flavours](#) (@TODO add link).

## Event Store

{.alert .alert-warning}
**Mandatory Dependency**

Event Engine inspects the event store dependency. If you provide an `EventEngine\EventStore\EventStore` only, the **MultiModeStore** mode gets disabled.
If you pass a `EventEngine\Persistence\MultiModelStore` instead, Event Engine makes use of it automatically.

{.alert .alert-light}
You can pass a `MultiModelStore` as `EventStore` because the MultiModelStore is a composition of the event store and document store.

- [Event Store details](#) (@TODO add link)
- [Multi Model Store details](#) (@TODO add link)

## LogEngine

{.alert .alert-warning}
**Mandatory Dependency**

To be able to provide rich logging capabilities, Event Engine requires a `EventEngine\Logger\LogEngine`. The LogEngine is responsible for translating
high level logging information into the format required by the low level logger. A PSR-3 compatible low level logger is included in the [logging package](#) (@TODO add link).

## PSR-11 Container

{.alert .alert-warning}
**Mandatory Dependency**

Whenever a component in the stack requires further dependencies you can configure a **service id** in the appropriate [Description](/api/descriptions/) and Event Engine will use that service id to pull
the component from the [PSR-11 container](https://github.com/php-fig/container) when needed. Typical components that have dependencies are: *Resolvers, ContextProviders and Projectors*.
 
{.alert .alert-success}
A specific container implementation is not required! Anyway, Event Engine wants to keep things simple and straightforward. Therefor, it provides a lightweight container implementation called
**Discolight**. Make sure to [try it out](/api/discolight.html). Maybe it's an eye opener ;).

## Document Store

{.alert .alert-light}
**Optional Dependency**
 
The `EventEngine\DocumentStore\DocumentStore` is only required if you **a) do not use a MultiModelStore** and **b) use the built-in aggregate projector** instead.

Learn more about the [Document Store](/api/document-store/) and the [Aggregate Projector](/api/projections/aggregate_projector.html).

## Event Queue

{.alert .alert-light}
**Optional Dependency**

Newly recorded events are dispatched automatically by Event Engine within the same PHP process. If you wish to publish them on a message queue instead, provide an implementation of
`EventEngine\Messaging\MessageProducer` and Event Engine will forward all recorded events to it.

Details about event publishing can be found [here](#) (@TODO add link).
