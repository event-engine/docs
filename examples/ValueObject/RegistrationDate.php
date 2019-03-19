<?php
declare(strict_types=1);

namespace ProophExample\ValueObject;

final class RegistrationDate
{
    public const FORMAT = 'Y-m-d\TH:i:s.u';

    /**
     * @var \DateTimeImmutable
     */
    private $date;

    public static function fromDateTime(\DateTimeImmutable $date): self
    {
        $date = self::ensureUTC($date);

        return new self($date);
    }

    public static function fromString(string $date): self
    {
        if (\strlen($date) === 19) {
            $date = $date . '.000';
        }

        $date = \DateTimeImmutable::createFromFormat(
            self::FORMAT,
            $date,
            new \DateTimeZone('UTC')
        );

        $date = self::ensureUTC($date);

        return new self($date);
    }

    private function __construct(\DateTimeImmutable $date)
    {
        $this->date = $date;
    }

    public function toString(): string
    {
        return $this->date->format(self::FORMAT);
    }

    public function dateTime(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function add(\DateInterval $interval): self
    {
        return new self($this->date->add($interval));
    }

    public function sub(\DateInterval $interval): self
    {
        return new self($this->date->sub($interval));
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function ensureUTC(\DateTimeImmutable $date): \DateTimeImmutable
    {
        if ($date->getTimezone()->getName() !== 'UTC') {
            $date = $date->setTimezone(new \DateTimeZone('UTC'));
        }

        return $date;
    }
}
