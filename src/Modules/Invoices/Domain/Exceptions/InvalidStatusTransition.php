<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;
use Modules\Invoices\Domain\Enums\StatusEnum;

final class InvalidStatusTransition extends DomainException
{
    public static function between(StatusEnum $from, StatusEnum $to): self
    {
        return new self(sprintf('Invoice status cannot change from "%s" to "%s".', $from->value, $to->value));
    }
}
