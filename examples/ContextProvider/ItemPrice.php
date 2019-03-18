<?php

declare(strict_types=1);

namespace ProophExample\ContextProvider;

use ProophExample\ContextProvider\Price\Amount;
use ProophExample\ContextProvider\Price\Currency;

final class ItemPrice
{
    private $amount;
    private $currency;

    public static function fromArray(array $data): self
    {
        return new self(
            Amount::fromInt($data['amount'] ?? 0),
            Currency::fromString($data['currency'] ?? '')
        );
    }

    private function __construct(Amount $amount, Currency $currency)
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }

    /**
     * @return Amount
     */
    public function amount(): Amount
    {
        return $this->amount;
    }

    /**
     * @return Currency
     */
    public function currency(): Currency
    {
        return $this->currency;
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount->toInt(),
            'currency' => $this->currency->toString(),
        ];
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
