<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ProductLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class InvoiceTest extends TestCase
{
    public function testIsCreatedAsDraft(): void
    {
        $invoice = Invoice::create('John Doe', 'john@example.com', []);

        $this->assertSame(StatusEnum::Draft, $invoice->status());
        $this->assertSame('John Doe', $invoice->customerName());
        $this->assertSame('john@example.com', $invoice->customerEmail());
        $this->assertSame([], $invoice->productLines());
    }

    public function testGeneratesUniqueIdOnCreation(): void
    {
        $first = Invoice::create('John Doe', 'john@example.com', []);
        $second = Invoice::create('John Doe', 'john@example.com', []);

        $this->assertFalse($first->id()->equals($second->id()));
    }

    public function testCalculatesTotalPriceAsSumOfLineTotals(): void
    {
        $invoice = Invoice::create('John Doe', 'john@example.com', [
            ProductLine::create('Consulting', 2, 1500),
            ProductLine::create('Hosting', 3, 200),
        ]);

        $this->assertSame(3600, $invoice->totalPrice());
    }

    public function testTotalPriceIsZeroWithoutProductLines(): void
    {
        $invoice = Invoice::create('John Doe', 'john@example.com', []);

        $this->assertSame(0, $invoice->totalPrice());
    }

    public function testSendMovesDraftToSending(): void
    {
        $invoice = Invoice::create('John Doe', 'john@example.com', [ProductLine::create('Consulting', 1, 100)]);

        $invoice->send();

        $this->assertSame(StatusEnum::Sending, $invoice->status());
    }

    public function testCannotBeSentWithoutProductLines(): void
    {
        $invoice = Invoice::create('John Doe', 'john@example.com', []);

        $this->expectException(InvoiceCannotBeSent::class);

        $invoice->send();
    }

    /** @return iterable<string, array{int, int}> */
    public static function nonPositiveLineValues(): iterable
    {
        yield 'zero quantity' => [0, 100];
        yield 'zero unit price' => [1, 0];
    }

    #[DataProvider('nonPositiveLineValues')]
    public function testCannotBeSentWithNonPositiveProductLine(int $quantity, int $unitPrice): void
    {
        $invoice = Invoice::create('John Doe', 'john@example.com', [
            ProductLine::create('Consulting', 1, 100),
            ProductLine::create('Hosting', $quantity, $unitPrice),
        ]);

        $this->expectException(InvoiceCannotBeSent::class);

        $invoice->send();
    }

    /** @return iterable<string, array{StatusEnum}> */
    public static function nonDraftStatuses(): iterable
    {
        yield 'sending' => [StatusEnum::Sending];
        yield 'sent to client' => [StatusEnum::SentToClient];
    }

    #[DataProvider('nonDraftStatuses')]
    public function testCannotBeSentWhenNotDraft(StatusEnum $status): void
    {
        $invoice = $this->invoiceIn($status);

        $this->expectException(InvalidStatusTransition::class);

        $invoice->send();
    }

    public function testMarkAsSentToClientMovesSendingToSentToClient(): void
    {
        $invoice = $this->invoiceIn(StatusEnum::Sending);

        $invoice->markAsSentToClient();

        $this->assertSame(StatusEnum::SentToClient, $invoice->status());
    }

    /** @return iterable<string, array{StatusEnum}> */
    public static function nonSendingStatuses(): iterable
    {
        yield 'draft' => [StatusEnum::Draft];
        yield 'sent to client' => [StatusEnum::SentToClient];
    }

    #[DataProvider('nonSendingStatuses')]
    public function testCannotBeMarkedAsSentToClientWhenNotSending(StatusEnum $status): void
    {
        $invoice = $this->invoiceIn($status);

        $this->expectException(InvalidStatusTransition::class);

        $invoice->markAsSentToClient();
    }

    private function invoiceIn(StatusEnum $status): Invoice
    {
        return Invoice::reconstitute(
            id: Uuid::uuid4(),
            status: $status,
            customerName: 'John Doe',
            customerEmail: 'john@example.com',
            productLines: [ProductLine::create('Consulting', 1, 100)],
        );
    }
}
