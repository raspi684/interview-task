<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;

final class InvalidCustomer extends DomainException
{
    public static function emptyName(): self
    {
        return new self('Customer name must not be empty.');
    }

    public static function invalidEmail(string $email): self
    {
        return new self(sprintf('Customer e-mail "%s" is not a valid e-mail address.', $email));
    }
}
