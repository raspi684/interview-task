<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;

final class InvoiceCannotBeSent extends DomainException
{
    public static function withoutProductLines(): self
    {
        return new self('Invoice without product lines cannot be sent.');
    }

    public static function withNonPositiveProductLine(string $productName): self
    {
        return new self(sprintf(
            'Product line "%s" must have positive quantity and unit price before the invoice can be sent.',
            $productName,
        ));
    }
}
