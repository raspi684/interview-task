<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Modules\Invoices\Application\Actions\SendInvoice;
use Modules\Invoices\Application\Exceptions\InvoiceNotificationFailed;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ProductLine;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Unit\Invoice\Application\Fakes\InlineTransactions;
use Tests\Unit\Invoice\Application\Fakes\InMemoryInvoiceRepository;
use Tests\Unit\Invoice\Application\Fakes\SpyInvoiceNotifier;

final class SendInvoiceTest extends TestCase
{
    use InlineTransactions;

    private InMemoryInvoiceRepository $invoices;

    private SpyInvoiceNotifier $notifier;

    private SendInvoice $sendInvoice;

    protected function setUp(): void
    {
        $this->invoices = new InMemoryInvoiceRepository;
        $this->notifier = new SpyInvoiceNotifier;
        $this->sendInvoice = new SendInvoice(
            connection: $this->inlineTransactions(),
            invoices: $this->invoices,
            notifier: $this->notifier,
        );
    }

    public function testMovesInvoiceToSendingAndRequestsNotification(): void
    {
        $invoice = $this->storedDraft([ProductLine::create('Consulting', 1, 100)]);

        $result = $this->sendInvoice->handle($invoice->id());

        $this->assertSame(StatusEnum::Sending, $result->status());
        $this->assertSame(StatusEnum::Sending, $this->invoices->find($invoice->id())?->status());
        $this->assertEquals([$invoice->id()], $this->notifier->notifiedInvoiceIds);
    }

    public function testThrowsWhenInvoiceDoesNotExist(): void
    {
        $this->expectException(InvoiceNotFound::class);

        $this->sendInvoice->handle(Uuid::uuid4());
    }

    public function testDoesNotNotifyWhenInvoiceCannotBeSent(): void
    {
        $invoice = $this->storedDraft([]);

        try {
            $this->sendInvoice->handle($invoice->id());
            $this->fail('Expected InvoiceCannotBeSent.');
        } catch (InvoiceCannotBeSent) {
        }

        $this->assertSame([], $this->notifier->notifiedInvoiceIds);
        $this->assertSame(StatusEnum::Draft, $this->invoices->find($invoice->id())?->status());
    }

    public function testDoesNotNotifyAgainWhenInvoiceIsAlreadySending(): void
    {
        $invoice = $this->storedDraft([ProductLine::create('Consulting', 1, 100)]);
        $this->sendInvoice->handle($invoice->id());

        try {
            $this->sendInvoice->handle($invoice->id());
            $this->fail('Expected InvalidStatusTransition.');
        } catch (InvalidStatusTransition) {
        }

        $this->assertCount(1, $this->notifier->notifiedInvoiceIds);
    }

    public function testWrapsNotifierFailure(): void
    {
        $this->notifier->failWith = new RuntimeException('Provider unavailable');
        $invoice = $this->storedDraft([ProductLine::create('Consulting', 1, 100)]);

        $this->expectException(InvoiceNotificationFailed::class);

        $this->sendInvoice->handle($invoice->id());
    }

    /** @param list<ProductLine> $productLines */
    private function storedDraft(array $productLines): Invoice
    {
        $invoice = Invoice::create('John Doe', 'john@example.com', $productLines);
        $this->invoices->save($invoice);

        return $invoice;
    }
}
