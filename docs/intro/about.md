# About Event Engine

Prooph Event Engine takes away all the boring, time consuming parts of event sourcing to speed up
development of event sourced applications and increase the fun. It can be used for prototypes as well as full featured applications.

## Origin

Event Engine was originally designed as a "workshop framework" for CQRS and Event Sourcing and is inspired by the **Dreyfus model**.

### Beginner friendly

> The Dreyfus model distinguishes five levels of competence, from novice to mastery. At the absolute beginner level people execute tasks based on “rigid adherence to taught rules or plans”. Beginners need recipes. They don’t need a list of parts, or a dozen different ways to do the same thing. Instead what works are step by step instructions that they can internalize. As they practice them over time they learn the reasoning behind them, and learn to deviate from them and improvise, but they first need to feel like they’re doing something.

(source: [https://lambdaisland.com/blog/25-05-2017-simple-and-happy-is-clojure-dying-and-what-has-ruby-got-to-do-with-it](https://lambdaisland.com/blog/25-05-2017-simple-and-happy-is-clojure-dying-and-what-has-ruby-got-to-do-with-it))

### Rapid Application Development
It turned out that Event Engine is not only a very good CQRS and Event Sourcing learning framework but that the same concept
can be used for rapid application development (RAD). RAD frameworks focus on developer happiness and coding speed.
Both can be achieved by using conventions, which allow the framework to do a lot of work "under the hood"
Developers can focus on the important part: **developing the application**.

## Event Engine Flavours

Event Engine Flavours make it possible to turn a rapidly developed prototype into a rock solid application.
You can switch from the default **PrototypingFlavour** to either the **FunctionalFlavour** or **OopFlavour**. Finally, you can implement your own
Flavour to build your very own CQRS / ES framework.

[Learn More](/tutorial/)

## Pros

- Developed and maintained by prooph core team members
- Ready-to-use [skeleton](https://github.com/event-engine/php-engine-skeleton)
- Less code to write
- Guided event sourcing
- extension points to inject custom logic
- Audit log from day one (no data loss)
- Multi-Model-Store 
- Replay functionality
- Projections based on domain events
- PSR friendly http message box
- OpenAPI v3 Swagger integration

## Cons

- Not suitable for monolithic architectures

### You may want to use Event Engine if:

- Your project is in an early stage and you need to try out different ideas or **deliver features very fast**
- You want to establish a **Microservices architecture** rather than building a monolithic system
- You want to automate business processes
- You have to develop a workflow-oriented service
- You're **new to the concepts** of CQRS and Event Sourcing and want to learn them

## Conclusion

Try the [tutorial](/tutorial/) and build a prototype with Event Engine!

## Powered By

[![prooph software](https://github.com/codeliner/php-ddd-cargo-sample/raw/master/docs/assets/prooph-software-logo.png)](http://prooph.de)

Event Engine is maintained by the [prooph software team](http://prooph-software.de/).
Prooph software offers commercial support and workshops for Event Engine as well as for the [prooph components](http://getprooph.org/).

If you are interested please [get in touch](http://getprooph.org/#get-in-touch)