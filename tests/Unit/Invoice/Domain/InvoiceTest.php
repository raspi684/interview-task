<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use InvalidArgumentException;
use Modules\Invoices\Domain\Customer;
use Modules\Invoices\Domain\Enums\DeliveryConfirmation;
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
        $customer = new Customer('John Doe', 'john@example.com');

        $invoice = Invoice::create($customer, []);

        $this->assertSame(StatusEnum::Draft, $invoice->status());
        $this->assertSame($customer, $invoice->customer());
        $this->assertSame([], $invoice->productLines());
    }

    public function testRejectsProductLinesOfWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Invoice::create(new Customer('John Doe', 'john@example.com'), [['product_name' => 'Consulting']]);
    }

    public function testGeneratesUniqueIdOnCreation(): void
    {
        $first = $this->draftWith([]);
        $second = $this->draftWith([]);

        $this->assertFalse($first->id()->equals($second->id()));
    }

    public function testCalculatesTotalPriceAsSumOfLineTotals(): void
    {
        $invoice = $this->draftWith([
            ProductLine::create('Consulting', 2, 1500),
            ProductLine::create('Hosting', 3, 200),
        ]);

        $this->assertSame(3600, $invoice->totalPrice());
    }

    public function testTotalPriceIsZeroWithoutProductLines(): void
    {
        $this->assertSame(0, $this->draftWith([])->totalPrice());
    }

    public function testRequestSendingMovesDraftToSending(): void
    {
        $invoice = $this->draftWith([ProductLine::create('Consulting', 1, 100)]);

        $invoice->requestSending();

        $this->assertSame(StatusEnum::Sending, $invoice->status());
    }

    public function testCannotRequestSendingWithoutProductLines(): void
    {
        $invoice = $this->draftWith([]);

        $this->expectException(InvoiceCannotBeSent::class);

        $invoice->requestSending();
    }

    /** @return iterable<string, array{int, int}> */
    public static function nonPositiveLineValues(): iterable
    {
        yield 'zero quantity' => [0, 100];
        yield 'zero unit price' => [1, 0];
    }

    #[DataProvider('nonPositiveLineValues')]
    public function testCannotRequestSendingWithNonPositiveProductLine(int $quantity, int $unitPrice): void
    {
        $invoice = $this->draftWith([
            ProductLine::create('Consulting', 1, 100),
            ProductLine::create('Hosting', $quantity, $unitPrice),
        ]);

        $this->expectException(InvoiceCannotBeSent::class);

        $invoice->requestSending();
    }

    /** @return iterable<string, array{StatusEnum}> */
    public static function nonDraftStatuses(): iterable
    {
        yield 'sending' => [StatusEnum::Sending];
        yield 'sent to client' => [StatusEnum::SentToClient];
    }

    #[DataProvider('nonDraftStatuses')]
    public function testCannotRequestSendingWhenNotDraft(StatusEnum $status): void
    {
        $invoice = $this->invoiceIn($status);

        $this->expectException(InvalidStatusTransition::class);

        $invoice->requestSending();
    }

    /** @return iterable<string, array{StatusEnum, DeliveryConfirmation, StatusEnum}> */
    public static function deliveryConfirmations(): iterable
    {
        yield 'sending is confirmed' => [StatusEnum::Sending, DeliveryConfirmation::Confirmed, StatusEnum::SentToClient];
        yield 'repeated confirmation' => [StatusEnum::SentToClient, DeliveryConfirmation::AlreadyConfirmed, StatusEnum::SentToClient];
        yield 'draft is not awaiting delivery' => [StatusEnum::Draft, DeliveryConfirmation::NotAwaitingDelivery, StatusEnum::Draft];
    }

    #[DataProvider('deliveryConfirmations')]
    public function testConfirmDelivery(StatusEnum $status, DeliveryConfirmation $expected, StatusEnum $statusAfter): void
    {
        $invoice = $this->invoiceIn($status);

        $this->assertSame($expected, $invoice->confirmDelivery());
        $this->assertSame($statusAfter, $invoice->status());
    }

    /** @param list<ProductLine> $productLines */
    private function draftWith(array $productLines): Invoice
    {
        return Invoice::create(new Customer('John Doe', 'john@example.com'), $productLines);
    }

    private function invoiceIn(StatusEnum $status): Invoice
    {
        return Invoice::reconstitute(
            id: Uuid::uuid4(),
            status: $status,
            customer: new Customer('John Doe', 'john@example.com'),
            productLines: [ProductLine::create('Consulting', 1, 100)],
        );
    }
}
