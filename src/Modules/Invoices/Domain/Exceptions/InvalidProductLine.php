<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;

final class InvalidProductLine extends DomainException
{
    public static function emptyName(): self
    {
        return new self('Product line name must not be empty.');
    }

    public static function negativeQuantity(int $quantity): self
    {
        return new self(sprintf('Product line quantity must not be negative, %d given.', $quantity));
    }

    public static function negativeUnitPrice(int $unitPrice): self
    {
        return new self(sprintf('Product line unit price must not be negative, %d given.', $unitPrice));
    }
}
