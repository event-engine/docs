<?php
declare(strict_types=1);

namespace ProophExample\ValueObject;

final class WriteAccess
{
    private $access;

    public static function fromBool(bool $access): self
    {
        return new self($access);
    }

    private function __construct(bool $access)
    {
        $this->access = $access;
    }

    public function toBool(): bool
    {
        return $this->access;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->access === $other->access;
    }

    public function __toString(): string
    {
        return $this->access ? 'TRUE' : 'FALSE';
    }

}
