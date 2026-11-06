<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use Modules\Invoices\Domain\Customer;
use Modules\Invoices\Domain\Exceptions\InvalidCustomer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    public function testHoldsNameAndEmail(): void
    {
        $customer = new Customer('John Doe', 'john@example.com');

        $this->assertSame('John Doe', $customer->name);
        $this->assertSame('john@example.com', $customer->email);
    }

    public function testRejectsBlankName(): void
    {
        $this->expectException(InvalidCustomer::class);

        new Customer('   ', 'john@example.com');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'missing at sign' => ['john.example.com'];
        yield 'missing domain' => ['john@'];
    }

    #[DataProvider('invalidEmails')]
    public function testRejectsInvalidEmail(string $email): void
    {
        $this->expectException(InvalidCustomer::class);

        new Customer('John Doe', $email);
    }
}
