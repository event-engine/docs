<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider\Api;

final class Command
{
    const START_SHOPPING_SESSION = Message::CTX.'StartShoppingSession';
    const ADD_ITEM = Message::CTX.'AddItem';
}
