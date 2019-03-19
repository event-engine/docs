<?php
declare(strict_types=1);

namespace ProophExample\ValueObject;

final class GivenNameList
{
    /**
     * @var GivenName[]
     */
    private $items;

    /**
     * @var string[]
     */
    private $rawValues;

    public static function emptyList(): self
    {
        return new self([]);
    }

    public static function fromItems(GivenName ...$items): self
    {
        $rawValues = \array_map(function (GivenName $item) {
            return $item->toString();
        }, $items);

        return new self($rawValues, ...$items);
    }

    public static function fromArray(array $items): self
    {
        return new self($items, ...array_map(function (string $item) {
            return GivenName::fromString($item);
        }, $items));
    }

    private function __construct(array $rawValues, GivenName ...$items)
    {
        $this->items = $items;
        $this->rawValues = \array_values($rawValues);
    }

    public function push(GivenName $item): self
    {
        $copy = clone $this;
        $copy->items[] = $item;
        $copy->rawValues[] = $item->toString();
        return $copy;
    }

    public function pop(): self
    {
        $copy = clone $this;
        \array_pop($copy->items);
        \array_pop($copy->rawValues);
        return $copy;
    }

    public function first(): ?GivenName
    {
        return $this->items[0] ?? null;
    }

    public function last(): ?GivenName
    {
        if (count($this->items) === 0) {
            return null;
        }

        return $this->items[count($this->items) - 1];
    }

    public function contains(GivenName $item): bool
    {
        foreach ($this->items as $item) {
            if($item->equals($item)) {
                return true;
            }
        }

        return false;
    }


    /**
     * @return GivenName[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return $this->rawValues;
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
