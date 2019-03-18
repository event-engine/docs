<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider;

interface PriceFinder
{
    public function findItemPrice(ItemId $itemId): ItemPrice;
}
