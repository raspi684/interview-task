<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\Exceptions\InvalidProductLine;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class ProductLine
{
    private function __construct(
        public UuidInterface $id,
        public string $productName,
        public int $quantity,
        public int $unitPrice,
    ) {
        if (trim($productName) === '') {
            throw InvalidProductLine::emptyName();
        }

        if ($quantity < 0) {
            throw InvalidProductLine::negativeQuantity($quantity);
        }

        if ($unitPrice < 0) {
            throw InvalidProductLine::negativeUnitPrice($unitPrice);
        }
    }

    public static function create(string $productName, int $quantity, int $unitPrice): self
    {
        // UUIDv7 is time-ordered (monotonic within a millisecond), so lines can be read back in creation order.
        return new self(Uuid::uuid7(), $productName, $quantity, $unitPrice);
    }

    public static function reconstitute(UuidInterface $id, string $productName, int $quantity, int $unitPrice): self
    {
        return new self($id, $productName, $quantity, $unitPrice);
    }

    public function totalUnitPrice(): int
    {
        return $this->quantity * $this->unitPrice;
    }

    public function isSendable(): bool
    {
        return $this->quantity > 0 && $this->unitPrice > 0;
    }
}
