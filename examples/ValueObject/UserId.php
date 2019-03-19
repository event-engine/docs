<?php
declare(strict_types=1);

namespace ProophExample\ValueObject;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UserId
{
    private $userId;

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $userId): self
    {
        return new self(Uuid::fromString($userId));
    }

    private function __construct(UuidInterface $userId)
    {
        $this->userId = $userId;
    }

    public function toString(): string
    {
        return $this->userId->toString();
    }

    public function equals($other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->userId->equals($other->userId);
    }

    public function __toString(): string
    {
        return $this->userId->toString();
    }

}
