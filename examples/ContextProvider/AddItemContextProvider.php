<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider;

use Prooph\EventMachine\Aggregate\ContextProvider;
use Prooph\EventMachine\Messaging\Message;
use ProophExample\ContextProvider\Api\Payload;
use ProophExample\ContextProvider\Policy\FreeShipping;
use ProophExample\ContextProvider\ShoppingCart\AddItemContext;

final class AddItemContextProvider implements ContextProvider
{
    /**
     * @var PriceFinder
     */
    private $priceFinder;

    /**
     * @param Message $command
     * @return mixed The context passed as last argument to aggregate functions
     */
    public function provide(Message $command)
    {
        $itemId = ItemId::fromString($command->get(Payload::ITEM_ID));
        $itemPrice = $this->priceFinder->findItemPrice($itemId);

        $item = Item::withIdAndPrice($itemId, $itemPrice);
        $freeShipping = FreeShipping::fromInt(4000);

        return AddItemContext::fromRecordData(['item' => $item, 'freeShipping' => $freeShipping]);
    }
}
