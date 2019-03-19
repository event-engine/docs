<?php
declare(strict_types=1);

namespace ProophExample\ValueObject;

final class Version
{
    private $version;

    public static function fromFloat(float $version): self
    {
        return new self($version);
    }

    private function __construct(float $version)
    {
        $this->version = $version;
    }

    public function toFloat(): float
    {
        return $this->version;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->version === $other->version;
    }

    public function __toString(): string
    {
        return (string)$this->version;
    }

}
