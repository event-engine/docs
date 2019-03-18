<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider\Policy;

final class FreeShipping
{
    private $minTotal;

    public static function fromInt(int $minTotal): self
    {
        return new self($minTotal);
    }

    private function __construct(int $minTotal)
    {
        $this->minTotal = $minTotal;
    }

    public function toInt(): int
    {
        return $this->minTotal;
    }

    public function isFree(int $orderSum): bool
    {
        return $orderSum >= $this->minTotal;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->minTotal === $other->minTotal;
    }

    public function __toString(): string
    {
        return (string)$this->minTotal;
    }
}
