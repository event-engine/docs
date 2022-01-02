# Event Engine Tutorial Available

{.alert .alert-light}
*Written by Alexander Miertsch ([@codeliner](https://github.com/codeliner)) - CEO prooph software GmbH - prooph core team - 2019-04-05*

Three weeks ago we've released the [first dev version of Event Engine](https://github.com/event-engine/php-engine/releases/tag/v0.1.0), which supersedes Event Machine.
Now we reached another important milestone towards a stable release. Make sure to check out the brand new Event Engine tutorial: [https://event-engine.github.io/tutorial/](https://event-engine.github.io/tutorial/)
along with a new [skeleton application](https://github.com/event-engine/php-engine-skeleton).

## Repo Split

A prerequisite for the new skeleton was the repo split announced in the release notes of v0.1. 
All packages are listed on [GitHub](https://github.com/event-engine) and [Packagist](https://packagist.org/packages/event-engine/).

## Rewritten Tutorial

The Event Engine tutorial is a rewrite of the previous Event Machine tutorial. The story is the same but it differs in many details like a changed skeleton structure and
the usage of the new **MultiModelStore** feature, which replaces aggregate projections in the default skeleton set up. That said, even if you did the Event Machine tutorial before 
and think you know the basics, I highly recommend to do the new Event Engine tutorial again to quickly learn more about new features and structural changes.

## API Docs

At the time of writing, many API docs are still outdated. You'll find a warning at the top of each page that still needs to be migrated.

## Immutable Objects

We've added new documentation about how to quickly generate immutable objects using PHPStorm templates and the `event-engine/php-data` package.
[Learn more](https://event-engine.github.io/api/immutable_state.html)

## Keep Up-To-Date

Follow us on [twitter](https://twitter.com/prooph_software) and watch changes/releases on the [event-engine repos](https://github.com/event-engine) 
