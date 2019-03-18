<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider\Price;

final class Currency
{
    private $currency;

    public static function fromString(string $currency): self
    {
        return new self($currency);
    }

    private function __construct(string $currency)
    {
        $this->currency = $currency;
    }

    public function toString(): string
    {
        return $this->currency;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return $this->currency;
    }
}
