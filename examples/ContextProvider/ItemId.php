<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ItemId
{
    private $itemId;

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $itemId): self
    {
        return new self(Uuid::fromString($itemId));
    }

    private function __construct(UuidInterface $itemId)
    {
        $this->itemId = $itemId;
    }

    public function toString(): string
    {
        return $this->itemId->toString();
    }

    public function equals($other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->itemId->equals($other->itemId);
    }

    public function __toString(): string
    {
        return $this->itemId->toString();
    }
}
