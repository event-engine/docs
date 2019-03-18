<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider\Api;

final class Event
{
    const SHOPPING_SESSION_STARTED = Message::CTX.'ShoppingSessionStarted';
    const ITEM_ADDED = Message::CTX.'ItemAdded';
    const FREE_SHIPPING_ENABLED = Message::CTX.'FreeShippingEnabled';
    const ITEM_REMOVED = Message::CTX.'ItemRemoved';
}
