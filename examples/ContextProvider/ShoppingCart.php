<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider;

use Prooph\EventMachine\Messaging\Message;
use ProophExample\ContextProvider\Api\Event;
use ProophExample\ContextProvider\Api\Payload;
use ProophExample\ContextProvider\ShoppingCart\AddItemContext;
use ProophExample\ContextProvider\ShoppingCart\ShoppingCartId;
use ProophExample\ContextProvider\ShoppingCart\State;

final class ShoppingCart
{
    public static function startShoppingSession(Message $startShoppingSession): \Generator
    {
        yield [Event::SHOPPING_SESSION_STARTED, [
            Payload::SHOPPING_CART_ID => $startShoppingSession->get(Payload::SHOPPING_CART_ID),
        ]];
    }

    public static function whenShoppingSessionStarted(Message $shoppingSessionStarted): State
    {
        return State::newSession(ShoppingCartId::fromString(
            $shoppingSessionStarted->get(Payload::SHOPPING_CART_ID)
        ));
    }

    public static function addItem(State $cart, Message $addItem, AddItemContext $context): \Generator
    {
        yield [Event::ITEM_ADDED, [
            Payload::SHOPPING_CART_ID => $addItem->get(Payload::SHOPPING_CART_ID),
            Payload::ITEM => $context->item()->toArray(),
        ]];

        if(!$cart->freeShipping()) {
            //Temporarily add item. We can safely do this, because $cart is immutable
            $cart = $cart->withAddedItem($context->item());

            if($context->freeShipping()->isFree($cart->orderSum())) {
                yield [Event::FREE_SHIPPING_ENABLED, [
                    Payload::SHOPPING_CART_ID => $addItem->get(Payload::SHOPPING_CART_ID),
                ]];
            }
        }
    }

    public static function whenItemAdded(State $cart, Message $itemAdded): State
    {
        return $cart->withAddedItem(Item::fromArray($itemAdded->get(Payload::ITEM)));
    }

    public static function whenFreeShippingEnabled(State $cart, Message $freeShippingEnabled): State
    {
        return $cart->withFreeShippingEnabled();
    }

    public static function removeItem(State $cart, Message $removeItem): \Generator
    {
        $item = Item::fromArray($removeItem->get(Payload::ITEM));

        if(!$cart->hasItem($item)) {
            yield null;
            return;
        }

        yield [Event::ITEM_REMOVED, $removeItem->payload()];
    }
}
