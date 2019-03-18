<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider\ShoppingCart;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ShoppingCartId
{
    private $cartId;

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $cartId): self
    {
        return new self(Uuid::fromString($cartId));
    }

    private function __construct(UuidInterface $cartId)
    {
        $this->cartId = $cartId;
    }

    public function toString(): string
    {
        return $this->cartId->toString();
    }

    public function equals($other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->cartId->equals($other->cartId);
    }

    public function __toString(): string
    {
        return $this->cartId->toString();
    }
}
