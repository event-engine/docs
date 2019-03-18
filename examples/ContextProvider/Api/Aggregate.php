<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider\Api;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use ProophExample\ContextProvider\AddItemContextProvider;
use ProophExample\ContextProvider\ShoppingCart;

final class Aggregate implements EventMachineDescription
{
    const SHOPPING_CART = 'ShoppingCart';

    public static function describe(EventMachine $eventMachine): void
    {
        $eventMachine->process(Command::START_SHOPPING_SESSION)
            ->withNew(self::SHOPPING_CART)
            ->identifiedBy(Payload::SHOPPING_CART_ID)
            ->handle([ShoppingCart::class, 'startShoppingSession'])
            ->recordThat(Event::SHOPPING_SESSION_STARTED)
            ->apply([ShoppingCart::class, 'whenShoppingSessionStarted']);


        $eventMachine->process(Command::ADD_ITEM)
            ->withExisting(self::SHOPPING_CART)
            ->provideContext(AddItemContextProvider::class)
            ->handle([ShoppingCart::class, 'addItem'])
            ->recordThat(Event::ITEM_ADDED)
            ->apply([ShoppingCart::class, 'whenItemAdded'])
            ->andRecordThat(Event::FREE_SHIPPING_ENABLED)
            ->apply([ShoppingCart::class, 'whenFreeShippingEnabled']);
    }
}
