<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider;

final class Item
{
    private $id;
    private $price;

    public static function withIdAndPrice(ItemId $itemId, ItemPrice $itemPrice): self
    {
        return new self($itemId, $itemPrice);
    }

    /**
     * @return ItemId
     */
    public function id(): ItemId
    {
        return $this->id;
    }

    /**
     * @return ItemPrice
     */
    public function price(): ItemPrice
    {
        return $this->price;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ItemId::fromString($data['itemId'] ?? ''),
            ItemPrice::fromArray($data['price'] ?? [])
        );
    }

    private function __construct(ItemId $itemId, ItemPrice $itemPrice)
    {
        $this->id = $itemId;
        $this->price = $itemPrice;

    }

    public function toArray(): array
    {
        return [
            'itemId' => $this->id->toString(),
            'price' => $this->price->toArray(),
        ];
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
