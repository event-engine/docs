<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider\ShoppingCart;

use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;
use ProophExample\ContextProvider\Item;
use ProophExample\ContextProvider\Policy\FreeShipping;

final class AddItemContext implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var FreeShipping
     */
    private $freeShipping;

    /**
     * @return Item
     */
    public function item(): Item
    {
        return $this->item;
    }

    /**
     * @return FreeShipping
     */
    public function freeShipping(): FreeShipping
    {
        return $this->freeShipping;
    }
}
