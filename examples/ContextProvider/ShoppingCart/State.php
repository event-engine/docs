<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider\ShoppingCart;

use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;
use ProophExample\ContextProvider\Item;

final class State implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var ShoppingCartId
     */
    private $shoppingCartId;

    /**
     * @var Item[]
     */
    private $items = [];

    /**
     * @var bool
     */
    private $freeShipping = false;

    private static function arrayPropItemTypeMap(): array
    {
        return ['items' => Item::class];
    }

    public static function newSession(ShoppingCartId $shoppingCartId): self
    {
        $self = new self();

        $self->shoppingCartId = $shoppingCartId;

        return $self;
    }

    /**
     * @return ShoppingCartId
     */
    public function shoppingCartId(): ShoppingCartId
    {
        return $this->shoppingCartId;
    }

    /**
     * @return Item[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function hasItem(Item $item): bool
    {
        foreach ($this->items() as $inCartItem) {
            if($inCartItem->equals($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function freeShipping(): bool
    {
        return $this->freeShipping;
    }

    public function orderSum(): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total += $item->price()->amount()->toInt();
        }

        return $total;
    }

    public function withAddedItem(Item $item): State
    {
        $copy = clone $this;
        $copy->items[] = $item;
        return $copy;
    }

    public function withFreeShippingEnabled(): State
    {
        $copy = clone $this;
        $copy->freeShipping = true;
        return $copy;
    }
}
