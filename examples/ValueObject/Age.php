<?php
declare(strict_types=1);

namespace ProophExample\ValueObject;

final class Age
{
    private $age;

    public static function fromInt(int $age): self
    {
        return new self($age);
    }

    private function __construct(int $age)
    {
        $this->age = $age;
    }

    public function toInt(): int
    {
        return $this->age;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->age === $other->age;
    }

    public function __toString(): string
    {
        return (string)$this->age;
    }

}
