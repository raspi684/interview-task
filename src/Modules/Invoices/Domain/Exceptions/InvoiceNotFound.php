<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;
use Ramsey\Uuid\UuidInterface;

final class InvoiceNotFound extends DomainException
{
    public static function withId(UuidInterface $id): self
    {
        return new self(sprintf('Invoice %s not found.', $id->toString()));
    }
}
