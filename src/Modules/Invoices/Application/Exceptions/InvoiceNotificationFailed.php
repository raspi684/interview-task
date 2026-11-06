<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Exceptions;

use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Throwable;

final class InvoiceNotificationFailed extends RuntimeException
{
    public static function forInvoice(UuidInterface $id, Throwable $previous): self
    {
        return new self(
            sprintf('Notification for invoice %s could not be requested.', $id->toString()),
            previous: $previous,
        );
    }
}
