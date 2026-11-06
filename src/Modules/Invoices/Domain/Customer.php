<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\Exceptions\InvalidCustomer;

final readonly class Customer
{
    public function __construct(
        public string $name,
        public string $email,
    ) {
        if (trim($name) === '') {
            throw InvalidCustomer::emptyName();
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidCustomer::invalidEmail($email);
        }
    }
}
