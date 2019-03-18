<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider\Price;

final class Amount
{
    private $amount;

    public static function fromInt(int $amount): self
    {
        return new self($amount);
    }

    private function __construct(int $amount)
    {
        $this->amount = $amount;
    }

    public function toInt(): int
    {
        return $this->amount;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->amount === $other->amount;
    }

    public function __toString(): string
    {
        return (string)$this->amount;
    }
}
