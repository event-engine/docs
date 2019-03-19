<?php
declare(strict_types=1);

namespace ProophExample\ValueObject;

final class FriendsList
{
    /**
     * @var Person[]
     */
    private $items;

    public static function fromArray(array $items): self
    {
        return new self(...array_map(function (array $item) {
            return Person::fromArray($item);
        }, $items));
    }

    public static function fromItems(Person ...$items): self
    {
        return new self(...$items);
    }

    public static function emptyList(): self
    {
        return new self([]);
    }

    private function __construct(Person ...$items)
    {
        $this->items = $items;
    }

    public function push(Person $item): self
    {
        $copy = clone $this;
        $copy->items[] = $item;
        return $copy;
    }

    public function pop(): self
    {
        $copy = clone $this;
        \array_pop($copy->items);
        return $copy;
    }

    public function first(): ?Person
    {
        return $this->items[0] ?? null;
    }

    public function last(): ?Person
    {
        if (count($this->items) === 0) {
            return null;
        }

        return $this->items[count($this->items) - 1];
    }

    public function contains(Person $item): bool
    {
        foreach ($this->items as $existingItem) {
            if ($existingItem->equals($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Person[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return \array_map(function (Person $item) {
            return $item->toArray();
        }, $this->items);
    }

    public function equals($other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    public function __toString(): string
    {
        return \json_encode($this->toArray());
    }
}
